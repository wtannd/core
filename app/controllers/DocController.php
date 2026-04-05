<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Document;
use app\models\Member;
use app\models\lookups\Institution;
use app\models\lookups\ResearchBranch;
use app\models\lookups\DocType;
use app\models\lookups\ResearchTopic;
use Exception;

/**
 * DocController
 * 
 * Handles document-related business logic.
 */
class DocController
{
    private Document $documentModel;
    private Member $memberModel;

    public function __construct()
    {
        $this->documentModel = new Document();
        $this->memberModel = new Member();
    }

    // ─────────────────────────────────────────────
    // AJAX Endpoints
    // ─────────────────────────────────────────────

    public function lookupAuthors(): void
    {
        $json = json_decode(file_get_contents('php://input'), true);
        $rawText = $json['text'] ?? '';

        $rawIds = preg_split('/[\n\r,;]+/', $rawText, -1, PREG_SPLIT_NO_EMPTY);
        $parsedIds = [];
        foreach ($rawIds as $id) {
            $clean = str_replace('-', '', trim($id));
            if (!empty($clean)) {
                $clean = ltrim($clean, '0');
                if ($clean === '') $clean = '0';
                $parsedIds[] = $clean;
            }
        }

        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }

    // ─────────────────────────────────────────────
    // View / Display
    // ─────────────────────────────────────────────

    public function feed(): void
    {
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $documents = $this->documentModel->getRecentDocuments(1, $limit, (int)$mRole);
        include rtrim(VIEWS_PATH, '/') . '/repository/feed_page.php';
    }

    public function viewDocument(string $id): void
    {
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;
        $mID = (int)($_SESSION['mID'] ?? 0);
        $doc = $this->documentModel->getDocument((int)$id, (int)$mRole, $mID);
        $this->renderDocument($doc);
    }

    public function viewDocDoi(string $doi): void
    {
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;
        $mID = (int)($_SESSION['mID'] ?? 0);
        $doc = $this->documentModel->getDocumentByDoi($doi, (int)$mRole, $mID);
        $this->renderDocument($doc);
    }

    private function renderDocument(array|false $doc): void
    {
        if (!$doc) {
            http_response_code(404);
            include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
            exit;
        }

        $dID = (int)$doc['dID'];
        $extLinks = $this->documentModel->getExternalLinks($dID);
        $mID = (int)($_SESSION['mID'] ?? 0);

        $docData = [
            'document'    => $doc,
            'authors'     => json_decode($doc['author_list'], true) ?? [],
            'extLinks'    => $extLinks,
            'branches'    => $this->documentModel->getDocBranches($dID),
            'topic'       => $this->documentModel->getDocTopic($dID),
            'isSubmitter' => $mID > 0 && (int)$doc['submitter_ID'] === $mID,
            'isOnHold'    => (int)$doc['visibility'] === VISIBILITY_ON_HOLD
        ];

        include rtrim(VIEWS_PATH, '/') . '/repository/view_doc.php';
    }

    public function viewDocDraft(string $id): void
    {
        if (!isset($_SESSION['mID'])) {
            header('Location: /login');
            exit;
        }

        $mID = (int)$_SESSION['mID'];
        $doc = $this->documentModel->getDraft((int)$id, $mID);

        if (!$doc) {
            $draftAuthors = $this->documentModel->getDraftAuthors((int)$id);
            $isCoAuthor = false;
            foreach ($draftAuthors as $da) {
                if ((int)$da['mID'] === $mID) {
                    $isCoAuthor = true;
                    break;
                }
            }
            if ($isCoAuthor) {
                $doc = $this->documentModel->getDraftById((int)$id);
            }
        }

        if (!$doc) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        $docData = [
            'document'       => $doc,
            'draftAuthors'   => $this->documentModel->getDraftAuthors((int)$id),
            'isFullyApproved'=> $this->documentModel->isDraftFullyApproved((int)$id),
            'branches'       => $this->parseBranchesJson($doc['branch_list'] ?? '[]'),
            'topic'          => !empty($doc['tID']) ? $this->documentModel->getTopicById((int)$doc['tID']) : null,
            'extLinks'       => json_decode($doc['link_list'] ?? '[]', true) ?? [],
        ];

        include rtrim(VIEWS_PATH, '/') . '/repository/view_docdraft.php';
    }

