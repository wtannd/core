<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;
use Exception;
use PDOException;

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
     * Save a document draft
     */
    public function saveDraft(array $data): int|bool
    {
        $fields = []; $placeholders = []; $params = []; $data['datetime_added'] = date('Y-m-d H:i:s');
        if (isset($data['tID']) && empty($data['tID'])) $data['tID'] = null;

        $requiredFields = ['submitter_ID', 'title', 'abstract', 'author_list', 'dtype', 'branch_list', 'datetime_added'];
        foreach ($requiredFields as $f) {
            $fields[] = $f;
            $placeholders[] = ":$f";
            $params[$f] = $data[$f];
        }

        $optionalFields = ['notes', 'recv_date', 'pub_date', 'full_text', 'link_list', 'tID', 'main_pages', 'main_figs', 'main_tabs', 'main_size', 'suppl_size', 'suppl_ext'];
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

        $dID = (int)$this->db->lastInsertId();

        return $dID;
    }

    /**
     * Update an existing draft.
     */
    public function updateDraft(int $dID, array $data): void
    {
        $fields = [];
        $params = ['dID' => $dID];
        if (isset($data['tID']) && empty($data['tID'])) $data['tID'] = null;

        $optionalFields = ['title', 'abstract', 'dtype', 'notes', 'author_list', 'recv_date', 'pub_date', 'full_text', 'link_list', 'branch_list', 'tID',
                           'main_size', 'suppl_size', 'suppl_ext', 'main_pages', 'main_figs', 'main_tabs'];
        foreach ($optionalFields as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = $f;
                $params[$f] = empty($data[$f]) ? null : $data[$f];
            }
        }

        $setClauses = array_map(fn($f) => "$f = :$f", $fields);
        $sql = "UPDATE DocDrafts SET " . implode(', ', $setClauses) . " WHERE dID = :dID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Fully processes a new document draft, handles DB insertion for the draft table,
     * and physically moves the uploaded files to the docdrafts directory.
     *
     * @param array $data Assumes merged and validated post/file data
     * @param array $files The raw $_FILES array
     * @return int|false Returns the new draft dID on success, false on failure
     */
    public function saveFull(array $data, array $files): int|false
    {
        // 1. Define Upload Directory for drafts
        $uploadDir = UPLOAD_PATH_TRIMMED . '/docdrafts';

        try {
            // Begin Atomic Transaction
            $this->db->beginTransaction();

            // 2. Primary DB Insert for Draft
            $dID = $this->saveDraft($data);
            
            if (!$dID) {
                throw new Exception("Failed to insert draft record.");
            }

            // 3. Relational DB Insert for Draft Authors
            if (!empty($data['author_array'])) {
                // submitter_ID is guaranteed to be set by the processUpload() logic
                $submitterID = (int)($data['submitter_ID'] ?? 0);
                $this->saveDraftAuthors($dID, $data['author_array'], $submitterID);
            }

            // 4. File Movement
            // Create the docdrafts directory if it doesn't exist yet
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0750, true)) {
                    throw new Exception("Failed to create docdrafts directory: $uploadDir");
                }
            }

            // Move Main File
            if (!empty($data['main_size']) && isset($files['main_file'])) {
                $mainDest = $uploadDir . '/' . $dID . '_main.pdf';
                
                if (!move_uploaded_file($files['main_file']['tmp_name'], $mainDest)) {
                    throw new Exception("Failed to move draft main file to disk.");
                }
            }

            // Move Supplemental File
            if (!empty($data['suppl_size']) && isset($files['supplemental_file'])) {
                $supplDest = $uploadDir . '/' . $dID . '_suppl.' . $data['suppl_ext'];
                
                if (!move_uploaded_file($files['supplemental_file']['tmp_name'], $supplDest)) {
                    throw new Exception("Failed to move draft supplemental file to disk.");
                }
            }

            // If we made it here, both DB and Filesystem succeeded!
            $this->db->commit();
            return $dID;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Error in DraftService::saveFull: " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("System/File Error in DraftService::saveFull: " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }
    }

    /**
     * Fully processes an edit to an existing document draft, updating the DB record,
     * authors, and moving any newly uploaded files.
     *
     * @param int $dID The draft ID being edited
     * @param array $data Assumes merged and validated post/file data
     * @param array $files The raw $_FILES array
     * @return int|false Returns the draft dID on success, false on failure
     */
    public function editFull(int $dID, array $data, array $files): int|false
    {
        // 1. Nullify empty fields as requested
        $nullableFields = [
            'link_list', 'notes', 'tID', 'main_size', 'suppl_size', 
            'suppl_ext', 'main_pages', 'main_figs', 'main_tabs', 
            'pub_date', 'recv_date', 'full_text'
        ];

        foreach ($nullableFields as $field) {
            if (isset($data[$field])) {
                // If it's strictly empty string, 0, '0', or an empty array, set to null
                if (empty($data[$field])) $data[$field] = null;
            }
        }

        // 2. Define Upload Directory for drafts
        $uploadDir = UPLOAD_PATH_TRIMMED . '/docdrafts';

        try {
            // Begin Atomic Transaction
            $this->db->beginTransaction();

            // 3. Update Draft Record
            $this->updateDraft($dID, $data);

            // 4. Update Draft Authors
            if (isset($data['author_array'])) {
                $submitterID = (int)($data['submitter_ID'] ?? 0);
                $this->updateDraftAuthors($dID, $data['author_array'], $submitterID);
            }

            // 5. File Movement (Only if new files were actually uploaded)
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0750, true)) {
                    throw new Exception("Failed to create docdrafts directory: $uploadDir");
                }
            }

            // Move new Main File (Overwrites the existing draft main file)
            if (!empty($data['main_size']) && isset($files['main_file'])) {
                $mainDest = $uploadDir . '/' . $dID . '_main.pdf';
                
                if (!move_uploaded_file($files['main_file']['tmp_name'], $mainDest)) {
                    throw new Exception("Failed to move updated draft main file to disk.");
                }
            }

            // Move new Supplemental File (Overwrites the existing draft suppl file)
            if (!empty($data['suppl_size']) && isset($files['supplemental_file'])) {
                $supplDest = $uploadDir . '/' . $dID . '_suppl.' . $data['suppl_ext'];
                
                if (!move_uploaded_file($files['supplemental_file']['tmp_name'], $supplDest)) {
                    throw new Exception("Failed to move updated draft supplemental file to disk.");
                }
            }

            // If everything was successful, commit the changes!
            $this->db->commit();
            return $dID;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Error in DraftService::editFull (dID $dID): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("System/File Error in DraftService::editFull (dID $dID): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }
    }

    /**
     * Save draft co-authors for the approval process (submitter is excluded).
     */
    public function saveDraftAuthors(int $dID, array $authors, int $submitterID = 0): array
    {
        if (empty($authors)) return [];

        $sql = "INSERT INTO DocDraftAuthors (dID, mID, is_editor, is_approved) VALUES (:dID, :mID, 1, 0)
                ON DUPLICATE KEY UPDATE is_editor = 1, is_approved = 0";
        $stmt = $this->db->prepare($sql);

        $validMIDs = [];
        foreach ($authors as $author) {  // $author format: [mID, duty, frac]
            $mID = (int)($author[0] ?? 0);
            if ($mID > 0 && $mID !== $submitterID) {
                $stmt->execute(['dID' => $dID, 'mID' => $mID]);
                $validMIDs[] = $mID;
            }
        }
        return $validMIDs;
    }

    /**
     * Update draft co-authors for the approval process (submitter is excluded).
     * Calls saveDraftAuthors() then removes orphaned rows.
     */
    public function updateDraftAuthors(int $dID, array $authors, int $submitterID = 0): array
    {
        $validMIDs = $this->saveDraftAuthors($dID, $authors, $submitterID);

        if (!empty($validMIDs)) {
            $placeholders = implode(',', array_fill(0, count($validMIDs), '?'));
            $stmt = $this->db->prepare(
                "DELETE FROM DocDraftAuthors WHERE dID = ? AND mID NOT IN ($placeholders)"
            );
            $stmt->execute(array_merge([$dID], $validMIDs));
        }

        return $validMIDs;
    }

    /**
     * Approve a draft for a specific member.
     */
    public function approveDraft(int $dID, int $mID, bool $lock = false): bool
    {
        $sql = "UPDATE DocDraftAuthors SET is_approved = 1, is_locked = :lock WHERE dID = :dID AND mID = :mID";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['lock' => $lock, 'dID' => $dID, 'mID' => $mID]);
    }

    /**
     * Reset all approvals for a draft.
     */
    public function resetDraftApprovals(int $dID): bool
    {
        $sql = "UPDATE DocDraftAuthors SET is_approved = 0 WHERE dID = :dID AND is_locked = 0";
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
    public function checkExternalLinkExists(string $link, int $dID = 0): bool
    {
        $sql = "SELECT COUNT(*) FROM ExternalDocs WHERE link = :link AND dID <> :dID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['link' => $link, 'dID' => $dID]);
        return ((int)$stmt->fetchColumn()) > 0;
    }
}
