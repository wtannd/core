<?php

declare(strict_types=1);

namespace app\models\system;

use config\Database;
use PDO;
use Exception;

/**
 * Cron Service Model
 *
 * Checks the database table 'SystemTasks' for due tasks and executes them.
 */
class CronService
{
    private PDO $db;
    private string $logFile;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logFile = LOG_PATH_TRIMMED . '/cron.log';
    }

    public function runDueTasks(): void
    {
        // Atomically lock and fetch due tasks using UPDATE ... RETURNING pattern
        // Since MySQL doesn't support RETURNING on UPDATE, we use a two-step approach
        // with a single UPDATE that claims tasks atomically.
        $this->db->beginTransaction();

        try {
            // Lock due tasks atomically
            $stmt = $this->db->prepare("
                UPDATE SystemTasks 
                SET is_running = 1 
                WHERE is_running = 0 
                AND TIMESTAMPDIFF(MINUTE, last_run, NOW()) >= freq_minutes
            ");
            $stmt->execute();

            // Fetch the tasks we just locked
            $stmt = $this->db->query("
                SELECT * FROM SystemTasks WHERE is_running = 1
            ");
            $tasks = $stmt->fetchAll();

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Cron lock error: " . $e->getMessage(), 3, $this->logFile);
            return;
        }

        if (empty($tasks)) {
            return;
        }

        $executed = [];

        foreach ($tasks as $task) {
            $result = $this->executeTask($task);
            $executed[] = $task['task_name'] . ($result ? ' [OK]' : ' [FAIL]');
        }

        error_log("Cron ran at " . date('Y-m-d H:i:s') . " — " . implode(', ', $executed), 3, $this->logFile);
    }

    /**
     * Execute a single task. Returns true on success, false on failure.
     */
    private function executeTask(array $task): bool
    {
        try {
            switch ($task['task_name']) {
                case 'announce_comment':
                    // (new \app\models\CommentManager())->announce();
                    break;
                case 'announce_doc':
                    (new \app\models\DocumentManager())->announce();
                    break;
                case 'calc_ecp':
                    // (new \app\models\evaluations\EcpRecord())->calculateAll();
                    break;
                case 'calc_als':
                    // (new \app\models\evaluations\AlsRecord())->calculateAll();
                    break;
                case 'move_logs':
                    // (new System())->moveLogs();
                    break;
                case 'check_misc':
                    // Run other checks
                    break;
                default:
                    throw new Exception("Unknown task: {$task['task_name']}");
            }

            // Success: update last_run, clear error, unlock
            $this->db->prepare(
                "UPDATE SystemTasks SET is_running = 0, last_run = NOW() WHERE task_id = ?"
            )->execute([$task['task_id']]);

            return true;

        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            error_log("Cron error [{$task['task_name']}]: $errorMsg", 3, $this->logFile);

            // Failure: unlock and record error, but do NOT advance last_run
            $this->db->prepare(
                "UPDATE SystemTasks SET is_running = 0, last_error = NOW() WHERE task_id = ?"
            )->execute([$task['task_id']]);

            return false;
        }
    }
}
