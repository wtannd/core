<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\DocumentRepository;
use app\models\DraftRepository;
use app\models\lookups\Institution;
use app\models\lookups\ResearchBranch;
use app\models\lookups\DocType;
use app\models\lookups\ResearchTopic;

/**
 * DocUploadController
 * 
 * Handles document upload, edit draft, and revise document views.
 */
class DocUploadController extends BaseController
{
    private DocumentRepository $docRepo;
    private DraftRepository $draftRepo;

    public function __construct()
    {
        parent::__construct();
        $this->docRepo = new DocumentRepository();
        $this->draftRepo = new DraftRepository();
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
}