<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;
use PDOException;
use Exception;

/**
 * AuthService
 * 
 * Authentication for a member to login, ORCID-login, logout, verify email, and reset password.
 */
class AuthService
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Handle login.
     *
     * @param string $email
     * @param string $password
     * @param bool $rememberMe
     * @return array Array with success status and member data or errors.
     */
    public function login(string $email, string $password, bool $rememberMe = false): array
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email.'];
        }

        $member = $this->findUser('email', $email);
 
        if (!$member) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        // Check for ORCID-only accounts
        if (empty($member['pass'])) {
            return [
                'success' => false, 
                'message' => 'This account is linked to ORCID and does not have a local password. Please use the Sign in with ORCID button.'
            ];
        }

        if (!password_verify($password, $member['pass'])) {
            return ['success' => false, 'message' => 'Invalid email or password.'];
        }

        if (!$member['is_active']) {
            return ['success' => false, 'message' => 'Account is inactive.'];
        }

        // Check if email verification is required (user has never logged in before)
        if ($this->needsEmailVerification((int)$member['mID']) && empty($member['email_verified'])) {
            return ['success' => false, 'message' => 'Please verify your email first.'];
        }

        // Return member data for session creation
        return ['success' => true, 'member' => $member];
    }

    /**
     * Find a user with simplified member info by flexible column lookup.
     *
     * @param string $column One of: 'mID', 'email', 'token', 'ORCID', 'CoreID'
     * @param mixed $value
     * @return array|bool
     */
    public function findUser(string $column, mixed $value): array|bool
    {
        $allowed = ['mID', 'email', 'token', 'ORCID', 'CoreID'];
        if (!in_array($column, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid search column.");
        }

        $sql = "SELECT * FROM Members WHERE $column = :value LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['value' => $value]);
        return $stmt->fetch();
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
     */
    public function updateToken(int $mID, ?string $token): bool
    {
        $stmt = $this->db->prepare("UPDATE Members SET token = :token WHERE mID = :mID");
        return $stmt->execute(['token' => $token, 'mID' => $mID]);
    }

    /**
     * Link an ORCID ID to an existing member.
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

    protected const TOKEN_EXPIRY = [
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

}
