<?php

declare(strict_types=1);

namespace app\controllers\admin;

use Exception;
use app\controllers\BaseController;
use app\models\Member;

/**
 * DashboardController
 * Handles administrative routes and logic.
 */
class DashboardController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(): void
    {
        $this->requireAdmin();
        $this->render('admin/dashboard.php');
    }

    /**
     * Trigger the UpdateComments stored procedure.
     */
    public function runUpdateComments(): void
    {
        $this->validateCsrf($_POST);

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