    // ─────────────────────────────────────────────
    // Draft Approve / Finalize
    // ─────────────────────────────────────────────

    public function approveDraft(array $postData): void
    {
        if (!isset($_SESSION['mID'])) {
            header('Location: /login');
            exit;
        }

        $dID = (int)($postData['dID'] ?? 0);
        $mID = (int)$_SESSION['mID'];

        $draftAuthors = $this->documentModel->getDraftAuthors($dID);
        $isValidAuthor = false;
        foreach ($draftAuthors as $da) {
            if ((int)$da['mID'] === $mID) {
                $isValidAuthor = true;
                break;
            }
        }

        if (!$isValidAuthor) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        if ($this->documentModel->approveDraft($dID, $mID)) {
            $_SESSION['success_message'] = "Draft approved successfully.";
        }

        header("Location: /docdraft?id=$dID");
        exit;
    }

    public function finalizeDraft(array $postData): void
    {
        if (!isset($_SESSION['mID'])) {
            header('Location: /login');
            exit;
        }

        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        $dID = (int)($postData['dID'] ?? 0);
        $mID = (int)$_SESSION['mID'];

        $draft = $this->documentModel->getDraft($dID, $mID);
        if (!$draft) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        if (!$this->documentModel->isDraftFullyApproved($dID)) {
            $_SESSION['error_message'] = "Cannot finalize: All co-authors must approve the draft first.";
            header("Location: /docdraft?id=$dID");
            exit;
        }

        try {
            $pubdate = $draft['pubdate'] ?? '';
            $submissionTime = $draft['submission_time'] ?? date('Y-m-d H:i:s');
            if ($pubdate === '') {
                $submissionTime = date('Y-m-d H:i:s');
                $pubdate = date('Ymd', strtotime($submissionTime));
            }

            // Determine sizes and suppl_ext for submitDocument
            $draftDir = rtrim(UPLOAD_PATH, '/') . '/docdrafts';
            $mainSize = 0;
            $supplSize = 0;
            $supplExt = null;
            $hasFile = (int)$draft['has_file'];

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

            $newDID = $this->documentModel->submitDocument([
                'submitter_ID'    => $mID,
                'title'           => $draft['title'],
                'abstract'        => $draft['abstract'],
                'author_list'     => $draft['author_list'],
                'main_size'       => $mainSize,
                'suppl_size'      => $supplSize,
                'suppl_ext'       => $supplExt,
                'submission_time' => $submissionTime,
                'pubdate'         => $pubdate,
                'notes'           => $draft['notes'],
                'full_text'       => $draft['full_text'],
                'dtype'           => $draft['dtype'] ?? 1,
                'link_list_array' => json_decode($draft['link_list'] ?? '[]', true)
            ]);

            $draftBranches = json_decode($draft['branch_list'] ?? '[]', true) ?? [];
            if (!empty($draftBranches)) {
                $this->documentModel->saveBranches($newDID, $draftBranches);
            }

            if (!empty($draft['tID'])) {
                $this->documentModel->saveTopic($newDID, (int)$draft['tID']);
            }

            // Save authors to DocAuthors
            if (!empty($draft['author_list'])) {
                $this->documentModel->saveAuthorsFromList($newDID, $draft['author_list']);
            }

            // Move files from docdrafts/ to YYYY/MM/DD/ with versioned naming
            $path = $this->getPathFromPubdate($pubdate);
            $targetDir = rtrim(UPLOAD_PATH, '/') . '/' . $path;
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

            // Email co-authors
            $draftAuthors = $this->documentModel->getDraftAuthors($dID);
            $subject = 'Your submission to ' . SITE_TITLE . ' has been received';
            $viewUrl = SITE_URL . "/document?id=" . $newDID;
            $body = "Your document titled '" . $draft['title'] . "' has been successfully submitted. It will be officially announced and visible on the platform after 24 hours. You can view your submission here: " . $viewUrl;
            $headers = ['From' => SUBMISSION_EMAIL, 'Reply-To' => SITE_EMAIL, 'X-Mailer' => 'PHP/' . phpversion()];

            foreach ($draftAuthors as $author) {
                if (!empty($author['email'])) {
                    mail($author['email'], $subject, $body, $headers);
                }
            }

            // Remove the draft now that it's published
            $this->documentModel->deleteDraft($dID);

            $_SESSION['success_message'] = "Document published successfully!";
            header("Location: /document?id=$newDID");
            exit;

        } catch (Exception $e) {
            $errorMessage = "Error finalizing draft operation: " . $e->getMessage();
            include rtrim(VIEWS_PATH, '/') . '/errors/general.php';
            exit;
        }
    }
    // ─────────────────────────────────────────────
    // File Streaming
    // ─────────────────────────────────────────────

