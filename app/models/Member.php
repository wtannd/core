<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;
use PDOException;
use Exception;

/**
 * Member Model
 * 
 * Handles database interactions for the Members table.
 */
class Member
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Find a member by email.
     *
     * @param string $email
     * @return array|bool
     */
    public function findByEmail(string $email): array|bool
    {
        $stmt = $this->db->prepare("
            SELECT m.*, i.iname 
            FROM Members m 
            LEFT JOIN Institutions i ON m.iID = i.iID 
            WHERE m.email = :email LIMIT 1
        ");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Find a member by mID.
     *
     * @param int $mID
     * @return array|bool
     */
    public function findById(int $mID): array|bool
    {
        $stmt = $this->db->prepare("
            SELECT m.*, i.iname 
            FROM Members m 
            LEFT JOIN Institutions i ON m.iID = i.iID 
            WHERE m.mID = :mID LIMIT 1
        ");
        $stmt->execute(['mID' => $mID]);
        $member = $stmt->fetch();

        if ($member) {
            $member['work_areas_display'] = $this->formatResearchAreas($member['work_areas'] ?? '', false);
            $member['interest_areas_display'] = $this->formatResearchAreas($member['interest_areas'] ?? '', false);
            $member['mail_areas_display'] = $this->formatResearchAreas($member['mail_areas'] ?? '', false);
        }

        return $member;
    }

    /**
     * Create a new member with flexible data.
     *
     * @param array $data
     * @return int|bool The new member's ID or false on failure.
     */
    public function create(array $data): int|bool
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);
        
        $sql = "INSERT INTO Members (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($data);

        if ($result) {
            $mID = (int)$this->db->lastInsertId();
            $this->updateAlphanumId($mID);
            return $mID;
        }

        return false;
    }

    /**
     * Add member metadata.
     *
     * @param int $mID
     * @param array $metaData
     * @param array $metaPublicFlags
     * @return void
     */
    public function addMemberMeta(int $mID, array $metaData, array $metaPublicFlags = []): void
    {
        if (empty($metaData)) {
            return;
        }

        $stmt = $this->db->query("SELECT mkname, ID FROM MemberMetaKeys WHERE is_active = 1");
        $keyMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $insertStmt = $this->db->prepare("
            INSERT INTO MemberMeta (mID, meta_ID, meta_value, is_public) 
            VALUES (:mID, :meta_ID, :meta_value, :is_public)
            ON DUPLICATE KEY UPDATE meta_value = VALUES(meta_value), is_public = VALUES(is_public)
        ");

        foreach ($metaData as $key => $value) {
            if ($value === null || $value === '' || !isset($keyMap[$key])) {
                continue;
            }

            $isPublic = (isset($metaPublicFlags[$key]) && $metaPublicFlags[$key] === '1') ? 1 : 0;
            
            $insertStmt->execute([
                'mID' => $mID,
                'meta_ID' => $keyMap[$key],
                'meta_value' => (string)$value,
                'is_public' => $isPublic
            ]);
        }
    }

    /**
     * Update the last login time for a member.
     */
    public function updateLastLogin(int $mID): bool
    {
        $stmt = $this->db->prepare("UPDATE Members SET last_login = CURRENT_TIMESTAMP WHERE mID = :mID");
        return $stmt->execute(['mID' => $mID]);
    }

    /**
     * Update the persistent login token for a member.
     *
     * @param int $mID
     * @param string|null $token
     * @return bool
     */
    public function updateToken(int $mID, ?string $token): bool
    {
        $stmt = $this->db->prepare("UPDATE Members SET token = :token WHERE mID = :mID");
        return $stmt->execute(['token' => $token, 'mID' => $mID]);
    }

    /**
     * Link an ORCID ID to an existing member.
     *
     * @param int $mID
     * @param string $orcid
     * @return bool
     */
    public function updateOrcid(int $mID, string $orcid): bool
    {
        try {
            $stmt = $this->db->prepare("UPDATE Members SET ORCID = :orcid WHERE mID = :mID");
            return $stmt->execute(['orcid' => $orcid, 'mID' => $mID]);
        } catch (PDOException $e) {
            error_log("Error linking ORCID for mID $mID: " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }
    }

    /**
     * Find multiple members by their alphanumeric IDs.
     *
     * @param array $alphaIds
     * @return array
     */
    public function findByAlphaIds(array $alphaIds): array
    {
        $alphaIds = array_filter(array_map('strtoupper', array_map('trim', $alphaIds)));
        if (empty($alphaIds)) return [];

        $placeholders = implode(',', array_fill(0, count($alphaIds), '?'));
        $values = array_values($alphaIds);
        $sql = "SELECT mID, pub_name, ID_alphanum FROM Members WHERE ID_alphanum IN ($placeholders)
                ORDER BY FIELD(ID_alphanum, $placeholders)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($values, $values));
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find a member by their persistent login token.
     *
     * @param string $token
     * @return array|bool
     */
    public function findByToken(string $token): array|bool
    {
        $stmt = $this->db->prepare("
            SELECT m.*, i.iname 
            FROM Members m 
            LEFT JOIN Institutions i ON m.iID = i.iID 
            WHERE m.token = :token LIMIT 1
        ");
        $stmt->execute(['token' => $token]);
        return $stmt->fetch();
    }

    /**
     * Find a member by ORCID.
     *
     * @param string $orcidId
     * @return array|bool
     */
    public function findByOrcid(string $orcidId): array|bool
    {
        $stmt = $this->db->prepare("
            SELECT m.*, i.iname 
            FROM Members m 
            LEFT JOIN Institutions i ON m.iID = i.iID 
            WHERE m.ORCID = :orcid LIMIT 1
        ");
        $stmt->execute(['orcid' => $orcidId]);
        return $stmt->fetch();
    }

    /**
     * Fetch a full editable member profile including all metadata and public flags.
     *
     * @param int $mID
     * @return array|bool
     */
    public function getFullEditableProfile(int $mID): array|bool
    {
        $member = $this->findById($mID);
        if (!$member) return false;

        $sql = "SELECT mm.meta_value, mk.mkname, mm.is_public
                FROM MemberMeta mm
                JOIN MemberMetaKeys mk ON mm.meta_ID = mk.ID
                WHERE mm.mID = :mID AND mk.is_active = 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['mID' => $mID]);
        $metaRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $meta = [];
        $metaPublic = [];
        foreach ($metaRows as $row) {
            $meta[$row['mkname']] = $row['meta_value'];
            $metaPublic[$row['mkname']] = (int)$row['is_public'];
        }

        $member['meta'] = $meta;
        $member['meta_public'] = $metaPublic;
        $member['formatted_id'] = self::formatAlphanumId($member['ID_alphanum']);

        return $member;
    }

    /**
     * Update a complete member profile (base info + metadata).
     */
    public function updateCompleteProfile(int $mID, array $baseData, array $metaData, array $metaPublic): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Update base Members table
            if (!empty($baseData)) {
                $this->update($mID, $baseData);
            }

            // 2. Process Metadata
            $this->addMemberMeta($mID, $metaData, $metaPublic);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating complete profile for mID $mID: " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }
    }

    /**
     * Update a member's details.
     */
    public function update(int $mID, array $data): bool
    {
        // Whitelist allowed columns to prevent mass-assignment of sensitive fields
        $allowed = [
            'first_name', 'family_name', 'display_name', 'pub_name', 'email', 
            'iID', 'work_areas', 'interest_areas', 'mail_areas', 
            'timezone', 'is_email_public'
        ];

        $fields = [];
        $params = ['mID' => $mID];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowed)) {
                $fields[] = "$key = :$key";
                $params[$key] = $value;
            }
        }

        if (empty($fields)) return false;

        $sql = "UPDATE Members SET " . implode(', ', $fields) . " WHERE mID = :mID";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Fetch a public member profile by their alphanumeric ID.
     *
     * @param string $ID_alphanum
     * @return array|bool
     */
    public function getPublicProfileByAlphaId(string $ID_alphanum): array|bool
    {
        $sql = "SELECT m.*, mm.meta_value, mk.mkname, mm.is_public as meta_public, i.iname
                FROM Members m
                LEFT JOIN MemberMeta mm ON m.mID = mm.mID AND mm.is_public = 1
                LEFT JOIN MemberMetaKeys mk ON mm.meta_ID = mk.ID AND mk.is_active = 1
                LEFT JOIN Institutions i ON m.iID = i.iID
                WHERE m.ID_alphanum = :ID_alphanum";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['ID_alphanum' => $ID_alphanum]);
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
        $member['formatted_id'] = self::formatAlphanumId($member['ID_alphanum']);
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
     * Format raw alphanumeric ID to XXX-XXX-XXX (padded to 9 chars).
     */
    public static function formatAlphanumId(string $rawId): string
    {
        $padded = str_pad(strtoupper(trim($rawId)), 9, '0', STR_PAD_LEFT);
        return substr($padded, 0, 3) . '-' . substr($padded, 3, 3) . '-' . substr($padded, 6, 3);
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
        if (empty($dbString)) return [];

        $parts = explode(';', $dbString);
        $formatted = [];

        foreach ($parts as $part) {
            $val = trim($part);
            if ($val === '') continue;
            
            $id = (int)$val;
            if ($id === 0) continue;
            if ($publicOnly && $id < 0) continue;

            $absId = abs($id);
            $stmt = $this->db->prepare("SELECT abbr, bname FROM ResearchBranches WHERE bID = :bID LIMIT 1");
            $stmt->execute(['bID' => $absId]);
            $branch = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($branch) {
                $formatted[] = "{$branch['abbr']} ({$branch['bname']})";
            }
        }

        return $formatted;
    }

    /**
     * Update the alphanumeric ID for a member.
     */
    private function updateAlphanumId(int $mID): void
    {
        $stmt = $this->db->prepare("UPDATE Members SET ID_alphanum = UPPER(CONV(:mID1, 10, 36)) WHERE mID = :mID2");
        $stmt->execute(['mID1' => $mID, 'mID2' => $mID]);
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

        $like = '%' . $query . '%';

        // Count total
        $countSql = "SELECT COUNT(*) FROM Members 
                     WHERE is_active = 1 AND is_good = 1
                       AND (display_name LIKE :q1 OR family_name LIKE :q2 OR first_name LIKE :q3 OR pub_name LIKE :q4)";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute(['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]);
        $total = (int)$stmt->fetchColumn();

        // Fetch results
        $sql = "SELECT m.mID, m.display_name, m.pub_name, m.ID_alphanum, i.iname
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

    /**
     * Convert selected area IDs with public/private flags into a semicolon-separated string.
     * Private areas are stored as negative IDs.
     */
    public static function processAreas(array $selectedIds, array $publicIds): string
    {
        $processed = [];
        foreach ($selectedIds as $id) {
            $idInt = (int)$id;
            if ($idInt === 0) continue;
            if (!in_array((string)$id, $publicIds)) {
                $idInt *= -1;
            }
            $processed[] = $idInt;
        }
        return implode(';', $processed);
    }

    private const TOKEN_EXPIRY = [
        'verify_email' => '48 HOUR',
        'reset_password' => '30 MINUTE',
    ];

    public function createEmailToken(int $mID, string $type): string|false
    {
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiry = self::TOKEN_EXPIRY[$type] ?? self::TOKEN_EXPIRY['verify_email'];

        $stmt = $this->db->prepare("
            INSERT INTO EmailTokens (mID, token_hash, token_type, expires_at)
            VALUES (:mID, :tokenHash, :type, DATE_ADD(NOW(), INTERVAL $expiry))
            ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = DATE_ADD(NOW(), INTERVAL $expiry)
        ");
        $result = $stmt->execute([
            'mID' => $mID,
            'tokenHash' => $tokenHash,
            'type' => $type
        ]);

        return $result ? $token : false;
    }

    public function findByEmailToken(string $token, string $type): array|false
    {
        $tokenHash = hash('sha256', $token);

        $stmt = $this->db->prepare("
            SELECT m.*, i.iname
            FROM EmailTokens et
            JOIN Members m ON et.mID = m.mID
            LEFT JOIN Institutions i ON m.iID = i.iID
            WHERE et.token_hash = :tokenHash 
            AND et.token_type = :type 
            AND et.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['tokenHash' => $tokenHash, 'type' => $type]);
        return $stmt->fetch();
    }

    public function deleteEmailToken(int $mID, string $type): bool
    {
        $stmt = $this->db->prepare("DELETE FROM EmailTokens WHERE mID = :mID AND token_type = :type");
        return $stmt->execute(['mID' => $mID, 'type' => $type]);
    }

    public function deleteEmailTokenByToken(string $token, string $type): bool
    {
        $tokenHash = hash('sha256', $token);
        $stmt = $this->db->prepare("DELETE FROM EmailTokens WHERE token_hash = :tokenHash AND token_type = :type");
        return $stmt->execute(['tokenHash' => $tokenHash, 'type' => $type]);
    }

    public function setEmailVerified(int $mID, bool $verified = true): bool
    {
        $stmt = $this->db->prepare("UPDATE Members SET email_verified = :verified WHERE mID = :mID");
        return $stmt->execute(['verified' => $verified ? 1 : 0, 'mID' => $mID]);
    }

    public function needsEmailVerification(int $mID): bool
    {
        $stmt = $this->db->prepare("SELECT last_login FROM Members WHERE mID = :mID");
        $stmt->execute(['mID' => $mID]);
        $member = $stmt->fetch();
        return empty($member['last_login']);
    }

    public function updatePassword(int $mID, string $hashedPassword): bool
    {
        $stmt = $this->db->prepare("UPDATE Members SET pass = :pass WHERE mID = :mID");
        return $stmt->execute(['pass' => $hashedPassword, 'mID' => $mID]);
    }

    public static function validatePassword(string $password): bool
    {
        return mb_strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    public function createWithMeta(array $data, array $metaData = [], array $metaPublicFlags = [], bool $sendVerificationEmail = true): int|bool
    {
        $this->db->beginTransaction();

        try {
            $mID = $this->create($data);
            if (!$mID) {
                throw new Exception('Failed to create member');
            }

            if (!empty($metaData)) {
                $this->addMemberMeta($mID, $metaData, $metaPublicFlags);
            }

            $token = null;
            if ($sendVerificationEmail) {
                $this->setEmailVerified($mID, false);
                $token = $this->createEmailToken($mID, 'verify_email');
            }

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in createWithMeta: " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            return false;
        }

        // Email sending outside transaction - won't block DB
        if ($sendVerificationEmail && $token) {
            try {
                $verifyUrl = SITE_URL . '/verify-email?token=' . $token;
                $subject = 'Verify your ' . SITE_TITLE . ' account';
                $body = "Click the link below to verify your email address:\n\n$verifyUrl\n\nThis link expires in 48 hours.";
                $headers = ['From' => SITE_EMAIL, 'Reply-To' => SITE_EMAIL, 'X-Mailer' => 'PHP/' . phpversion()];
                mail($data['email'], $subject, $body, $headers);
            } catch (Exception $e) {
                error_log("Error sending verification email: " . $e->getMessage(), 3, LOG_PATH_TRIMMED . '/error.log');
            }
        }

        return $mID;
    }
}
