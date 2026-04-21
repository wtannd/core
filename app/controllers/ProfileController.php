<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Member;
use app\models\MemberService;
use app\models\lookups\ResearchBranch;

/**
 * ProfileController
 * 
 * Logic for registration and member profile edit/update.
 */
class ProfileController extends BaseController
{
    private MemberService $memberService;
    private ResearchBranch $branchModel;

    public function __construct()
    {
        parent::__construct();
        $this->memberService = new MemberService();
        $this->branchModel = new ResearchBranch();
    }

    /**
     * Show the registration page.
     */
    public function showRegister(): void
    {
        $errors = [];
        $message = '';
        $researchBranches = $this->branchModel->getAllBranches();
        $this->render('auth/register.php', ['errors' => $errors, 'message' => $message, 'researchBranches' => $researchBranches]);
    }

    /**
     * Show the complete profile page for ORCID users.
     */
    public function showCompleteProfile(): void
    {
        $pending = $_SESSION['pending_orcid_registration'] ?? null;
        if (!$pending) {
            header('Location: /login');
            exit;
        }

        $parts = explode(' ', $pending['name'], 2);
        $preFirstName = count($parts) > 1 ? $parts[0] : '';
        $preFamilyName = count($parts) > 1 ? $parts[1] : $parts[0];

        $errors = [];
        $researchBranches = $this->branchModel->getAllBranches();
        $this->render('auth/complete_profile.php', ['errors' => $errors, 'researchBranches' => $researchBranches]);
    }

    /**
     * Process registration request.
     */
    public function processRegister(array $postData): void
    {
        $this->validateCsrf($postData);

        $result = $this->memberService->register($postData);
        
        if ($result['success']) {
            $_SESSION['success_message'] = 'Registration successful! Please check your email to verify your account before login.';
            header('Location: /login');
            exit;
        } else {
            $errors = $result['errors'];
            $researchBranches = $this->branchModel->getAllBranches();
            $this->render('auth/register.php', ['errors' => $errors, 'researchBranches' => $researchBranches]);
        }
    }

    /**
     * Process complete profile request.
     */
    public function processCompleteProfile(array $postData): void
    {
        $this->validateCsrf($postData);

        $pending = $_SESSION['pending_orcid_registration'] ?? null;
        if (!$pending) {
            $errors = ['general' => 'No pending ORCID registration found.'];
            $this->render('auth/complete_profile.php', ['errors' => $errors]);
            exit;
        }

        $result = $this->memberService->finalizeOrcidRegistration($postData, $pending);
        
        if ($result['success']) {
            unset($_SESSION['pending_orcid_registration']);
            
            $member = $result['member'];
            if (isset($postData['remember_me'])) $this->memberService->setRememberMe((int)$member['mID']);
            $this->memberService->startSession($member);
            
            header('Location: /');
            exit;
        } else {
            $errors = $result['errors'] ?? ['general' => $result['message']];
            $this->render('auth/complete_profile.php', ['errors' => $errors]);
        }
    }

