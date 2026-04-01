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

    /**
     * AJAX endpoint for looking up authors by CORE-ID.
     */
    public function lookupAuthors(): void
    {
        $json = json_decode(file_get_contents('php://input'), true);
        $rawText = $json['text'] ?? '';

        // Parse text: replace hyphens, split by delimiters, filter valid
        $rawIds = preg_split('/[\n\r,;]+/', $rawText, -1, PREG_SPLIT_NO_EMPTY);
        $parsedIds = [];
        foreach ($rawIds as $id) {
            $clean = str_replace('-', '', trim($id));
            if (!empty($clean)) {
                $clean = ltrim($clean, '0');
                if ($clean === '') $clean = '0'; // Handle edge case of all zeroes
                $parsedIds[] = $clean;
            }
        }

        $results = [];
        if (!empty($parsedIds)) {
            $members = $this->memberModel->findByAlphaIds($parsedIds);
            foreach ($members as $m) {
                $id = $m['ID_alphanum'];
                // Format ID: XXX-XXX-XXX (pad to 9 chars)
                $paddedId = str_pad((string)$id, 9, '0', STR_PAD_LEFT);
                $formattedId = substr($paddedId, 0, 3) . '-' . substr($paddedId, 3, 3) . '-' . substr($paddedId, 6, 3);
                
                $results[] = [
                    'mID'      => (int)$m['mID'],
                    'pub_name' => $m['pub_name'],
                    'core_id'  => $formattedId
                ];
            }
        }

        header('Content-Type: application/json');
        echo json_encode($results);
        exit;
    }

    /**
     * Display the document feed.
     * GET /feed?limit=20
     */
    public function feed(): void
    {
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
        $documents = $this->documentModel->getRecentDocuments(1, $limit, (int)$mRole);
        include rtrim(VIEWS_PATH, '/') . '/repository/feed_page.php';
    }

    /**
     * Display a single document.
     */
    public function viewDocument(string $id): void
    {
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;
        $doc = $this->documentModel->getDocument((int)$id, (int)$mRole);
        $this->renderDocument($doc);
    }

    /**
     * Display a single document by DOI.
     */
    public function viewDocDoi(string $doi): void
    {
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;
        $doc = $this->documentModel->getDocumentByDoi($doi, (int)$mRole);
        $this->renderDocument($doc);
    }

    /**
     * Render a single published document view.
     */
    private function renderDocument(array|false $doc): void
    {
        if (!$doc) {
            http_response_code(404);
            include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
            exit;
        }

        $dID = (int)$doc['dID'];
        $extLinks = $this->documentModel->getExternalLinks($dID);

        $docData = [
            'document'  => $doc,
            'authors'   => json_decode($doc['author_list'], true) ?? [],
            'extLinks'  => $extLinks,
            'branches'  => $this->documentModel->getDocBranches($dID),
            'topic'     => $this->documentModel->getDocTopic($dID),
        ];

        include rtrim(VIEWS_PATH, '/') . '/repository/view_doc.php';
    }

    /**
     * Display a document draft.
     */
    public function viewDocDraft(string $id): void
    {
        if (!isset($_SESSION['mID'])) {
            header('Location: /login');
            exit;
        }

        $mID = (int)$_SESSION['mID'];
        $doc = $this->documentModel->getDraft((int)$id, $mID);

        // If not the submitter, check if they are a co-author
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
                // Fetch the draft using the model method if they are a co-author
                $doc = $this->documentModel->getDraftById((int)$id);
            }
        }

        if (!$doc) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        // Parse branches from branch_list JSON
        $draftBranches = [];
        $branchList = json_decode($doc['branch_list'] ?? '[]', true) ?? [];
        if (!empty($branchList)) {
            $branchIds = array_column($branchList, 'bID');
            $branchMap = $this->documentModel->getBranchesByIds($branchIds);
            foreach ($branchList as $bl) {
                $bid = (int)$bl['bID'];
                if (isset($branchMap[$bid])) {
                    $draftBranches[] = [
                        'bID'    => $bid,
                        'abbr'   => $branchMap[$bid]['abbr'],
                        'bname'  => $branchMap[$bid]['bname'],
                        'num'    => (int)$bl['num'],
                        'impact' => (int)$bl['impact'],
                    ];
                }
            }
            usort($draftBranches, fn($a, $b) => $a['num'] <=> $b['num']);
        }

        // Fetch topic from tID
        $draftTopic = null;
        if (!empty($doc['tID'])) {
            $draftTopic = $this->documentModel->getTopicById((int)$doc['tID']);
        }

        // Parse external links from link_list JSON
        $extLinks = json_decode($doc['link_list'] ?? '[]', true) ?? [];

        $docData = [
            'document'       => $doc,
            'draftAuthors'   => $this->documentModel->getDraftAuthors((int)$id),
            'isFullyApproved'=> $this->documentModel->isDraftFullyApproved((int)$id),
            'branches'       => $draftBranches,
            'topic'          => $draftTopic,
            'extLinks'       => $extLinks,
        ];

        include rtrim(VIEWS_PATH, '/') . '/repository/view_docdraft.php';
    }

    /**
     * Handle draft approval by co-author.
     */
    public function approveDraft(array $postData): void
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

        // Verify user is an author of this draft
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

    /**
     * Finalize submission (move from draft to published).
     */
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

        // Check for full approval
        if (!$this->documentModel->isDraftFullyApproved($dID)) {
            $_SESSION['error_message'] = "Cannot finalize: All co-authors must approve the draft first.";
            header("Location: /docdraft?id=$dID");
            exit;
        }

        try {
            // Finalize submission
            $pubdate = $draft['pubdate'] ?? '';
            $submission_time = $draft['submission_time'] ?? date('Y-m-d H:i:s');
            if ($pubdate === '') {    // for new submission
                $submission_time = date('Y-m-d H:i:s');  // reset the submission time
                // Extract pubdate string from submission_time for file path
                $pubdate = date('Ymd', strtotime($submission_time));
            }
            $newDID = $this->documentModel->submitDocument([
                'submitter_ID'    => $mID,
                'title'           => $draft['title'],
                'abstract'        => $draft['abstract'],
                'author_list'     => $draft['author_list'],
                'has_file'        => $draft['has_file'],
                'submission_time' => $submission_time,
                'pubdate'         => $pubdate,
                'notes'           => $draft['notes'],
                'full_text'       => $draft['full_text'],
                'dtype'           => $draft['dtype'] ?? 1,
                'link_list_array' => json_decode($draft['link_list'] ?? '[]', true)
            ]);

            // Save research branches from draft
            $draftBranches = json_decode($draft['branch_list'] ?? '[]', true) ?? [];
            if (!empty($draftBranches)) {
                $this->documentModel->saveBranches($newDID, $draftBranches);
            }

            // Save topic from draft tID
            if (!empty($draft['tID'])) {
                $this->documentModel->saveTopic($newDID, (int)$draft['tID']);
            }

            // Move files from docdrafts/ to YYYY/MM/DD/
            $path = $this->getPathFromPubdate($pubdate);
            $targetDir = rtrim(UPLOAD_PATH, '/') . '/' . $path;
            if (!is_dir($targetDir)) mkdir($targetDir, 0750, true);

            $draftDir = rtrim(UPLOAD_PATH, '/') . '/docdrafts';
            if (file_exists("$draftDir/{$dID}.pdf")) {
                rename("$draftDir/{$dID}.pdf", "$targetDir/{$newDID}.pdf");
            }
            if (file_exists("$draftDir/{$dID}_suppl.zip")) {
                rename("$draftDir/{$dID}_suppl.zip", "$targetDir/{$newDID}_suppl.zip");
            }

            // --- Send Emails to Co-authors ---
            $draftAuthors = $this->documentModel->getDraftAuthors($dID);
            $subject = 'Your submission to OpenArxiv has been received';
            $baseUrl = SITE_URL;
            $viewUrl = $baseUrl . "/document?id=" . $newDID;
            $body = "Your document titled '" . $draft['title'] . "' has been successfully submitted. It will be officially announced and visible on the platform after 24 hours. You can view your submission here: " . $viewUrl;
            $headers = [
                'From' => SUBMISSION_EMAIL,
                'Reply-To' => SITE_EMAIL,
                'X-Mailer' => 'PHP/' . phpversion()
            ];

            foreach ($draftAuthors as $author) {
                if (!empty($author['email'])) {
                    mail($author['email'], $subject, $body, $headers);
                }
            }

            // Optional: Delete draft record
            // $this->documentModel->deleteDraft($dID);

            $_SESSION['success_message'] = "Document published successfully!";
            header("Location: /document?id=$newDID");
            exit;

        } catch (Exception $e) {
            $errorMessage = "Error finalizing draft operation: " . $e->getMessage();
            include rtrim(VIEWS_PATH, '/') . '/errors/general.php';
            exit;
        }
    }

    /**
     * Securely stream a PDF or Supplemental file.
     */
    public function streamPdf(string $type, string $id, bool $isSuppl = false): void
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
            // Fetch draft and verify access
            $doc = $this->documentModel->getDraftById((int)$id);
            if (!$doc) {
                http_response_code(404);
                include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
                exit;
            }
            // Check if user is submitter or co-author
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
            $doc = $this->documentModel->getDocument((int)$id, (int)$mRole);
            if (!$doc) {
                http_response_code(404);
                include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
                exit;
            }
            $path = $this->getPathFromPubdate($doc['submission_time']);
            $uploadDir = rtrim(UPLOAD_PATH, '/') . '/' . $path;
        }

        $hasFile = (int)$doc['has_file'];
        $filePath = null;
        $contentType = 'application/pdf';

        if ($isSuppl) {
            if ($hasFile === 2) {
                $filePath = "$uploadDir/{$id}_suppl.pdf";
                $contentType = 'application/pdf';
            } elseif ($hasFile === 3) {
                $filePath = "$uploadDir/{$id}_suppl.zip";
                $contentType = 'application/zip';
            } else {
                http_response_code(404);
                include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
                exit;
            }
        } else {
            if ($hasFile >= 1) {
                $filePath = "$uploadDir/{$id}.pdf";
                $contentType = 'application/pdf';
            } else {
                http_response_code(404);
                include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
                exit;
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

    /**
     * Show the upload form.
     */
    public function showUpload(?array $errors = null): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $availableSources = $this->documentModel->getAvailableSources();
        $institutions = (new Institution())->getAllInstitutions();
        $researchBranches = (new ResearchBranch())->getAllBranches();
        $researchTopics = (new ResearchTopic())->getAllTopics();
        $docTypes = (new DocType())->getAllDocTypes();

        include rtrim(VIEWS_PATH, '/') . '/repository/upload.php';
    }

    /**
     * Process document upload (Draft or Submit).
     */
    public function processUpload(array $postData, array $fileData): array
    {
        if (!isset($_SESSION['mID'])) {
            return ['success' => false, 'message' => 'Not authenticated.'];
        }

        $action = $postData['action'] ?? 'draft';
        $mID = (int)$_SESSION['mID'];

        $linkListJson = $postData['link_list_json'] ?? '';
        $fullText = $postData['full_text'] ?? '';
        $tID = (int)($postData['tID'] ?? 0);

        $hasFile = 0;
        $isMainUploaded = isset($fileData['main_file']) && $fileData['main_file']['error'] === UPLOAD_ERR_OK;
        $isSupplUploaded = isset($fileData['supplemental_file']) && $fileData['supplemental_file']['error'] === UPLOAD_ERR_OK;

        // Size validation
        if ($isMainUploaded && $fileData['main_file']['size'] > MAX_UPLOAD_SIZE) {
            return ['success' => false, 'message' => 'File exceeds the maximum allowed size of ' . (MAX_UPLOAD_SIZE / (1024 * 1024)) . 'MB.'];
        }
        if ($isSupplUploaded && $fileData['supplemental_file']['size'] > MAX_UPLOAD_SIZE) {
            return ['success' => false, 'message' => 'File exceeds the maximum allowed size of ' . (MAX_UPLOAD_SIZE / (1024 * 1024)) . 'MB.'];
        }

        // If files are uploaded, full_text should be null/empty
        if ($hasFile > 0) {
            $fullText = '';
        }

        // MIME Type Validation using Fileinfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        if ($isMainUploaded) {
            $mainMime = $finfo->file($fileData['main_file']['tmp_name']);
            if ($mainMime !== 'application/pdf') {
                return ['success' => false, 'message' => 'Security Error: Main document must be a valid PDF.'];
            }
            $hasFile = 1;
        }

        if ($isSupplUploaded) {
            $supplMime = $finfo->file($fileData['supplemental_file']['tmp_name']);
            $ext = strtolower(pathinfo($fileData['supplemental_file']['name'], PATHINFO_EXTENSION));

            if ($ext === 'pdf') {
                if ($supplMime !== 'application/pdf') {
                    return ['success' => false, 'message' => 'Security Error: Supplemental PDF is invalid.'];
                }
                $hasFile = 2;
            } elseif ($ext === 'zip') {
                if ($supplMime !== 'application/zip' && $supplMime !== 'application/x-zip-compressed') {
                    return ['success' => false, 'message' => 'Security Error: Supplemental ZIP is invalid.'];
                }
                $hasFile = 3;
            }
        }

        // Process link list
        $linkListArray = json_decode($linkListJson, true) ?? [];
        $cleanedLinks = [];
        
        foreach ($linkListArray as $link) {
            if (isset($link[2])) {
                // Strip DOI prefix if present
                $cleanUrl = str_ireplace('https://doi.org/', '', $link[2]);
                $cleanUrl = trim($cleanUrl);
                
                // Check if link exists
                if ($this->documentModel->checkExternalLinkExists($cleanUrl)) {
                    return ['success' => false, 'message' => 'Validation failed: The link/DOI ' . $cleanUrl . ' already exists in our database.'];
                }
                
                $cleanedLinks[] = [(int)$link[0], trim($link[1]), $cleanUrl];
            }
        }
        
        // Re-encode for draft storage (as it stores JSON)
        $linkListJson = json_encode($cleanedLinks);

        // Construct pubdate and submission_time from date fields
        $isOld = isset($postData['is_old']) && $postData['is_old'] === '1';
        $pubdate = '';     // keep it empty for new submission until submitting
        $submissionTime = date('Y-m-d H:i:s');

        if ($isOld) {
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
        }

        // Parse branch data
        $branches = json_decode($postData['branch_list_json'] ?? '[]', true) ?? [];
        $cleanedBranches = [];
        foreach ($branches as $b) {
            if (isset($b['bID'], $b['num'], $b['impact']) && (int)$b['bID'] > 0) {
                $cleanedBranches[] = [
                    'bID'    => (int)$b['bID'],
                    'num'    => (int)$b['num'],
                    'impact' => (int)$b['impact']
                ];
            }
        }

        // Collect all relevant fields into a single $docData array
        $docData = [
            'submitter_ID'    => $mID,
            'title'           => $postData['title'] ?? '',
            'abstract'        => $postData['abstract'] ?? '',
            'author_list'     => $postData['author_list_json'] ?? '',
            'has_file'        => $hasFile,
            'dtype'           => (int)($postData['dtype'] ?? 1),
            'notes'           => $postData['notes'] ?? '',
            'full_text'       => $fullText,
            'pubdate'         => $pubdate,
            'link_list'       => $linkListJson, // For draft storage
            'link_list_array' => $cleanedLinks, // For final submission link insertion
            'branch_list'     => !empty($cleanedBranches) ? json_encode($cleanedBranches) : '', // For draft storage
            'tID'             => $tID > 0 ? $tID : null, // For draft storage
            'submission_time' => $submissionTime
        ];

        // Optional metric fields
        if (isset($postData['main_pages']) && $postData['main_pages'] !== '') { $docData['main_pages'] = (int)$postData['main_pages']; }
        if (isset($postData['main_figs']) && $postData['main_figs'] !== '') { $docData['main_figs'] = (int)$postData['main_figs']; }
        if (isset($postData['main_tabs']) && $postData['main_tabs'] !== '') { $docData['main_tabs'] = (int)$postData['main_tabs']; }

        try {
            if ($action === 'draft') {
                $dID = $this->documentModel->saveDraft($docData);
                // Reset approvals on edit/save
                $this->documentModel->resetDraftApprovals((int)$dID);
                $uploadDir = rtrim(UPLOAD_PATH, '/') . '/docdrafts';
            } else {
                if ($pubdate === '') {    // for new submission
                    $docData['submission_time'] = date('Y-m-d H:i:s');
                    // Extract pubdate string from submission_time for file path
                    $pubdate = date('Ymd', strtotime($docData['submission_time']));
                    $docData['pubdate'] = $pubdate;
                }
                $dID = $this->documentModel->submitDocument($docData);
                $path = $this->getPathFromPubdate($pubdate);
                $uploadDir = rtrim(UPLOAD_PATH, '/') . '/' . $path;

                // Save research branches
                if (!empty($cleanedBranches)) {
                    $this->documentModel->saveBranches($dID, $cleanedBranches);
                }

                // Save topic
                if ($tID > 0) {
                    $this->documentModel->saveTopic($dID, $tID);
                }
            }

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0750, true);
            }

            if ($hasFile >= 1) {
                move_uploaded_file($fileData['main_file']['tmp_name'], "$uploadDir/{$dID}.pdf");
            }
            if ($hasFile === 2) {
                move_uploaded_file($fileData['supplemental_file']['tmp_name'], "$uploadDir/{$dID}_suppl.pdf");
            } elseif ($hasFile === 3) {
                move_uploaded_file($fileData['supplemental_file']['tmp_name'], "$uploadDir/{$dID}_suppl.zip");
            }

            return ['success' => true, 'message' => "Document " . ($action === 'draft' ? "saved as draft" : "submitted") . " successfully!"];

        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

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
}
