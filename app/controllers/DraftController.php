<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\DraftRepository;
use app\models\DraftService;
use app\models\lookups\ResearchTopic;

/**
 * DraftController
 * 
 * Handles draft-related operations.
 */
class DraftController extends BaseController
{
    private DraftRepository $draftRepo;
    private DraftService $draftService;
    private ResearchTopic $topicModel;

    public function __construct()
    {
        parent::__construct();
        $this->draftRepo = new DraftRepository();
        $this->draftService = new DraftService();
        $this->topicModel = new ResearchTopic();
    }

    // ─────────────────────────────────────────────
    // View Draft
    // ─────────────────────────────────────────────

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
            'isSubmitter'    => $doc->isSubmitter($mID)
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

    // ─────────────────────────────────────────────
    // Stream Draft PDF
    // ─────────────────────────────────────────────

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
        $ext = $doc->suppl_ext ?? '';

        if ($isSuppl) {
            if ($ext === 'pdf') {
                $filePath = "$uploadDir/{$id}_suppl.pdf";
            } elseif ($ext === 'zip') {
                $filePath = "$uploadDir/{$id}_suppl.zip";
                $contentType = 'application/zip';
            }
        } else {
            if (!empty($doc->main_size)) {
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
    // My Drafts
    // ─────────────────────────────────────────────

    public function myDrafts(): void
    {
        $mID = $this->requireLogin();
        
        $drafts = $this->draftRepo->getMyDrafts($mID);

        $this->render('repository/my_drafts.php', ['drafts' => $drafts]);
    }
}
