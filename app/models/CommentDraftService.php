<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;
use Exception;
use PDOException;

/**
 * CommentDraftService
 * 
 * WRITE operations for CommentDrafts (INSERT/UPDATE/DELETE).
 */
class CommentDraftService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Save a comment draft
     */
    public function saveCommentDraft(array $data): int|false
    {
        $fields = []; $placeholders = []; $params = [];
        $data['ts'] = date('Y-m-d H:i:s');

        $requiredFields = ['submitter_ID', 'dID', 'comment_text', 'ctype'];
        foreach ($requiredFields as $f) {
            $fields[] = $f;
            $placeholders[] = ":$f";
            $params[$f] = $data[$f];
        }

        $optionalFields = ['parent_ID', 'inviter_id', 'author_list', 'anonymity', 'passcode', 'to_be_moderated'];
        foreach ($optionalFields as $f) {
            if (isset($data[$f]) && $data[$f] !== '') {
                $fields[] = $f;
                $placeholders[] = ":$f";
                $params[$f] = $data[$f];
            }
        }

        $sql = "INSERT INTO CommentDrafts (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update an existing comment draft.
     */
    public function updateCommentDraft(int $cID, array $data): void
    {
        $fields = [];
        $params = ['cID' => $cID];

        $allowedFields = ['comment_text', 'ctype', 'parent_ID', 'inviter_id', 'author_list', 'anonymity', 'passcode', 'to_be_moderated'];
        foreach ($allowedFields as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "$f = :$f";
                $params[$f] = $data[$f];
            }
        }

        if (!empty($fields)) {
            $sql = "UPDATE CommentDrafts SET " . implode(', ', $fields) . " WHERE cID = :cID";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        }
    }

    /**
     * Fully processes a comment draft save.
     * Handles DB insertion and author management.
     *
     * @param array $data Validated comment draft data
     * @return int|false Returns the new draft cID on success, false on failure
     */
    public function saveFullDraft(array $data): int|false
    {
        try {
            $this->db->beginTransaction();

            $cID = $this->saveCommentDraft($data);
            if (!$cID) {
                throw new Exception("Failed to insert comment draft.");
            }

            // Save draft authors if provided
            if (!empty($data['author_array'])) {
                $submitterID = (int)($data['submitter_ID'] ?? 0);
                $this->saveCommentDraftAuthors($cID, $data['author_array'], $submitterID);
            }

            $this->db->commit();
            return $cID;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Error in CommentDraftService::saveFullDraft(): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in CommentDraftService::saveFullDraft(): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }
    }

    /**
     * Update an existing comment draft with full data.
     */
    public function editFullDraft(int $cID, array $data): int|false
    {
        try {
            $this->db->beginTransaction();

            $this->updateCommentDraft($cID, $data);

            // Update draft authors if provided
            if (isset($data['author_array'])) {
                $submitterID = (int)($data['submitter_ID'] ?? 0);
                $this->updateCommentDraftAuthors($cID, $data['author_array'], $submitterID);
            }

            $this->db->commit();
            return $cID;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Error in CommentDraftService::editFullDraft(): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in CommentDraftService::editFullDraft(): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }
    }

    /**
     * Save comment draft authors for approval workflow.
     * Submitter is excluded from draft authors table.
     */
    public function saveCommentDraftAuthors(int $cID, array $authors, int $submitterID = 0): array
    {
        if (empty($authors)) return [];

        $sql = "INSERT INTO CommentDraftAuthors (cID, mID, is_approved) 
                VALUES (:cID, :mID, 0) 
                ON DUPLICATE KEY UPDATE is_approved = 0";
        $stmt = $this->db->prepare($sql);

        $validMIDs = [];
        foreach ($authors as $author) {
            $mID = (int)($author[0] ?? 0);
            if ($mID > 0 && $mID !== $submitterID) {
                $stmt->execute([
                    'cID' => $cID,
                    'mID' => $mID
                ]);
                $validMIDs[] = $mID;
            }
        }

        return $validMIDs;
    }

    /**
     * Update comment draft authors.
     * Calls saveCommentDraftAuthors() then removes orphaned rows.
     */
    public function updateCommentDraftAuthors(int $cID, array $authors, int $submitterID = 0): array
    {
        $validMIDs = $this->saveCommentDraftAuthors($cID, $authors, $submitterID);

        if (!empty($validMIDs)) {
            $placeholders = implode(',', array_fill(0, count($validMIDs), '?'));
            $stmt = $this->db->prepare(
                "DELETE FROM CommentDraftAuthors WHERE cID = ? AND mID NOT IN ($placeholders)"
            );
            $stmt->execute(array_merge([$cID], $validMIDs));
        }

        return $validMIDs;
    }

    /**
     * Approve a comment draft for a specific member.
     */
    public function approveCommentDraft(int $cID, int $mID, bool $lock = false): bool
    {
        $sql = "UPDATE CommentDraftAuthors SET is_approved = 1, is_locked = :lock WHERE cID = :cID AND mID = :mID";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['lock' => $lock, 'cID' => $cID, 'mID' => $mID]);
    }

    /**
     * Reset all approvals for a comment draft.
     */
    public function resetDraftApprovals(int $cID): bool
    {
        $sql = "UPDATE CommentDraftAuthors SET is_approved = 0 WHERE cID = :cID AND is_locked = 0";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['cID' => $cID]);
    }

    /**
     * Delete a comment draft.
     */
    public function deleteCommentDraft(int $cID): void
    {
        $stmt = $this->db->prepare("DELETE FROM CommentDrafts WHERE cID = :cID");
        $stmt->execute(['cID' => $cID]);

        $stmt = $this->db->prepare("DELETE FROM CommentDraftAuthors WHERE cID = :cID");
        $stmt->execute(['cID' => $cID]);
    }

    /**
     * Check if an external link already exists (for validation).
     */
    public function checkExternalLinkExists(string $link, int $cID = 0): bool
    {
        $sql = "SELECT COUNT(*) FROM ExternalDocs WHERE link = :link AND dID <> :cID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['link' => $link, 'cID' => $cID]);
        return ((int)$stmt->fetchColumn()) > 0;
    }
}
