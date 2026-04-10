<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Document;
use app\models\DocumentRepository;
use app\models\DocumentService;
use app\models\DraftRepository;
use app\models\DraftService;
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
class DocController extends BaseController
{
    private DocumentRepository $docRepo;
    private DocumentService $docService;
    private DraftRepository $draftRepo;
    private DraftService $draftService;
    private Member $memberModel;
    private ResearchTopic $topicModel;

    public function __construct()
    {
        parent::__construct();
        $this->docRepo = new DocumentRepository();
        $this->docService = new DocumentService();
        $this->draftRepo = new DraftRepository();
        $this->draftService = new DraftService();
        $this->memberModel = new Member();
        $this->topicModel = new ResearchTopic();
    }

    // ─────────────────────────────────────────────
    // AJAX Endpoints
    // ─────────────────────────────────────────────

    public function lookupAuthors(): void
    {
        $json = json_decode(file_get_contents('php://input'), true) ?? [];
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

        $results = [];
        if (!empty($parsedIds)) {
            $results = $this->memberModel->findByAlphaIds($parsedIds);
        }

        $this->jsonResponse($results);
    }

    // ─────────────────────────────────────────────
    // View / Display
    // ─────────────────────────────────────────────

    public function feed(): void
    {
        $mRole = $this->getCurrentUserRole();
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $result = $this->docRepo->getRecentDocuments(1, $limit, $mRole);
        $documents = $result['results'];
        $this->render('repository/feed_page.php', ['documents' => $documents, 'limit' => $limit]);
    }

    public function viewDocument(string $id): void
    {
        $mRole = $this->getCurrentUserRole();
        $mID = $this->getCurrentUserId();
        $doc = $this->docRepo->getDocument('dID', (int)$id, $mRole, $mID);
        $this->renderDocument($doc);
    }

    public function viewDocDoi(string $doi): void
    {
        $mRole = $this->getCurrentUserRole();
        $mID = $this->getCurrentUserId();
        $doc = $this->docRepo->getDocument('doi', $doi, $mRole, $mID);
        $this->renderDocument($doc);
    }

    private function renderDocument(?Document $doc): void
    {
        if (!$doc) {
            http_response_code(404);
            $this->render('errors/404.php');
            exit;
        }

        $mID = $this->getCurrentUserId();

        $docData = [
            'doc'    => $doc,
            'extLinks'    => $this->docRepo->getExternalLinks($doc->dID),
            'branches'    => $this->docRepo->getDocBranches($doc->dID),
            'topic'       => $this->docRepo->getDocTopic($doc->dID),
            'isSubmitter' => $doc->isSubmitter($mID),
            'isOnHold'    => $doc->isOnHold()
        ];

        $this->render('repository/view_doc.php', $docData);
    }

    public function viewDocDraft(string $id): void
    {
        $mID = $this->requireLogin();
        
        $doc = $this->draftRepo->getDraft((int)$id, $mID);

        if (!$doc) {
            $draftAuthors = $this->draftRepo->getDraftAuthors((int)$id);
            $isCoAuthor = false;
            foreach ($draftAuthors as $da) {
                if ((int)$da['mID'] === $mID) {
                    $isCoAuthor = true;
                    break;
                }
            }
            if ($isCoAuthor) {
                $doc = $this->draftRepo->getDraftById((int)$id);
            }
        }

        if (!$doc) {
            http_response_code(403);
            $this->render('errors/403.php');
            exit;
        }

        $docData = [
            'doc'            => $doc,
            'draftAuthors'   => $this->draftRepo->getDraftAuthors((int)$id),
            'isFullyApproved'=> $this->draftRepo->isDraftFullyApproved((int)$id),
            'branches'       => $this->draftRepo->getDraftBranches($doc->branch_list),
            'topic'          => !empty($doc->tID) ? $this->topicModel->getTopicById((int)$doc->tID) : null,
            'extLinks'       => $doc->getExtLinks(),
            'isSubmitter'        => $doc->isSubmitter($mID)
        ];

        $this->render('repository/view_docdraft.php', $docData);
    }

