`<?php

declare(strict_types=1);

namespace app\models;

use PDOException;
use Exception;

/**
 * MemberService
 *
 * Handles member profile creation/registration/update for the Members table.
 */
class MemberService extends AuthService
{
    /**
     * Set the alphanumeric ID (CORE-ID) for a member.
     */
    private function setCoreID(int $mID): void
    {
        $stmt = $this->db->prepare("UPDATE Members SET CoreID = UPPER(CONV(:mID1, 10, 36)) WHERE mID = :mID2");
        $stmt->execute(['mID1' => $mID, 'mID2' => $mID]);
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
            $this->setCoreID($mID);
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
     * Fetch a full editable member profile including all metadata and public flags.
     *
     * @param int $mID
     * @return array|bool
     */
    public function getFullEditableProfile(int $mID): array|bool
    {
        $member = $this->findUser('mID', $mID);
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
        $member['formatted_id'] = self::formatCoreID($member['CoreID']);

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
     * Update a member's information in main table 'Members'.
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

    // Atomic creation of a member's full new profile
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

    /**
     * Register a new member.
     *
     * @param array $data Registration data
     * @return array Array with success status and message/errors.
     */
    public function register(array $data): array
    {
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check for existing email
        $data['email'] = trim(strtolower($data['email']));
        if ($this->findUser('email', $data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'Email already registered.']];
        }

        // Check for strong password
        if (!self::validatePassword($data['password'])) {
            return [
                'success' => false, 
                'errors' => ['password' => 'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.']
            ];
        }

        // Prepare member data
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $firstName = $data['first_name'] ?? '';
        $familyName = $data['family_name'];
        if (empty($data['display_name'])) {
            $displayName = ($firstName === '' ? $familyName : $firstName . ' ' . $familyName);
        } else {
            $displayName = $data['display_name'];
        }
        if (empty($data['pub_name'])) {
            $pubName = ($firstName === '' ? $familyName : substr($firstName, 0, 1) . '. ' . $familyName);
        } else {
            $pubName = $data['pub_name'];
        }
        
        // Process work and interest areas
        $workAreasStr = self::processAreas($data['work_areas'] ?? [], $data['work_areas_public'] ?? []);
        $interestAreasStr = self::processAreas($data['interest_areas'] ?? [], $data['interest_areas_public'] ?? []);
        $mailAreasStr = implode(';', $data['mail_areas'] ?? []);

        $memberData = [
            'family_name'      => $familyName,
            'first_name'       => $firstName,
            'display_name'     => $displayName,
            'pub_name'         => $pubName,
            'email'            => $data['email'],
            'pass'             => $hashedPassword,
            'iID'              => (int)($data['iID'] ?? 1),
            'work_areas'       => $workAreasStr,
            'interest_areas'   => $interestAreasStr,
            'mail_areas'       => $mailAreasStr,
            'timezone'         => $data['timezone'] ?? 'UTC',
            'is_email_public'  => (int)(isset($data['is_email_public']) ? 1 : 0)
        ];

        $metaKeys = ['full_name', 'other_names', 'prefix', 'suffix', 'position', 'affiliations', 'address', 'url1', 'url2', 'education', 'cv', 'research_statement', 'other_interests', 'mstatus'];
        $metaData = [];
        foreach ($metaKeys as $key) {
            if (!empty($data[$key])) {
                $metaData[$key] = $data[$key];
            }
        }

        $mID = $this->createWithMeta($memberData, $metaData, $data['meta_public'] ?? [], true);
        if ($mID) {
            return ['success' => true, 'message' => 'Registration successful. Please log in.', 'mID' => $mID];
        }

        return ['success' => false, 'errors' => ['general' => 'Registration failed. Please try again.']];
    }

    /**
     * Finalize registration for an ORCID user.
     *
     * @param array $data
     * @param array $pending ORCID pending data from session
     * @return array
     */
    public function finalizeOrcidRegistration(array $data, array $pending): array
    {
        // Validate email
        $data['email'] = trim(strtolower($data['email'] ?? ''));
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'errors' => ['email' => 'Valid email is required.']];
        }
        if ($this->findUser('email', $data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'This email is already registered.']];
        }

        // Hash password
        $hashedPassword = null;
        if (!empty($data['password'])) {
            if (!self::validatePassword($data['password'])) {
                return [
                    'success' => false, 
                    'errors' => ['password' => 'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.']
                ];
            }
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // Prepare member data
        $parts = explode(' ', $pending['name'], 2);
        $firstName = $data['first_name'] ?? (count($parts) > 1 ? $parts[0] : '');
        $familyName = $data['family_name'] ?? (count($parts) > 1 ? $parts[1] : $parts[0]);

        // Process work and interest areas
        $workAreasStr = self::processAreas($data['work_areas'] ?? [], $data['work_areas_public'] ?? []);
        $interestAreasStr = self::processAreas($data['interest_areas'] ?? [], $data['interest_areas_public'] ?? []);

        $memberData = [
            'ORCID'            => $pending['orcid'],
            'family_name'      => $familyName,
            'first_name'       => $firstName,
            'display_name'     => $data['display_name'] ?? ($firstName === '' ? $familyName : $firstName . ' ' . $familyName),
            'pub_name'         => $data['pub_name'] ?? ($firstName === '' ? $familyName : substr($firstName, 0, 1) . '. ' . $familyName),
            'email'            => $data['email'],
            'pass'             => $hashedPassword,
            'iID'              => (int)($data['iID'] ?? 1),
            'work_areas'       => $workAreasStr,
            'interest_areas'   => $interestAreasStr,
            'timezone'         => $data['timezone'] ?? 'UTC',
            'is_email_public'  => (int)(isset($data['is_email_public']) ? 1 : 0)
        ];

        $metaKeys = ['full_name', 'other_names', 'prefix', 'suffix', 'position', 'affiliations', 'address', 'url1', 'url2', 'education', 'cv', 'research_statement', 'other_interests', 'mstatus'];
        $metaData = [];
        foreach ($metaKeys as $key) {
            if (!empty($data[$key])) {
                $metaData[$key] = $data[$key];
            }
        }

        $mID = $this->createWithMeta($memberData, $metaData, $data['meta_public'] ?? [], true);
        if (!$mID) {
            return ['success' => false, 'message' => 'Failed to finalize registration.'];
        }

        $member = $this->findUser('mID', (int)$mID);
        return ['success' => true, 'member' => $member];
    }

    private function validateRegistration(array $data): array
    {
        $errors = [];
        if (empty($data['family_name'])) $errors['family_name'] = 'Family name is required.';
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Valid email is required.';
        if (empty($data['password']) || strlen($data['password']) < 8) $errors['password'] = 'Password must be at least 8 characters.';
        
        if (!empty($data['password']) && ($data['password'] !== ($data['confirm_password'] ?? ''))) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        return $errors;
    }

    /**
     * Convert selected area IDs with public/private flags into a semicolon-separated string.
     * Private areas are stored as negative IDs.
     */
    public static function processAreas(array $selectedIds, array $publicIds): string
    {
        $processed = [];
        foreach (array_unique($selectedIds) as $id) {
            $idInt = (int)$id;
            if ($idInt === 0) continue;
            if (!in_array((string)$id, $publicIds)) {
                $idInt *= -1;
            }
            $processed[] = $idInt;
        }
        return implode(';', $processed);
    }
}
