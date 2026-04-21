<?php

declare(strict_types=1);

namespace app\models;

use PDO;

/**
 * AuthTokenService
 * 
 * Authentication for a member to verify email, and do password forgot & reset.
 */
class AuthTokenService extends AuthService
{
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

    public function updatePassword(int $mID, string $hashedPassword): bool
    {
        $stmt = $this->db->prepare("UPDATE Members SET pass = :pass WHERE mID = :mID");
        return $stmt->execute(['pass' => $hashedPassword, 'mID' => $mID]);
    }
}
