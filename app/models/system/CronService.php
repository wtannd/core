<?php

declare(strict_types=1);

namespace app\models\system;

use config\Database;
use PDO;
use Exception;
use PDOException;

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
            if (is_array($result)) {
                $executed[] = $task['task_name'] . ' [' . $result['ok'] . ' OKs and ' . $result['fail'] . ' FAILs]';
            } else {
                $executed[] = $task['task_name'] . ($result ? ' [OK]' : ' [FAIL]');
            }
        }

        error_log("Cron ran at " . date('Y-m-d H:i:s') . " — " . implode(', ', $executed) . "\n", 3, $this->logFile);
    }

    /**
     * Execute a single task. Returns true on success, false on failure or an array of numbers of success and failures.
     */
    private function executeTask(array $task): array|bool
    {
        try {
            switch ($task['task_name']) {
                case 'announce_comment':
                    // (new \app\models\CommentService())->announce();
                    break;
                case 'announce_doc':
                    $result = $this->announceDoc();
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
                case 'cleanup_ratelimits':
                    \app\models\RateLimiter::cleanup(86400);
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

            return $result ?? true;

        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            error_log("Cron error [{$task['task_name']}]: $errorMsg\n", 3, $this->logFile);

            // Failure: unlock and record error, but do NOT advance last_run
            $this->db->prepare(
                "UPDATE SystemTasks SET is_running = 0, last_error = NOW() WHERE task_id = ?"
            )->execute([$task['task_id']]);

            return false;
        }
    }

    /**
     * Finds documents on hold for >24 hours, assigns them a DOI,
     * renames their files, makes them public, and notifies the submitter.
     * Return an array of numbers of success and failures
     */
    public function announceDoc(): array
    {
        $result['ok'] = 0; $result['fail'] = 0;

        // 1. Fetch all documents due for announcement
        // We join the Members table to get the submitter's email address for the notification
        $sql = "SELECT d.dID, d.pubdate, d.submitter_ID, m.email, m.display_name 
                FROM Documents d
                LEFT JOIN Members m ON d.submitter_ID = m.mID
                WHERE d.visibility = :holdStatus 
                  AND d.last_update_time <= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY d.last_update_time ASC";
                  
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['holdStatus' => VISIBILITY_ON_HOLD]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($documents)) {
            return $result; // Nothing to announce
        }

        // 2. Process each document individually
        foreach ($documents as $doc) {
            $isOk = true;
            $dID = (int)$doc['dID'];
            $pubdate = $doc['pubdate'];
            $uploadDir = UPLOAD_PATH_TRIMMED . '/' . str_replace('-', '/', $pubdate);
            
            try {
                // Begin Atomic Transaction per document
                $this->db->beginTransaction();

                // 3. Count items with the same pubdate that already have a DOI
                // no need FOR UPDATE for race conditions as this is the only routine to modify doi 
                $countSql = "SELECT COUNT(*) FROM Documents 
                             WHERE pubdate = :pubdate AND doi IS NOT NULL";
                $countStmt = $this->db->prepare($countSql);
                $countStmt->execute(['pubdate' => $pubdate]);
                $count = (int)$countStmt->fetchColumn();

                // 4. Calculate new values
                $idMini = $count + 1;
                // Convert base 10 to base 36 for the DOI suffix (e.g., 1 -> 1, 10 -> a, 36 -> 10)
                $doiSuffix = strtoupper(base_convert((string)$idMini, 10, 36));
                $doi = str_replace('-', '', $pubdate) . '.' . $doiSuffix;
                $now = date('Y-m-d H:i:s');

                // 5. Update DB Record
                $updateSql = "UPDATE Documents 
                              SET ID_mini = :id_mini,
                                  doi = :doi,
                                  visibility = 1,
                                  announce_time = :now
                              WHERE dID = :dID";
                $updateStmt = $this->db->prepare($updateSql);
                $updateStmt->execute([
                    'id_mini' => $idMini,
                    'doi' => $doi,
                    'now' => $now,
                    'dID' => $dID
                ]);

                // 6. Rename attached files
                if (is_dir($uploadDir)) {
                    // Find all files in the directory prefixed with "{dID}_"
                    $files = glob($uploadDir . '/' . $dID . '_*');
                    
                    if ($files !== false) {
                        foreach ($files as $oldFile) {
                            $filename = basename($oldFile);
                            
                            // Replace the prefix "{dID}_" with "{doi}_"
                            $newFilename = preg_replace('/^' . $dID . '_/', $doi . '_', $filename);
                            $newFile = $uploadDir . '/' . $newFilename;
                            
                            if (!rename($oldFile, $newFile)) {
                                throw new Exception("Failed to rename file $oldFile to $newFile");
                            }
                        }
                    }
                }

                // 7. Commit Transaction
                $this->db->commit();

            } catch (PDOException $e) {
                $this->db->rollBack();
                error_log("DB Error announcing document dID {$dID}: " . $e->getMessage(), 3, $this->logFile);
                $isOk = false;
            } catch (Exception $e) {
                $this->db->rollBack();
                error_log("System Error announcing document dID {$dID}: " . $e->getMessage(), 3, $this->logFile);
                $isOk = false;
            }
            try {
                // 8. Send Notification Email
                if (!empty($doc['email'])) {
                    $to = $doc['email'];
                    $subject = "Your document has been published!";
                    $body = "Hello " . ($doc['display_name'] ?? 'Submitter') . ",\n\n"
                          . "Good news! Your document has passed the hold period and is now officially announced and visible.\n\n"
                          . "Assigned DOI: " . $doi . "  " . SITE_URL . "/doc/" . $doi . "\n\n"
                          . "Best regards,\n" . SITE_TITLE;
                          
                    $headers = [
                        'From' => SITE_EMAIL,
                        'Reply-To' => SITE_EMAIL,
                        'X-Mailer' => 'PHP/' . phpversion()
                    ];
                    
                    mail($to, $subject, $body, $headers);
                }
            } catch (Exception $e) {
                error_log("Error of sending email when announcing document dID {$dID}: " . $e->getMessage(), 3, $this->logFile);
                $isOk = false;
            }
            if ($isOk) {
                $result['ok'] += 1;
            } else {
                $result['fail'] += 1;
            }
        }
        return $result;
    }
}
