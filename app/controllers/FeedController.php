<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\FeedDocument;
use app\models\DocumentRepository;
use app\models\lookups\DocType;
use app\models\lookups\ResearchBranch;
use app\models\lookups\ResearchTopic;

/**
 * FeedController
 * 
 * Handles all document search, matching, and filtered feeds.
 */
class FeedController
{
    private DocumentRepository $docRepo;
    private DocType $docTypeModel;
    private ResearchBranch $branchModel;
    private ResearchTopic $topicModel;
    private int $perPage = 20;

    public function __construct()
    {
        $this->docRepo = new DocumentRepository();
        $this->docTypeModel = new DocType();
        $this->branchModel = new ResearchBranch();
        $this->topicModel = new ResearchTopic();
    }

    /**
     * FULLTEXT search on title + abstract (header search bar).
     * GET /search?q=keyword&page=N
     */
    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $this->perPage;
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;

        $result = $this->docRepo->searchDocuments($query, [], $this->perPage, $offset, (int)$mRole);

        $documents = $result['results'];
        $totalResults = $result['total'];
        $totalPages = max(1, (int)ceil($totalResults / $this->perPage));

        // Build pagination URL
        $buildPageUrl = function (int $p) use ($query) {
            return '/search?q=' . urlencode($query) . '&page=' . $p;
        };

        // View data
        $pageTitle = 'Search: ' . htmlspecialchars($query);
        $pageHeading = 'Search Results';
        $searchQuery = $query;
        $filters = [];
        $showFilters = true;
        $filterAction = '/search';
        $filterMethod = 'GET';

        $docTypes = $this->docTypeModel->getAllDocTypes();
        $branches = $this->branchModel->getAllBranches();
        $topics = $this->topicModel->getAllTopics();
        include VIEWS_PATH_TRIMMED . '/repository/search_results.php';
    }

    /**
     * Title + abstract matching with filters.
     * GET /match?q=keyword&type=X&branch=Y&topic=Z&range=week&page=N
     */
    public function match(): void
    {
        $query = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $this->perPage;
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;

        $filters = $this->extractFilters($_GET);

        $result = $this->docRepo->searchDocuments($query, $filters, $this->perPage, $offset, (int)$mRole);

        $documents = $result['results'];
        $totalResults = $result['total'];
        $totalPages = max(1, (int)ceil($totalResults / $this->perPage));

        $buildPageUrl = function (int $p) use ($query, $filters) {
            $params = array_merge(['q' => $query, 'page' => $p], array_filter($filters));
            return '/match?' . http_build_query($params);
        };

        $pageTitle = 'Match: ' . htmlspecialchars($query);
        $pageHeading = 'Match Results';
        $searchQuery = $query;
        $showFilters = true;
        $filterAction = '/match';
        $filterMethod = 'GET';

        $docTypes = $this->docTypeModel->getAllDocTypes();
        $branches = $this->branchModel->getAllBranches();
        $topics = $this->topicModel->getAllTopics();
        include VIEWS_PATH_TRIMMED . '/repository/search_results.php';
    }

    /**
     * Filtered document browse (no query required).
     * GET /browse?type=X&branch=Y&topic=Z&range=week&from=...&to=...&page=N
     */
    public function browse(): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $this->perPage;
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;

        $filters = $this->extractFilters($_GET);

        if (empty(array_filter($filters))) {
            $filters['range'] = 'month';
        }

        $result = $this->docRepo->getDocumentsByFilter($filters, $this->perPage, $offset, (int)$mRole);

        $documents = $result['results'];
        $totalResults = $result['total'];
        $totalPages = max(1, (int)ceil($totalResults / $this->perPage));

        $buildPageUrl = function (int $p) use ($filters) {
            $params = array_merge(['page' => $p], array_filter($filters));
            return '/browse?' . http_build_query($params);
        };

        $filterDesc = $this->describeFilters($filters);
        $pageTitle = 'Browse: ' . $filterDesc;
        $pageHeading = 'Browse Documents';
        $searchQuery = '';
        $showFilters = true;
        $filterAction = '/browse';
        $filterMethod = 'GET';

        $docTypes = $this->docTypeModel->getAllDocTypes();
        $branches = $this->branchModel->getAllBranches();
        $topics = $this->topicModel->getAllTopics();
        include VIEWS_PATH_TRIMMED . '/repository/search_results.php';
    }

    /**
     * Extract filter parameters from GET data.
     */
    private function extractFilters(array $data): array
    {
        return [
            'type'  => $data['type'] ?? '',
            'branch' => $data['branch'] ?? '',
            'topic'  => $data['topic'] ?? '',
            'range'  => $data['range'] ?? '',
            'from'   => $data['from'] ?? '',
            'to'     => $data['to'] ?? '',
        ];
    }

    /**
     * Build a human-readable filter description.
     */
    private function describeFilters(array $filters): string
    {
        $parts = [];
        if (!empty($filters['range'])) {
            $parts[] = ucfirst($filters['range']);
        }
        if (!empty($filters['from'])) {
            $parts[] = 'from ' . $filters['from'];
        }
        if (!empty($filters['to'])) {
            $parts[] = 'to ' . $filters['to'];
        }
        return !empty($parts) ? implode(' ', $parts) : 'All Documents';
    }
}
