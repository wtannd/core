<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\FeedDocument;
use app\models\DocumentRepository;
use app\models\lookups\DocType;
use app\models\lookups\ResearchBranch;
use app\models\lookups\ResearchTopic;
use app\models\Member;

/**
 * HomeController
 * 
 * Handles the main dashboard and home page.
 */
class HomeController extends BaseController
{
    private DocType $docTypeModel;
    private ResearchBranch $branchModel;
    private ResearchTopic $topicModel;
    private DocumentRepository $docRepo;
    private Member $memberModel;

    public function __construct()
    {
        parent::__construct();
        $this->docTypeModel = new DocType();
        $this->branchModel = new ResearchBranch();
        $this->topicModel = new ResearchTopic();
        $this->docRepo = new DocumentRepository();
        $this->memberModel = new Member();
    }

    /**
     * Display the main dashboard.
     */
    public function index(): void
    {
        $mRole = $this->getCurrentUserRole();
        
        $docTypes = $this->docTypeModel->getAllDocTypes();
        $branches = $this->branchModel->getAllBranches();
        $topics = $this->topicModel->getAllTopics();
        $result = $this->docRepo->getRecentDocuments(1, 20, $mRole);
        $recentDocs = $result['results'];

        $userWorkAreas = [];
        $userInterestAreas = [];

        $currentUserId = $this->getCurrentUserId();
        if ($currentUserId > 0) {
            $user = $this->memberModel->findUser('mID', $currentUserId);
            if ($user) {
                $userWorkAreas = $this->parseAreasForDisplay($user['work_areas'] ?? '', $branches);
                $userInterestAreas = $this->parseAreasForDisplay($user['interest_areas'] ?? '', $branches);

                if (!empty($user['last_login']) && empty($user['email_verified'])) {
                    $_SESSION['warning_message'] = 'Please verify your email to access all features.';
                }
            }
        }

        $this->render('home/index.php', ['docTypes' => $docTypes, 'branches' => $branches, 'topics' => $topics, 'recentDocs' => $recentDocs, 'userWorkAreas' => $userWorkAreas, 'userInterestAreas' => $userInterestAreas]);
    }

    private function parseAreasForDisplay(string $areasStr, array $branches): array
    {
        $result = [];
        foreach (explode(';', $areasStr) as $part) {
            $id = abs((int)trim($part));
            if ($id === 0) continue;
            $b = $branches[$id] ?? null;
            if (!$b) continue;
            $result[] = [
                'bID'   => $id,
                'label' => $b['abbr'] . ' (' . $b['bname'] . ')',
                'abbr'  => $b['abbr'],
            ];
        }
        return $result;
    }
}
