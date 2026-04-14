<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Document;
use app\models\DocumentRepository;

/**
 * DocController
 * 
 * Handles document-related business logic.
 */
class DocController extends BaseController
{
    private DocumentRepository $docRepo;

    public function __construct()
    {
        parent::__construct();
        $this->docRepo = new DocumentRepository();
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

        $path = str_replace('-', '/', $doc->pubdate);
        $uploadDir = UPLOAD_PATH_TRIMMED . '/' . $path;
        $docDoi = $doc->doi ?? '';

        $filePath = null;
        $contentType = 'application/pdf';
        $filePrefix = (!empty($docDoi)) ? $docDoi : $id;

        if ($isSuppl) {
            $supplVersion = $ver !== null ? (int)$ver : (int)($doc->ver_suppl ?? 0);
            if ($supplVersion > 0) {
                $supplExt = $doc->suppl_ext ?? '';
                
                if ($ver !== null && $ver < (int)($doc->ver_suppl ?? 0)) {
                    $history = $doc->revision_history ?? [];
                    foreach ($history as $rev) {
                        if (isset($rev[1]) && (int)$rev[1] === $ver) {
                            $supplExt = $rev[2] ?? '';
                            break;
                        }
                    }
                }

                if ($supplExt === 'pdf') {
                    $filePath = "$uploadDir/{$filePrefix}_suppl_v{$supplVersion}.pdf";
                } elseif ($supplExt === 'zip') {
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
}
