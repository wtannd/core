<?php

declare(strict_types=1);

namespace app\models\lookups;

use config\Database;
use PDO;

/**
 * Base Model for lookup tables.
 */
class BaseLookupModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generic fetch method for lookup tables.
     * 
     * @param string $tableName
     * @param string $idCol
     * @param string $nameCol Comma-separated list of column names to fetch
     * @param string $orderByCol
     * @param bool $isActive
     * @param int $lowLimit
     * @param int $highLimit
     * @return array
     */
    public function getAll(
        string $tableName, 
        string $idCol, 
        string $nameCol, 
        string $orderByCol, 
        bool $isActive = true, 
        int $lowLimit = 0, 
        int $highLimit = 0
    ): array {
        $whereClauses = [];
        $params = [];

        if ($isActive) {
            $whereClauses[] = "is_active = 1";
        }

        if ($lowLimit !== 0) {
            $whereClauses[] = "{$idCol} > :lowLimit";
            $params['lowLimit'] = $lowLimit;
        }

        if ($highLimit !== 0) {
            $whereClauses[] = "{$idCol} < :highLimit";
            $params['highLimit'] = $highLimit;
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(' AND ', $whereClauses) : "";
        
        $sql = "SELECT {$idCol}, {$nameCol} FROM {$tableName} {$whereSql} ORDER BY {$orderByCol} ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
