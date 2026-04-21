<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\DocumentRepository;
use app\models\DraftRepository;
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

    public function showUpload(?array $errors = null): void
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
        } else {  // display with errors after the POST method
            $action = $_POST['action'] ?? '';
            if ($action === 'revise') {  // revise_doc with errors
                $this->reviseDoc($_POST['dID'], $errors);
            } elseif ($action === 'edit') {  // edit_draft with errors
                $this->editDraft($_POST['dID'], $errors);
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

        $docData = (array)$draft;

        $this->renderForm('edit_draft', $errors, $dID, $docData);
    }

    public function reviseDoc(string $id, ?array $errors = null): void
    {
        $mID = $this->requireGoodStanding();
        
        $dID = (int)$id;
        $doc = $this->docRepo->getMyDoc('dID', $dID, $mID);

        if (!$doc) {
            http_response_code(403);
            $this->render('errors/403.php');
            exit;
        } elseif ($doc->version >= DOC_REVISION_MAX || $doc->ver_suppl >= DOC_REVISION_MAX) {
            $_SESSION['error_message'] = 'No more than ' . DOC_REVISION_MAX . ' revisions are allowed.';
            header("Location: /document?id=$dID");
            exit;
        } elseif ($doc->version > DOC_REVISION_MAX/2 || $doc->ver_suppl > DOC_REVISION_MAX/2) {
            $errors['revision_warning'] = 'Warning: maxium revisions allowed are '. DOC_REVISION_MAX . '.'; 
        }

        $docData = (array)$doc;

        $topic = $this->docRepo->getDocTopic($dID);
        $docData['tID'] = $topic ? $topic['tID'] : null;
        $docData['branch_list'] = json_encode($this->docRepo->getDocBranches($dID) ?? []);
        $docData['link_list'] = json_encode($this->docRepo->getExternalLinks($dID) ?? []);

        $this->renderForm('revise_doc', $errors, $dID, $docData);
    }

    private function renderForm(string $mode, ?array $errors = null, int $dID = 0, ?array $docData = null): void
    {
        $availableSources = $this->docRepo->getAvailableSources();
        $researchBranches = (new ResearchBranch())->getAllBranches();
        $researchTopics = (new ResearchTopic())->getAllTopics();
        $docTypes = (new DocType())->getAllDocTypes();
        $mainSize = (!empty($docData['main_size'])) ? BaseController::formatSize($docData['main_size']) : '';
        $supplSize = (!empty($docData['suppl_size'])) ? BaseController::formatSize($docData['suppl_size']) : '';

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

        $this->render('repository/upload.php', [
            'mode' => $mode,
            'dID' => $dID,
            'mainSize' => $mainSize,
            'supplSize' => $supplSize,
            'docData' => $docData,
            'errors' => $errors,
            'availableSources' => $availableSources,
            'researchBranches' => $researchBranches,
            'researchTopics' => $researchTopics,
            'docTypes' => $docTypes,
            'pageTitle' => $pageTitle,
            'actionUrl' => $actionUrl,
            'cancelUrl' => $cancelUrl
        ]);
    }
}
