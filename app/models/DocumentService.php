<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;
use Exception;

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
    public function submitDocument(array $data): int
    {
        $mainSize = isset($data['main_size']) && $data['main_size'] !== '' ? (int)$data['main_size'] : 0;
        $supplSize = isset($data['suppl_size']) && $data['suppl_size'] !== '' ? (int)$data['suppl_size'] : 0;
        $supplExt = isset($data['suppl_ext']) ? (int)$data['suppl_ext'] : null;

        $version = $mainSize > 0 ? 1 : 0;
        $verSuppl = $supplSize > 0 ? 1 : null;

        $fields = ['submitter_ID', 'title', 'abstract', 'dtype', 'version', 'ver_suppl', 'suppl_ext'];
        $placeholders = [':submitter_ID', ':title', ':abstract', ':dtype', ':version', ':ver_suppl', ':suppl_ext'];
        $params = [
            'submitter_ID' => $data['submitter_ID'],
            'title'        => $data['title'],
            'abstract'     => $data['abstract'],
            'dtype'        => (int)($data['dtype'] ?? 1),
            'version'      => $version,
            'ver_suppl'    => $verSuppl,
            'suppl_ext'    => $verSuppl !== null ? $supplExt : null
        ];

        $optionalFields = ['notes', 'author_list', 'submission_time', 'pubdate', 'full_text', 'main_pages', 'main_figs', 'main_tabs', 'main_size', 'suppl_size'];
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

        if (!empty($data['link_list_array'])) {
            $sqlLink = "INSERT INTO ExternalDocs (dID, sID, esname, link) VALUES (:dID, :sID, :esname, :link)";
            $stmtLink = $this->db->prepare($sqlLink);
            foreach ($data['link_list_array'] as $link) {
                if (isset($link[0], $link[2])) {
                    $stmtLink->execute([
                        'dID'    => $dID,
                        'sID'    => (int)$link[0],
                        'esname' => $link[1],
                        'link'   => $link[2]
                    ]);
                }
            }
        }

        return $dID;
    }

    /**
     * Revise a published document.
     */
    public function reviseDocument(int $dID, array $data, bool $mainChanged, bool $supplChanged): array
    {
        $stmt = $this->db->prepare("SELECT version, ver_suppl, suppl_ext, revision_history, last_revision_time, main_size, suppl_size FROM Documents WHERE dID = :dID");
        $stmt->execute(['dID' => $dID]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current) {
            return ['version' => 0, 'ver_suppl' => null];
        }

        $oldVersion = (int)($current['version'] ?? 0);
        $oldVerSuppl = $current['ver_suppl'] !== null ? (int)$current['ver_suppl'] : null;
        $oldSupplExt = $current['suppl_ext'] !== null ? (int)$current['suppl_ext'] : null;

        $newVersion = $mainChanged ? $oldVersion + 1 : $oldVersion;
        $newVerSuppl = $supplChanged ? ($oldVerSuppl !== null ? $oldVerSuppl + 1 : 1) : $oldVerSuppl;
        $newSupplExt = $supplChanged && isset($data['suppl_ext']) ? (int)$data['suppl_ext'] : $oldSupplExt;

        $fields = ['title', 'abstract', 'dtype'];
        $params = [
            'dID'      => $dID,
            'title'    => $data['title'],
            'abstract' => $data['abstract'],
            'dtype'    => (int)($data['dtype'] ?? 1),
        ];

        if ($mainChanged || $supplChanged) {
            $revisionHistory = json_decode($current['revision_history'] ?? '[]', true) ?: [];

            $revisionHistory[] = [
                $oldVersion,
                $oldVerSuppl,
                $oldSupplExt,
                $current['last_revision_time'],
                $data['revision_notes'] ?? '',
                (int)($current['main_size'] ?? 0),
                (int)($current['suppl_size'] ?? 0)
            ];

            $fields[] = 'version';
            $fields[] = 'ver_suppl';
            $fields[] = 'suppl_ext';
            $fields[] = 'revision_history';
            $fields[] = 'last_revision_time';
            $params['version'] = $newVersion;
            $params['ver_suppl'] = $newVerSuppl;
            $params['suppl_ext'] = $newSupplExt;
            $params['revision_history'] = json_encode($revisionHistory);
            $params['last_revision_time'] = date('Y-m-d H:i:s');
            
            if ($mainChanged) {
                $fields[] = 'main_size';
                $params['main_size'] = (int)($data['main_size'] ?? 0);
            }
            if ($supplChanged) {
                $fields[] = 'suppl_size';
                $params['suppl_size'] = (int)($data['suppl_size'] ?? 0);
            }
        }

        $optionalFields = ['notes', 'author_list', 'full_text', 'main_pages', 'main_figs', 'main_tabs'];
        foreach ($optionalFields as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = $f;
                $params[$f] = $data[$f] === '' ? null : $data[$f];
            }
        }

        $setClauses = array_map(fn($f) => "$f = :$f", $fields);
        $sql = "UPDATE Documents SET " . implode(', ', $setClauses) . " WHERE dID = :dID";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return [
            'version' => $newVersion,
            'ver_suppl' => $newVerSuppl
        ];
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
     * Upsert external links for a document.
     */
    public function updateExternalDocs(int $dID, array $links): void
    {
        if (empty($links)) return;

        $this->deleteExternalDocs($dID);

        $sqlLink = "INSERT INTO ExternalDocs (dID, sID, esname, link) VALUES (:dID, :sID, :esname, :link)";
        $stmtLink = $this->db->prepare($sqlLink);

        foreach ($links as $link) {
            if (isset($link[0], $link[2])) {
                $stmtLink->execute([
                    'dID'    => $dID,
                    'sID'    => (int)$link[0],
                    'esname' => $link[1] ?? '',
                    'link'   => $link[2]
                ]);
            }
        }
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
     * Save authors from author_list JSON.
     */
    public function saveAuthorsFromList(int $dID, string $authorListJson): void
    {
        $authorData = json_decode($authorListJson, true);
        if (empty($authorData) || !isset($authorData['authors'])) {
            return;
        }

        $this->deleteAuthors($dID);

        $sql = "INSERT INTO DocAuthors (dID, mID, author_order, duty) VALUES (:dID, :mID, :order, :duty)";
        $stmt = $this->db->prepare($sql);

        $order = 1;
        foreach ($authorData['authors'] as $author) {
            $mID = $author[1] ?? 0;
            if ($mID > 0) {
                $stmt->execute([
                    'dID'   => $dID,
                    'mID'   => $mID,
                    'order' => $order,
                    'duty'  => $author[2] ?? 100
                ]);
                $order++;
            }
        }
    }

    /**
     * Upsert authors from author_list JSON.
     */
    public function upsertAuthorsFromList(int $dID, string $authorListJson): void
    {
        $authorData = json_decode($authorListJson, true);
        if (empty($authorData) || !isset($authorData['authors'])) {
            return;
        }

        $this->deleteAuthors($dID);

        $sql = "INSERT INTO DocAuthors (dID, mID, author_order, duty) VALUES (:dID, :mID, :order, :duty)";
        $stmt = $this->db->prepare($sql);

        $order = 1;
        foreach ($authorData['authors'] as $author) {
            $mID = $author[1] ?? 0;
            if ($mID > 0) {
                $stmt->execute([
                    'dID'   => $dID,
                    'mID'   => $mID,
                    'order' => $order,
                    'duty'  => $author[2] ?? 100
                ]);
                $order++;
            }
        }
    }

    /**
     * Save branches for a document.
     */
    public function saveBranches(int $dID, array $branches): void
    {
        $this->deleteDocBranches($dID);

        $sql = "INSERT INTO DocBranches (dID, bID, num, impact) VALUES (:dID, :bID, :num, :impact)";
        $stmt = $this->db->prepare($sql);

        $num = 1;
        foreach ($branches as $branch) {
            $stmt->execute([
                'dID'    => $dID,
                'bID'    => (int)$branch['bID'],
                'num'    => $num,
                'impact' => (int)($branch['impact'] ?? 0)
            ]);
            $num++;
        }
    }

    /**
     * Upsert branches for a document.
     */
    public function upsertBranches(int $dID, array $branches): void
    {
        $this->saveBranches($dID, $branches);
    }

    /**
     * Save topic for a document.
     */
    public function saveTopic(int $dID, int $tID): void
    {
        $this->deleteDocTopic($dID);

        if ($tID > 0) {
            $sql = "INSERT INTO DocTopics (dID, tID) VALUES (:dID, :tID)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['dID' => $dID, 'tID' => $tID]);
        }
    }
}
