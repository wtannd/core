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
    // Form: Show (upload / edit_draft / revise_doc) for both before and after POST with errors
    // ─────────────────────────────────────────────

    public function showUpload(?array $errors = null, string $id = ''): void
    {
        $this->requireGoodStanding();
        if (empty($errors)) {  // GET method
            if (!empty($_GET['doc'])) {
               $this->reviseDoc($_GET['doc']);
            } elseif (!empty($_GET['draft'])) {
               $this->editDraft($_GET['draft']);
            } else {
               $this->renderForm('upload');
            }
        } else {  // display after the POST method
            $action = $_POST['action'] ?? '';
            if ($action === 'revise') {  // revise_doc with errors
                $this->reviseDoc($id, $errors);
            } elseif ($action === 'edit') {  // edit_draft with errors
                $this->editDraft($id, $errors);
            } else {  // new upload with errors
                $this->renderForm('upload', $errors);
            }
        }
    }

    public function editDraft(string $id, ?array $errors = null): void
    {
        $mID = $this->requireGoodStanding();
        
        $dID = (int)$id;
        $draft = $this->draftRepo->getMyDraft($dID, $mID);

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
            'main_size'       => $draft->main_size ?? 0,
            'suppl_size'      => $draft->suppl_size ?? 0,
            'suppl_ext'       => $draft->suppl_ext ?? '',
            'tID'             => $draft->tID,
            'author_list'     => $draft->author_list,
            'branches'        => $draft->branch_list,
            'ext_links'       => $draft->link_list,
            'pub_date'        => $draft->pub_date ?? '',
            'recv_date'       => $draft->recv_date ?? '',
            'main_pages'      => $draft->main_pages ?? '',
            'main_figs'       => $draft->main_pages ?? '',
            'main_tabs'       => $draft->main_pages ?? '',
        ];

        $this->renderForm('edit_draft', $errors, $dID, $docData);
    }

    public function reviseDoc(string $id, ?array $errors = null): void
    {
        $mID = $this->requireGoodStanding();
        
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
            'suppl_ext'       => $doc->suppl_ext ?? '',
            'tID'             => $topic ? $topic['tID'] : null,
            'author_list'     => $doc->author_list,
            'branches'        => json_encode($this->docRepo->getDocBranches($dID) ?? []),
            'ext_links'       => json_encode($this->docRepo->getExternalLinks($dID) ?? []),
            'pubdate'         => $doc->pubdate ?? '',
            'main_size'       => $doc->main_size ?? 0,
            'suppl_size'      => $doc->suppl_size ?? 0,
            'main_pages'      => $doc->main_pages ?? '',
            'main_figs'       => $doc->main_figs ?? '',
            'main_tabs'       => $doc->main_tabs ?? '',
        ];

        $this->renderForm('revise_doc', $errors, $dID, $docData);
    }

    private function renderForm(string $mode, ?array $errors = null, int $dID = 0, ?array $docData = null): void
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
