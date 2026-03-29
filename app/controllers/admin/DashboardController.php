<?php

declare(strict_types=1);

namespace app\controllers\admin;

use Exception;

use app\models\Member;
// ... other models ...

/**
 * DashboardController
 * * Handles administrative routes and logic.
 */
class DashboardController
{
    public function index(): void
    {
        // 1. Strict Admin Check
        if (empty($_SESSION['admin_role']) || (int)$_SESSION['admin_role'] < ADMIN_ROLE_MIN) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        // 2. Fetch admin data
        // $stats = ...

        // 3. Load admin view
        include rtrim(VIEWS_PATH, '/') . '/admin/dashboard.php';
    }

    /**
     * Trigger the UpdateComments stored procedure.
     */
    public function runUpdateComments(): void
    {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        try {
            $db = \config\Database::getInstance();
            $stmt = $db->prepare("CALL UpdateComments()");
            $stmt->execute();

            $_SESSION['success_message'] = "UpdateComments() procedure executed successfully.";
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Error executing procedure runUpdateComments(): " . $e->getMessage();
        }

        header('Location: /admin/dashboard');
        exit;
    }
}