    public function streamPdf(string $type, string $id, bool $isSuppl = false, ?int $ver = null): void
    {
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;
        $mID = $_SESSION['mID'] ?? null;

        $isDraft = ($type === 'draft' || $type === 'draft_suppl');
        $isSuppl = ($type === 'suppl' || $type === 'draft_suppl' || $isSuppl);

        if ($isDraft) {
            if ($mID === null) {
                http_response_code(403);
                include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
                exit;
            }
            $doc = $this->documentModel->getDraftById((int)$id);
            if (!$doc) {
                http_response_code(404);
                include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
                exit;
            }
            $isOwner = ((int)$doc['submitter_ID'] === (int)$mID);
            if (!$isOwner) {
                $draftAuthors = $this->documentModel->getDraftAuthors((int)$id);
                foreach ($draftAuthors as $da) {
                    if ((int)$da['mID'] === (int)$mID) {
                        $isOwner = true;
                        break;
                    }
                }
            }
            if (!$isOwner) {
                http_response_code(403);
                include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
                exit;
            }
            $uploadDir = rtrim(UPLOAD_PATH, '/') . '/docdrafts';
        } else {
            // For published documents, we MUST query the DB if $ver is null to get current versions
            $doc = $this->documentModel->getDocument((int)$id, (int)$mRole, (int)($mID ?? 0));
            if (!$doc) {
                http_response_code(404);
                include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
                exit;
            }
            $path = $this->getPathFromPubdate($doc['submission_time']);
            $uploadDir = rtrim(UPLOAD_PATH, '/') . '/' . $path;
        }

        $filePath = null;
        $contentType = 'application/pdf';

        if ($isDraft) {
            $hasFile = (int)$doc['has_file'];
            if ($isSuppl) {
                if ($hasFile === 2) {
                    $filePath = "$uploadDir/{$id}_suppl.pdf";
                } elseif ($hasFile === 3) {
                    $filePath = "$uploadDir/{$id}_suppl.zip";
                    $contentType = 'application/zip';
                }
            } else {
                if ($hasFile >= 1) {
                    $filePath = "$uploadDir/{$id}.pdf";
                }
            }
        } else {
            // Published Document logic
            if ($isSuppl) {
                $supplVersion = $ver !== null ? (int)$ver : (int)($doc['ver_suppl'] ?? 0);
                if ($supplVersion > 0) {
                    $supplExt = (int)($doc['suppl_ext'] ?? 0);
                    
                    // If requesting an old version, find its suppl_ext in history
                    if ($ver !== null && $ver < (int)($doc['ver_suppl'] ?? 0)) {
                        $history = json_decode($doc['revision_history'] ?? '[]', true) ?: [];
                        foreach ($history as $rev) {
                            if (isset($rev[1]) && (int)$rev[1] === $ver) {
                                $supplExt = (int)($rev[2] ?? 0);
                                break;
                            }
                        }
                    }

                    if ($supplExt === 1) {
                        $filePath = "$uploadDir/{$id}_suppl_v{$supplVersion}.pdf";
                    } elseif ($supplExt === 2) {
                        $filePath = "$uploadDir/{$id}_suppl_v{$supplVersion}.zip";
                        $contentType = 'application/zip';
                    }
                }
            } else {
                $mainVersion = $ver !== null ? (int)$ver : (int)($doc['version'] ?? 0);
                if ($mainVersion > 0) {
                    $filePath = "$uploadDir/{$id}_v{$mainVersion}.pdf";
                }
            }
        }

        if (!$filePath || !file_exists($filePath)) {
            http_response_code(404);
            include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
            exit;
        }

        header("Content-Type: $contentType");
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        readfile($filePath);
        exit;
    }

