<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;

/**
 * Institution Model
 *
 * Handles DB operations related to the 'Institutions' table.
 */
class Institution
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Search institutions by a partial name match.
     *
     * @param string $query
     * @param int $limit
     * @return array
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        // Escape SQL wildcards to prevent search abuse
        $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $like = '%' . $escapedQuery . '%';

        $sql = "SELECT iID, iname 
                FROM Institutions 
                WHERE iname LIKE :query 
                ORDER BY iname ASC 
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', $like, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
