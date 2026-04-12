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
        $mID = $this->requireLogin();
        
        $this->validateCsrf($postData);

        $dID = (int)($postData['dID'] ?? 0);

        $draft = $this->draftRepo->getDraft($dID, $mID);
        if (!$draft) {
            http_response_code(403);
            $this->render('errors/403.php');
            exit;
        }

        if (!$this->draftRepo->isDraftFullyApproved($dID)) {
            $_SESSION['error_message'] = "Cannot finalize: All co-authors must approve the draft first.";
            header("Location: /docdraft?id=$dID");
            exit;
        }

        try {
            $pubDate = $draft->pub_date ?? '';
            $recvDate = $draft->recv_date ?? '';
            $submissionTime = $draft->submission_time ?? date('Y-m-d H:i:s');
            if (empty($recvDate) && empty($pubDate)) {
                $submissionTime = date('Y-m-d H:i:s');
                $pubDate = date('Y-m-d', strtotime($submissionTime));
            } elseif (!empty($recvDate)) {
                $submissionTime = $recvDate . ' 00:00:00';
            }

            $draftDir = UPLOAD_PATH_TRIMMED . '/docdrafts';
            $mainSize = 0;
            $supplSize = 0;
            $supplExt = null;
            $hasFile = (int)$draft->has_file;

            if ($hasFile >= 1 && file_exists("$draftDir/{$dID}.pdf")) {
                $mainSize = (int)filesize("$draftDir/{$dID}.pdf");
            }
            if ($hasFile >= 2) {
                if (file_exists("$draftDir/{$dID}_suppl.pdf")) {
                    $supplSize = (int)filesize("$draftDir/{$dID}_suppl.pdf");
                    $supplExt = 1; // PDF
                } elseif (file_exists("$draftDir/{$dID}_suppl.zip")) {
                    $supplSize = (int)filesize("$draftDir/{$dID}_suppl.zip");
                    $supplExt = 2; // ZIP
                }
            }

            $newDID = $this->docService->submitDocument([
                'submitter_ID'    => $mID,
                'title'           => $draft->title,
                'abstract'        => $draft->abstract,
                'author_list'     => $draft->author_list,
                'main_size'       => $mainSize,
                'suppl_size'      => $supplSize,
                'suppl_ext'       => $supplExt,
                'submission_time' => $submissionTime,
                'pubdate'         => $pubDate,
                'notes'           => $draft->notes,
                'full_text'       => $draft->full_text,
                'dtype'           => $draft->dtype ?? 1,
                'main_pages'      => $draft->main_pages ?? '',
                'main_figs'       => $draft->main_figs ?? '',
                'main_tabs'       => $draft->main_tabs ?? '',
                'link_list_array' => json_decode($draft->link_list ?? '[]', true)
            ]);

            $draftBranches = json_decode($draft->branch_list ?? '[]', true) ?? [];
            if (!empty($draftBranches)) {
                $this->docService->saveBranches($newDID, $draftBranches);
            }

            if (!empty($draft->tID)) {
                $this->docService->saveTopic($newDID, (int)$draft->tID);
            }

            if (!empty($draft->author_list)) {
                $this->docService->saveAuthorsFromList($newDID, $draft->author_list);
            }

            $path = str_replace('-', '/', $pubDate);
            $targetDir = UPLOAD_PATH_TRIMMED . '/' . $path;
            if (!is_dir($targetDir)) mkdir($targetDir, 0750, true);

            if ($mainSize > 0 && file_exists("$draftDir/{$dID}.pdf")) {
                rename("$draftDir/{$dID}.pdf", "$targetDir/{$newDID}_v1.pdf");
            }
            if ($supplSize > 0) {
                if ($supplExt === 1 && file_exists("$draftDir/{$dID}_suppl.pdf")) {
                    rename("$draftDir/{$dID}_suppl.pdf", "$targetDir/{$newDID}_suppl_v1.pdf");
                } elseif ($supplExt === 2 && file_exists("$draftDir/{$dID}_suppl.zip")) {
                    rename("$draftDir/{$dID}_suppl.zip", "$targetDir/{$newDID}_suppl_v1.zip");
                }
            }

            $draftAuthors = $this->draftRepo->getDraftAuthors($dID);
            $subject = 'Your submission to ' . SITE_TITLE . ' has been received';
            $viewUrl = SITE_URL . "/document?id=" . $newDID;
            $body = "Your document titled '" . $draft->title . "' has been successfully submitted. It will be officially announced and visible on the platform after 24 hours. You can view your submission here: " . $viewUrl;
            $headers = ['From' => SUBMISSION_EMAIL, 'Reply-To' => SITE_EMAIL, 'X-Mailer' => 'PHP/' . phpversion()];

            foreach ($draftAuthors as $author) {
                if (!empty($author['email'])) {
                    mail($author['email'], $subject, $body, $headers);
                }
            }

            $this->draftService->deleteDraft($dID);

            $_SESSION['success_message'] = "Document published successfully!";
            header("Location: /document?id=$newDID");
            exit;

        } catch (Exception $e) {
            $errorMessage = "Error finalizing draft operation: " . $e->getMessage();
            $this->render('errors/general.php', ['errorMessage' => $errorMessage]);
            exit;
        }
    }

    // ─────────────────────────────────────────────
    // Form: Process (upload / edit_draft / revise_doc)
    // ─────────────────────────────────────────────

    public function processFormSubmission(array $postData, array $fileData): array
    {
        $mID = $this->requireLogin();
        
        $mode = $postData['form_mode'] ?? 'upload';
        $dID = (int)($postData['dID'] ?? 0);
        $action = $postData['action'] ?? ($mode === 'revise_doc' ? 'update' : 'draft');
        $isUpload = ($mode === 'upload');
        $isEditDraft = ($mode === 'edit_draft');
        $isReviseDoc = ($mode === 'revise_doc');

        $existingSupplExt = 0;
        $existingDoc = null;

        if ($isEditDraft) {
            $draft = $this->draftRepo->getDraft($dID, $mID);
            if (!$draft) {
                return ['success' => false, 'message' => 'Draft not found or access denied.'];
            }
            $draftHasFile = (int)$draft->has_file;
            $existingSupplExt = ($draftHasFile === 3 ? 2 : ($draftHasFile === 2 ? 1 : 0));
        } elseif ($isReviseDoc) {
            $mRole = $this->getCurrentUserRole();
            $existingDoc = $this->docRepo->getDocument('dID', $dID, $mRole, $mID);
            if (!$existingDoc || $existingDoc->submitter_ID !== $mID) {
                return ['success' => false, 'message' => 'Document not found or access denied.'];
            }
            $existingSupplExt = (int)($existingDoc->suppl_ext ?? 0);
        }

        $fileResult = $this->validateFileUploads($fileData, $existingSupplExt);
        if ($fileResult['error']) {
            return ['success' => false, 'message' => $fileResult['error']];
        }
        $supplExt = $fileResult['suppl_ext'];
        $isMainUploaded = $fileResult['isMainUploaded'];
        $isSupplUploaded = $fileResult['isSupplUploaded'];
        $mainSize = $fileResult['mainSize'];
        $supplSize = $fileResult['supplSize'];

        $fullText = $postData['full_text'] ?? '';
        $tID = (int)($postData['tID'] ?? 0);

        $cleanedLinks = $this->cleanLinkList($postData['link_list_json'] ?? '');

        if ($isUpload && $action === 'submit') {
            foreach ($cleanedLinks as $link) {
                if (isset($link[2]) && $this->draftService->checkExternalLinkExists(trim($link[2]))) {
                    return ['success' => false, 'message' => 'Validation failed: The link/DOI ' . $link[2] . ' already exists in our database.'];
                }
            }
        }

        $jsonBranches = $postData['branch_list_json'] ?? '[]';
        $arrayBranches = json_decode($jsonBranches, true) ?? [];

        $dateFields = ['pub_date' => '', 'recv_date' => '', 'submission_time' => date('Y-m-d H:i:s')];

        if ($isUpload) {
            $dateFields = $this->parseDateFields($postData);
        }

        $pubDate = $dateFields['pub_date'];
        $recvDate = $dateFields['recv_date'];
        $submissionTime = $dateFields['submission_time'];

        $docData = [
            'title'       => $postData['title'] ?? '',
            'abstract'    => $postData['abstract'] ?? '',
            'author_list' => $postData['author_list_json'] ?? '',
            'dtype'       => (int)($postData['dtype'] ?? 1),
            'notes'       => $postData['notes'] ?? '',
            'full_text'   => $fullText,
            'tID'         => $tID > 0 ? $tID : null,
            'main_pages'  => $postData['main_pages'] ?? '',
            'main_figs'   => $postData['main_figs'] ?? '',
            'main_tabs'   => $postData['main_tabs'] ?? '',
        ];

        if ($isUpload) {
            $docData['submitter_ID'] = $mID;
            $docData['pubdate'] = $pubDate;
            $docData['pub_date'] = $pubDate ?: null;
            $docData['recv_date'] = $recvDate ?: null;
            $docData['submission_time'] = $submissionTime;
            $docData['link_list'] = json_encode($cleanedLinks);
            $docData['link_list_array'] = $cleanedLinks;
            $docData['branch_list'] = $jsonBranches;
            $docData['main_size'] = $mainSize;
            $docData['suppl_size'] = $supplSize;
            $docData['suppl_ext'] = $supplExt;
        } elseif ($isEditDraft) {
            $draftHasFile = $isMainUploaded ? 1 : (int)$draft->has_file;
            if ($isSupplUploaded) {
                $draftHasFile = ($supplExt === 2 ? 3 : 2);
            } elseif ($isMainUploaded && $existingSupplExt > 0) {
                $draftHasFile = ($existingSupplExt === 2 ? 3 : 2);
            }
            $docData['has_file'] = $draftHasFile;
            $docData['link_list'] = json_encode($cleanedLinks);
            $docData['branch_list'] = $jsonBranches;
        } elseif ($isReviseDoc) {
            $docData['revision_notes'] = $postData['revision_notes'] ?? '';
            $docData['main_size'] = $mainSize;
            $docData['suppl_size'] = $supplSize;
            $docData['suppl_ext'] = $supplExt;
        }

        try {
            if ($isUpload && $action === 'draft') {
                $draftHasFile = $isMainUploaded ? 1 : 0;
                if ($isSupplUploaded) {
                    $draftHasFile = ($supplExt === 2 ? 3 : 2);
                }
                $docData['has_file'] = $draftHasFile;
                
                $dID = $this->draftService->saveDraft($docData);
                $uploadDir = UPLOAD_PATH_TRIMMED . '/docdrafts';

            } elseif ($isUpload && $action === 'submit') {
                if ($pubDate === '') {
                    $docData['submission_time'] = date('Y-m-d H:i:s');
                    $pubDate = date('Y-m-d', strtotime($docData['submission_time']));
                    $docData['pubdate'] = $pubDate;
                }
                $dID = $this->docService->submitDocument($docData);
                $path = str_replace('-', '/', $pubDate);
                $uploadDir = UPLOAD_PATH_TRIMMED . '/' . $path;

                if (!empty($arrayBranches)) {
                    $this->docService->saveBranches($dID, $arrayBranches);
                }
                if ($tID > 0) {
                    $this->docService->saveTopic($dID, $tID);
                }

                if (!empty($docData['author_list'])) {
                    $this->docService->saveAuthorsFromList($dID, $docData['author_list']);
                }

                $newVersion = 1;
                $newVerSuppl = $supplSize > 0 ? 1 : null;

            } elseif ($isEditDraft) {
                $this->draftService->updateDraft($dID, $docData);
                $this->draftService->resetDraftApprovals($dID);
                $uploadDir = UPLOAD_PATH_TRIMMED . '/docdrafts';

            } elseif ($isReviseDoc) {
                $res = $this->docService->reviseDocument($dID, $docData, $isMainUploaded, $isSupplUploaded);
                $newVersion = $res['version'];
                $newVerSuppl = $res['ver_suppl'];
                
                $this->docService->updateExternalDocs($dID, $cleanedLinks);

                if (!empty($arrayBranches)) {
                    $this->docService->upsertBranches($dID, $arrayBranches);
                }

                if ($tID > 0) {
                    $this->docService->saveTopic($dID, $tID);
                }

                if (!empty($docData['author_list'])) {
                    $this->docService->upsertAuthorsFromList($dID, $docData['author_list']);
                }

                $pubdate = $existingDoc->pubdate ?? '';
                $path = str_replace('-', '/', $pubdate);
                $uploadDir = UPLOAD_PATH_TRIMMED . '/' . $path;
                $docDoi = $existingDoc->doi ?? '';
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'dID' => $dID, 'action' => $action];
        }

        try {
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0750, true);
            }

            $fileErrors = [];

            if ($isMainUploaded) {
                $filePrefix = ($isReviseDoc && !empty($docDoi)) ? $docDoi : (string)$dID;
                $filename = ($isUpload || $isReviseDoc) ? "{$filePrefix}_v{$newVersion}.pdf" : "{$dID}.pdf";
                if (!move_uploaded_file($fileData['main_file']['tmp_name'], "$uploadDir/$filename")) {
                    $fileErrors[] = 'Main PDF upload failed.';
                }
            }
            
            if ($isSupplUploaded) {
                $filePrefix = ($isReviseDoc && !empty($docDoi)) ? $docDoi : (string)$dID;
                $ext = ($supplExt === 2 ? 'zip' : 'pdf');
                $filename = ($isUpload || $isReviseDoc) ? "{$filePrefix}_suppl_v{$newVerSuppl}.{$ext}" : "{$dID}_suppl.{$ext}";
                if (!move_uploaded_file($fileData['supplemental_file']['tmp_name'], "$uploadDir/$filename")) {
                    $fileErrors[] = 'Supplemental file upload failed.';
                }
            }

            if (!empty($fileErrors)) {
                return ['success' => false, 'message' => implode(' ', $fileErrors), 'dID' => $dID, 'action' => $action];
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'File error: ' . $e->getMessage(), 'dID' => $dID, 'action' => $action];
        }

        $message = match ($mode) {
            'edit_draft' => 'Draft updated successfully!',
            'revise_doc' => 'Document revised successfully! (v' . ($newVersion ?? 1) . ')',
            default      => 'Document ' . ($action === 'draft' ? 'saved as draft' : 'submitted') . ' successfully!'
        };

        return ['success' => true, 'message' => $message, 'dID' => $dID, 'action' => $action];
    }

    // ─────────────────────────────────────────────
    // Private Helpers
    // ─────────────────────────────────────────────

    private function cleanLinkList(string $json): array
    {
        $links = json_decode($json, true) ?? [];
        $cleaned = [];

        foreach ($links as $link) {
            if (isset($link[2])) {
                $url = str_ireplace('https://doi.org/', '', $link[2]);
                $url = trim($url);
                $cleaned[] = [(int)$link[0], trim($link[1]), $url];
            }
        }

        return $cleaned;
    }

    private function validateFileUploads(array $fileData, int $existingSupplExt = 0): array
    {
        $isMainUploaded = isset($fileData['main_file']) && $fileData['main_file']['error'] === UPLOAD_ERR_OK;
        $isSupplUploaded = isset($fileData['supplemental_file']) && $fileData['supplemental_file']['error'] === UPLOAD_ERR_OK;
        $mainSize = $isMainUploaded ? (int)$fileData['main_file']['size'] : 0;
        $supplSize = $isSupplUploaded ? (int)$fileData['supplemental_file']['size'] : 0;
        $supplExt = $existingSupplExt;

        if (!$isMainUploaded && !$isSupplUploaded) {
            return ['error' => null, 'suppl_ext' => $supplExt, 'isMainUploaded' => false, 'isSupplUploaded' => false, 'mainSize' => 0, 'supplSize' => 0];
        }

        if ($isMainUploaded && $fileData['main_file']['size'] > MAX_UPLOAD_SIZE) {
            return ['error' => 'File exceeds the maximum allowed size of ' . (MAX_UPLOAD_SIZE / (1024 * 1024)) . 'MB.', 'suppl_ext' => 0, 'isMainUploaded' => false, 'isSupplUploaded' => false, 'mainSize' => 0, 'supplSize' => 0];
        }
        if ($isSupplUploaded && $fileData['supplemental_file']['size'] > MAX_UPLOAD_SIZE) {
            return ['error' => 'File exceeds the maximum allowed size of ' . (MAX_UPLOAD_SIZE / (1024 * 1024)) . 'MB.', 'suppl_ext' => 0, 'isMainUploaded' => false, 'isSupplUploaded' => false, 'mainSize' => 0, 'supplSize' => 0];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $supplExt = 0;

        if ($isMainUploaded) {
            $mainMime = $finfo->file($fileData['main_file']['tmp_name']);
            if ($mainMime !== 'application/pdf') {
                return ['error' => 'Security Error: Main document must be a valid PDF.', 'suppl_ext' => 0, 'isMainUploaded' => false, 'isSupplUploaded' => false, 'mainSize' => 0, 'supplSize' => 0];
            }
        }

        if ($isSupplUploaded) {
            $supplMime = $finfo->file($fileData['supplemental_file']['tmp_name']);
            $ext = strtolower(pathinfo($fileData['supplemental_file']['name'], PATHINFO_EXTENSION));

            if ($ext === 'pdf') {
                if ($supplMime !== 'application/pdf') {
                    return ['error' => 'Security Error: Supplemental PDF is invalid.', 'suppl_ext' => 0, 'isMainUploaded' => false, 'isSupplUploaded' => false, 'mainSize' => 0, 'supplSize' => 0];
                }
                $supplExt = 1;
            } else            if ($ext === 'zip') {
                if ($supplMime !== 'application/zip' && $supplMime !== 'application/x-zip-compressed') {
                    return ['error' => 'Security Error: Supplemental ZIP is invalid.', 'suppl_ext' => 0, 'isMainUploaded' => false, 'isSupplUploaded' => false, 'mainSize' => 0, 'supplSize' => 0];
                }
                $supplExt = 2;
            }
        }

        return ['error' => null, 'suppl_ext' => $supplExt, 'isMainUploaded' => $isMainUploaded, 'isSupplUploaded' => $isSupplUploaded, 'mainSize' => $mainSize, 'supplSize' => $supplSize];
    }

    private function parseDateFields(array $postData): array
    {
        $pubDate = trim($postData['pub_date'] ?? '');
        $recvDate = trim($postData['recv_date'] ?? '');
        $submissionTime = date('Y-m-d H:i:s');

        if (!empty($recvDate)) {
            $submissionTime = $recvDate . ' 00:00:00';
        } elseif (!empty($pubDate)) {
            $submissionTime = $pubDate . ' 00:00:00';
        }

        return [
            'pub_date' => $pubDate,
            'recv_date' => $recvDate,
            'submission_time' => $submissionTime
        ];
    }
}