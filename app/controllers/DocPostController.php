<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\DraftRepository;
use app\models\DraftService;
use app\models\DocumentRepository;
use app\models\DocumentService;
use app\models\lookups\ResearchTopic;
use Exception;

/**
 * DocPostController
 * 
 * Handles document POST operations (form submissions, finalization).
 */
class DocPostController extends BaseController
{
    private DocumentRepository $docRepo;
    private DocumentService $docService;
    private DraftRepository $draftRepo;
    private DraftService $draftService;
    private ResearchTopic $topicModel;

    public function __construct()
    {
        parent::__construct();
        $this->docRepo = new DocumentRepository();
        $this->docService = new DocumentService();
        $this->draftRepo = new DraftRepository();
        $this->draftService = new DraftService();
        $this->topicModel = new ResearchTopic();
    }

    // ─────────────────────────────────────────────
    // Draft Finalize
    // ─────────────────────────────────────────────

    public function finalizeDraft(array $postData): void
    {
        $mID = $this->requireGoodStanding();
        $this->validateCsrf($postData);

        $dID = (int)($postData['dID'] ?? 0);

        $draft = $this->draftRepo->copyDraft($dID, $mID);
        if (!$draft) {
            http_response_code(403);
            $this->render('errors/403.php');
            exit;
        }

        if (!$this->draftRepo->isDraftFullyApproved($dID)) {
            $_SESSION['error_message'] = 'Cannot finalize: All co-authors must approve the draft first.';
            header("Location: /docdraft?id=$dID");
            exit;
        }

        if (!empty($draft['author_list'])) {
            $authorData = json_decode($draft['author_list'], true);
            $totDuty = 0; $author_array = [];
            foreach ($authorData['authors'] as $author) {
                $duty = (int)($author[2] ?? 0);
                $totDuty += $duty;
                $id = (int)($author[1] ?? 0);
                if ($id > 0) $author_array[] = [$id, $duty, (float)$duty];
            }
            foreach ($author_array as $a) { $a[2] /= (float)$totDuty; }
            $draft['author_array'] = $author_array;
        }
        if (!empty($draft['branch_list'])) {
            $draft['branch_list_array'] = json_decode($draft['branch_list'], true);
        }
        if (!empty($draft['link_list'])) {
            $draft['link_list_array'] = json_decode($draft['link_list'], true);
        }
        
        $newDID = $this->docService->submitFull($draft, [], $dID);

        if($newDID) {
            $_SESSION['success_message'] = "Document published successfully!";
            header("Location: /document?id=$newDID");
            exit;
        } else {
            $_SESSION['error_message'] = "DB/System Error in finalizing draft - please try again later.";
            header("Location: /docdraft?id=$dID");
            exit;
        }
    }

