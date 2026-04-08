<?php

declare(strict_types=1);

namespace app\models;

use config\Database;
use config\Config;
use PDO;
use Exception;

/**
 * AuthService
 * 
 * Authentication business logic - handles registration, login, and ORCID registration.
 */
class AuthService
{
    private Member $memberModel;

    public function __construct()
    {
        $this->memberModel = new Member();
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
        if ($this->memberModel->findByEmail($data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'Email already registered.']];
        }

        // Check for strong password
        if (!Member::validatePassword($data['password'])) {
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

        $metaKeys = ['full_name', 'other_names', 'prefix', 'suffix', 'position', 'affiliations', 'address', 'url1', 'url2', 'education', 'cv', 'research_statement', 'other_interests', 'mstatus'];
        $metaData = [];
        foreach ($metaKeys as $key) {
            if (!empty($data[$key])) {
                $metaData[$key] = $data[$key];
            }
        }

        $mID = $this->memberModel->createWithMeta($memberData, $metaData, $data['meta_public'] ?? [], true);
        if ($mID) {
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
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email.'];
        }

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

        // Check if email verification is required (user has never logged in before)
        if ($this->memberModel->needsEmailVerification((int)$member['mID']) && empty($member['email_verified'])) {
            return ['success' => false, 'message' => 'Please verify your email first.'];
        }

        // Return member data for session creation
        return ['success' => true, 'member' => $member];
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
        if ($this->memberModel->findByEmail($data['email'])) {
            return ['success' => false, 'errors' => ['email' => 'This email is already registered.']];
        }

        // Hash password
        $hashedPassword = null;
        if (!empty($data['password'])) {
            if (!Member::validatePassword($data['password'])) {
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

        $metaKeys = ['full_name', 'other_names', 'prefix', 'suffix', 'position', 'affiliations', 'address', 'url1', 'url2', 'education', 'cv', 'research_statement', 'other_interests', 'mstatus'];
        $metaData = [];
        foreach ($metaKeys as $key) {
            if (!empty($data[$key])) {
                $metaData[$key] = $data[$key];
            }
        }

        $mID = $this->memberModel->createWithMeta($memberData, $metaData, $data['meta_public'] ?? [], true);
        if (!$mID) {
            return ['success' => false, 'message' => 'Failed to finalize registration.'];
        }

        $member = $this->memberModel->findById((int)$mID);
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
}
