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
    protected function getAll(
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

    /**
     * Generic fetch for a single record by its ID.
     * * @param string $tableName The table to query
     * @param string $idCol The primary/unique key column name
     * @param int|string $idValue The value to search for
     * @param string $fetchCols Comma-separated columns to return (defaults to '*')
     * @return array|null Associative array of the record, or null if not found
     */
    protected function getOne(
        string $tableName, 
        string $idCol, 
        int|string $idValue, 
        string $fetchCols = '*'
    ): ?array {
        // Prepare the SQL safely using PDO
        $sql = "SELECT {$fetchCols} FROM {$tableName} WHERE {$idCol} = :id LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $idValue]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null; // Return the array, or strictly null if not found
    }
}