    /**
     * Show the edit profile form for the logged-in user.
     * @param array $errors from failed submission
     */
    public function editProfile(array $errors = []): void
    {
        $mID = $this->requireLogin();

        $user = $this->memberService->getFullEditableProfile($mID);
        if (!$user) {
            http_response_code(404);
            $this->render('errors/404.php');
            exit;
        }

        // Pre-populate form data for the view
        $formData = [
            'first_name' => $_POST['first_name'] ?? $user['first_name'],
            'family_name' => $_POST['family_name'] ?? $user['family_name'],
            'display_name' => $_POST['display_name'] ?? $user['display_name'],
            'pub_name' => $_POST['pub_name'] ?? $user['pub_name'],
            'iID' => $_POST['iID'] ?? $user['iID'],
            'timezone' => $_POST['timezone'] ?? $user['timezone'],
            'is_email_public' => $_POST['is_email_public'] ?? ($user['is_email_public'] ? '1' : null),
            'email' => $_POST['email'] ?? $user['email']
        ];

        // Map semicolon-separated areas back to checkbox arrays for the partial
        $formData['work_areas'] = [];
        $formData['work_areas_public'] = [];
        foreach (explode(';', $user['work_areas'] ?? '') as $wa) {
            if ($wa === '') continue;
            $id = (int)$wa;
            $absId = (string)abs($id);
            $formData['work_areas'][] = $absId;
            if ($id > 0) $formData['work_areas_public'][] = $absId;
        }
        if (isset($_POST['work_areas'])) $formData['work_areas'] = $_POST['work_areas'];
        if (isset($_POST['work_areas_public'])) $formData['work_areas_public'] = $_POST['work_areas_public'];

        $formData['interest_areas'] = [];
        $formData['interest_areas_public'] = [];
        foreach (explode(';', $user['interest_areas'] ?? '') as $ia) {
            if ($ia === '') continue;
            $id = (int)$ia;
            $absId = (string)abs($id);
            $formData['interest_areas'][] = $absId;
            if ($id > 0) $formData['interest_areas_public'][] = $absId;
        }
        if (isset($_POST['interest_areas'])) $formData['interest_areas'] = $_POST['interest_areas'];
        if (isset($_POST['interest_areas_public'])) $formData['interest_areas_public'] = $_POST['interest_areas_public'];

        $formData['mail_areas'] = [];
        foreach (explode(';', $user['mail_areas'] ?? '') as $ma) {
            if ($ma !== '') $formData['mail_areas'][] = (string)$ma;
        }
        if (isset($_POST['mail_areas'])) $formData['mail_areas'] = $_POST['mail_areas'];

        // Pre-populate metadata fields
        foreach ($user['meta'] as $key => $val) {
            $formData[$key] = $_POST[$key] ?? $val;
        }
        foreach ($user['meta_public'] as $key => $isPub) {
            $formData['meta_public'][$key] = $_POST['meta_public'][$key] ?? ($isPub ? '1' : '0');
        }

        // read-only
        $formData['formatted_id'] = $user['formatted_id'];
        $formData['ORCID'] = $user['ORCID'];
        $formData['CoreID'] = $user['CoreID'];

        $researchBranches = $this->branchModel->getAllBranches();

        $this->render('member/edit_profile.php', ['formData' => $formData, 'researchBranches' => $researchBranches]);
    }