    /**
     * Centralized method to process all document uploads/edits/revisions.
     *
     * @param string $action Expected to be "submit", "save", "revise", or "edit"
     */
    public function processUpload(): array
    {
        $postData = $_POST;
        $files = $_FILES;
        $action = $postData['action'] ?? '';

        // 1. Initial Access & Security Checks
        $mID = $this->requireGoodStanding();
        $this->validateCsrf($postData);

        // Safely extract dID if it's provided in the POST request
        $dID = isset($postData['dID']) ? (int)$postData['dID'] : 0;

        // 2. Validate POST and FILES arrays
        $postCheck = $this->validatePostUpload($postData);
        $fileCheck = $this->validateFileUpload($files);

        // Merge errors and data
        $errors = array_merge($postCheck['errors'] ?? [], $fileCheck['errors'] ?? []);
        $data = array_merge($postCheck['data'] ?? [], $fileCheck['data'] ?? []);

        // 3. Move specific extra fields from POST to clean data if they are set
        $extraFields = ['dtype', 'tID', 'main_pages', 'main_figs', 'main_tabs', 'full_text'];
        foreach ($extraFields as $field) {
            if (isset($postData[$field]) && trim((string)$postData[$field]) !== '') {
                $data[$field] = trim($postData[$field]);
            }
        }
        if (empty($data)) {
            $errors['no_data'] = 'No valid data are provided.';
        }
        // Assign submitter
        $data['submitter_ID'] = $mID;

        // 4. Action-specific Rules: "submit" and "save"
        if ($action === 'submit' || $action === 'save') {
            
            // Check required fields
            if (empty($data['title'])) {
                $errors['title'] = 'Title must be provided.';
            }
            if (empty($data['abstract'])) {
                $errors['abstract'] = 'Abstract must be provided.';
            }
            if (empty($data['dtype'])) {
                $errors['dtype'] = 'Document type (dtype) must be set.';
            }
            if (empty($data['author_list'])) {
                $errors['author_list'] = 'Author list must be provided.';
            }
            if (empty($data['branch_list'])) {
                $errors['branch_list'] = 'Branch list must be provided.';
            }

            // Supplemental file logic check
            if (!empty($data['suppl_size']) && empty($data['main_size'])) {
                $errors['main_file'] = 'A main file must be uploaded if you are providing a supplemental file.';
            }
        }

        // 5. Sub-action / Final specific logic validations
        if ($action === 'submit') {
            // Check co-author approvals 
            if (isset($data['author_array']) && is_array($data['author_array'])) {
                foreach ($data['author_array'] as $author) {
                    // author is in format [mID, duty, frac]
                    $authorMID = isset($author[0]) ? (int)$author[0] : 0;
                    
                    // If author has an mID (>0) and it's not the submitter's mID
                    if ($authorMID > 0 && $authorMID !== $mID) {
                        $errors['co_author'] = 'Submission requires the approval of other co-authors. Please save as a draft first.';
                        break; // Stop checking after finding the first mismatch
                    }
                }
            }
        } elseif ($action === 'revise') {
            if ($dID <= 0 || !$this->docRepo->checkDocID($dID, $mID)) {
                $errors['dID'] = 'Document not found or access denied.';
            }
        } elseif ($action === 'edit') {
            if ($dID <= 0 || !$this->draftRepo->checkDraftID($dID, $mID)) {
                $errors['dID'] = 'Draft not found or access denied.';
            }
        }

        // 6. Halt and render if ANY errors were collected
        if (!empty($errors)) {
            // Repopulate form fields using the original POST data
            return ['success' => false, 'errors' => $errors, 'dID' => $dID, 'action' => $action];
        }

        // 7. Success! Execute DB Operations & Move files
        switch ($action) {
            case 'submit':
                $result = $this->docService->submitFull($data, $files);
                break;
            case 'save':
                $result = $this->draftService->saveFull($data, $files);
                break;
            case 'revise':
                $result = $this->docService->reviseFull($dID, $data, $files);
                break;
            case 'edit':
                $result = $this->draftService->editFull($dID, $data, $files); 
                break;
            default:
                // Hard fallback (just in case)
                $this->render('errors/general.php', [ 'errorMessage' => 'Invalid processing action.' ]);
                exit;
        }

        if ($result) {  // $result is returned dID or false
            if ($action === 'submit') {
                $_SESSION['success_message'] = 'Document is successfully submitted and will be announced in 24 hours.';
                header("Location: /document?id=$result");
            } elseif ($action === 'save') {
                $_SESSION['success_message'] = 'Document is successfully saved as a draft.';
                header("Location: /docdraft?id=$result");
            } elseif ($action === 'revise') {
                $_SESSION['success_message'] = 'Document is successfully revised.';
                header("Location: /document?id=$result");
            } elseif ($action === 'edit') {
                $_SESSION['success_message'] = 'Draft is successfully modified.';
                header("Location: /docdraft?id=$result");
            }
            exit;
        } else {
            return ['success' => false, 'errors' => ['DB/System failure'], 'dID' => $dID, 'action' => $action];
        }
    }

