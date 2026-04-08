<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\FeedDocument;
use app\models\DocumentRepository;
use app\models\Member;
use app\models\lookups\Institution;
use app\models\lookups\ResearchBranch;

/**
 * MemberController
 * 
 * Handles public member profile requests and personal profile edits.
 */
class MemberController
{
    private Member $memberModel;
    private Institution $institutionModel;
    private ResearchBranch $branchModel;
    private DocumentRepository $docRepo;

    public function __construct()
    {
        $this->memberModel = new Member();
        $this->institutionModel = new Institution();
        $this->branchModel = new ResearchBranch();
        $this->docRepo = new DocumentRepository();
    }

    /**
     * Show the edit profile form for the logged-in user.
     * @param array $stickyData Optional data from failed submission
     */
    public function editProfile(array $stickyData = []): void
    {
        $mID = (int)($_SESSION['mID'] ?? 0);
        if ($mID === 0) {
            header('Location: /login');
            exit;
        }

        $user = $this->memberModel->getFullEditableProfile($mID);
        if (!$user) {
            http_response_code(404);
            include VIEWS_PATH_TRIMMED . '/errors/404.php';
            exit;
        }

        // Pre-populate form data for the view
        $formData = [
            'first_name' => $user['first_name'],
            'family_name' => $user['family_name'],
            'display_name' => $user['display_name'],
            'pub_name' => $user['pub_name'],
            'iID' => $user['iID'],
            'timezone' => $user['timezone'],
            'is_email_public' => $user['is_email_public'] ? '1' : null,
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

        $formData['interest_areas'] = [];
        $formData['interest_areas_public'] = [];
        foreach (explode(';', $user['interest_areas'] ?? '') as $ia) {
            if ($ia === '') continue;
            $id = (int)$ia;
            $absId = (string)abs($id);
            $formData['interest_areas'][] = $absId;
            if ($id > 0) $formData['interest_areas_public'][] = $absId;
        }

        $formData['mail_areas'] = [];
        foreach (explode(';', $user['mail_areas'] ?? '') as $ma) {
            if ($ma !== '') $formData['mail_areas'][] = (string)$ma;
        }

        // Pre-populate metadata fields
        foreach ($user['meta'] as $key => $val) {
            $formData[$key] = $val;
        }
        foreach ($user['meta_public'] as $key => $isPub) {
            if ($isPub) $formData['meta_public'][$key] = '1';
        }

        $institutions = $this->institutionModel->getAllInstitutions();
        $researchBranches = $this->branchModel->getAllBranches();

        include VIEWS_PATH_TRIMMED . '/member/edit_profile.php';
    }

    /**
     * Process the profile update request.
     *
     * @param array $postData
     */
    public function updateProfile(array $postData): void
    {
        $mID = (int)($_SESSION['mID'] ?? 0);
        if ($mID === 0) exit;

        if (!isset($postData['csrf_token']) || $postData['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            include VIEWS_PATH_TRIMMED . '/errors/403.php';
            exit;
        }

        $errors = [];
        $currentUser = $this->memberModel->findById($mID);
        
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
                $existingUser = $this->memberModel->findByEmail($newEmail);
                if ($existingUser && (int)$existingUser['mID'] !== $mID) {
                    $errors['email'] = 'This email address is already registered to another account.';
                }
            }
        }

        // Validate new password format
        if ($passwordChanging) {
            if (!Member::validatePassword($newPassword)) {
                $errors['new_password'] = 'Password must be at least 8 characters with uppercase, lowercase, number, and special character.';
            } elseif ($newPassword !== $confirmPassword) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }
        }

        if (!empty($errors)) {
            $this->editProfile($postData); 
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
        $workAreasStr = Member::processAreas($postData['work_areas'] ?? [], $postData['work_areas_public'] ?? []);
        $interestAreasStr = Member::processAreas($postData['interest_areas'] ?? [], $postData['interest_areas_public'] ?? []);
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

        if ($this->memberModel->updateCompleteProfile($mID, $baseData, $metaData, $postData['meta_public'] ?? [])) {
            // Handle password change (AFTER email is updated in case email changed)
            if ($passwordChanging) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $this->memberModel->updatePassword($mID, $hashedPassword);
                // Do NOT change email_verified
            }

            // Handle email change with verification (AFTER password change)
            if ($emailChanged) {
                $this->memberModel->setEmailVerified($mID, false);
                $token = $this->memberModel->createEmailToken($mID, 'verify_email');
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
            $updatedUser = $this->memberModel->findById($mID);
            header('Location: /member/' . $updatedUser['ID_alphanum']);
            exit;
        } else {
            $_SESSION['error_message'] = 'Failed to update profile. Please try again.';
            $this->editProfile();
        }
    }

    /**
     * Display a member's public profile.
     *
     * @param string $ID_alphanum
     */
    public function show(string $ID_alphanum): void
    {
        $cleanId = ltrim(str_replace('-', '', strtoupper($ID_alphanum)), '0');

        if (empty($cleanId)) {
            http_response_code(400);
            $errorMessage = "The provided ID is not a valid CORE-ID.";
            include VIEWS_PATH_TRIMMED . '/errors/400.php';
            exit;
        }

        // 1. Call model to get user data - fully formatted by the model (Fat Model)
        $member = $this->memberModel->getPublicProfileByAlphaId($cleanId);

        if (!$member) {
            http_response_code(404);
            include VIEWS_PATH_TRIMMED . '/errors/404.php';
            exit;
        }

        // 2. Handle email visibility (Controller-level privacy check)
        if (!(int)$member['is_email_public']) {
            unset($member['email']);
        }

        // 3. Fetch authored documents with pagination
        $mRole = $_SESSION['mrole'] ?? GUEST_ROLE;
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;
        $offset = ($currentPage - 1) * $perPage;

        $authoredResult = $this->docRepo->getDocumentsByAuthor((int)$member['mID'], (int)$mRole, $perPage, $offset);
        $authoredDocs = $authoredResult['results'];
        $totalAuthored = $authoredResult['total'];
        $totalPages = max(1, (int)ceil($totalAuthored / $perPage));

        // Pass sanitized data to the view
        include VIEWS_PATH_TRIMMED . '/member/profile.php';
    }

    /**
     * Search members by name.
     * GET /members?q=smith&page=N
     */
    public function search(): void
    {
        $query = trim($_GET['q'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $result = $this->memberModel->searchMembers($query, $perPage, $offset);
        $members = $result['results'];
        $totalResults = $result['total'];
        $totalPages = max(1, (int)ceil($totalResults / $perPage));

        $buildPageUrl = function (int $p) use ($query) {
            return '/members?q=' . urlencode($query) . '&page=' . $p;
        };

        include VIEWS_PATH_TRIMMED . '/member/search_results.php';
    }
}

