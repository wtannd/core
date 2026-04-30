<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;

/**
 * CommentDraftRepository
 * 
 * READ operations for CommentDrafts (SELECT queries only).
 * Returns CommentDraft entity objects.
 */
class CommentDraftRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Fetch a single comment draft by cID with authorization.
     *
     * @param int $cID - Comment Draft ID
     * @param int $mID - Member ID for authorization
     * @return CommentDraft|false
     */
    public function getCommentDraft(int $cID, int $mID): CommentDraft|false
    {
        $sql = "SELECT cd.*, 
                       d.title AS doc_title, d.doi AS doc_doi
                FROM CommentDrafts cd
                JOIN Documents d ON cd.dID = d.dID
                WHERE cd.cID = :cID AND cd.submitter_ID = :mID
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cID' => $cID, 'mID' => $mID]);
        $row = $stmt->fetch();
        return $row ? new CommentDraft($row) : false;
    }

    /**
     * Fetch draft authors for a comment draft.
     *
     * @param int $cID - Comment Draft ID
     * @return array
     */
    public function getCommentDraftAuthors(int $cID): array
    {
        $sql = "SELECT cda.*, m.pub_name, m.display_name, m.CoreID
                FROM CommentDraftAuthors cda
                JOIN Members m ON cda.mID = m.mID
                WHERE cda.cID = :cID
                ORDER BY cda.mID";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cID' => $cID]);
        return $stmt->fetchAll();
    }

    /**
     * Check if a comment draft is fully approved.
     *
     * @param int $cID - Comment Draft ID
     * @return bool
     */
    public function isCommentDraftFullyApproved(int $cID): bool
    {
        $sql = "SELECT COUNT(*) = SUM(is_approved) 
                FROM CommentDraftAuthors 
                WHERE cID = :cID";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cID' => $cID]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Fetch comment drafts by submitter.
     *
     * @param int $mID - Submitter's member ID
     * @return CommentDraft[]
     */
    public function getMyCommentDrafts(int $mID): array
    {
        $sql = "SELECT cd.*, 
                       d.title AS doc_title, d.doi AS doc_doi
                FROM CommentDrafts cd
                JOIN Documents d ON cd.dID = d.dID
                WHERE cd.submitter_ID = :mID
                ORDER BY cd.ts DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['mID' => $mID]);
        $rows = $stmt->fetchAll();
        return array_map(fn($row) => new CommentDraft($row), $rows);
    }

    /**
     * Check if cID is a user's draft.
     *
     * @param int $cID - Comment Draft ID
     * @param int $mID - Member ID
     * @return bool
     */
    public function checkCommentDraftID(int $cID, int $mID): bool
    {
        $sql = "SELECT EXISTS(SELECT 1 FROM CommentDrafts WHERE cID = :cID AND submitter_ID = :mID LIMIT 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['cID' => $cID, 'mID' => $mID]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Get full draft details with author approval status.
     *
     * @param int $cID - Comment Draft ID
     * @param int $mID - Member ID
     * @return array|false
     */
    public function getDraftWithAuthors(int $cID, int $mID): array|false
    {
        $draft = $this->getCommentDraft($cID, $mID);
        if (!$draft) {
            return false;
        }

        $draftAuthors = $this->getCommentDraftAuthors($cID);
        // Note: We can't dynamically add properties to the entity, so return array with draft object
        return [
            'draft' => $draft,
            'authors' => $draftAuthors,
            'is_fully_approved' => $this->isCommentDraftFullyApproved($cID)
        ];
    }
}
