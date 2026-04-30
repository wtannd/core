<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;
use Exception;
use PDOException;

/**
 * CommentService
 * 
 * WRITE operations for published Comments (INSERT/UPDATE/DELETE).
 */
class CommentService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Submit a comment (insert into Comments table).
     */
    public function submitComment(array $data): int
    {
        $fields = []; $placeholders = []; $params = [];

        $requiredFields = ['submitter_ID', 'dID', 'comment_text', 'ctype'];
        foreach ($requiredFields as $f) {
            $fields[] = $f;
            $placeholders[] = ":$f";
            $params[$f] = $data[$f];
        }

        $optionalFields = ['parent_ID', 'visibility'];
        foreach ($optionalFields as $f) {
            if (isset($data[$f]) && $data[$f] !== '') {
                $fields[] = $f;
                $placeholders[] = ":$f";
                $params[$f] = $data[$f];
            }
        }

        $sql = "INSERT INTO Comments (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Submit CRComment extra data.
     */
    public function submitCRComment(int $cID, array $data): void
    {
        $fields = ['cID']; $placeholders = [':cID']; $params = ['cID' => $cID];

        $allowedFields = ['ID_num', 'inviter_id', 'author_list', 'anonymity', 'passcode', 'Nth', 'T'];
        foreach ($allowedFields as $f) {
            if (isset($data[$f])) {
                $fields[] = $f;
                $placeholders[] = ":$f";
                $params[$f] = $data[$f];
            }
        }

        // Calculate ID_alphanum from ID_num
        if (isset($data['ID_num'])) {
            $fields[] = 'ID_alphanum';
            $placeholders[] = ':ID_alphanum';
            $params['ID_alphanum'] = base_convert((int)$data['ID_num'], 10, 36);
        }

        if (count($fields) > 1) {
            $sql = "INSERT INTO CRComments (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
    }

    /**
     * Update a comment.
     */
    public function updateComment(int $cID, array $data): void
    {
        $fields = [];
        $params = ['cID' => $cID];

        $allowedFields = ['comment_text', 'visibility'];
        foreach ($allowedFields as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = :$f";
                $params[$f] = $data[$f];
            }
        }

        if (!empty($fields)) {
            $sql = "UPDATE Comments SET " . implode(', ', $fields) . " WHERE cID = :cID";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
    }

    /**
     * Update CRComment extra data.
     */
    public function updateCRComment(int $cID, array $data): void
    {
        $fields = [];
        $params = ['cID' => $cID];

        $allowedFields = ['ID_num', 'ID_alphanum', 'inviter_id', 'author_list', 'anonymity', 'passcode', 'Nth', 'T', 'N_ratings', 'S_ave', 'ECP'];
        foreach ($allowedFields as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = :$f";
                $params[$f] = $data[$f];
            }
        }

        if (!empty($fields)) {
            $sql = "UPDATE CRComments SET " . implode(', ', $fields) . " WHERE cID = :cID";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
    }

    /**
     * Fully processes a comment submission from draft to published comment.
     * Handles DB insertion across Comments and CRComments tables.
     *
     * @param array $data Validated comment data
     * @param int $draftID Draft ID (0 if direct submission)
     * @return int|false Returns the new cID on success, false on failure
     */
    public function submitFull(array $data, int $draftID = 0): int|false
    {
        $data['ts'] = date('Y-m-d H:i:s');

        try {
            $this->db->beginTransaction();

            // 1. Insert into Comments table
            $cID = $this->submitComment($data);
            if (!$cID) {
                throw new Exception("Failed to insert comment record.");
            }

            // 2. Insert CRComment data if creditable (ctype 1-3)
            $ctype = (int)($data['ctype'] ?? 3);
            if ($ctype >= 1 && $ctype <= 3) {
                $this->submitCRComment($cID, $data);
            }

            // 3. Save authors if provided
            if (!empty($data['author_array'])) {
                $this->saveCommentAuthors($cID, $data['author_array']);
            }

            // 4. Update document comment counts
            if (!empty($data['dID'])) {
                $this->incrementDocCommentCount((int)$data['dID']);
            }

            // 5. Delete the draft if submitting from draft
            if ($draftID > 0) {
                $draftService = new CommentDraftService();
                $draftService->deleteCommentDraft($draftID);
            }

            $this->db->commit();
            return $cID;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Error in CommentService::submitFull(): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in CommentService::submitFull(): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }
    }

    /**
     * Save comment authors.
     * Input format: [[mID, duty, frac], ...]
     */
    public function saveCommentAuthors(int $cID, array $authors): array
    {
        if (empty($authors)) return [];

        $sql = "INSERT INTO CommentAuthors (cID, mID, duty, frac) 
                VALUES (:cID, :mID, :duty, :frac) 
                ON DUPLICATE KEY UPDATE duty = VALUES(duty), frac = VALUES(frac)";
        $stmt = $this->db->prepare($sql);

        $validMIDs = [];
        foreach ($authors as $author) {
            $mID = (int)($author[0] ?? 0);
            if ($mID > 0) {
                $stmt->execute([
                    'cID'  => $cID,
                    'mID'  => $mID,
                    'duty' => (int)($author[1] ?? 10),
                    'frac' => (float)($author[2] ?? 0)
                ]);
                $validMIDs[] = $mID;
            }
        }

        return $validMIDs;
    }

    /**
     * Rate a comment.
     */
    public function rateComment(int $cID, int $raterID, int $score, int $num = 1): bool
    {
        try {
            $this->db->beginTransaction();

            // Insert or update rating
            $sql = "INSERT INTO CommentRatings (cID, rater_ID, num, score) 
                    VALUES (:cID, :rater_ID, :num, :score)
                    ON DUPLICATE KEY UPDATE score = VALUES(score)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'cID' => $cID,
                'rater_ID' => $raterID,
                'num' => $num,
                'score' => $score
            ]);

            // Update rating count and average
            $this->updateCommentRatingStats($cID);

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Error in CommentService::rateComment(): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }
    }

    /**
     * Update comment rating statistics.
     */
    private function updateCommentRatingStats(int $cID): void
    {
        $sql = "SELECT COUNT(*) as N_ratings, AVG(score) as S_ave 
                FROM CommentRatings 
                WHERE cID = :cID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cID' => $cID]);
        $stats = $stmt->fetch();

        if ($stats) {
            $updateSql = "UPDATE CRComments 
                         SET N_ratings = :N_ratings, S_ave = :S_ave 
                         WHERE cID = :cID";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                'N_ratings' => (int)$stats['N_ratings'],
                'S_ave' => (float)$stats['S_ave'],
                'cID' => $cID
            ]);
        }
    }

    /**
     * Increment document comment count.
     */
    private function incrementDocCommentCount(int $dID): void
    {
        $sql = "UPDATE Documents SET Ntot_comments = Ntot_comments + 1 WHERE dID = :dID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
    }

    /**
     * Calculate ECP for a comment.
     */
    public function calculateECP(int $cID): float
    {
        // Basic ECP calculation - can be expanded based on system formulas
        $sql = "SELECT cr.S_ave, cr.N_ratings, cr.Nth, cr.T, c.ctype, c.dID
                FROM CRComments cr
                JOIN Comments c ON cr.cID = c.cID
                WHERE cr.cID = :cID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cID' => $cID]);
        $data = $stmt->fetch();

        if (!$data) return 0.0;

        // Simplified ECP formula - adjust based on actual system requirements
        $baseECP = 0.1;
        $ratingFactor = ($data['N_ratings'] > 0) ? $data['S_ave'] / 10.0 : 0;
        $ECP = $baseECP + ($ratingFactor * 0.1);

        // Update ECP
        $updateSql = "UPDATE CRComments SET ECP = :ECP WHERE cID = :cID";
        $updateStmt = $this->db->prepare($updateSql);
        $updateStmt->execute(['ECP' => $ECP, 'cID' => $cID]);

        return $ECP;
    }

    /**
     * Delete a published comment.
     */
    public function deleteComment(int $cID): void
    {
        $stmt = $this->db->prepare("DELETE FROM Comments WHERE cID = :cID");
        $stmt->execute(['cID' => $cID]);

        $stmt = $this->db->prepare("DELETE FROM CRComments WHERE cID = :cID");
        $stmt->execute(['cID' => $cID]);

        $stmt = $this->db->prepare("DELETE FROM CommentAuthors WHERE cID = :cID");
        $stmt->execute(['cID' => $cID]);

        $stmt = $this->db->prepare("DELETE FROM CommentRatings WHERE cID = :cID");
        $stmt->execute(['cID' => $cID]);

        $stmt = $this->db->prepare("DELETE FROM CRCommentPhases WHERE cID = :cID");
        $stmt->execute(['cID' => $cID]);
    }

    /**
     * Save comment phase data.
     */
    public function saveCommentPhase(int $cID, int $nphase, array $data): void
    {
        $fields = ['cID', 'nphase']; 
        $placeholders = [':cID', ':nphase']; 
        $params = ['cID' => $cID, 'nphase' => $nphase];
        $updateFields = [];

        $allowedFields = ['Nth', 'T', 'N_ratings', 'S_ave', 'ECP'];
        foreach ($allowedFields as $f) {
            if (isset($data[$f])) {
                $fields[] = $f;
                $placeholders[] = ":$f";
                $params[$f] = $data[$f];
                $updateFields[] = "$f = VALUES($f)";
            }
        }

        $sql = "INSERT INTO CRCommentPhases (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updateFields);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
}