    // ─────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────

	/**
	 * Validates file uploads for main and supplemental documents.
	 *
	 * @param array $files Usually $_FILES
	 * @return array Associative array containing 'errors' and 'data'
	 */
	private function validateFileUpload(array $files): array
	{
		$errors = [];
		$data = [];

		// Fallback if MAX_UPLOAD_SIZE is missing (e.g. 10MB)
		$maxSize = defined('MAX_UPLOAD_SIZE') ? MAX_UPLOAD_SIZE : 10485760;

		// Use finfo to securely verify the actual file contents, not just the extension
		$finfo = new \finfo(FILEINFO_MIME_TYPE);

		// 1. Validate Main File
		if (isset($files['main_file']) && $files['main_file']['error'] !== UPLOAD_ERR_NO_FILE) {
			$mainFile = $files['main_file'];

			if ($mainFile['error'] !== UPLOAD_ERR_OK) {
				$errors['main_file'] = 'Error uploading main file (Upload Code: ' . $mainFile['error'] . ').';
			} elseif ($mainFile['size'] > $maxSize) {
				$errors['main_file'] = 'Main file exceeds the maximum allowed size of ' . ($maxSize / (1024 * 1024)) . 'MB.';
			} else {
				$mimeType = $finfo->file($mainFile['tmp_name']);
				$ext = strtolower(pathinfo($mainFile['name'], PATHINFO_EXTENSION));

				if ($mimeType !== 'application/pdf' || $ext !== 'pdf') {
					$errors['main_file'] = 'Main file must be a valid PDF document.';
				} else {
					$data['main_size'] = (int)$mainFile['size'];
				}
			}
		}

		// 2. Validate Supplemental File
		if (isset($files['supplemental_file']) && $files['supplemental_file']['error'] !== UPLOAD_ERR_NO_FILE) {
			$supplFile = $files['supplemental_file'];

			if ($supplFile['error'] !== UPLOAD_ERR_OK) {
				$errors['supplemental_file'] = 'Error uploading supplemental file (Upload Code: ' . $supplFile['error'] . ').';
			} elseif ($supplFile['size'] > $maxSize) {
				$errors['supplemental_file'] = 'Supplemental file exceeds the maximum allowed size of ' . ($maxSize / (1024 * 1024)) . 'MB.';
			} else {
				$mimeType = $finfo->file($supplFile['tmp_name']);
				$ext = strtolower(pathinfo($supplFile['name'], PATHINFO_EXTENSION));

				$isPdf = ($mimeType === 'application/pdf' && $ext === 'pdf');
				
				// Note: ZIP files can have multiple valid MIME types depending on the OS uploading it
				$validZipMimes = ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip'];
				$isZip = (in_array($mimeType, $validZipMimes) && $ext === 'zip');

				if ($isPdf || $isZip) {
					$data['suppl_size'] = (int)$supplFile['size'];
					$data['suppl_ext'] = $ext;
				} else {
					$errors['supplemental_file'] = 'Supplemental file must be either a PDF or ZIP file.';
				}
			}
		}

		return [
			'errors' => $errors,
			'data' => $data
		];
	}