    // ─────────────────────────────────────────────
    // My Documents
    // ─────────────────────────────────────────────

    public function myDocuments(): void
    {
        if (!isset($_SESSION['mID'])) {
            header('Location: /login');
            exit;
        }

        $mID = (int)$_SESSION['mID'];
        $allDocs = $this->documentModel->getMyDocuments($mID);

        $pendingDocs = [];
        $announcedDocs = [];

        foreach ($allDocs as $doc) {
            if ((int)$doc['visibility'] >= \VISIBILITY_ON_HOLD) {
                $pendingDocs[] = $doc;
            } else {
                $announcedDocs[] = $doc;
            }
        }

        // Pagination for announced group
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $totalAnnounced = count($announcedDocs);
        $totalPages = max(1, (int)ceil($totalAnnounced / $perPage));
        $announcedSlice = array_slice($announcedDocs, ($page - 1) * $perPage, $perPage);

        include rtrim(VIEWS_PATH, '/') . '/repository/my_docs.php';
    }

    // ─────────────────────────────────────────────
    // My Drafts
    // ─────────────────────────────────────────────

    public function myDrafts(): void
    {
        if (!isset($_SESSION['mID'])) {
            header('Location: /login');
            exit;
        }

        $mID = (int)$_SESSION['mID'];
        $drafts = $this->documentModel->getMyDrafts($mID);

        include rtrim(VIEWS_PATH, '/') . '/repository/my_drafts.php';
    }

    // ─────────────────────────────────────────────
    // Form: Show (upload / edit_draft / revise_doc)
    // ─────────────────────────────────────────────

    public function showUpload(?array $errors = null): void
    {
        $this->renderForm('upload', 0, null, $errors);
    }

    public function editDraft(string $id, ?array $errors = null): void
    {
        if (!isset($_SESSION['mID'])) {
            header('Location: /login');
            exit;
        }

        $mID = (int)$_SESSION['mID'];
        $dID = (int)$id;
        $draft = $this->documentModel->getDraft($dID, $mID);

        if (!$draft) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        $docData = [
            'dID'             => $dID,
            'dtype'           => $draft['dtype'],
            'title'           => $draft['title'],
            'abstract'        => $draft['abstract'],
            'notes'           => $draft['notes'] ?? '',
            'full_text'       => $draft['full_text'] ?? '',
            'has_file'        => $draft['has_file'],
            'tID'             => $draft['tID'],
            'author_list'     => json_decode($draft['author_list'] ?? '{}', true) ?? [],
            'branches'        => $this->parseBranchesJson($draft['branch_list'] ?? '[]'),
            'ext_links'       => json_decode($draft['link_list'] ?? '[]', true) ?? [],
            'pubdate'         => $draft['pubdate'] ?? '',
            'submission_time' => $draft['submission_time'] ?? '',
        ];

        $this->renderForm('edit_draft', $dID, $docData, $errors);
    }

