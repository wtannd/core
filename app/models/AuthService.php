<?PHP

declare(strict_types=1);

namespace app\models;

use config\Database;
use PDO;
use PDOException;
use Exception;

/**
 * AuthService
 * 
 * Authentication for a member to login, ORCID-login, and logout.
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

    public function needsEmailVerification(int $mID): bool
    {
        $stmt = $this->db->prepare("SELECT last_login FROM Members WHERE mID = :mID");
        $stmt->execute(['mID' => $mID]);
        $member = $stmt->fetch();
        return empty($member['last_login']);
    }

    public static function validatePassword(string $password): bool
    {
        return mb_strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    /**
     * Format raw CoreID to XXX-XXX-XXX (padded to 9 chars) called Core_ID.
     */
    public static function formatCoreID(string $coreId): string
    {
        $padded = str_pad(strtoupper(trim($coreId)), 9, '0', STR_PAD_LEFT);
        return substr($padded, 0, 3) . '-' . substr($padded, 3, 3) . '-' . substr($padded, 6, 3);
    }

    public static function fillSession(array $member): void
    {
        $_SESSION['mID'] = $member['mID'];
        $_SESSION['is_good'] = $member['is_good'];
        $_SESSION['email'] = $member['email'];
        $_SESSION['display_name'] = $member['display_name'];
        $_SESSION['pub_name'] = $member['pub_name'];
        $_SESSION['Core_ID'] = self::formatCoreID($member['CoreID'] ?? '');
        $_SESSION['mrole'] = $member['mrole'];
        $_SESSION['admin_role'] = $member['admin_role'];
    }

    public function startSession(array $member): void
    {
        self::fillSession($member);
        $this->updateLastLogin((int)$_SESSION['mID']);
    }

    /**
     * Helper to set Remember Me token and cookie.
     *
     * @param int $mID
     * @return void
     */
    public function setRememberMe(int $mID): void
    {
        $token = bin2hex(random_bytes(32));
        if ($this->updateToken($mID, $token)) {
            $expiry = time() + REMEMBER_ME_DURATION;
            setcookie('remember_token', $token, [
                'expires' => $expiry,
                'path' => '/',
                'domain' => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }
}