    // ─────────────────────────────────────────────
    // Draft Approve / Finalize
    // ─────────────────────────────────────────────

    public function approveDraft(array $postData): void
    {
        $mID = $this->requireLogin();
        
        $dID = (int)($postData['dID'] ?? 0);

        $draftAuthors = $this->draftRepo->getDraftAuthors($dID);
        $isValidAuthor = false;
        foreach ($draftAuthors as $da) {
            if ((int)$da['mID'] === $mID) {
                $isValidAuthor = true;
                break;
            }
        }

        if (!$isValidAuthor) {
            http_response_code(403);
            $this->render('errors/403.php');
            exit;
        }

        if ($this->draftService->approveDraft($dID, $mID)) {
            $_SESSION['success_message'] = "Draft approved successfully.";
        }

        header("Location: /docdraft?id=$dID");
        exit;
    }

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
            $pubdate = $draft->pubdate ?? '';
            $submissionTime = $draft->submission_time ?? date('Y-m-d H:i:s');
            if ($pubdate === '') {
                $submissionTime = date('Y-m-d H:i:s');
                $pubdate = date('Ymd', strtotime($submissionTime));
            }

            // Determine sizes and suppl_ext for submitDocument
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
                'pubdate'         => $pubdate,
                'notes'           => $draft->notes,
                'full_text'       => $draft->full_text,
                'dtype'           => $draft->dtype ?? 1,
                'link_list_array' => json_decode($draft->link_list ?? '[]', true)
            ]);

            $draftBranches = json_decode($draft->branch_list ?? '[]', true) ?? [];
            if (!empty($draftBranches)) {
                $this->docService->saveBranches($newDID, $draftBranches);
            }

            if (!empty($draft->tID)) {
                $this->docService->saveTopic($newDID, (int)$draft->tID);
            }

            // Save authors to DocAuthors
            if (!empty($draft->author_list)) {
                $this->docService->saveAuthorsFromList($newDID, $draft->author_list);
            }

            // Move files from docdrafts/ to YYYY/MM/DD/ with versioned naming
            $path = $this->getPathFromPubdate($pubdate);
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

            // Email co-authors
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

            // Remove the draft now that it's published
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
    // File Streaming
    // ─────────────────────────────────────────────
    // File Streaming
    // ─────────────────────────────────────────────

    /**
     * Stream a published document PDF or supplemental file.
     */
    public function streamDocPdf(string $id, bool $isSuppl = false, ?int $ver = null): void
    {
        $mRole = $this->getCurrentUserRole();
        $mID = $this->getCurrentUserId();

        $doc = $this->docRepo->getDocument('dID', (int)$id, $mRole, $mID);
        if (!$doc) {
            http_response_code(404);
            $this->render('errors/404.php');
            exit;
        }

        $path = $this->getPathFromPubdate($doc->submission_time);
        $uploadDir = UPLOAD_PATH_TRIMMED . '/' . $path;
        $docDoi = $doc->doi ?? '';

        $filePath = null;
        $contentType = 'application/pdf';
        $filePrefix = (!empty($docDoi)) ? $docDoi : $id;

        if ($isSuppl) {
            $supplVersion = $ver !== null ? (int)$ver : (int)($doc->ver_suppl ?? 0);
            if ($supplVersion > 0) {
                $supplExt = (int)($doc->suppl_ext ?? 0);
                
                if ($ver !== null && $ver < (int)($doc->ver_suppl ?? 0)) {
                    $history = $doc->revision_history ?? [];
                    foreach ($history as $rev) {
                        if (isset($rev[1]) && (int)$rev[1] === $ver) {
                            $supplExt = (int)($rev[2] ?? 0);
                            break;
                        }
                    }
                }

                if ($supplExt === 1) {
                    $filePath = "$uploadDir/{$filePrefix}_suppl_v{$supplVersion}.pdf";
                } elseif ($supplExt === 2) {
                    $filePath = "$uploadDir/{$filePrefix}_suppl_v{$supplVersion}.zip";
                    $contentType = 'application/zip';
                }
            }
        } else {
            $mainVersion = $ver !== null ? (int)$ver : (int)($doc->version ?? 0);
            if ($mainVersion > 0) {
                $filePath = "$uploadDir/{$filePrefix}_v{$mainVersion}.pdf";
            }
        }

        if (!$filePath || !file_exists($filePath)) {
            if ($filePrefix !== $id && !empty($filePath)) {
                $dIdBasedPath = str_replace($filePrefix, $id, $filePath);
                if (file_exists($dIdBasedPath)) {
                    $filePath = $dIdBasedPath;
                }
            }
            if (!$filePath || !file_exists($filePath)) {
                http_response_code(404);
                $this->render('errors/404.php');
                exit;
            }
        }

        $this->serveFile($filePath, $contentType);
    }

    /**
     * Stream a draft PDF or supplemental file.
     */
    public function streamDraftPdf(string $id, bool $isSuppl = false): void
    {
        $mID = $this->requireLogin();

        $doc = $this->draftRepo->getDraftById((int)$id);
        if (!$doc) {
            http_response_code(404);
            $this->render('errors/404.php');
            exit;
        }

        $isOwner = ($doc->submitter_ID === $mID);
        if (!$isOwner) {
            $draftAuthors = $this->draftRepo->getDraftAuthors((int)$id);
            foreach ($draftAuthors as $da) {
                if ((int)$da['mID'] === $mID) {
                    $isOwner = true;
                    break;
                }
            }
        }
        if (!$isOwner) {
            http_response_code(403);
            $this->render('errors/403.php');
            exit;
        }

        $uploadDir = UPLOAD_PATH_TRIMMED . '/docdrafts';
        $filePath = null;
        $contentType = 'application/pdf';
        $hasFile = (int)$doc->has_file;

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

        if (!$filePath || !file_exists($filePath)) {
            http_response_code(404);
            $this->render('errors/404.php');
            exit;
        }

        $this->serveFile($filePath, $contentType);
    }

    // ─────────────────────────────────────────────
    // My Documents
    // ─────────────────────────────────────────────

    public function myDocuments(): void
    {
        $mID = $this->requireLogin();
        
        $result = $this->docRepo->getMyDocuments($mID);
        $allDocs = $result['results'];

        $pendingDocs = [];
        $announcedDocs = [];

        foreach ($allDocs as $doc) {
            if ($doc->visibility >= VISIBILITY_ON_HOLD) {
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

        $this->render('repository/my_docs.php', ['announcedDocs' => $announcedSlice, 'pendingDocs' => $pendingDocs, 'totalAnnounced' => $totalAnnounced, 'totalPages' => $totalPages, 'currentPage' => $page]);
    }

    // ─────────────────────────────────────────────
    // My Drafts
    // ─────────────────────────────────────────────

    public function myDrafts(): void
    {
        $mID = $this->requireLogin();
        
        $drafts = $this->draftRepo->getMyDrafts($mID);

        $this->render('repository/my_drafts.php', ['drafts' => $drafts]);
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
        $mID = $this->requireLogin();
        
        $dID = (int)$id;
        $draft = $this->draftRepo->getDraft($dID, $mID);

        if (!$draft) {
            http_response_code(403);
            $this->render('errors/403.php');
            exit;
        }

        $docData = [
            'dID'             => $dID,
            'dtype'           => $draft->dtype,
            'title'           => $draft->title,
            'abstract'        => $draft->abstract,
            'notes'           => $draft->notes ?? '',
            'full_text'       => $draft->full_text ?? '',
            'has_file'        => $draft->has_file,
            'tID'             => $draft->tID,
            'author_list'     => $draft->author_list,
            'branches'        => $draft->branch_list,
            'ext_links'       => $draft->link_list,
            'pubdate'         => $draft->pubdate ?? '',
            'submission_time' => $draft->submission_time ?? '',
        ];

        $this->renderForm('edit_draft', $dID, $docData, $errors);
    }

    public function reviseDoc(string $id, ?array $errors = null): void
    {
        $mID = $this->requireLogin();
        
        $mRole = $this->getCurrentUserRole();
        $dID = (int)$id;
        $doc = $this->docRepo->getDocument('dID', $dID, $mRole, $mID);

        if (!$doc || $doc->submitter_ID !== $mID) {
            http_response_code(403);
            $this->render('errors/403.php');
            exit;
        }

        $topic = $this->docRepo->getDocTopic($dID);

        $docData = [
            'dID'             => $dID,
            'dtype'           => $doc->dtype,
            'title'           => $doc->title,
            'abstract'        => $doc->abstract,
            'notes'           => $doc->notes ?? '',
            'full_text'       => $doc->full_text ?? '',
            'version'         => (int)($doc->version ?? 0),
            'ver_suppl'       => $doc->ver_suppl,
            'suppl_ext'       => (int)($doc->suppl_ext ?? 0),
            'tID'             => $topic ? $topic['tID'] : null,
            'author_list'     => $doc->author_list,
            'branches'        => json_encode($this->docRepo->getDocBranches($dID) ?? []),
            'ext_links'       => $this->docRepo->getExternalLinks($dID),
            'pubdate'         => $doc->pubdate ?? '',
            'submission_time' => $doc->submission_time ?? '',
            'main_pages'      => $doc->main_pages ?? '',
            'main_figs'       => $doc->main_figs ?? '',
            'main_tabs'       => $doc->main_tabs ?? '',
        ];

        $this->renderForm('revise_doc', $dID, $docData, $errors);
    }

    private function renderForm(string $mode, int $dID, ?array $docData = null, ?array $errors = null): void
    {
        $availableSources = $this->docRepo->getAvailableSources();
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

        $this->render('repository/upload.php', [
            'mode' => $mode,
            'dID' => $dID,
            'docData' => $docData,
            'errors' => $errors,
            'availableSources' => $availableSources,
            'institutions' => $institutions,
            'researchBranches' => $researchBranches,
            'researchTopics' => $researchTopics,
            'docTypes' => $docTypes,
            'pageTitle' => $pageTitle,
            'actionUrl' => $actionUrl,
            'cancelUrl' => $cancelUrl,
            'submitLabel' => $submitLabel
        ]);
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
        $mID = $this->requireLogin();
        
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
                if (isset($link[2]) && $this->draftService->checkExternalLinkExists(trim($link[2]))) {
                    return ['success' => false, 'message' => 'Validation failed: The link/DOI ' . $link[2] . ' already exists in our database.'];
                }
            }
        }

        // ── Process branches ──
        $jsonBranches = $postData['branch_list_json'] ?? '[]';
        $arrayBranches = json_decode($jsonBranches, true) ?? [];

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
            $docData['branch_list'] = $jsonBranches;
            $docData['main_size'] = $mainSize;
            $docData['suppl_size'] = $supplSize;
            $docData['suppl_ext'] = $supplExt;
        } elseif ($isEditDraft) {
            // Drafts STILL use has_file as per requirement
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
                
                $dID = $this->draftService->saveDraft($docData);
                $this->draftService->resetDraftApprovals($dID);
                $uploadDir = UPLOAD_PATH_TRIMMED . '/docdrafts';

            } elseif ($isUpload && $action === 'submit') {
                if ($pubdate === '') {
                    $docData['submission_time'] = date('Y-m-d H:i:s');
                    $pubdate = date('Ymd', strtotime($docData['submission_time']));
                    $docData['pubdate'] = $pubdate;
                }
                $dID = $this->docService->submitDocument($docData);
                $path = $this->getPathFromPubdate($pubdate);
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
                $path = $this->getPathFromPubdate($pubdate);
                $uploadDir = UPLOAD_PATH_TRIMMED . '/' . $path;
                $docDoi = $existingDoc->doi ?? '';
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
