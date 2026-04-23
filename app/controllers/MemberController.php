<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\FeedDocument;
use app\models\DocumentRepository;
use app\models\Member;

/**
 * MemberController
 * 
 * Handles public member profile requests and personal profile edits.
 */
class MemberController extends BaseController
{
    private Member $memberModel;
    private DocumentRepository $docRepo;

    public function __construct()
    {
        parent::__construct();
        $this->memberModel = new Member();
        $this->docRepo = new DocumentRepository();
    }

    /**
     * Display a member's public profile.
     *
     * @param string $coreId
     */
    public function show(string $coreId): void
    {
        $cleanId = ltrim(str_replace('-', '', strtoupper($coreId)), '0');

        if (empty($cleanId)) {
            http_response_code(400);
            $errorMessage = "The provided ID is not a valid CORE-ID.";
            $this->render('errors/400.php', ['errorMessage' => $errorMessage]);
            exit;
        }

        // 1. Call model to get user data - fully formatted by the model (Fat Model)
        $member = $this->memberModel->getPublicProfileByCoreID($cleanId);

        if (!$member) {
            http_response_code(404);
            $this->render('errors/404.php');
            exit;
        }

        // 2. Handle email visibility (Controller-level privacy check)
        if (!(int)$member['is_email_public']) {
            unset($member['email']);
        }

        // 3. Fetch authored documents with pagination
        $mRole = $this->getCurrentUserRole();
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $offset = ($currentPage - 1) * $perPage;

        $authoredResult = $this->docRepo->getDocumentsByAuthor((int)$member['mID'], $mRole, $perPage, $offset);
        $authoredDocs = $authoredResult['results'];
        $totalAuthored = $authoredResult['total'];
        $totalPages = max(1, (int)ceil($totalAuthored / $perPage));

        $this->render('member/profile.php', ['member' => $member, 'authoredDocs' => $authoredDocs, 'totalAuthored' => $totalAuthored, 'currentPage' => $currentPage, 'totalPages' => $totalPages]);
    }

    /**
     * Search members by name.
     * GET /findmembers?q=smith&page=N
     */
    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $result = $this->memberModel->searchMembers($query, $perPage, $offset);
        $members = $result['results'];
        $totalResults = $result['total'];
        $totalPages = max(1, (int)ceil($totalResults / $perPage));

        $buildPageUrl = function (int $p) use ($query) {
            return '/findmembers?q=' . urlencode($query) . '&page=' . $p;
        };

        $this->render('member/search_results.php', ['members' => $members, 'query' => $query, 'totalResults' => $totalResults, 'totalPages' => $totalPages, 'currentPage' => $page]);
    }
}