    public function reviseDoc(string $id, ?array $errors = null): void
    {
        if (!isset($_SESSION['mID'])) {
            header('Location: /login');
            exit;
        }

        $mID = (int)$_SESSION['mID'];
        $mRole = (int)($_SESSION['mrole'] ?? GUEST_ROLE);
        $dID = (int)$id;
        $doc = $this->documentModel->getDocument($dID, $mRole, $mID);

        if (!$doc || (int)$doc['submitter_ID'] !== $mID) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        $topic = $this->documentModel->getDocTopic($dID);

        $docData = [
            'dID'             => $dID,
            'dtype'           => $doc['dtype'],
            'title'           => $doc['title'],
            'abstract'        => $doc['abstract'],
            'notes'           => $doc['notes'] ?? '',
            'full_text'       => $doc['full_text'] ?? '',
            'version'         => (int)($doc['version'] ?? 0),
            'ver_suppl'       => $doc['ver_suppl'],
            'suppl_ext'       => (int)($doc['suppl_ext'] ?? 0),
            'tID'             => $topic ? $topic['tID'] : null,
            'author_list'     => json_decode($doc['author_list'] ?? '{}', true) ?? [],
            'branches'        => $this->documentModel->getDocBranches($dID),
            'ext_links'       => $this->documentModel->getExternalLinks($dID),
            'pubdate'         => $doc['pubdate'] ?? '',
            'submission_time' => $doc['submission_time'] ?? '',
            'main_pages'      => $doc['main_pages'] ?? '',
            'main_figs'       => $doc['main_figs'] ?? '',
            'main_tabs'       => $doc['main_tabs'] ?? '',
        ];

        $this->renderForm('revise_doc', $dID, $docData, $errors);
    }

    private function renderForm(string $mode, int $dID, ?array $docData = null, ?array $errors = null): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $availableSources = $this->documentModel->getAvailableSources();
        $institutions = (new Institution())->getAllInstitutions();
        $researchBranches = (new ResearchBranch())->getAllBranches();
        $researchTopics = (new ResearchTopic())->getAllTopics();
        $docTypes = (new DocType())->getAllDocTypes();

        $pageTitle = match ($mode) {
            'edit_draft' => 'Edit Draft',
            'revise_doc' => 'Revise Document',
            default      => 'Upload New Document (ePrint)'
        };

        $actionUrl = match ($mode) {
            'edit_draft' => '/edit_draft',
            'revise_doc' => '/revise_doc',
            default      => '/upload'
        };

        $cancelUrl = match ($mode) {
            'edit_draft' => $dID > 0 ? "/docdraft?id=$dID" : '/',
            'revise_doc' => $dID > 0 ? "/document?id=$dID" : '/',
            default      => '/'
        };

        $submitLabel = match ($mode) {
            'edit_draft' => 'Update Draft',
            'revise_doc' => 'Update Document',
            default      => 'Submit Document'
        };

