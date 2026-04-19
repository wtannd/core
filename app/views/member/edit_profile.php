<?php
declare(strict_types=1);
/**
 * Edit Member Profile View
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Edit Profile</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include VIEWS_PATH_TRIMMED . '/partials/header.php'; ?>

    <main>
        <div class="main-container profile-container">
            <h1>Edit Your Profile</h1>
<a href="/member/<?php echo htmlspecialchars($formData['CoreID']); ?>" class="edit-profile-btn">View Public Profile</a>
            <?php 
            if (isset($_SESSION['warning_message'])) {
                echo '<div class="alert alert-warning">' . htmlspecialchars($_SESSION['warning_message']) . '</div>';
                unset($_SESSION['warning_message']);
            }
            ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="/profile/edit" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <!-- CORE-ID (Read-only) -->
                <div class="form-group">
                    <label>CORE-ID:</label>
                    <div class="id-badge"><?php echo htmlspecialchars($formData['formatted_id']); ?></div>
                    <p><small class="text-muted">Your unique CORE-ID cannot be modified.</small></p>
                </div>

                <!-- ORCID (Read-only if present) -->
                <div class="form-group">
                    <label for="orcid">ORCID iD:</label>
                    <input type="text" id="orcid" value="<?php echo htmlspecialchars($formData['ORCID'] ?? 'Not linked'); ?>" disabled class="form-control">
                    <?php if (empty($formData['ORCID'])): ?>
                        <p><small class="text-muted"><a href="/orcid_login">Link your ORCID profile</a></small></p>
                    <?php endif; ?>
                </div>

                <!-- Base Fields -->
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="family_name">Family Name (Required):</label>
                    <input type="text" id="family_name" name="family_name" required value="<?php echo htmlspecialchars($formData['family_name'] ?? ''); ?>">
                </div>

                <!-- Email & Privacy -->
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required class="form-control">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_email_public" name="is_email_public" value="1" <?php echo (isset($formData['is_email_public']) && $formData['is_email_public'] == '1') ? 'checked' : ''; ?>>
                        <label for="is_email_public">Make my email address public</label>
                    </div>
                    <p><small class="text-muted">Changing this will update the email you use to log in. You must enter your current password to change email or password.</small></p>
                </div>

                <!-- Password Change -->
                <div class="form-group">
                    <label for="current_password">Current Password (required to change email or password):</label>
                    <input type="password" id="current_password" name="current_password" class="form-control">
                </div>

                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" class="form-control">
                    <small class="form-hint">At least 8 characters with uppercase, lowercase, number, and special character.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                </div>

                <!-- Shared Form Details Partial -->
                <?php include VIEWS_PATH_TRIMMED . '/partials/profile_details_form.php'; ?>

                <div class="form-submit">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="/member/<?php echo $formData['CoreID']; ?>" class="btn btn-secondary">Cancel</a> 
                </div>
            </form>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
