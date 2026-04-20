<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;
use Exception;
use PDOException;

/**
 * DocumentService
 * 
 * WRITE operations for published Documents (INSERT/UPDATE/DELETE).
 */
class DocumentService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Submit a final document.
     */
    public function submitDoc(array $data): int
    {
        $fields = []; $placeholders = []; $params = [];

        $requiredFields = ['submitter_ID', 'title', 'abstract', 'dtype', 'author_list', 'pubdate'];
        foreach ($requiredFields as $f) {
            $fields[] = $f;
            $placeholders[] = ":$f";
            $params[$f] = $data[$f];
        }

        $optionalFields = ['notes', 'submission_time', 'datetime_added', 'full_text', 'main_pages', 'main_figs', 'main_tabs',
                           'main_size', 'suppl_size', 'suppl_ext', 'version', 'ver_suppl'];
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

        return $dID;
    }

    /**
     * Revise a published document.
     */
    public function reviseDoc(int $dID, array $data): void
    {
        $fields = [];
        $params = ['dID' => $dID];

        $optionalFields = ['title', 'abstract', 'dtype', 'notes', 'author_list', 'full_text', 'main_pages', 'main_figs', 'main_tabs',
                           'main_size', 'suppl_size', 'suppl_ext', 'last_revision_time', 'version', 'ver_suppl'];
        foreach ($optionalFields as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = $f;
                $params[$f] = $data[$f];
            }
        }
        if (empty($fields)) return;

        $setClauses = array_map(fn($f) => "$f = :$f", $fields);
        $sql = "UPDATE Documents SET " . implode(', ', $setClauses) . " WHERE dID = :dID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Fully processes a new document submission, handles DB insertion across multiple
     * tables, and physically moves the uploaded files to their correct directories.
     *
     * @param array $data Assumes merged and validated post/file data
     * @param array $files The raw $_FILES array or taken from a draft-dID
     * @return int|false Returns the new dID on success, false on failure
     */
    public function submitFull(array $data, array $files, int $draftID = 0): int|false
    {
        // 1. Determine Dates based on provided rules
        if (!empty($data['pub_date'])) {
            $pubdate = trim($data['pub_date']);
            $recvDate = !empty($data['recv_date']) ? trim($data['recv_date']) : $pubdate;
            
            $data['pubdate'] = $pubdate;
            $data['submission_time'] = $recvDate . ' 00:00:00';
        } else {
            $data['datetime_added'] = date('Y-m-d H:i:s');
            $data['pubdate'] = DateTimeImmutable::createFromFormat('Y-m-d', $data['datetime_added']);
        }

        // 2. Define Upload Directory & Set file versions
        $uploadDir = UPLOAD_PATH_TRIMMED . '/'. str_replace('-', '/', $pubdate);
        if (!empty($data['main_size'])) {
            $data['version'] = 1;
        }
        if (!empty($data['suppl_size'])) {
            $data['ver_suppl'] = 1;
        }

        try {
            // Begin Atomic Transaction
            $this->db->beginTransaction();

            // 3. Primary DB Insert
            $dID = $this->submitDoc($data);
            
            if (!$dID) {
                throw new Exception("Failed to insert core document record.");
            }

            // 4. Relational DB Inserts
            if (!empty($data['author_array'])) {
                $this->saveAuthors($dID, $data['author_array']);
            }
            
            if (!empty($data['branch_list_array'])) {
                $this->saveBranches($dID, $data['branch_list_array']);
            }
            
            if (!empty($data['link_list_array'])) {
                $this->saveExternalDocs($dID, $data['link_list_array']);
            }
            
            if (!empty($data['tID'])) {  // tID = 0 means no topic
                $this->saveTopic($dID, $data['tID']);
            }

            // 5. File Movement
            // Create the nested directory structure if it doesn't exist
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0750, true)) {
                    throw new Exception("Failed to create upload directory: $targetPath");
                }
            }

            // Move Main File
            if (!empty($data['main_size'])) {
                $mainDest = $uploadDir . '/' . ((string)$dID) . '_main_v1.pdf';
                if ($draftID > 0) {
                    $moveMain = rename(UPLOAD_PATH_TRIMMED . '/docdrafts/' . $draftID . '_main.pdf', $mainDest);
                } else {
                    $moveMain = move_uploaded_file($files['main_file']['tmp_name'], $mainDest);
                }
                if (!moveMain) {
                    throw new Exception("Failed to move main file to the document directory.");
                }
            }

            // Move Supplemental File
            if (!empty($data['suppl_size'])) {
                $supplDest = $uploadDir . '/' . ((string)$dID) . '_suppl_v1.' . ($data['suppl_ext'] ?? 'zip');
                if ($draftID > 0) {
                    $moveSuppl = rename(UPLOAD_PATH_TRIMMED . '/docdrafts/' . $draftID . '_suppl.' . ($data['suppl_ext'] ?? 'zip'), $supplDest);
                } else {
                    $moveSuppl = move_uploaded_file($files['supplemental_file']['tmp_name'], $supplDest);
                }
                if (!moveSuppl) {
                    throw new Exception("Failed to move supplemental file to the document directory.");
                }
            }

            // Delete the draft
            if ($draftID > 0) {
                $stmt = $this->db->prepare("DELETE FROM DocDrafts WHERE dID = :dID");
                $stmt->execute(['dID' => $draftID]);
            }

            // If we made it here, both DB and Filesystem succeeded!
            $this->db->commit();
            return $dID;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Error in DocumentService::submitFull(): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("System/File Error in DocumentService::submitFull(): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }
    }

    /**
     * Fully processes a document revision, updates version history, 
     * handles DB updates, and physically moves the new files.
     *
     * @param int $dID The document ID being revised
     * @param array $data Assumes merged and validated post/file data
     * @param array $files The raw $_FILES array
     * @return int|false Returns the dID on success, false on failure
     */
    public function reviseFull(int $dID, array $data, array $files): int|false
    {
        // 1. Retrieve old data
        $stmt = $this->db->prepare("SELECT doi, pubdate, version, ver_suppl, suppl_ext, revision_history, last_revision_time, main_size, suppl_size FROM Documents WHERE dID = :dID");
        $stmt->execute(['dID' => $dID]);
        $old = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$old) {
            error_log("DocumentService::reviseFull(): Document $dID not found in database.", 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }

        // 2. Check if new files are uploaded
        $hasNewMain = !empty($data['main_size']) && isset($files['main_file']);
        $hasNewSuppl = !empty($data['suppl_size']) && isset($files['supplemental_file']);

        // 3. Increment versions
        if ($hasNewMain) {
            $data['version'] = (int)$old['version'] + 1; if ($data['version'] > DOC_REVISION_MAX) return false;
        }
        if ($hasNewSuppl) {
            $data['ver_suppl'] = (int)$old['ver_suppl'] + 1; if ($data['ver_suppl'] > DOC_REVISION_MAX) return false;
        }

        // 4. Set the new revision time
        if ($hasNewMain || $hasNewSuppl) { $data['last_revision_time'] = date('Y-m-d H:i:s'); }

        // 5. Build the Revision History
        // Only append to history if there is actually a file update and it's a version > 1
        if (($data['version'] > 1 || $data['ver_suppl'] > 1) && ($hasNewMain || $hasNewSuppl)) {
            $history = json_decode($old['revision_history'] ?? '[]', true);
            
            // Format: [version, ver_suppl, suppl_ext, last_revision_time, revision_notes, main_size, suppl_size]
            // We append the OLD state, but apply the NEW notes submitted in this request
            $history[] = [
                (int)$old['version'],
                (int)$old['ver_suppl'],
                $old['suppl_ext'],
                $old['last_revision_time'],
                $data['revision_notes'] ?? '', // The new revision notes
                (int)$old['main_size'],
                (int)$old['suppl_size']
            ];
            
            $data['revision_history'] = json_encode($history);
        }

        // 6. Define Upload Directory
        $uploadDir = UPLOAD_PATH_TRIMMED . '/' . str_replace('-', '/', $old['pubdate']);

        try {
            // Begin Atomic Transaction
            $this->db->beginTransaction();

            // 7. DB Updates
            $this->reviseDoc($dID, $data);
            
            if (!empty($data['author_array'])) {
                $this->updateAuthors($dID, $data['author_array']);
            }
            if (!empty($data['branch_list_array'])) {
                $this->updateBranches($dID, $data['branch_list_array']);
            }
            if (!empty($data['link_list_array'])) {
                $this->updateExternalDocs($dID, $data['link_list_array']);
            }
            if (!empty($data['tID'])) {
                $this->saveTopic($dID, $data['tID']);
            } elseif (isset($data['tID']) && $data['tID'] === '0') {
                $this->deleteDocTopic($dID);
            }

            // 8. File Movements
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0750, true)) {
                    throw new Exception("Failed to create directory: $uploadDir");
                }
            }

            // Move new main file
            $filePrefix = empty($old['doi']) ? (string)$dID : $old['doi'];
            if ($hasNewMain) {
                $mainDest = $uploadDir . '/' . $filePrefix . '_main_v' . $data['version'] . '.pdf';
                if (!move_uploaded_file($files['main_file']['tmp_name'], $mainDest)) {
                    throw new Exception("Failed to move revised main file to disk.");
                }
            }

            // Move new supplemental file
            if ($hasNewSuppl) {
                $supplDest = $uploadDir . '/' . $filePrefix . '_suppl_v' . $data['ver_suppl'] . '.' . $data['suppl_ext'];
                if (!move_uploaded_file($files['supplemental_file']['tmp_name'], $supplDest)) {
                    throw new Exception("Failed to move revised supplemental file to disk.");
                }
            }

            // If we made it here, both DB and Filesystem succeeded!
            $this->db->commit();
            return $dID;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("DB Error in DocumentService::reviseFull (dID $dID): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("System/File Error in DocumentService::reviseFull (dID $dID): " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }
    }

    /**
     * Delete all external links for a document.
     */
    public function deleteExternalDocs(int $dID): void
    {
        $stmt = $this->db->prepare("DELETE FROM ExternalDocs WHERE dID = :dID");
        $stmt->execute(['dID' => $dID]);
    }

    /**
     * Save external links for a document.
     * Returns array of valid sIDs.
     */
    public function saveExternalDocs(int $dID, array $links): array
    {
        $sql = "INSERT INTO ExternalDocs (dID, sID, esname, link) 
                VALUES (:dID, :sID, :esname, :link) 
                ON DUPLICATE KEY UPDATE esname = VALUES(esname), link = VALUES(link)";
        $stmt = $this->db->prepare($sql);

        $validSIDs = [];
        foreach ($links as $link) {
            if (isset($link[0], $link[2])) {
                $sID = (int)$link[0];
                $validSIDs[] = $sID;
                $stmt->execute([
                    'dID'    => $dID,
                    'sID'    => $sID,
                    'esname' => $link[1],
                    'link'   => $link[2]
                ]);
            }
        }

        return $validSIDs;
    }

    /**
     * Upsert external links for a document.
     * Calls saveExternalDocs() then removes orphaned rows.
     */
    public function updateExternalDocs(int $dID, array $links): array
    {
        if (empty($links)) {
            $this->deleteExternalDocs($dID);
            return [];
        }

        $validSIDs = $this->saveExternalDocs($dID, $links);

        if (!empty($validSIDs)) {
            $placeholders = implode(',', array_fill(0, count($validSIDs), '?'));
            $stmt = $this->db->prepare(
                "DELETE FROM ExternalDocs WHERE dID = ? AND sID NOT IN ($placeholders)"
            );
            $stmt->execute(array_merge([$dID], $validSIDs));
        }
        return $validSIDs;
    }

    /**
     * Delete all branch associations for a document.
     */
    public function deleteDocBranches(int $dID): void
    {
        $stmt = $this->db->prepare("DELETE FROM DocBranches WHERE dID = :dID");
        $stmt->execute(['dID' => $dID]);
    }

    /**
     * Delete topic association for a document.
     */
    public function deleteDocTopic(int $dID): void
    {
        $stmt = $this->db->prepare("DELETE FROM DocTopics WHERE dID = :dID");
        $stmt->execute(['dID' => $dID]);
    }

    /**
     * Delete all authors for a document.
     */
    public function deleteAuthors(int $dID): void
    {
        $stmt = $this->db->prepare("DELETE FROM DocAuthors WHERE dID = :dID");
        $stmt->execute(['dID' => $dID]);
    }

    /**
     * Save authors using array input and upsert method.
     * Input format: [[mID, duty, frac], ...]
     * Returns array of valid mIDs.
     */
    public function saveAuthors(int $dID, array $authors): array
    {
        if (empty($authors)) return [];

        $sql = "INSERT INTO DocAuthors (dID, mID, duty, frac) 
                VALUES (:dID, :mID, :duty, :frac) 
                ON DUPLICATE KEY UPDATE duty = VALUES(duty), frac = VALUES(frac)";
        $stmt = $this->db->prepare($sql);

        $validMIDs = [];
        foreach ($authors as $author) {
            $mID = (int)($author[0] ?? 0);
            if ($mID > 0) {
                $stmt->execute([
                    'dID'  => $dID,
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
     * Update authors using array input.
     * Calls saveAuthors() then removes orphaned rows.
     */
    public function updateAuthors(int $dID, array $authors): array
    {
        $validMIDs = $this->saveAuthors($dID, $authors);

        if (!empty($validMIDs)) {
            $placeholders = implode(',', array_fill(0, count($validMIDs), '?'));
            $stmt = $this->db->prepare(
                "DELETE FROM DocAuthors WHERE dID = ? AND mID NOT IN ($placeholders)"
            );
            $stmt->execute(array_merge([$dID], $validMIDs));
        }

        return $validMIDs;
    }

    /**
     * Save branches for a document.
     * Uses INSERT ... ON DUPLICATE KEY UPDATE for upsert.
     */
    public function saveBranches(int $dID, array $branches): void
    {
        $sql = "INSERT INTO DocBranches (dID, bID, num, impact) 
                VALUES (:dID, :bID, :num, :impact) 
                ON DUPLICATE KEY UPDATE bID = VALUES(bID), impact = VALUES(impact)";
        $stmt = $this->db->prepare($sql);

        foreach ($branches as $branch) {
            $stmt->execute([
                'dID'    => $dID,
                'bID'    => (int)$branch['bID'],
                'num'    => (int)$branch['num'],
                'impact' => (int)$branch['impact']
            ]);
        }
    }

    /**
     * Update branches for a document.
     * Calls saveBranches() then removes rows beyond count.
     */
    public function updateBranches(int $dID, array $branches): void
    {
        $this->saveBranches($dID, $branches);

        $curNum = count($branches);
        if ($curNum > 0 && $curNum < DOC_BRANCH_MAX) {
            $stmt = $this->db->prepare(
                "DELETE FROM DocBranches WHERE dID = :dID AND num > :curNum"
            );
            $stmt->execute(['dID' => $dID, 'curNum' => $curNum]);
        }
    }

    /**
     * Save topic for a document.
     * Uses ON DUPLICATE KEY UPDATE for true upsert.
     */
    public function saveTopic(int $dID, int $tID): void
    {
        $sql = "INSERT INTO DocTopics (dID, tID) VALUES (:dID, :tID)
                ON DUPLICATE KEY UPDATE tID = VALUES(tID)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID, 'tID' => $tID]);
    }
}