	/**
	 * Validates the $_POST data for document uploads/edits.
	 *
	 * @param array $postData Usually $_POST
	 * @return array Associative array containing 'errors' and 'data'.
	 */
	private function validatePostUpload(array $postData): array
	{
		$errors = [];
		$data = [];

		// Helper function to validate YYYY-MM-DD range 1000-01-01 to 9999-12-31
		$isValidDate = function ($date) {
			$d = \DateTime::createFromFormat('Y-m-d', $date);
			if (!$d || $d->format('Y-m-d') !== $date) return false;
			$year = (int)$d->format('Y');
			return $year >= 1000 && $year <= 9999;
		};
		// Helper function to check if a list of numbers are all unique and positive
		$isUniqueID = function ($ids) {
			return count($ids) === count(array_unique($ids)) && min($ids) > 0;
		};

		// 1. Validate Dates if 'is_old' is set (assuming value is '1' or similar truthy value)
		if (!empty($postData['is_old'])) {
			$pubDate = trim($postData['pub_date'] ?? '');
			$recvDate = trim($postData['recv_date'] ?? '');

			if ($pubDate === '') {
				$errors['pub_date'] = 'Publication date is required for old documents.';
			} elseif (!$isValidDate($pubDate)) {
				$errors['pub_date'] = 'Publication date is invalid or out of allowed range (1000-9999).';
			} else {
			    $data['pub_date'] = $pubDate;
			}

			if ($recvDate !== '' && !$isValidDate($recvDate)) {
				$errors['recv_date'] = 'Receive date is invalid or out of allowed range (1000-9999).';
			}

			// Check if pub_date is strictly later than recv_date
			if (!isset($errors['pub_date']) && !isset($errors['recv_date']) && $pubDate !== '' && $recvDate !== '') {
				if (strtotime($pubDate) <= strtotime($recvDate)) {
					$errors['pub_date'] = 'Publication date must be later than the receive date.';
				} else {
				    $data['recv_date'] = $recvDate;
				}
			}
		}

		// 2. Title and Abstract cannot be empty if provided
		if (isset($postData['title'])) {
			$title = trim($postData['title']);
			if ($title === '') {
				$errors['title'] = 'Title cannot be empty.';
			} else {
				$data['title'] = $title;
			}
		}
		if (isset($postData['abstract'])) {
			$abstract = trim($postData['abstract']);
			if ($abstract === '') {
				$errors['abstract'] = 'Abstract cannot be empty.';
			} else {
				$data['abstract'] = $abstract;
			}
		}

		// 3. Notes limit to 255 chars
		if (isset($postData['notes'])) {
			$notes = mb_strlen($postData['notes']);
			if ($notes > 255) {
				$errors['notes'] = 'Notes cannot exceed 255 characters.';
			} else {
				$data['notes'] = trim($postData['notes']);
			}
		}
		if (isset($postData['revision_notes'])) {
			$notes = mb_strlen($postData['revision_notes']);
			if ($notes > 255) {
				$errors['revision_notes'] = 'Revision notes cannot exceed 255 characters.';
			} else {
				$data['revision_notes'] = trim($postData['revision_notes']);
			}
		}

		// 4. Author List JSON Validation
		if (isset($postData['author_list_json'])) {
			$trimmedAuthorList = trim($postData['author_list_json']);
			if (empty($trimmedAuthorList)) {
				$errors['author_list_json'] = 'Author list can not be empty.';
			} else {
				$authorData = json_decode($postData['author_list_json'], true);
				if (!is_array($authorData) || !isset($authorData['authors'])) {
					$errors['author_list_json'] = 'Invalid author list format.';
				} else {
					$hasDuty100 = false;
					$sumDuty20Plus = 0;
					$validDuties = true;
					$totDuty = 0;
					$author_array = [];
					foreach ($authorData['authors'] as $author) {
						// Author array format: [name, mID, duty, affRefs]
						$duty = (int)($author[2] ?? 0);
					
						if ($duty === 100) {
							$hasDuty100 = true;
						}
					
						if ($duty === 10 || ($duty >= 20 && $duty <= 100)) {
							if ($duty >= 20) {
								$sumDuty20Plus += $duty;
							}
							$totDuty += $duty;
							$id = (int)($author[1] ?? 0);
							if ($id > 0) $author_array[] = [$id, $duty, (float)$duty];
						} else {
							$validDuties = false;
							break;
						}
					}

					if (!$validDuties) {
						$errors['author_list_json'] = 'Author duties must be 10, or between 20 and 100 inclusive.';
					} elseif (!$hasDuty100) {
						$errors['author_list_json'] = 'At least one author must have a duty of 100.';
					} elseif ($sumDuty20Plus > 875) {
						$errors['author_list_json'] = 'The sum of primary author duties (>= 20) cannot exceed 875.';
					} else {
						$data['author_list'] = $trimmedAuthorList;
						foreach ($author_array as $a) { $a[2] /= (float)$totDuty; }
						$data['author_array'] = $author_array;
					}
				}
			}
		}

		// 5. Branch List JSON Validation
		if (isset($postData['branch_list_json'])) {
			$trimmedBranchList = trim($postData['branch_list_json']);
			if (empty($trimmedBranchList)) {
				$errors['branch_list_json'] = 'You must select between 1 and 3 branches.';
			} else {
				$branches = json_decode($postData['branch_list_json'], true);
				if (!is_array($branches) || !isset($branches[0]['bID'],$branches[0]['num'],$branches[0]['impact'])) {
					$errors['branch_list_json'] = 'Invalid branch list format.';
				} else {
					$count = count($branches);
				
					if ($count < 1 || $count > 3) {
						$errors['branch_list_json'] = 'You must select between 1 and 3 branches.';
					} else {
						$impactSum = 0;
						$nums = []; $ids = [];

						foreach ($branches as $branch) {
							$impactSum += (int)($branch['impact'] ?? 0);
							$nums[] = (int)($branch['num'] ?? 0);
							$ids[] = (int)($branch['bID'] ?? 0);
						}

						// Verify 'num' is exactly 1,2,3 with no skipping
						sort($nums);
						$expectedNums = range(1, $count);
					
						if ($nums !== $expectedNums) {
							$errors['branch_list_json'] = 'Branch numbering is invalid or skipped.';
						} elseif ($impactSum !== 100) {
							$errors['branch_list_json'] = 'The sum of all branch impacts must equal exactly 100.';
						} elseif (!$isUniqueID($ids)) {
							$errors['branch_list_json'] = 'Research branche IDs must be unique and exist.';
						} else {
							$data['branch_list'] = $trimmedBranchList;
							$data['branch_list_array'] = $branches;
						}
					}
				}
			}
		}

		// 6. Link List JSON Validation
		if (isset($postData['link_list_json'])) {
			$trimmedLinkList = trim($postData['link_list_json']);
			if ($trimmedLinkList === '[]') {  // intentionally delete all links
				$data['link_list'] = '[]'; $data['link_list_array'] = [];
			} else {
				$links = json_decode($postData['link_list_json'], true);
				if (!is_array($links) || !isset($links[0][0], $links[0][1], $links[0][2])) {
					$errors['link_list_json'] = 'Invalid link list format.';
				} elseif (count($links) > 8) {
					$errors['link_list_json'] = 'Number of external links cannot exceed 8.';
				} else {
					$err = false; $ids = []; $cleaned = [];
					foreach ($links as $index => $link) {
						// Link array format: [sID, esname, link]
						$esname = trim((string)($link[1] ?? ''));
						$url = trim(str_ireplace('https://doi.org/', '', $link[2]));
						$id = (int)($link[0] ?? 0);
						$ids[] = $id;
						$cleaned[] = [$id, $esname, $url];
					
						if (mb_strlen($esname) > 30) {
							$errors['link_list_json_' . $index] = 'Link names cannot exceed 30 characters.'; $err = true;
						}

	                    if (isset($link[2]) && $this->draftService->checkExternalLinkExists($url)) {
    	                    $errors['link_exists_' . $index] = 'The link/DOI ' . $link[2] . ' already exists in our database.'; $err = true;
        	            }
					}
					if (!$isUniqueID($ids)) {
						$errors['link_list_json_id'] = 'External link IDs must be unique and exist.'; $err = true;
					}
					if (!$err) {
						$data['link_list_array'] = $cleaned;
						$data['link_list'] = json_encode($cleaned);
					}
				}
			}
		}

		return [
			'errors' => $errors,
			'data' => $data
		];
	}
}
