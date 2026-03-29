<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\lookups\DocType;
use app\models\lookups\ResearchBranch;
use app\models\Document;
use app\models\Member;

/**
 * HomeController
 * 
 * Handles the main dashboard and home page.
 */
class HomeController
{
    private DocType $docTypeModel;
    private ResearchBranch $branchModel;
    private Document $documentModel;
    private Member $memberModel;

    public function __construct()
    {
        $this->docTypeModel = new DocType();
        $this->branchModel = new ResearchBranch();
        $this->documentModel = new Document();
        $this->memberModel = new Member();
    }

    /**
     * Display the main dashboard.
     */
    public function index(): void
    {
        $mRole = $_SESSION['mrole'] ?? 0;
        
        $docTypes = $this->docTypeModel->getAllDocTypes();
        $branches = $this->branchModel->getAllBranches();
        $recentDocs = $this->documentModel->getRecentDocuments(1, 20, (int)$mRole);

        $userWorkAreas = [];
        $userInterestAreas = [];

        if (isset($_SESSION['mID'])) {
            $user = $this->memberModel->findById((int)$_SESSION['mID']);
            if ($user) {
                $userWorkAreas = $user['work_areas_display'] ?? [];
                $userInterestAreas = $user['interest_areas_display'] ?? [];
            }
        }

        include rtrim(VIEWS_PATH, '/') . '/home/index.php';
    }
}
