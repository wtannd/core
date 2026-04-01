<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Member;
use app\models\lookups\Institution;
use app\models\lookups\ResearchBranch;

/**
 * AuthController
 * 
 * Logic for registration and login.
 */
class AuthController
{
    private Member $memberModel;
    private Institution $institutionModel;
    private ResearchBranch $branchModel;

    public function __construct()
    {
        $this->memberModel = new Member();
        $this->institutionModel = new Institution();
        $this->branchModel = new ResearchBranch();
    }

    /**
     * Check for Remember Me cookie and log in user if valid.
     */
    public static function checkRememberMe(): void
    {
        if (isset($_SESSION['mID'])) return;

        $token = $_COOKIE['remember_token'] ?? null;
        if (!$token) return;

        $memberModel = new Member();
        $member = $memberModel->findByToken($token);

        if ($member && $member['is_active']) {
            session_regenerate_id(true);
            $_SESSION['mID'] = $member['mID'];
            $_SESSION['email'] = $member['email'];
            $_SESSION['display_name'] = $member['display_name'];
            $_SESSION['pub_name'] = $member['pub_name'];
            $_SESSION['core_id'] = $memberModel->formatAlphanumId($member['ID_alphanum'] ?? '');
            $_SESSION['mrole'] = $member['mrole'];
            $_SESSION['admin_role'] = $member['admin_role'];

            $memberModel->updateLastLogin((int)$_SESSION['mID']);
        } else {
            // Invalid token, clear cookie
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
    }

    /**
     * Show the login page.
     */
    public function showLogin(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $errors = [];
        $message = '';
        include rtrim(VIEWS_PATH, '/') . '/auth/login.php';
    }

    /**
     * Show the registration page.
     */
    public function showRegister(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        $errors = [];
        $message = '';
        $institutions = $this->institutionModel->getAllInstitutions();
        $researchBranches = $this->branchModel->getAllBranches();
        include rtrim(VIEWS_PATH, '/') . '/auth/register.php';
    }

    /**
     * Show the complete profile page for ORCID users.
     */
    public function showCompleteProfile(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        $pending = $_SESSION['pending_orcid_registration'] ?? null;
        if (!$pending) {
            header('Location: /login');
            exit;
        }

        // Split ORCID name into First and Last name for pre-filling
        $parts = explode(' ', $pending['name'], 2);
        $preFirstName = count($parts) > 1 ? $parts[0] : '';
        $preFamilyName = count($parts) > 1 ? $parts[1] : $parts[0];

        $errors = [];
        $institutions = $this->institutionModel->getAllInstitutions();
        $researchBranches = $this->branchModel->getAllBranches();
        include rtrim(VIEWS_PATH, '/') . '/auth/complete_profile.php';
    }

    /**
     * Process login request.
     */
    public function processLogin(array $postData): void
    {
        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        $email = $postData['email'] ?? '';
        $password = $postData['password'] ?? '';
        $rememberMe = isset($postData['remember_me']);

        $result = $this->login($email, $password, $rememberMe);
        
        if ($result['success']) {
            header('Location: /');
            exit;
        } else {
            $errors = ['login' => $result['message']];
            include rtrim(VIEWS_PATH, '/') . '/auth/login.php';
        }
    }

    /**
     * Process registration request.
     */
    public function processRegister(array $postData): void
    {
        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        $result = $this->register($postData);
        
        if ($result['success']) {
            $message = $result['message'];
            include rtrim(VIEWS_PATH, '/') . '/auth/login.php';
        } else {
            $errors = $result['errors'];
            $institutions = $this->institutionModel->getAllInstitutions();
            $researchBranches = $this->branchModel->getAllBranches();
            include rtrim(VIEWS_PATH, '/') . '/auth/register.php';
        }
    }

    /**
     * Process complete profile request.
     */
    public function processCompleteProfile(array $postData): void
    {
        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        $result = $this->finalizeOrcidRegistration($postData);
        
        if ($result['success']) {
            header('Location: /');
            exit;
        } else {
            $errors = $result['errors'] ?? ['general' => $result['message']];
            include rtrim(VIEWS_PATH, '/') . '/auth/complete_profile.php';
        }
    }

    /**
     * Handle registration.
     *
     * @param array $data
     * @return array Array with success status and message/errors.
     */
    public function register(array $data): array
    {
        $errors = $this->validateRegistration($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Check for existing email
        if ($this->memberModel->findByEmail($data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'Email already registered.']];
        }

        // Check for strong password
        if (!$this->validatePassword($data['password'])) {
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
        $workAreasStr = Member::processAreas($data['work_areas'] ?? [], $data['work_areas_public'] ?? []);
        $interestAreasStr = Member::processAreas($data['interest_areas'] ?? [], $data['interest_areas_public'] ?? []);
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

        $mID = $this->memberModel->create($memberData);
        if ($mID) {
            // Extract and save optional metadata
            $this->saveMemberMeta((int)$mID, $data);

            return ['success' => true, 'message' => 'Registration successful. Please log in.', 'mID' => $mID];
        }

        return ['success' => false, 'errors' => ['general' => 'Registration failed. Please try again.']];
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
        $member = $this->memberModel->findByEmail($email);

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

        // Handle Remember Me
        if ($rememberMe) {
            $token = bin2hex(random_bytes(32));
            if ($this->memberModel->updateToken((int)$member['mID'], $token)) {
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

        // Start session and store member info
        $_SESSION['mID'] = $member['mID'];
        $_SESSION['email'] = $member['email'];
        $_SESSION['display_name'] = $member['display_name'];
        $_SESSION['pub_name'] = $member['pub_name'];
        $_SESSION['core_id'] = $this->memberModel->formatAlphanumId($member['ID_alphanum'] ?? '');
        $_SESSION['mrole'] = $member['mrole'];
        $_SESSION['admin_role'] = $member['admin_role'];

        $this->memberModel->updateLastLogin((int)$_SESSION['mID']);

        return ['success' => true, 'member' => $member];
    }

    /**
     * Handle logout.
     */
    public function logout(): void
    {
        if (isset($_SESSION['mID'])) {
            $this->memberModel->updateToken((int)$_SESSION['mID'], null);
        }

        // Clear session
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        // Clear remember_token cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);

        header('Location: /login');
        exit;
    }

    /**
     * Handle ORCID login.
     */
    public function orcidLogin(): void
    {
        $client_id = ORCID_CLIENT_ID;
        $redirect_uri = ORCID_REDIRECT_URI;
        $orcid_url = "https://orcid.org/oauth/authorize?client_id=$client_id&response_type=code&scope=/authenticate&redirect_uri=$redirect_uri";
        
        header("Location: $orcid_url");
        exit;
    }

    /**
     * Handle ORCID callback logic.
     *
     * @param string $code
     * @return array
     */
    public function orcidCallback(string $code): array
    {
        $client_id     = ORCID_CLIENT_ID;
        $client_secret = ORCID_CLIENT_SECRET;
        $redirect_uri  = ORCID_REDIRECT_URI;

        $token_url = "https://orcid.org/oauth/token"; 
        $post_data = [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirect_uri
        ];

        $ch = curl_init($token_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code !== 200) {
            return ['success' => false, 'message' => 'Failed to exchange code for token.'];
        }

        $data = json_decode($response, true);
        $orcid = $data['orcid'];
        $name  = $data['name'] ?? 'ORCID User';

        // Check if user is already logged in (Linking ORCID to existing account)
        if (isset($_SESSION['mID'])) {
            if ($this->memberModel->updateOrcid((int)$_SESSION['mID'], $orcid)) {
                $_SESSION['success_message'] = 'ORCID successfully linked.';
            } else {
                $_SESSION['error_message'] = 'Failed to link ORCID. It may already be associated with another account.';
            }
            header('Location: /profile/edit');
            exit;
        }

        $member = $this->memberModel->findByOrcid($orcid);

        if (!$member) {
            // New ORCID user - store data in session for profile completion
            $_SESSION['pending_orcid_registration'] = [
                'orcid' => $orcid,
                'name'  => $name
            ];
            return ['success' => true, 'pending' => true];
        }

        // Existing user - Log the user in
        $this->setRememberMe((int)$member['mID']);

        $_SESSION['mID'] = $member['mID'];
        $_SESSION['email'] = $member['email'] ?? '';
        $_SESSION['display_name'] = $member['display_name'];
        $_SESSION['pub_name'] = $member['pub_name'];
        $_SESSION['core_id'] = $this->memberModel->formatAlphanumId($member['ID_alphanum'] ?? '');
        $_SESSION['mrole'] = $member['mrole'];
        $_SESSION['admin_role'] = $member['admin_role'];

        $this->memberModel->updateLastLogin((int)$_SESSION['mID']);

        return ['success' => true];
    }

    /**
     * Finalize registration for an ORCID user.
     * 
     * @param array $data
     * @return array
     */
    public function finalizeOrcidRegistration(array $data): array
    {
        // 1. Verify the CSRF token
        if (!isset($data['csrf_token']) || $data['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            return ['success' => false, 'message' => 'CSRF token validation failed.'];
        }

        // 2. Retrieve ORCID iD and Name from $_SESSION['pending_orcid_registration']
        $pending = $_SESSION['pending_orcid_registration'] ?? null;
        if (!$pending) {
            return ['success' => false, 'message' => 'No pending ORCID registration found.'];
        }

        // 3. Validate email
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'errors' => ['email' => 'Valid email is required.']];
        }
        if ($this->memberModel->findByEmail($data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'This email is already registered.']];
        }

        // 4. Hash password
        $hashedPassword = null;
        if (!empty($data['password'])) {
            if (!$this->validatePassword($data['password'])) {
                return [
                    'success' => false, 
                    'errors' => ['password' => 'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character.']
                ];
            }
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        // 5. Insert the new Member into the database
        $parts = explode(' ', $pending['name'], 2);
        $firstName = $data['first_name'] ?? (count($parts) > 1 ? $parts[0] : '');
        $familyName = $data['family_name'] ?? (count($parts) > 1 ? $parts[1] : $parts[0]);

        // Process work and interest areas
        $workAreasStr = Member::processAreas($data['work_areas'] ?? [], $data['work_areas_public'] ?? []);
        $interestAreasStr = Member::processAreas($data['interest_areas'] ?? [], $data['interest_areas_public'] ?? []);

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

        $mID = $this->memberModel->create($memberData);
        if (!$mID) {
            return ['success' => false, 'message' => 'Failed to finalize registration.'];
        }

        // Extract and save optional metadata
        $this->saveMemberMeta((int)$mID, $data);

        // 6. Clear the pending session data
        unset($_SESSION['pending_orcid_registration']);

        // 7. Log the user in and redirect to dashboard
        $member = $this->memberModel->findById((int)$mID);

        $this->setRememberMe((int)$member['mID']);

        $_SESSION['mID'] = $member['mID'];
        $_SESSION['email'] = $member['email'];
        $_SESSION['display_name'] = $member['display_name'];
        $_SESSION['pub_name'] = $member['pub_name'];
        $_SESSION['core_id'] = $this->memberModel->formatAlphanumId($member['ID_alphanum'] ?? '');
        $_SESSION['mrole'] = $member['mrole'];
        $_SESSION['admin_role'] = $member['admin_role'];

        $this->memberModel->updateLastLogin((int)$_SESSION['mID']);

        header('Location: /');
        exit;
    }

    /**
     * Helper to set Remember Me token and cookie.
     *
     * @param int $mID
     * @return void
     */
    private function setRememberMe(int $mID): void
    {
        $token = bin2hex(random_bytes(32));
        if ($this->memberModel->updateToken($mID, $token)) {
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

    /**
     * Validate strong password requirements.
     *
     * @param string $password
     * @return bool
     */
    private function validatePassword(string $password): bool
    {
        return mb_strlen($password) >= 8
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    /**
     * Basic validation for registration data.
     */
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
     * Extract and save member metadata from registration/profile data.
     */
    private function saveMemberMeta(int $mID, array $data): void
    {
        $metaKeys = [
            'full_name', 'other_names', 'prefix', 'suffix', 'position',
            'affiliations', 'address', 'url1', 'url2', 'education',
            'cv', 'research_statement', 'other_interests', 'mstatus'
        ];
        $metaData = [];
        foreach ($metaKeys as $key) {
            if (!empty($data[$key])) {
                $metaData[$key] = $data[$key];
            }
        }
        if (!empty($metaData)) {
            $this->memberModel->addMemberMeta($mID, $metaData, $data['meta_public'] ?? []);
        }
    }
}
