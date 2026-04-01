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

        $optionalFields = ['notes', 'author_list', 'submission_time', 'pubdate', 'full_text', 'link_list', 'branch_list', 'tID'];
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

        // Insert external links if provided
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
        $sql = "INSERT INTO DocBranches (dID, bID, num, impact) 
                VALUES (:dID, :bID, :num, :impact)";
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
     * Save topic for a document (DocTopics table).
     */
    public function saveTopic(int $dID, int $tID): void
    {
        $sql = "INSERT INTO DocTopics (dID, tID) VALUES (:dID, :tID)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID, 'tID' => $tID]);
    }

    /**
     * Fetch research branches linked to a published document.
     * 
     * @return array [['bID'=>int, 'abbr'=>string, 'bname'=>string, 'num'=>int, 'impact'=>int], ...]
     */
    public function getDocBranches(int $dID): array
    {
        $sql = "SELECT db.bID, rb.abbr, rb.bname, db.num, db.impact
                FROM DocBranches db
                INNER JOIN ResearchBranches rb ON db.bID = rb.bID
                WHERE db.dID = :dID
                ORDER BY db.num ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch topic linked to a published document.
     * 
     * @return array|false ['tID'=>int, 'abbr'=>string, 'tname'=>string] or false
     */
    public function getDocTopic(int $dID): array|false
    {
        $sql = "SELECT dt.tID, rt.abbr, rt.tname
                FROM DocTopics dt
                INNER JOIN ResearchTopics rt ON dt.tID = rt.tID
                WHERE dt.dID = :dID
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['dID' => $dID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: false;
    }

    /**
     * Fetch topic for a draft by tID.
     * 
     * @return array|false ['tID'=>int, 'abbr'=>string, 'tname'=>string] or false
     */
    public function getTopicById(int $tID): array|false
    {
        $sql = "SELECT tID, abbr, tname FROM ResearchTopics WHERE tID = :tID LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['tID' => $tID]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: false;
    }

    /**
     * Fetch branches by an array of bIDs.
     * 
     * @return array Keyed by bID: [bID => ['bID'=>int, 'abbr'=>string, 'bname'=>string], ...]
     */
    public function getBranchesByIds(array $bIDs): array
    {
        if (empty($bIDs)) return [];
        $placeholders = implode(',', array_fill(0, count($bIDs), '?'));
        $sql = "SELECT bID, abbr, bname FROM ResearchBranches WHERE bID IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($bIDs));
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $b) {
            $result[(int)$b['bID']] = $b;
        }
        return $result;
    }

    /**
     * Build shared WHERE clause, JOINs, and params for document filter conditions.
     * 
     * Supported filter keys: type, branch, topic, range (day/week/month/all), from, to
     * 
     * @return array ['where' => string[], 'joins' => string, 'params' => array, 'paramIdx' => int]
     */
    private function buildFilterWhere(array $filters, int $paramIdx = 0): array
    {
        $where = [];
        $joins = '';
        $params = [];

        if (!empty($filters['type'])) {
            $idx = $paramIdx++;
            $where[] = "d.dtype = :f_dtype{$idx}";
            $params["f_dtype{$idx}"] = (int)$filters['type'];
        }

        if (!empty($filters['branch'])) {
            $idx = $paramIdx++;
            $joins .= " INNER JOIN DocBranches db{$idx} ON d.dID = db{$idx}.dID";
            $where[] = "db{$idx}.bID = :f_bID{$idx}";
            $params["f_bID{$idx}"] = (int)$filters['branch'];
        }

        if (!empty($filters['topic'])) {
            $idx = $paramIdx++;
            $joins .= " INNER JOIN DocTopics dt{$idx} ON d.dID = dt{$idx}.dID";
            $where[] = "dt{$idx}.tID = :f_tID{$idx}";
            $params["f_tID{$idx}"] = (int)$filters['topic'];
        }

        // Date range
        $now = date('Y-m-d H:i:s');
        if (!empty($filters['from']) || !empty($filters['to'])) {
            $idx = $paramIdx++;
            if (!empty($filters['from'])) {
                $where[] = "d.submission_time >= :f_from{$idx}";
                $params["f_from{$idx}"] = $filters['from'] . ' 00:00:00';
            }
            if (!empty($filters['to'])) {
                $idx2 = $paramIdx++;
                $where[] = "d.submission_time <= :f_to{$idx2}";
                $params["f_to{$idx2}"] = $filters['to'] . ' 23:59:59';
            }
        } elseif (!empty($filters['range'])) {
            $idx = $paramIdx++;
            $range = strtolower($filters['range']);
            $interval = match ($range) {
                'day' => '1 DAY',
                'week' => '7 DAY',
                'month' => '30 DAY',
                default => null
            };
            if ($interval !== null) {
                $where[] = "d.submission_time >= DATE_SUB(:f_now{$idx}, INTERVAL {$interval})";
                $params["f_now{$idx}"] = $now;
            }
        }

        return ['where' => $where, 'joins' => $joins, 'params' => $params, 'paramIdx' => $paramIdx];
    }

    /**
     * FULLTEXT search on title + abstract with optional filters.
     * 
     * @return array ['results' => array, 'total' => int]
     */
    public function searchDocuments(string $query, array $filters, int $limit, int $offset): array
    {
        $query = trim($query);
        $result = ['results' => [], 'total' => 0];
        if ($query === '') return $result;

        $filter = $this->buildFilterWhere($filters, 0);
        $whereClauses = array_merge(
            ["MATCH(d.title, d.abstract) AGAINST (:search_query IN BOOLEAN MODE)"],
            $filter['where']
        );
        $whereSql = implode(' AND ', $whereClauses);

        $params = array_merge(['search_query' => $query], $filter['params']);

        // Count total
        $countSql = "SELECT COUNT(*) FROM Documents d{$filter['joins']} WHERE {$whereSql}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Fetch results with relevance score
        $sql = "SELECT d.*, m.display_name as submitter_name,
                        MATCH(d.title, d.abstract) AGAINST (:search_query2 IN BOOLEAN MODE) as relevance
                FROM Documents d
                JOIN Members m ON d.submitter_ID = m.mID
                {$filter['joins']}
                WHERE {$whereSql}
                ORDER BY relevance DESC, d.submission_time DESC
                LIMIT :limit OFFSET :offset";

        $params['search_query2'] = $query;
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === 'limit' || $key === 'offset') continue;
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['results' => $stmt->fetchAll(), 'total' => $total];
    }

    /**
     * Filtered document feed (no FULLTEXT query).
     * 
     * @return array ['results' => array, 'total' => int]
     */
    public function getDocumentsByFilter(array $filters, int $limit, int $offset): array
    {
        $result = ['results' => [], 'total' => 0];
        $filter = $this->buildFilterWhere($filters, 0);

        if (empty($filter['where'])) {
            return $result;
        }

        $whereSql = implode(' AND ', $filter['where']);
        $params = $filter['params'];

        // Count total
        $countSql = "SELECT COUNT(*) FROM Documents d{$filter['joins']} WHERE {$whereSql}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Fetch results
        $sql = "SELECT d.*, m.display_name as submitter_name
                FROM Documents d
                JOIN Members m ON d.submitter_ID = m.mID
                {$filter['joins']}
                WHERE {$whereSql}
                ORDER BY d.submission_time DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['results' => $stmt->fetchAll(), 'total' => $total];
    }
}
