<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;

/**
 * DocumentRepository
 * 
 * READ operations for published Documents (SELECT queries only).
 */
class DocumentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Fetch recent documents with visibility filtering and type filtering.
     * @return array{results: FeedDocument[], total: int}
     */
    public function getRecentDocuments(int $dtID = 1, int $limit = 20, int $mRole = 0): array
    {
        $countSql = "SELECT COUNT(*) FROM Documents d WHERE d.dtype = :dtID AND :mRole >= d.visibility";
        $stmt = $this->db->prepare($countSql);
        $stmt->bindValue(':dtID', $dtID, PDO::PARAM_INT);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        $stmt->execute();
        $total = (int)$stmt->fetchColumn();
        
        $sql = "SELECT d.dID, d.doi, d.version, d.ver_suppl, d.main_pages, d.main_size, 
                       d.submission_time, d.author_list, d.abstract, d.title, d.visibility
                FROM Documents d
                WHERE d.dtype = :dtID AND :mRole >= d.visibility
                ORDER BY d.announce_time DESC
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':dtID', $dtID, PDO::PARAM_INT);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $rows = $stmt->fetchAll();
        return [
            'results' => array_map(fn($row) => new FeedDocument($row), $rows),
            'total' => $total
        ];
    }

    /**
     * Fetch a single document by dID or DOI with visibility filtering.
     * Authors listed in DocAuthors can view regardless of visibility.
     *
     * @param string $column - The column to search by ('dID' or 'doi')
     * @param mixed $idValue - The value to search for
     * @param int $mRole - User's role for visibility check
     * @param int $mID - User's member ID for author check
     * @return Document|null
     * @throws \InvalidArgumentException If column is not 'dID' or 'doi'
     */
    public function getDocument(string $column, mixed $idValue, int $mRole, int $mID = 0): ?Document
    {
        // Security check: Only allow specific columns to prevent SQL Injection
        $allowedColumns = ['dID', 'doi'];
        if (!in_array($column, $allowedColumns, true)) {
            throw new \InvalidArgumentException("Invalid search column.");
        }

        $sql = "SELECT d.*, m.pub_name as submitter_name, m.ID_alphanum as submitter_coreid
                FROM Documents d
                JOIN Members m ON d.submitter_ID = m.mID
                WHERE d.$column = :idValue AND (:mRole2 >= d.visibility OR EXISTS (
                    SELECT 1 FROM DocAuthors da WHERE da.dID = d.dID AND da.mID = :mID
                ))
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'idValue' => $idValue,
            'mRole2' => $mRole,
            'mID'    => $mID
        ]);

        $row = $stmt->fetch();
        return $row ? new Document($row) : null;
    }

    // get my submitted document
    public function getMyDoc(string $column, mixed $idValue, int $mID = 0): ?Document
    {
        // Security check: Only allow specific columns to prevent SQL Injection
        $allowedColumns = ['dID', 'doi'];
        if (!in_array($column, $allowedColumns, true)) {
            throw new \InvalidArgumentException("Invalid search column.");
        }

        $sql = "SELECT * FROM Documents 
                WHERE $column = :idValue AND submitter_ID = :mID LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'idValue' => $idValue,
            'mID'    => $mID
        ]);

        $row = $stmt->fetch();
        return $row ? new Document($row) : null;
    }

    /**
     * Get available external sources.
     */
    public function getAvailableSources(): array
    {
        $stmt = $this->db->query("SELECT sID, esname FROM ExternalSources WHERE is_active = 1 ORDER BY esname ASC");
        return $stmt->fetchAll();
    }

    /**
     * Fetch branches for a document.
     */
    public function getDocBranches(int $dID): array
    {
        $sql = "SELECT db.bID, db.num, db.impact, rb.abbr, rb.bname
                FROM DocBranches db
                JOIN ResearchBranches rb ON db.bID = rb.bID
                WHERE db.dID = :dID
                ORDER BY db.num ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch topic for a document.
     */
    public function getDocTopic(int $dID): array|false
    {
        $sql = "SELECT rt.tID, rt.abbr, rt.tname
                FROM DocTopics dt
                JOIN ResearchTopics rt ON dt.tID = rt.tID
                WHERE dt.dID = :dID
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        return $stmt->fetch();
    }

    /**
     * Get external links for a document.
     */
    public function getExternalLinks(int $dID): array
    {
        $sql = "SELECT ed.sID, es.esname, ed.link 
                FROM ExternalDocs ed
                INNER JOIN ExternalSources es ON ed.sID = es.sID
                WHERE ed.dID = :dID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        return $stmt->fetchAll(PDO::FETCH_NUM);
    }

    /**
     * Search documents with filters.
     * @return array{results: FeedDocument[], total: int}
     */
    public function searchDocuments(string $query, array $filters, int $limit, int $offset, int $mRole = 0): array
    {
        $paramIdx = 0;
        $params = [];

        $countSql = "SELECT COUNT(*)
                     FROM Documents d
                     WHERE :mRole >= d.visibility";

        if (!empty($query)) {
            $countSql .= " AND (MATCH(d.title, d.abstract) AGAINST(:query IN BOOLEAN MODE) OR d.title LIKE :likeQuery)";
            $params['query'] = $query;
            $params['likeQuery'] = "%$query%";
        }

        [$whereClause, $params] = $this->buildFilterWhere($filters, $paramIdx, $params);
        $countSql .= $whereClause;

        $stmt = $this->db->prepare($countSql);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT d.dID, d.doi, d.version, d.ver_suppl, d.main_pages, d.main_size, 
                       d.submission_time, d.author_list, d.abstract, d.title, d.visibility
                FROM Documents d
                WHERE :mRole >= d.visibility";

        if (!empty($query)) {
            $sql .= " AND (MATCH(d.title, d.abstract) AGAINST(:query IN BOOLEAN MODE) OR d.title LIKE :likeQuery)";
        }

        $sql .= $whereClause;
        $sql .= " ORDER BY d.announce_time DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return [
            'results' => array_map(fn($row) => new FeedDocument($row), $rows),
            'total' => $total
        ];
    }

    /**
     * Get documents by filter (browse without search query).
     * @return array{results: FeedDocument[], total: int}
     */
    public function getDocumentsByFilter(array $filters, int $limit, int $offset, int $mRole = 0): array
    {
        $params = [];

        [$whereClause, $params] = $this->buildFilterWhere($filters, 0, $params);

        $countSql = "SELECT COUNT(*)
                     FROM Documents d
                     WHERE :mRole >= d.visibility" . $whereClause;

        $stmt = $this->db->prepare($countSql);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT d.dID, d.doi, d.version, d.ver_suppl, d.main_pages, d.main_size, 
                       d.submission_time, d.author_list, d.abstract, d.title, d.visibility
                FROM Documents d
                WHERE :mRole >= d.visibility" . $whereClause;

        $sql .= " ORDER BY d.announce_time DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return [
            'results' => array_map(fn($row) => new FeedDocument($row), $rows),
            'total' => $total
        ];
    }

    /**
     * Build WHERE clause from filters.
     */
    private function buildFilterWhere(array $filters, int $paramIdx = 0, array $params = []): array
    {
        $whereParts = [];

        if (!empty($filters['dtype'])) {
            $whereParts[] = "d.dtype = :dtype$paramIdx";
            $params["dtype$paramIdx"] = (int)$filters['dtype'];
            $paramIdx++;
        }

        if (!empty($filters['branch'])) {
            $whereParts[] = "EXISTS (
                SELECT 1 FROM DocBranches db WHERE db.dID = d.dID AND db.bID = :branch$paramIdx
            )";
            $params["branch$paramIdx"] = (int)$filters['branch'];
            $paramIdx++;
        }

        if (!empty($filters['topic'])) {
            $whereParts[] = "EXISTS (
                SELECT 1 FROM DocTopics dt WHERE dt.dID = d.dID AND dt.tID = :topic$paramIdx
            )";
            $params["topic$paramIdx"] = (int)$filters['topic'];
            $paramIdx++;
        }

        if (!empty($filters['author'])) {
            $whereParts[] = "EXISTS (
                SELECT 1 FROM DocAuthors da 
                JOIN Members m ON da.mID = m.mID 
                WHERE da.dID = d.dID AND m.ID_alphanum = :author$paramIdx
            )";
            $params["author$paramIdx"] = $filters['author'];
            $paramIdx++;
        }

        if (!empty($filters['range'])) {
            $days = match ($filters['range']) {
                'day' => 1,
                'week' => 7,
                'month' => 30,
                'year' => 365,
                default => 7
            };
            $whereParts[] = "d.announce_time >= DATE_SUB(NOW(), INTERVAL :days$paramIdx DAY)";
            $params["days$paramIdx"] = $days;
            $paramIdx++;
        }

        return [empty($whereParts) ? '' : ' AND ' . implode(' AND ', $whereParts), $params];
    }

    /**
     * Get documents by author.
     * @return array{results: FeedDocument[], total: int}
     */
    public function getDocumentsByAuthor(int $mID, int $mRole, int $limit, int $offset): array
    {
        $countSql = "SELECT COUNT(*)
                     FROM Documents d
                     JOIN DocAuthors da ON d.dID = da.dID
                     WHERE da.mID = :mID AND :mRole >= d.visibility";

        $stmt = $this->db->prepare($countSql);
        $stmt->bindValue(':mID', $mID, PDO::PARAM_INT);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        $stmt->execute();
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT d.dID, d.doi, d.version, d.ver_suppl, d.main_pages, d.main_size, 
                       d.submission_time, d.author_list, d.abstract, d.title, d.visibility
                FROM Documents d
                JOIN DocAuthors da ON d.dID = da.dID
                WHERE da.mID = :mID AND :mRole >= d.visibility
                ORDER BY d.announce_time DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':mID', $mID, PDO::PARAM_INT);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return [
            'results' => array_map(fn($row) => new FeedDocument($row), $rows),
            'total' => $total
        ];
    }

    /**
     * Get documents authored by a member (no visibility filter).
     * @return array{results: FeedDocument[], total: int}
     */
    public function getMyDocuments(int $mID): array
    {
        $sql = "SELECT d.dID, d.doi, d.version, d.ver_suppl, d.main_pages, d.main_size,
                       d.submission_time, d.author_list, d.abstract, d.title, d.visibility
                FROM Documents d
                 INNER JOIN DocAuthors da ON d.dID = da.dID
                WHERE da.mID = :mID
                ORDER BY d.submission_time DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['mID' => $mID]);
        $rows = $stmt->fetchAll();
        $total = count($rows);
        return [
            'results' => array_map(fn($row) => new FeedDocument($row), $rows),
            'total' => $total
        ];
    }
}
