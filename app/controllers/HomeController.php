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
                $userWorkAreas = $this->parseAreasForDisplay($user['work_areas'] ?? '', $branches);
                $userInterestAreas = $this->parseAreasForDisplay($user['interest_areas'] ?? '', $branches);
            }
        }

        include rtrim(VIEWS_PATH, '/') . '/home/index.php';
    }

    /**
     * Parse semicolon-separated area IDs into structured display data.
     * 
     * @return array [['bID' => int, 'label' => string], ...]
     */
    private function parseAreasForDisplay(string $areasStr, array $branches): array
    {
        if (empty($areasStr)) return [];

        $branchMap = [];
        foreach ($branches as $b) {
            $branchMap[(int)$b['bID']] = $b;
        }

        $result = [];
        foreach (explode(';', $areasStr) as $part) {
            $id = abs((int)trim($part));
            if ($id === 0 || !isset($branchMap[$id])) continue;
            $b = $branchMap[$id];
            $result[] = [
                'bID'   => $id,
                'label' => $b['abbr'] . ' (' . $b['bname'] . ')',
                'abbr'  => $b['abbr'],
            ];
        }
        return $result;
    }
}
