<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;

/**
 * CommentRepository
 * 
 * READ operations for Comments (SELECT queries only).
 */
class CommentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Fetch a single comment by cID with visibility filtering.
     * Authors listed in CommentAuthors can view regardless of visibility.
     *
     * @param int $cID - Comment ID
     * @param int $mRole - User's role for visibility check
     * @param int $mID - User's member ID for author check
     * @return Comment|null
     */
    public function getComment(int $cID, int $mRole, int $mID = 0): ?Comment
    {
        $sql = "SELECT c.*, 
                       cr.ID_num, cr.ID_alphanum, cr.inviter_id, cr.author_list, 
                       cr.anonymity, cr.passcode, cr.Nth, cr.T, cr.N_ratings, cr.S_ave, cr.ECP,
                       m.pub_name AS submitter_name,
                       REGEXP_REPLACE(LPAD(m.CoreID, 9, '0'), '(.{3})(?=.)', '\\\\1-') AS submitter_coreid
                FROM Comments c
                LEFT JOIN CRComments cr ON c.cID = cr.cID
                LEFT JOIN Members m ON c.submitter_ID = m.mID
                WHERE c.cID = :cID 
                  AND (:mRole >= c.visibility OR 
                       c.submitter_ID = :mID OR 
                       EXISTS (SELECT 1 FROM CommentAuthors ca WHERE ca.cID = c.cID AND ca.mID = :mID2)
                  )
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'cID' => $cID,
            'mRole' => $mRole,
            'mID' => $mID,
            'mID2' => $mID
        ]);

        $row = $stmt->fetch();
        return $row ? new Comment($row) : null;
    }

    /**
     * Fetch comments for a document with visibility filtering.
     *
     * @param int $dID - Document ID
     * @param int $mRole - User's role for visibility check
     * @param int $mID - User's member ID
     * @param array $filters - Optional filters (ctype, parent_ID, etc.)
     * @return Comment[]
     */
    public function getCommentsByDoc(int $dID, int $mRole, int $mID = 0, array $filters = []): array
    {
        $params = [
            'dID' => $dID,
            'mRole' => $mRole,
            'mID' => $mID
        ];

        $sql = "SELECT c.*, 
                       cr.ID_num, cr.ID_alphanum, cr.inviter_id, cr.author_list,
                       cr.anonymity, cr.passcode, cr.Nth, cr.T, cr.N_ratings, cr.S_ave, cr.ECP,
                       m.pub_name AS submitter_name,
                       REGEXP_REPLACE(LPAD(m.CoreID, 9, '0'), '(.{3})(?=.)', '\\\\1-') AS submitter_coreid
                FROM Comments c
                LEFT JOIN CRComments cr ON c.cID = cr.cID
                LEFT JOIN Members m ON c.submitter_ID = m.mID
                WHERE c.dID = :dID 
                  AND (:mRole >= c.visibility OR 
                       c.submitter_ID = :mID OR 
                       EXISTS (SELECT 1 FROM CommentAuthors ca WHERE ca.cID = c.cID AND ca.mID = :mID)
                  )";

        if (!empty($filters['ctype'])) {
            $sql .= " AND c.ctype = :ctype";
            $params['ctype'] = (int)$filters['ctype'];
        }

        if (isset($filters['parent_ID'])) {
            if ($filters['parent_ID'] === null) {
                $sql .= " AND c.parent_ID IS NULL";
            } else {
                $sql .= " AND c.parent_ID = :parent_ID";
                $params['parent_ID'] = (int)$filters['parent_ID'];
            }
        }

        $sql .= " ORDER BY c.ts ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll();
        return array_map(fn($row) => new Comment($row), $rows);
    }

    /**
     * Fetch authors for a creditable comment.
     *
     * @param int $cID - Comment ID
     * @return array
     */
    public function getCommentAuthors(int $cID): array
    {
        $sql = "SELECT ca.*, m.pub_name, m.email, m.CoreID
                FROM CommentAuthors ca
                JOIN Members m ON ca.mID = m.mID
                WHERE ca.cID = :cID
                ORDER BY ca.duty DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cID' => $cID]);
        return $stmt->fetchAll();
    }

    /**
     * Fetch ratings for a comment.
     *
     * @param int $cID - Comment ID
     * @return array
     */
    public function getCommentRatings(int $cID): array
    {
        $sql = "SELECT cr.*, m.pub_name AS rater_name
                FROM CommentRatings cr
                JOIN Members m ON cr.rater_ID = m.mID
                WHERE cr.cID = :cID
                ORDER BY cr.ts DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cID' => $cID]);
        return $stmt->fetchAll();
    }

    /**
     * Get comments by author (mID).
     *
     * @param int $mID - Author's member ID
     * @param int $mRole - Viewer's role
     * @return Comment[]
     */
    public function getCommentsByAuthor(int $mID, int $mRole): array
    {
        $sql = "SELECT c.*, 
                       cr.ID_num, cr.ID_alphanum, cr.inviter_id, cr.author_list,
                       cr.anonymity, cr.passcode, cr.Nth, cr.T, cr.N_ratings, cr.S_ave, cr.ECP,
                       m.pub_name AS submitter_name
                FROM Comments c
                JOIN CommentAuthors ca ON c.cID = ca.cID
                LEFT JOIN CRComments cr ON c.cID = cr.cID
                LEFT JOIN Members m ON c.submitter_ID = m.mID
                WHERE ca.mID = :mID AND :mRole >= c.visibility
                ORDER BY c.ts DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['mID' => $mID, 'mRole' => $mRole]);

        $rows = $stmt->fetchAll();
        return array_map(fn($row) => new Comment($row), $rows);
    }

    /**
     * Get the next ID_num for a document's comments.
     *
     * @param int $dID - Document ID
     * @param int $nphase - Current phase number
     * @return int
     */
    public function getNextCommentIDNum(int $dID, int $nphase): int
    {
        $sql = "SELECT COALESCE(MAX(cr.ID_num), 0) + 1 
                FROM CRComments cr
                JOIN Comments c ON cr.cID = c.cID
                WHERE c.dID = :dID";
        // Note: ID_num is per document, not per phase
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Search comments with fulltext search.
     *
     * @param string $query - Search query
     * @param int $mRole - User's role
     * @param int $limit - Results limit
     * @param int $offset - Results offset
     * @return array{results: Comment[], total: int}
     */
    public function searchComments(string $query, int $mRole, int $limit = 20, int $offset = 0): array
    {
        $countSql = "SELECT COUNT(*) 
                     FROM Comments c
                     WHERE :mRole >= c.visibility 
                       AND MATCH(c.comment_text) AGAINST(:query IN BOOLEAN MODE)";

        $stmt = $this->db->prepare($countSql);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->execute();
        $total = (int)$stmt->fetchColumn();

        $sql = "SELECT c.*, 
                       cr.ID_num, cr.ID_alphanum, cr.anonymity, cr.N_ratings, cr.S_ave,
                       m.pub_name AS submitter_name
                FROM Comments c
                LEFT JOIN CRComments cr ON c.cID = cr.cID
                LEFT JOIN Members m ON c.submitter_ID = m.mID
                WHERE :mRole >= c.visibility 
                  AND MATCH(c.comment_text) AGAINST(:query IN BOOLEAN MODE)
                ORDER BY c.ts DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        $stmt->bindValue(':query', $query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return [
            'results' => array_map(fn($row) => new Comment($row), $rows),
            'total' => $total
        ];
    }
}