        include rtrim(VIEWS_PATH, '/') . '/repository/upload.php';
    }

    // ─────────────────────────────────────────────
    // Form: Process (upload / edit_draft / revise_doc)
    // ─────────────────────────────────────────────

    /**
     * Unified form submission handler for all three modes.
     * POST /upload, POST /edit_draft, POST /revise_doc
     */
    public function processFormSubmission(array $postData, array $fileData): array
    {
        if (!isset($_SESSION['mID'])) {
            return ['success' => false, 'message' => 'Not authenticated.'];
        }

        $mID = (int)$_SESSION['mID'];
        $mode = $postData['form_mode'] ?? 'upload';
        $dID = (int)($postData['dID'] ?? 0);
        $action = $postData['action'] ?? ($mode === 'revise_doc' ? 'update' : 'draft');
        $isUpload = ($mode === 'upload');
        $isEditDraft = ($mode === 'edit_draft');
        $isReviseDoc = ($mode === 'revise_doc');

        // ── Auth / ownership check ──
        $existingSupplExt = 0;
        $existingDoc = null;

        if ($isEditDraft) {
            $draft = $this->documentModel->getDraft($dID, $mID);
            if (!$draft) {
                return ['success' => false, 'message' => 'Draft not found or access denied.'];
            }
            $draftHasFile = (int)$draft['has_file'];
            $existingSupplExt = ($draftHasFile === 3 ? 2 : ($draftHasFile === 2 ? 1 : 0));
        } elseif ($isReviseDoc) {
            $mRole = (int)($_SESSION['mrole'] ?? GUEST_ROLE);
            $existingDoc = $this->documentModel->getDocument($dID, $mRole, $mID);
            if (!$existingDoc || (int)$existingDoc['submitter_ID'] !== $mID) {
                return ['success' => false, 'message' => 'Document not found or access denied.'];
            }
            $existingSupplExt = (int)($existingDoc['suppl_ext'] ?? 0);
        }

        // ── File upload validation ──
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

        // ── Process links ──
        $cleanedLinks = $this->cleanLinkList($postData['link_list_json'] ?? '');

        // New upload: check for duplicate external links
        if ($isUpload && $action === 'submit') {
            foreach ($cleanedLinks as $link) {
                if (isset($link[2]) && $this->documentModel->checkExternalLinkExists(trim($link[2]))) {
                    return ['success' => false, 'message' => 'Validation failed: The link/DOI ' . $link[2] . ' already exists in our database.'];
                }
            }
        }

        // ── Process branches ──
        $cleanedBranches = $this->parseBranchesJson($postData['branch_list_json'] ?? '[]');

        // ── Build pubdate ──
        $pubdate = '';
        $submissionTime = date('Y-m-d H:i:s');

        if ($isUpload) {
            [$pubdate, $submissionTime] = $this->buildPubdate($postData);
        }

        // ── Build document data ──
        $docData = [
            'title'       => $postData['title'] ?? '',
            'abstract'    => $postData['abstract'] ?? '',
            'author_list' => $postData['author_list_json'] ?? '',
            'dtype'       => (int)($postData['dtype'] ?? 1),
            'notes'       => $postData['notes'] ?? '',
            'full_text'   => $fullText,
            'tID'         => $tID > 0 ? $tID : null,
        ];

        if ($isUpload) {
            $docData['submitter_ID'] = $mID;
            $docData['pubdate'] = $pubdate;
            $docData['submission_time'] = $submissionTime;
            $docData['link_list'] = json_encode($cleanedLinks);
            $docData['link_list_array'] = $cleanedLinks;
            $docData['branch_list'] = !empty($cleanedBranches) ? json_encode($cleanedBranches) : '';
            $docData['main_size'] = $mainSize;
            $docData['suppl_size'] = $supplSize;
            $docData['suppl_ext'] = $supplExt;
        } elseif ($isEditDraft) {
            // Drafts STILL use has_file as per requirement
            $draftHasFile = $isMainUploaded ? 1 : (int)$draft['has_file'];
            if ($isSupplUploaded) {
                $draftHasFile = ($supplExt === 2 ? 3 : 2);
            } elseif ($isMainUploaded && $existingSupplExt > 0) {
                 $draftHasFile = ($existingSupplExt === 2 ? 3 : 2);
            }
            $docData['has_file'] = $draftHasFile;
            $docData['link_list'] = json_encode($cleanedLinks);
            $docData['branch_list'] = !empty($cleanedBranches) ? json_encode($cleanedBranches) : '';
        } elseif ($isReviseDoc) {
            $docData['revision_notes'] = $postData['revision_notes'] ?? '';
            $docData['main_pages'] = $postData['main_pages'] ?? '';
            $docData['main_figs'] = $postData['main_figs'] ?? '';
            $docData['main_tabs'] = $postData['main_tabs'] ?? '';
            $docData['main_size'] = $mainSize;
            $docData['suppl_size'] = $supplSize;
            $docData['suppl_ext'] = $supplExt;
        }

        // ── Phase 1: DB operation ──
        try {
            if ($isUpload && $action === 'draft') {
                $draftHasFile = $isMainUploaded ? 1 : 0;
                if ($isSupplUploaded) {
                    $draftHasFile = ($supplExt === 2 ? 3 : 2);
                }
                $docData['has_file'] = $draftHasFile;
                
                $dID = $this->documentModel->saveDraft($docData);
                $this->documentModel->resetDraftApprovals($dID);
                $uploadDir = rtrim(UPLOAD_PATH, '/') . '/docdrafts';

            } elseif ($isUpload && $action === 'submit') {
                if ($pubdate === '') {
                    $docData['submission_time'] = date('Y-m-d H:i:s');
                    $pubdate = date('Ymd', strtotime($docData['submission_time']));
                    $docData['pubdate'] = $pubdate;
                }
                $dID = $this->documentModel->submitDocument($docData);
                $path = $this->getPathFromPubdate($pubdate);
                $uploadDir = rtrim(UPLOAD_PATH, '/') . '/' . $path;

                if (!empty($cleanedBranches)) {
                    $this->documentModel->saveBranches($dID, $cleanedBranches);
                }
                if ($tID > 0) {
                    $this->documentModel->saveTopic($dID, $tID);
                }

                if (!empty($docData['author_list'])) {
                    $this->documentModel->saveAuthorsFromList($dID, $docData['author_list']);
                }

                $newVersion = 1;
                $newVerSuppl = $supplSize > 0 ? 1 : null;

            } elseif ($isEditDraft) {
                $this->documentModel->updateDraft($dID, $docData);
                $this->documentModel->resetDraftApprovals($dID);
                $uploadDir = rtrim(UPLOAD_PATH, '/') . '/docdrafts';

            } elseif ($isReviseDoc) {
                $res = $this->documentModel->reviseDocument($dID, $docData, $isMainUploaded, $isSupplUploaded);
                $newVersion = $res['version'];
                $newVerSuppl = $res['ver_suppl'];
                
                $this->documentModel->updateExternalDocs($dID, $cleanedLinks);

                if (!empty($cleanedBranches)) {
                    $this->documentModel->upsertBranches($dID, $cleanedBranches);
                }

                if ($tID > 0) {
                    $this->documentModel->saveTopic($dID, $tID);
                }

                if (!empty($docData['author_list'])) {
                    $this->documentModel->upsertAuthorsFromList($dID, $docData['author_list']);
                }

                $pubdate = $existingDoc['pubdate'] ?? '';
                $path = $this->getPathFromPubdate($pubdate);
                $uploadDir = rtrim(UPLOAD_PATH, '/') . '/' . $path;
            }

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage(), 'dID' => $dID, 'action' => $action];
        }

        // ── Phase 2: File operations ──
        try {
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0750, true);
            }

            $fileErrors = [];

            if ($isMainUploaded) {
                $filename = ($isUpload || $isReviseDoc) ? "{$dID}_v{$newVersion}.pdf" : "{$dID}.pdf";
                if (!move_uploaded_file($fileData['main_file']['tmp_name'], "$uploadDir/$filename")) {
                    $fileErrors[] = 'Main PDF upload failed.';
                }
            }
            
            if ($isSupplUploaded) {
                $ext = ($supplExt === 2 ? 'zip' : 'pdf');
                $filename = ($isUpload || $isReviseDoc) ? "{$dID}_suppl_v{$newVerSuppl}.{$ext}" : "{$dID}_suppl.{$ext}";
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

        // ── Success ──
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

    private function getPathFromPubdate(string $pubdate): string
    {
        $clean = preg_replace('/[^0-9]/', '', $pubdate);
        $len = strlen($clean);

        if ($len >= 8) {
            return substr($clean, 0, 4) . '/' . substr($clean, 4, 2) . '/' . substr($clean, 6, 2);
        } elseif ($len >= 6) {
            return substr($clean, 0, 4) . '/' . substr($clean, 4, 2);
        } elseif ($len >= 4) {
            return substr($clean, 0, 4);
        }
        return date('Y/m/d');
    }

    /**
     * Parse branch_list JSON into enriched branch array.
     */
    private function parseBranchesJson(string $json): array
    {
        $branches = [];
        $branchList = json_decode($json, true) ?? [];
        if (empty($branchList)) return $branches;

        $branchIds = array_column($branchList, 'bID');
        $branchMap = $this->documentModel->getBranchesByIds($branchIds);

        foreach ($branchList as $bl) {
            $bid = (int)$bl['bID'];
            if (isset($branchMap[$bid])) {
                $branches[] = [
                    'bID'    => $bid,
                    'abbr'   => $branchMap[$bid]['abbr'],
                    'bname'  => $branchMap[$bid]['bname'],
                    'num'    => (int)$bl['num'],
                    'impact' => (int)$bl['impact'],
                ];
            }
        }

        usort($branches, fn($a, $b) => $a['num'] <=> $b['num']);
        return $branches;
    }

    /**
     * Clean and validate link_list JSON from form submission.
     * Returns array of [sID, esname, cleanedUrl].
     */
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

    /**
     * Validate uploaded files (size + MIME).
     * Returns ['error' => string|null, 'suppl_ext' => int (0=none,1=PDF,2=ZIP), 'isMainUploaded' => bool, 'isSupplUploaded' => bool]
     */
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

        // Size check
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

    /**
     * Build pubdate and submission_time from date form fields (upload mode only).
     * Returns [pubdate, submissionTime].
     */
    private function buildPubdate(array $postData): array
    {
        $isOld = isset($postData['is_old']) && $postData['is_old'] === '1';
        $pubdate = '';
        $submissionTime = date('Y-m-d H:i:s');

        if (!$isOld) {
            return [$pubdate, $submissionTime];
        }

        $pubYear = trim((string)($postData['pub_year'] ?? ''));
        $pubMonth = trim((string)($postData['pub_month'] ?? ''));
        $pubDay = trim((string)($postData['pub_day'] ?? ''));

        if ($pubYear !== '') {
            $pubdate = str_pad($pubYear, 4, '0', STR_PAD_LEFT);
            if ($pubMonth !== '') {
                $pubdate .= str_pad($pubMonth, 2, '0', STR_PAD_LEFT);
                if ($pubDay !== '') {
                    $pubdate .= str_pad($pubDay, 2, '0', STR_PAD_LEFT);
                }
            }
        }

        $recvYear = trim((string)($postData['recv_year'] ?? ''));
        $recvMonth = trim((string)($postData['recv_month'] ?? ''));
        $recvDay = trim((string)($postData['recv_day'] ?? ''));

        if ($recvYear !== '') {
            $ry = str_pad($recvYear, 4, '0', STR_PAD_LEFT);
            $rm = $recvMonth !== '' ? str_pad($recvMonth, 2, '0', STR_PAD_LEFT) : '00';
            $rd = $recvDay !== '' ? str_pad($recvDay, 2, '0', STR_PAD_LEFT) : '00';
            $submissionTime = "$ry-$rm-$rd 00:00:00";
        } elseif ($pubYear !== '') {
            $py = str_pad($pubYear, 4, '0', STR_PAD_LEFT);
            $pm = $pubMonth !== '' ? str_pad($pubMonth, 2, '0', STR_PAD_LEFT) : '00';
            $pd = $pubDay !== '' ? str_pad($pubDay, 2, '0', STR_PAD_LEFT) : '00';
            $submissionTime = "$py-$pm-$pd 00:00:00";
        }

        return [$pubdate, $submissionTime];
    }
}
