<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;

/**
 * Member Model
 * 
 * Handles DB reading of the Members table to display and search member information.
 */
class Member
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find multiple members by their alphanumeric IDs (CoreIDs).
     *
     * @param array $alphaIds
     * @return array
     */
    public function lookUpByCoreIDs(array $alphaIds): array
    {
        $alphaIds = array_filter(array_map('strtoupper', array_map('trim', $alphaIds)));
        if (empty($alphaIds)) return [];

        $placeholders = implode(',', array_fill(0, count($alphaIds), '?'));
        $values = array_values($alphaIds);
        $sql = "SELECT mID, pub_name, CoreID FROM Members WHERE CoreID IN ($placeholders)
                ORDER BY FIELD(CoreID, $placeholders)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($values, $values));
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch a public member profile by their CoreID.
     *
     * @param string $coreId
     * @return array|bool
     */
    public function getPublicProfileByCoreID(string $coreId): array|bool
    {
        $sql = "SELECT m.*, mm.meta_value, mk.mkname, mm.is_public as meta_public, i.iname
                FROM Members m
                LEFT JOIN MemberMeta mm ON m.mID = mm.mID AND mm.is_public = 1
                LEFT JOIN MemberMetaKeys mk ON mm.meta_ID = mk.ID AND mk.is_active = 1
                LEFT JOIN Institutions i ON m.iID = i.iID
                WHERE m.CoreID = :coreId";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['coreId' => $coreId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) return false;

        $member = $rows[0];
        
        // Consolidate metadata
        $metadata = [];
        foreach ($rows as $row) {
            if (!empty($row['mkname']) && !empty($row['meta_value'])) {
                $metadata[$row['mkname']] = $row['meta_value'];
            }
        }

        // Apply formatting (Fat Model logic)
        $member['formatted_id'] = AuthService::formatCoreID($member['CoreID']);
        $member['fullName'] = $this->buildFullName($member, $metadata);
        $member['work_areas_sanitized'] = $this->sanitizeAreas($member['work_areas'] ?? '');
        $member['interest_areas_sanitized'] = $this->sanitizeAreas($member['interest_areas'] ?? '');
        
        // Add display versions of areas
        $member['work_areas_display'] = $this->formatResearchAreas($member['work_areas'] ?? '', true);
        $member['interest_areas_display'] = $this->formatResearchAreas($member['interest_areas'] ?? '', true);

        // Filter metadata
        $filterKeys = ['full_name', 'prefix', 'suffix', 'other_names'];
        foreach ($filterKeys as $key) unset($metadata[$key]);
        $member['metadata'] = $metadata;

        // Metrics fallbacks
        $member['AL']  = $member['AL'] ?? 'N/A';
        $member['ALS'] = $member['ALS'] ?? 'N/A';
        $member['ECP'] = $member['ECP'] ?? 'N/A';

        return $member;
    }

    /**
     * Build full name from parts or metadata.
     */
    public function buildFullName(array $member, array $metadata): string
    {
        if (!empty($metadata['full_name'])) {
            return $metadata['full_name'];
        }
        
        $parts = [];
        if (!empty($metadata['prefix'])) $parts[] = $metadata['prefix'];
        if (!empty($member['first_name'])) $parts[] = $member['first_name'];
        if (!empty($member['family_name'])) $parts[] = $member['family_name'];
        if (!empty($metadata['suffix'])) $parts[] = $metadata['suffix'];
        
        return implode(' ', $parts);
    }

    /**
     * Sanitize semicolon-separated area strings.
     */
    public function sanitizeAreas(string $areas): string
    {
        if (empty($areas)) return '';
        $parts = explode(';', $areas);
        $clean = [];
        foreach ($parts as $p) {
            $val = trim($p);
            if ($val !== '' && (int)$val >= 0) {
                $clean[] = $val;
            }
        }
        return implode('; ', $clean);
    }

    /**
     * Format research areas from semicolon-separated IDs to 'abbr (bname)' strings.
     */
	private function formatResearchAreas(string $dbString, bool $publicOnly = false): array
	{
		if (empty(trim($dbString))) return [];

		$parts = explode(';', $dbString);
		$validIds = [];

		// 1. Gather and filter all valid IDs first (No DB calls here!)
		foreach ($parts as $part) {
			$id = (int)$part;
			if ($publicOnly && $id < 0) continue;
			$validIds[] = abs($id);
		}

		if (empty($validIds)) return [];

		// 2. Query the DB EXACTLY ONCE for all branches
		$placeholders = implode(',', array_fill(0, count($validIds), '?'));
		$sql = "SELECT bID, abbr, bname FROM ResearchBranches WHERE bID IN ($placeholders)";
		
		$stmt = $this->db->prepare($sql);
		// Use array_values to ensure the array is numerically indexed for the '?' placeholders
		$stmt->execute(array_values($validIds)); 
		$branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// 3. Create a quick lookup map [bID => "ABBR (Branch Name)"]
		$branchMap = [];
		foreach ($branches as $branch) {
			$branchMap[$branch['bID']] = "{$branch['abbr']} ({$branch['bname']})";
		}

		// 4. Build the final formatted array (maintaining the original string's order)
		$formatted = [];
		foreach ($validIds as $id) {
			if (isset($branchMap[$id])) {
				$formatted[] = $branchMap[$id];
			}
		}

		return $formatted;
	}

    /**
     * Search members by name or pub_name.
     * 
     * @return array ['results' => array, 'total' => int]
     */
    public function searchMembers(string $query, int $limit = 20, int $offset = 0): array
    {
        $query = trim($query);
        $result = ['results' => [], 'total' => 0];
        if ($query === '') return $result;

        // Escape SQL wildcards to prevent search abuse
        $escapedQuery = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $like = '%' . $escapedQuery . '%';

        // Count total
        $countSql = "SELECT COUNT(*) FROM Members 
                     WHERE is_active = 1 AND is_good = 1
                       AND (display_name LIKE :q1 OR family_name LIKE :q2 OR first_name LIKE :q3 OR pub_name LIKE :q4)";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]);
        $total = (int)$stmt->fetchColumn();
        if ($total === 0) return $result;

        // Fetch results
        $sql = "SELECT m.mID, m.display_name, m.pub_name, m.CoreID, i.iname
                FROM Members m
                LEFT JOIN Institutions i ON m.iID = i.iID
                WHERE m.is_active = 1 AND m.is_good = 1
                  AND (m.display_name LIKE :q1 OR m.family_name LIKE :q2 OR m.first_name LIKE :q3 OR m.pub_name LIKE :q4)
                ORDER BY m.display_name ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':q1', $like);
        $stmt->bindValue(':q2', $like);
        $stmt->bindValue(':q3', $like);
        $stmt->bindValue(':q4', $like);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['results' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total];
    }
}
