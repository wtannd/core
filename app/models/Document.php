<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;
use Exception;

/**
 * Document Model
 * 
 * Handles database interactions for Documents, DocDrafts, and DocAuthors.
 */
class Document
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Check if a user can view a document based on its visibility settings.
     */
    public function canUserView(int $docVisibility, int $mRole): bool
    {
        return $mRole >= $docVisibility;
    }

    /**
     * Save a document draft.
     */
    public function saveDraft(array $data): int
    {
        $fields = ['submitter_ID', 'title', 'abstract', 'has_file', 'dtype'];
        $placeholders = [':submitter_ID', ':title', ':abstract', ':has_file', ':dtype'];
        $params = [
            'submitter_ID' => $data['submitter_ID'],
            'title'        => $data['title'],
            'abstract'     => $data['abstract'],
            'has_file'     => (int)$data['has_file'],
            'dtype'        => (int)($data['dtype'] ?? 1)
        ];

        $optionalFields = ['notes', 'ext_url', 'author_list', 'submission_time', 'pubdate', 'full_text', 'link_list', 'branch_list'];
        foreach ($optionalFields as $f) {
            if (isset($data[$f]) && $data[$f] !== '') {
                $fields[] = $f;
                $placeholders[] = ":$f";
                $params[$f] = $data[$f];
            }
        }

        $sql = "INSERT INTO DocDrafts (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Submit a final document.
     */
    public function submitDocument(array $data): int
    {
        $fields = ['submitter_ID', 'title', 'abstract', 'has_file', 'dtype'];
        $placeholders = [':submitter_ID', ':title', ':abstract', ':has_file', ':dtype'];
        $params = [
            'submitter_ID' => $data['submitter_ID'],
            'title'        => $data['title'],
            'abstract'     => $data['abstract'],
            'has_file'     => (int)$data['has_file'],
            'dtype'        => (int)($data['dtype'] ?? 1)
        ];

        $optionalFields = ['notes', 'ext_url', 'author_list', 'submission_time', 'pubdate', 'full_text', 'main_pages', 'main_figs', 'main_tabs'];
        foreach ($optionalFields as $f) {
            if (isset($data[$f]) && $data[$f] !== '') {
                $fields[] = $f;
                $placeholders[] = ":$f";
                $params[$f] = $data[$f];
            }
        }

        $sql = "INSERT INTO Documents (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $dID = (int)$this->db->lastInsertId();

        // Insert external links if provided
        if (!empty($data['link_list_array'])) {
            $sqlLink = "INSERT INTO ExternalDocs (dID, sID, esname, url) VALUES (:dID, :sID, :esname, :url)";
            $stmtLink = $this->db->prepare($sqlLink);
            foreach ($data['link_list_array'] as $link) {
                if (isset($link[0], $link[2])) {
                    $stmtLink->execute([
                        'dID' => $dID,
                        'sID' => (int)$link[0],
                        'esname' => $link[1],
                        'url' => $link[2]
                    ]);
                }
            }
        }

        return $dID;
    }

    /**
     * Check if an external link already exists.
     */
    public function checkExternalLinkExists(string $link): bool
    {
        $sql = "SELECT COUNT(*) FROM ExternalDocs WHERE link = :link";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['link' => $link]);
        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * Get external links for a document.
     */
    public function getExternalLinks(int $dID): array
    {
        $sql = "SELECT es.esname, ed.link 
                FROM ExternalDocs ed
                INNER JOIN ExternalSources es ON ed.sID = es.sID
                WHERE ed.dID = :dID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        return $stmt->fetchAll(PDO::FETCH_NUM); // Fetch as indexed array [esname, url]
    }

    /**
     * Fetch recent documents with visibility filtering and type filtering.
     */
    public function getRecentDocuments(int $dtID = 1, int $limit = 20, int $mRole = 0): array
    {
        $sql = "SELECT d.*, m.display_name as submitter_name 
                FROM Documents d
                JOIN Members m ON d.submitter_ID = m.mID
                WHERE d.dtype = :dtID AND :mRole >= d.visibility
                ORDER BY d.submission_time DESC 
                LIMIT :limit";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':dtID', $dtID, PDO::PARAM_INT);
        $stmt->bindValue(':mRole', $mRole, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single document by DOI with visibility filtering.
     */
    public function getDocumentByDoi(string $doi, int $mRole): array|bool
    {
        $sql = "SELECT d.*, m.display_name as submitter_name 
                FROM Documents d
                JOIN Members m ON d.submitter_ID = m.mID
                WHERE d.doi = :doi AND :mRole2 >= d.visibility
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'doi'    => $doi,
            'mRole2' => $mRole
        ]);

        return $stmt->fetch();
    }

    /**
     * Fetch a single document by dID with visibility filtering.
     */
    public function getDocument(int $dID, int $mRole): array|bool
    {
        $sql = "SELECT d.*, m.display_name as submitter_name 
                FROM Documents d
                JOIN Members m ON d.submitter_ID = m.mID
                WHERE d.dID = :dID AND :mRole2 >= d.visibility
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'dID'    => $dID,
            'mRole2' => $mRole
        ]);
        
        return $stmt->fetch();
    }

    public function getDraftById(int $dID): array|bool
    {
        $sql = "SELECT * FROM DocDrafts WHERE dID = :dID LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        return $stmt->fetch();
    }

    /**
     * Fetch a draft by dID, ensuring owner access.
     */
    public function getDraft(int $dID, int $mID): array|bool
    {
        $sql = "SELECT * FROM DocDrafts WHERE dID = :dID AND submitter_ID = :mID LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID, 'mID' => $mID]);
        return $stmt->fetch();
    }

    /**
     * Fetch all authors for a draft.
     */
    public function getDraftAuthors(int $dID): array
    {
        $sql = "SELECT dda.*, m.email, m.ID_alphanum as CORE_ID
                FROM DocDraftAuthors dda
                LEFT JOIN Members m ON dda.mID = m.mID
                WHERE dda.dID = :dID
                ORDER BY dda.author_order ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        return $stmt->fetchAll();
    }

    /**
     * Approve a draft for a specific member.
     */
    public function approveDraft(int $dID, int $mID): bool
    {
        $sql = "UPDATE DocDraftAuthors SET approved = 1 WHERE dID = :dID AND mID = :mID";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['dID' => $dID, 'mID' => $mID]);
    }

    /**
     * Reset all approvals for a draft.
     */
    public function resetDraftApprovals(int $dID): bool
    {
        $sql = "UPDATE DocDraftAuthors SET approved = 0 WHERE dID = :dID";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['dID' => $dID]);
    }

    /**
     * Check if a draft is fully approved by all members.
     */
    public function isDraftFullyApproved(int $dID): bool
    {
        // Fetch authors who have a valid mID (internal members)
        $sql = "SELECT COUNT(*) FROM DocDraftAuthors WHERE dID = :dID AND mID IS NOT NULL";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        $totalMembers = (int)$stmt->fetchColumn();

        if ($totalMembers <= 1) {
            return true; // Single author or no internal members
        }

        $sqlApproved = "SELECT COUNT(*) FROM DocDraftAuthors WHERE dID = :dID AND mID IS NOT NULL AND approved = 1";
        $stmtApproved = $this->db->prepare($sqlApproved);
        $stmtApproved->execute(['dID' => $dID]);
        $approvedCount = (int)$stmtApproved->fetchColumn();

        return $approvedCount === $totalMembers;
    }

    /**
     * Get all external sources ordered by name.
     */
    public function getAvailableSources(): array
    {
        $stmt = $this->db->query("SELECT sID, esname FROM ExternalSources WHERE is_active = 1 ORDER BY esname ASC");
        return $stmt->fetchAll();
    }

    /**
     * Save research branches for a document (DocBranches table).
     * 
     * @param int   $dID      The document ID
     * @param array $branches Array of ['bID' => int, 'num' => int, 'impact' => int]
     */
    public function saveBranches(int $dID, array $branches): void
    {
        $sql = "INSERT INTO DocBranches (dID, bID, num, impact, frac) 
                VALUES (:dID, :bID, :num, :impact, :frac)";
        $stmt = $this->db->prepare($sql);

        foreach ($branches as $branch) {
            $impact = (int)$branch['impact'];
            $stmt->execute([
                'dID'    => $dID,
                'bID'    => (int)$branch['bID'],
                'num'    => (int)$branch['num'],
                'impact' => $impact,
                'frac'   => $impact / 100.0
            ]);
        }
    }
}
