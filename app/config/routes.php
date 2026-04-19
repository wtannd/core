<?php

declare(strict_types=1);

use app\controllers\AuthController;

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

if (preg_match('#^/doc/(\d{4,8}\.[A-Za-z0-9]{1,6})$#', $requestUri, $matches)) {
    $doi = $matches[1];
    (new \app\controllers\DocController())->viewDocDoi($doi);
    exit;
}

if (preg_match('#^/member/([A-Za-z0-9\-]{4,11})$#', $requestUri, $matches)) {
    $coreId = $matches[1];
    (new \app\controllers\MemberController())->show($coreId);
    exit;
}

switch ($requestUri) {
    // --- Home / Dashboard ---
    case '/':
    case '/index.php':
        (new \app\controllers\HomeController())->index();
        break;

    // --- Authentication ---
    case '/login':
        $authController = new AuthController();
        if ($requestMethod === 'POST') {
            $authController->processLogin($_POST);
        } else {
            $authController->showLogin();
        }
        break;

    case '/logout':
        (new AuthController())->logout();
        break;

    case '/verify-email':
        (new \app\controllers\AuthTokenController())->verifyEmail($_GET['token'] ?? '');
        break;

    case '/forgot-password':
        $authTokenController = new \app\controllers\AuthTokenController();
        if ($requestMethod === 'POST') {
            $authTokenController->processForgotPassword($_POST);
        } else {
            $authTokenController->showForgotPassword();
        }
        break;

    case '/reset-password':
        $authTokenController = new \app\controllers\AuthTokenController();
        if ($requestMethod === 'POST') {
            $authTokenController->processResetPassword($_POST);
        } else {
            $authTokenController->showResetPassword($_GET['token'] ?? '');
        }
        break;

    case '/orcid_login':
        (new AuthController())->orcidLogin();
        break;

    case '/orcid_callback':
        if (isset($_GET['code'])) {
            (new AuthController())->orcidCallback($_GET['code']);
        }
        break;

    // --- Search & Feed ---
    case '/search':
        (new \app\controllers\FeedController())->search();
        break;

    case '/match':
        (new \app\controllers\FeedController())->match();
        break;

    case '/browse':
        (new \app\controllers\FeedController())->browse();
        break;

    case '/feed':
        (new \app\controllers\DocController())->feed();
        break;

    case '/mydocs':
        (new \app\controllers\DocController())->myDocuments();
        break;

    case '/mydrafts':
        (new \app\controllers\DraftController())->myDrafts();
        break;

    // --- Document/Draft Upload/View/Management ---
    case '/upload':
        $uploadController = new \app\controllers\DocUploadController();
        if ($requestMethod === 'POST') {
            $result = (new \app\controllers\DocPostController())->processUpload();
            $uploadController->showUpload($result['errors']);
        } else {
            $uploadController->showUpload();
        }
        break;

    case '/edit_draft':
        $uploadController = new \app\controllers\DocUploadController();
        if ($requestMethod === 'POST') {
            $result = (new \app\controllers\DocPostController())->processUpload();
            $uploadController->editDraft($_POST['dID'] ?? '', $result['errors']);
        } else {
            $uploadController->editDraft($_GET['id'] ?? '');
        }
        break;

    case '/revise_doc':
        $uploadController = new \app\controllers\DocUploadController();
        if ($requestMethod === 'POST') {
            $result = (new \app\controllers\DocPostController())->processUpload();
            $uploadController->reviseDoc($_POST['dID'] ?? '', $result['errors']);
        } else {
            $uploadController->reviseDoc($_GET['id'] ?? '');
        }
        break;

    case '/document':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            header('Location: /');
        } else {
            (new \app\controllers\DocController())->viewDocument($id);
        }
        break;

    case '/docdraft':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            header('Location: /');
        } else {
            (new \app\controllers\DraftController())->viewDocDraft($id);
        }
        break;

    case '/draft/approve':
        if ($requestMethod === 'POST' && $isLoggedIn) {
            (new \app\controllers\DraftController())->approveDraft($_POST);
        } else {
            header('Location: /login');
        }
        break;

    case '/draft/finalize':
        if ($requestMethod === 'POST') {
            (new \app\controllers\DocPostController())->finalizeDraft($_POST);
        } else {
            header('Location: /login');
        }
        break;

    case '/lookupAuthors':
        if ($requestMethod === 'POST') {
            (new \app\controllers\api\DocAjaxController())->lookupAuthors();
        } else {
            http_response_code(405); // Method Not Allowed
            $errorMessage = 'POST method is required';
            include VIEWS_PATH_TRIMMED . '/errors/general.php';
        }
        exit;

    // --- Member Registration & Profile Management ---
    case '/register':
        $profileController = new \app\controllers\ProfileController();
        if ($requestMethod === 'POST') {
            $profileController->processRegister($_POST);
        } else {
            $profileController->showRegister();
        }
        break;

    case '/complete_profile':
        $profileController = new \app\controllers\ProfileController();
        if ($requestMethod === 'POST') {
            $profileController->processCompleteProfile($_POST);
        } else {
            $profileController->showCompleteProfile();
        }
        break;

    case '/profile/edit':
        $profileController = new \app\controllers\ProfileController();
        if ($requestMethod === 'POST') {
            $profileController->updateProfile($_POST);
        } else {
            $profileController->editProfile();
        }
        break;

    case '/findmembers':
        (new \app\controllers\MemberController())->search();
        break;

    // --- Utilities ---
    case '/stream':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            http_response_code(400);
            $errorMessage = 'A valid document ID is required to stream the file.';
            include VIEWS_PATH_TRIMMED . '/errors/400.php';
            exit;
        }
        $type = $_GET['type'] ?? 'doc';
        $suppl = isset($_GET['suppl']);
        $ver = isset($_GET['ver']) ? (int)$_GET['ver'] : null;
        if ($type === 'draft' || $type === 'draft_suppl') {
            (new \app\controllers\DraftController())->streamDraftPdf($id, $type === 'draft_suppl');
        } else {
            (new \app\controllers\DocController())->streamDocPdf($id, $suppl, $ver);
        }
        break;

    // --- Admin Routes ---
    case '/admin':
    case '/admin/dashboard':
        if (!$isAdmin) {
            http_response_code(403);
            include VIEWS_PATH_TRIMMED . '/errors/403.php';
            exit;
        }
        (new \app\controllers\admin\DashboardController())->index();
        break;

    case '/admin/update-comments':
        if (!$isAdmin || $requestMethod !== 'POST') {
            http_response_code(403);
            include VIEWS_PATH_TRIMMED . '/errors/403.php';
            exit;
        }
        (new \app\controllers\admin\DashboardController())->runUpdateComments();
        break;

    // --- System ---
    case '/cron':
        (new \app\controllers\system\CronController())->run();
        break;

    // --- 404 Not Found ---
    default:
        http_response_code(404);
        include VIEWS_PATH_TRIMMED . '/errors/404.php';
        break;
}
