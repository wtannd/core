<?php
declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;

class RateLimiter
{
    /**
     * Checks if an action is within the rate limit, and increments the counter.
     *
     * @param string $actionKey Unique identifier (e.g., 'pwd_reset_ip_192.168.1.1')
     * @param int $maxAttempts Maximum allowed attempts in the time window
     * @param int $timeWindowSeconds How long the window lasts (e.g., 3600 for 1 hour)
     * @return bool True if allowed, False if rate limit exceeded
     */
    public static function checkAndIncrement(string $actionKey, int $maxAttempts, int $timeWindowSeconds): bool
    {
        $db = Database::getInstance();
        $now = time();
        $windowStart = $now - $timeWindowSeconds;

        // 1. Atomic Upsert: 
        // If it's a new key, insert it with 1 attempt.
        // If it exists but is older than the window, reset it to 1 attempt and update the time.
        // If it exists and is within the window, increment the attempts.
        $sql = "INSERT INTO RateLimits (action_key, attempts, first_attempt_time) VALUES (:key, 1, :now)
                ON DUPLICATE KEY UPDATE 
                attempts = IF(first_attempt_time < :window_start, 1, attempts + 1),
                first_attempt_time = IF(first_attempt_time < :window_start2, :now2, first_attempt_time)
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'key'          => $actionKey,
            'now'          => $now,
            'window_start' => $windowStart,
            'window_start2' => $windowStart,
            'now2'          => $now
        ]);

        // 2. Fetch the current attempts count
        $stmt = $db->prepare("SELECT attempts FROM RateLimits WHERE action_key = :key");
        $stmt->execute(['key' => $actionKey]);
        $attempts = (int)$stmt->fetchColumn();

        // 3. Return true if they are under or exactly at the limit
        return $attempts <= $maxAttempts;
    }

    /**
     * Optional: Clear out old records to keep the database table tiny.
     * You can call this from a Cron job or occasionally during admin actions.
     */
    public static function cleanup(int $olderThanSeconds = 86400): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("DELETE FROM RateLimits WHERE first_attempt_time < :cutoff");
        $stmt->execute(['cutoff' => time() - $olderThanSeconds]);
    }
}
