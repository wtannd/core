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

    case '/register':
        $authController = new AuthController();
        if ($requestMethod === 'POST') {
            $authController->processRegister($_POST);
        } else {
            $authController->showRegister();
        }
        break;

    case '/logout':
        (new AuthController())->logout();
        break;

    case '/orcid_login':
        (new AuthController())->orcidLogin();
        break;

    case '/orcid_callback':
        if (isset($_GET['code'])) {
            $result = (new AuthController())->orcidCallback($_GET['code']);
            if ($result['success']) {
                header('Location: ' . (!empty($result['pending']) ? '/complete_profile' : '/'));
            } else {
                $errorMessage = $result['message'];
                include rtrim(VIEWS_PATH, '/') . '/errors/general.php';
            }
        }
        break;

    case '/complete_profile':
        $authController = new AuthController();
        if ($requestMethod === 'POST') {
            $authController->processCompleteProfile($_POST);
        } else {
            $authController->showCompleteProfile();
        }
        break;

    // --- Document Management ---
    case '/upload':
        if (!$isLoggedIn) {
            header('Location: /login');
            exit;
        }
        $docController = new \app\controllers\DocController();
        if ($requestMethod === 'POST') {
            $result = $docController->processUpload($_POST, $_FILES);
            if ($result['success']) {
                $_SESSION['success_message'] = $result['message'];
                header('Location: /');
            } else {
                $errors = [$result['message']];
                $docController->showUpload($errors);
            }
            } else {
                $docController->showUpload([]);
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
            (new \app\controllers\DocController())->viewDocDraft($id);
        }
        break;

    case '/draft/approve':
        if ($requestMethod === 'POST' && $isLoggedIn) {
            (new \app\controllers\DocController())->approveDraft($_POST);
        } else {
            header('Location: /login');
        }
        break;

    case '/draft/finalize':
        if ($requestMethod === 'POST' && $isLoggedIn) {
            (new \app\controllers\DocController())->finalizeDraft($_POST);
        } else {
            header('Location: /login');
        }
        break;

    case '/lookupAuthors':
        if ($requestMethod === 'POST') {
            (new \app\controllers\DocController())->lookupAuthors();
        } else {
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'POST method required']);
        }
        exit;

    // --- Member Profiles ---
    case '/profile':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            header('Location: /');
        } else {
            (new \app\controllers\MemberController())->show($id);
        }
        break;

    case '/profile/edit':
        if (!$isLoggedIn) {
            header('Location: /login');
            exit;
        }
        $memberController = new \app\controllers\MemberController();
        if ($requestMethod === 'POST') {
            $memberController->updateProfile($_POST);
        } else {
            $memberController->editProfile();
        }
        break;

    case '/members':
        (new \app\controllers\MemberController())->search();
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

    // --- Utilities ---
    case '/stream':
        $type = $_GET['type'] ?? 'doc';
        $id = $_GET['id'] ?? '';
        $suppl = isset($_GET['suppl']);
        if (empty($id)) {
            http_response_code(400);
            $errorMessage = 'A valid document ID is required to stream the file.';
            include rtrim(VIEWS_PATH, '/') . '/errors/400.php';
            exit;
        }
        (new \app\controllers\DocController())->streamPdf($type, $id, $suppl);
        break;

    // --- Admin Routes ---
    case '/admin':
    case '/admin/dashboard':
        if (!$isAdmin) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }
        (new \app\controllers\admin\DashboardController())->index();
        break;

    case '/admin/update-comments':
        if (!$isAdmin || $requestMethod !== 'POST') {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }
        (new \app\controllers\admin\DashboardController())->runUpdateComments();
        break;

    // --- 404 Not Found ---
    default:
        http_response_code(404);
        include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
        break;
}
