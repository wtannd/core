<?php

declare(strict_types=1);

namespace app\controllers;

use app\models\Document;
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
    private Document $documentModel;

    public function __construct()
    {
        $this->memberModel = new Member();
        $this->institutionModel = new Institution();
        $this->branchModel = new ResearchBranch();
        $this->documentModel = new Document();
    }

    /**
     * Show the edit profile form for the logged-in user.
     */
    public function editProfile(): void
    {
        $mID = (int)($_SESSION['mID'] ?? 0);
        if ($mID === 0) {
            header('Location: /login');
            exit;
        }

        $user = $this->memberModel->getFullEditableProfile($mID);
        if (!$user) {
            http_response_code(404);
            include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
            exit;
        }

        // Pre-populate $_POST for the partial to auto-fill
        $_POST['first_name'] = $user['first_name'];
        $_POST['family_name'] = $user['family_name'];
        $_POST['display_name'] = $user['display_name'];
        $_POST['pub_name'] = $user['pub_name'];
        $_POST['iID'] = $user['iID'];
        $_POST['timezone'] = $user['timezone'];
        $_POST['is_email_public'] = $user['is_email_public'] ? '1' : null;

        // Map semicolon-separated areas back to checkbox arrays for the partial
        $_POST['work_areas'] = [];
        $_POST['work_areas_public'] = [];
        foreach (explode(';', $user['work_areas'] ?? '') as $wa) {
            if ($wa === '') continue;
            $id = (int)$wa;
            $absId = (string)abs($id);
            $_POST['work_areas'][] = $absId;
            if ($id > 0) $_POST['work_areas_public'][] = $absId;
        }

        $_POST['interest_areas'] = [];
        $_POST['interest_areas_public'] = [];
        foreach (explode(';', $user['interest_areas'] ?? '') as $ia) {
            if ($ia === '') continue;
            $id = (int)$ia;
            $absId = (string)abs($id);
            $_POST['interest_areas'][] = $absId;
            if ($id > 0) $_POST['interest_areas_public'][] = $absId;
        }

        $_POST['mail_areas'] = [];
        foreach (explode(';', $user['mail_areas'] ?? '') as $ma) {
            if ($ma !== '') $_POST['mail_areas'][] = (string)$ma;
        }

        // Pre-populate metadata fields
        foreach ($user['meta'] as $key => $val) {
            $_POST[$key] = $val;
        }
        foreach ($user['meta_public'] as $key => $isPub) {
            if ($isPub) $_POST['meta_public'][$key] = '1';
        }

        $institutions = $this->institutionModel->getAllInstitutions();
        $researchBranches = $this->branchModel->getAllBranches();

        include rtrim(VIEWS_PATH, '/') . '/member/edit_profile.php';
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
            include rtrim(VIEWS_PATH, '/') . '/errors/403.php';
            exit;
        }

        $errors = [];
        $currentUser = $this->memberModel->findById($mID);
        
        // Secure Email Update Logic
        $newEmail = $postData['email'] ?? '';
        if ($newEmail !== $currentUser['email']) {
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format.';
            } else {
                $existingUser = $this->memberModel->findByEmail($newEmail);
                if ($existingUser && (int)$existingUser['mID'] !== $mID) {
                    $errors['email'] = 'This email address is already registered to another account.';
                }
            }
        }

        if (!empty($errors)) {
            // Re-show form with errors
            // Need to merge postData back into pre-population for sticky form
            $_POST = array_merge($_POST, $postData); 
            $this->editProfile(); 
            return;
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
            // Refresh session data if needed
            $_SESSION['display_name'] = $baseData['display_name'];
            if ($newEmail !== $currentUser['email']) {
                $_SESSION['email'] = $newEmail;
            }
            
            // Get alphanum ID for redirect
            $updatedUser = $this->memberModel->findById($mID);
            $_SESSION['success_message'] = "Profile updated successfully!";
            header('Location: /profile?id=' . $updatedUser['ID_alphanum']);
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
            include rtrim(VIEWS_PATH, '/') . '/errors/400.php';
            exit;
        }

        // 1. Call model to get user data - fully formatted by the model (Fat Model)
        $member = $this->memberModel->getPublicProfileByAlphaId($cleanId);

        if (!$member) {
            http_response_code(404);
            include rtrim(VIEWS_PATH, '/') . '/errors/404.php';
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

        $authoredResult = $this->documentModel->getDocumentsByAuthor((int)$member['mID'], (int)$mRole, $perPage, $offset);
        $authoredDocs = $authoredResult['results'];
        $totalAuthored = $authoredResult['total'];
        $totalPages = max(1, (int)ceil($totalAuthored / $perPage));

        // Pass sanitized data to the view
        include rtrim(VIEWS_PATH, '/') . '/member/profile.php';
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

        include rtrim(VIEWS_PATH, '/') . '/member/search_results.php';
    }
}