    /**
     * Process the profile update request.
     *
     * @param array $postData
     */
    public function updateProfile(array $postData): void
    {
        $mID = $this->requireLogin();

        $this->validateCsrf($postData);

        $errors = [];
        $currentUser = $this->memberService->findUser('mID', $mID);
        
        // Get form data
        $newEmail = trim($postData['email'] ?? '');
        $currentPassword = $postData['current_password'] ?? '';
        $newPassword = $postData['new_password'] ?? '';
        $confirmPassword = $postData['confirm_password'] ?? '';
        $emailChanged = ($newEmail !== $currentUser['email']);
        $passwordChanging = !empty($newPassword);

        // Require current password for sensitive changes
        if ($emailChanged || $passwordChanging) {
            if (empty($currentPassword)) {
                $errors['current_password'] = 'Current password is required to change email or password.';
            } elseif (!password_verify($currentPassword, $currentUser['pass'])) {
                $errors['current_password'] = 'Current password is incorrect.';
            }
        }

        // Validate new email format
        if ($emailChanged) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format.';
            } else {
                $existingUser = $this->memberService->findUser('email', $newEmail);
                if ($existingUser && (int)$existingUser['mID'] !== $mID) {
                    $errors['email'] = 'This email address is already registered to another account.';
                }
            }
        }

        // Validate new password format
        if ($passwordChanging) {
            if (!MemberService::validatePassword($newPassword)) {
                $errors['new_password'] = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }
        }

        if (!empty($errors)) {
            $this->editProfile($errors); 
            return;
        }

        // Send notification to OLD email if sensitive changes made
        $sensitiveChange = $emailChanged || $passwordChanging;
        if ($sensitiveChange) {
            $oldEmail = $currentUser['email'];
            if ($passwordChanging) {
                $subject = 'Your ' . SITE_TITLE . ' password was changed';
                $body = "Your password was changed on " . SITE_TITLE . ".\n\nIf you did not make this change, please reset your password immediately.";
                $headers = ['From' => SITE_EMAIL, 'Reply-To' => SITE_EMAIL, 'X-Mailer' => 'PHP/' . phpversion()];
                mail($oldEmail, $subject, $body, $headers);
            } elseif ($emailChanged) {
                $subject = 'Your ' . SITE_TITLE . ' email was changed';
                $body = "Your email was changed to: $newEmail\n\nIf you did not make this change, please contact support immediately.";
                $headers = ['From' => SITE_EMAIL, 'Reply-To' => SITE_EMAIL, 'X-Mailer' => 'PHP/' . phpversion()];
                mail($oldEmail, $subject, $body, $headers);
            }
        }

        // Process research areas strings
        $workAreasStr = MemberService::processAreas($postData['work_areas'] ?? [], $postData['work_areas_public'] ?? []);
        $interestAreasStr = MemberService::processAreas($postData['interest_areas'] ?? [], $postData['interest_areas_public'] ?? []);
        $mailAreasStr = implode(';', $postData['mail_areas'] ?? []);

        $baseData = [
            'first_name'      => $postData['first_name'] ?? '',
            'family_name'     => $postData['family_name'] ?? '',
            'display_name'    => $postData['display_name'] ?? '',
            'pub_name'        => $postData['pub_name'] ?? '',
            'email'           => $newEmail,
            'iID'             => (int)($postData['iID'] ?? 1),
            'work_areas'      => $workAreasStr,
            'interest_areas'  => $interestAreasStr,
            'mail_areas'      => $mailAreasStr,
            'timezone'        => $postData['timezone'] ?? 'UTC',
            'is_email_public' => isset($postData['is_email_public']) ? 1 : 0
        ];

        $metaKeys = [
            'full_name', 'prefix', 'suffix', 'other_names', 'position', 
            'affiliations', 'address', 'url1', 'url2', 'education', 
            'cv', 'research_statement', 'other_interests', 'mstatus'
        ];
        $metaData = [];
        foreach ($metaKeys as $key) {
            $metaData[$key] = $postData[$key] ?? '';
        }

        if ($this->memberService->updateCompleteProfile($mID, $baseData, $metaData, $postData['meta_public'] ?? [])) {
            // Handle password change (AFTER email is updated in case email changed)
            if ($passwordChanging) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $this->memberService->updatePassword($mID, $hashedPassword);
            }

            // Handle email change with verification (AFTER password change)
            if ($emailChanged) {
                $this->memberService->setEmailVerified($mID, false);
                $token = $this->memberService->createEmailToken($mID, 'verify_email');
                if ($token) {
                    $verifyUrl = SITE_URL . '/verify-email?token=' . $token;
                    $subject = 'Verify your new ' . SITE_TITLE . ' email';
                    $body = "Your email was changed. Click to verify your new email:\n\n$verifyUrl\n\nThis link expires in 48 hours.";
                    $headers = ['From' => SITE_EMAIL, 'Reply-To' => SITE_EMAIL, 'X-Mailer' => 'PHP/' . phpversion()];
                    mail($newEmail, $subject, $body, $headers);
                }
                $_SESSION['warning_message'] = 'Profile updated! Please verify your new email.';
            } else {
                $_SESSION['success_message'] = 'Profile updated successfully!';
            }

            // Refresh session data if needed
            $_SESSION['display_name'] = $baseData['display_name'];
            if ($emailChanged) {
                $_SESSION['email'] = $newEmail;
            }
            
            // Get alphanum ID for redirect
            $updatedUser = $this->memberService->findUser('mID', $mID);
            header('Location: /member/' . $updatedUser['CoreID']);
            exit;
        } else {
            $_SESSION['error_message'] = 'Failed to update profile. Please try again.';
            $this->editProfile();
        }
    }
}
