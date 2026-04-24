<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;

/**
 * News Model
 *
 * Handles DB operations of the News table.
 */
class News
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // display list of current news
    public function getNewsFeed(int $mRole = 0, int $adminRole = 0): array {
        $sql = "SELECT * FROM News WHERE expire_time < NOW() AND (vis <= :mRole OR (vis > 100 AND (vis - 100) * 10 <= :adminRole)) ORDER BY last_update_time DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['mRole' => $mRole, 'adminRole' => $adminRole]);
        return $stmt->fetchAll();
    }
}
