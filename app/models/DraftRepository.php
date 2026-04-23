<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;

/**
 * DraftRepository
 * 
 * READ operations for DocDrafts (SELECT queries only).
 */
class DraftRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Fetch a draft by dID.
     */
    public function getDraftById(int $dID): Draft|false
    {
        $sql = "SELECT * FROM DocDrafts WHERE dID = :dID LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        $row = $stmt->fetch();
        return $row ? new Draft($row) : false;
    }

    /**
     * Fetch a draft class by dID, ensuring submitter-only access.
     */
    public function getMySubmittedDraft(int $dID, int $mID): Draft|false
    {
        if ($dID < 1 || $mID < 1) return false;
        $sql = "SELECT * FROM DocDrafts WHERE dID = :dID AND submitter_ID = :mID LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID, 'mID' => $mID]);
        $row = $stmt->fetch();
        return $row ? new Draft($row) : false;
    }

    /**
     * Fetch a draft class by dID, ensuring owner or coauthor access.
     */
    public function getMyDraft(int $dID, int $mID): Draft|false
    {
        if ($dID < 1 || $mID < 1) return false;
        $sql = "SELECT dd.*, m.pub_name as submitter_name, m.CoreID as submitter_coreid FROM DocDrafts dd JOIN Members m ON dd.submitter_ID = m.mID
                WHERE dd.dID = :dID AND (dd.submitter_ID = :mID
                OR EXISTS (SELECT 1 FROM DocDraftAuthors dda WHERE dda.dID = dd.dID AND dda.mID = :mID2)) LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID, 'mID' => $mID, 'mID2' => $mID]);
        $row = $stmt->fetch();
        return $row ? new Draft($row) : false;
    }

    // check if dID is the user saved draft
    public function checkDraftID(int $dID, int $mID): bool
    {
        $sql = "SELECT EXISTS(SELECT 1 FROM DocDrafts WHERE dID = :dID AND submitter_ID = :mID LIMIT 1)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'dID' => $dID,
            'mID' => $mID
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Fetch all authors for a draft.
     */
    public function getDraftAuthors(int $dID): array
    {
        $sql = "SELECT dda.*, m.email, m.CoreID, m.pub_name
                FROM DocDraftAuthors dda
                LEFT JOIN Members m ON dda.mID = m.mID
                WHERE dda.dID = :dID
                ORDER BY dda.mID ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        return $stmt->fetchAll();
    }

    /**
     * Check if a draft is fully approved by all members.
     */
    public function isDraftFullyApproved(int $dID): bool
    {
        $sql = "SELECT COUNT(*) FROM DocDraftAuthors WHERE dID = :dID AND is_approved = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        $num_unapproved = (int)$stmt->fetchColumn();

        return $num_unapproved === 0;
    }

    /**
     * Get all drafts submitted by a member.
     */
    public function getMySubmittedDrafts(int $mID): array
    {
        $sql = "SELECT * FROM DocDrafts WHERE submitter_ID = :mID ORDER BY datetime_added DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['mID' => $mID]);
        $rows = $stmt->fetchAll();
        return array_map(fn($row) => new Draft($row), $rows);
    }

    /**
     * Get all drafts submitted or coauthored by a member.
     */
    public function getMyDrafts(int $mID): array
    {
        $sql = "SELECT dd.* FROM DocDrafts dd WHERE (dd.submitter_ID = :mID
                OR EXISTS (SELECT 1 FROM DocDraftAuthors dda WHERE dda.dID = dd.dID AND dda.mID = :mID2))
                ORDER BY dd.datetime_added DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['mID' => $mID, 'mID2' => $mID]);
        $rows = $stmt->fetchAll();
        return array_map(fn($row) => new Draft($row), $rows);
    }

    /**
     * Get branch data from draft's branch_list JSON.
     */
    public function getDraftBranches(string $branchListJson): array
    {
        $branchList = json_decode($branchListJson, true) ?? [];
        if (empty($branchList)) return [];

        $branchIds = array_column($branchList, 'bID');
        if (empty($branchIds)) return [];

        $placeholders = implode(',', array_fill(0, count($branchIds), '?'));
        $sql = "SELECT bID, abbr, bname FROM ResearchBranches WHERE bID IN ($placeholders) ORDER BY FIELD(bID, " . implode(',', $branchIds) . ")";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($branchIds);
        
        return $stmt->fetchAll();
    }
}
