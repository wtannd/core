<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;
use Exception;

/**
 * DraftService
 * 
 * WRITE operations for DocDrafts (INSERT/UPDATE/DELETE).
 */
class DraftService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Save a document draft with co-authors.
     * Uses DB transaction to ensure atomicity.
     */
    public function saveDraft(array $data): int|bool
    {
        $submitterId = (int)$data['submitter_ID'];
        
        $fields = ['submitter_ID', 'title', 'abstract', 'has_file', 'dtype'];
        $placeholders = [':submitter_ID', ':title', ':abstract', ':has_file', ':dtype'];
        $params = [
            'submitter_ID' => $submitterId,
            'title'        => $data['title'],
            'abstract'     => $data['abstract'],
            'has_file'     => (int)$data['has_file'],
            'dtype'        => (int)($data['dtype'] ?? 1)
        ];

        $optionalFields = ['notes', 'author_list', 'recv_date', 'pub_date', 'full_text', 'link_list', 'branch_list', 'tID', 'main_pages', 'main_figs', 'main_tabs'];
        foreach ($optionalFields as $f) {
            if (isset($data[$f]) && $data[$f] !== '') {
                $fields[] = $f;
                $placeholders[] = ":$f";
                $params[$f] = $data[$f];
            }
        }

        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO DocDrafts (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $dID = (int)$this->db->lastInsertId();

            $authorListJson = $data['author_list'] ?? '';
            $authorData = json_decode($authorListJson, true);
            if (!empty($authorData['authors'])) {
                $sqlAuthors = "INSERT INTO DocDraftAuthors (dID, mID, is_editor, is_approved) VALUES (:dID, :mID, 1, 0)";
                $stmtAuthors = $this->db->prepare($sqlAuthors);

                foreach ($authorData['authors'] as $author) {
                    $authorId = (int)($author[1] ?? 0);
                    if ($authorId > 0 && $authorId !== $submitterId) {
                        $stmtAuthors->execute(['dID' => $dID, 'mID' => $authorId]);
                    }
                }
            }

            $this->db->commit();
            return $dID;

        } catch (\PDOException $e) {
            $this->db->rollBack();
            error_log("DraftService::saveDraft() error: " . $e->getMessage(), 3, rtrim(LOG_PATH, '/') . '/error.log');
            return false;
        }
    }

    /**
     * Update an existing draft.
     */
    public function updateDraft(int $dID, array $data): void
    {
        $fields = ['title', 'abstract', 'dtype'];
        $params = [
            'dID'      => $dID,
            'title'    => $data['title'],
            'abstract' => $data['abstract'],
            'dtype'    => (int)($data['dtype'] ?? 1),
        ];

        $optionalFields = ['notes', 'author_list', 'recv_date', 'pub_date', 'full_text', 'link_list', 'branch_list', 'tID'];
        foreach ($optionalFields as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = $f;
                $params[$f] = $data[$f] === '' ? null : $data[$f];
            }
        }

        if (isset($data['has_file'])) {
            $fields[] = 'has_file';
            $params['has_file'] = (int)$data['has_file'];
        }

        $setClauses = array_map(fn($f) => "$f = :$f", $fields);
        $sql = "UPDATE DocDrafts SET " . implode(', ', $setClauses) . " WHERE dID = :dID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Approve a draft for a specific member.
     */
    public function approveDraft(int $dID, int $mID): bool
    {
        $sql = "UPDATE DocDraftAuthors SET is_approved = 1 WHERE dID = :dID AND mID = :mID";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['dID' => $dID, 'mID' => $mID]);
    }

    /**
     * Reset all approvals for a draft.
     */
    public function resetDraftApprovals(int $dID): bool
    {
        $sql = "UPDATE DocDraftAuthors SET is_approved = 0 WHERE dID = :dID";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['dID' => $dID]);
    }

    /**
     * Delete a draft.
     */
    public function deleteDraft(int $dID): void
    {
        $stmt = $this->db->prepare("DELETE FROM DocDrafts WHERE dID = :dID");
        $stmt->execute(['dID' => $dID]);
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
}
