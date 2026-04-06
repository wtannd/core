<?php
declare(strict_types=1);
/**
 * Edit Member Profile View
 * 
 * An amalgamation of register.php and profile.php for logged-in users.
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
<a href="/member/<?php echo htmlspecialchars($user['ID_alphanum']); ?>" class="edit-profile-btn">View Public Profile</a>
            <?php 
            if (isset($_SESSION['success_message'])) {
                echo '<div class="alert alert-info">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
                unset($_SESSION['success_message']);
            }
            if (isset($_SESSION['error_message'])) {
                echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
                unset($_SESSION['error_message']);
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
                    <?php 
                        $padded = str_pad(strtoupper($user['ID_alphanum']), 9, '0', STR_PAD_LEFT);
                        $formattedId = substr($padded, 0, 3) . '-' . substr($padded, 3, 3) . '-' . substr($padded, 6, 3);
                    ?>
                    <div class="id-badge"><?php echo htmlspecialchars($formattedId); ?></div>
                    <p><small class="text-muted">Your unique CORE-ID cannot be modified.</small></p>
                </div>

                <!-- ORCID (Read-only if present) -->
                <div class="form-group">
                    <label for="orcid">ORCID iD:</label>
                    <input type="text" id="orcid" value="<?php echo htmlspecialchars($user['ORCID'] ?? 'Not linked'); ?>" disabled class="form-control">
                    <?php if (empty($user['ORCID'])): ?>
                        <p><small class="text-muted"><a href="/orcid_login">Link your ORCID profile</a></small></p>
                    <?php endif; ?>
                </div>

                <!-- Base Fields -->
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="family_name">Family Name (Required):</label>
                    <input type="text" id="family_name" name="family_name" required value="<?php echo htmlspecialchars($_POST['family_name'] ?? ''); ?>">
                </div>

                <!-- Email & Privacy -->
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required class="form-control">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_email_public" name="is_email_public" value="1" <?php echo (isset($_POST['is_email_public']) && $_POST['is_email_public'] == '1') ? 'checked' : ''; ?>>
                        <label for="is_email_public">Make my email address public</label>
                    </div>
                    <p><small class="text-muted">Changing this will update the email you use to log in.</small></p>
                </div>

                <!-- Shared Form Details Partial -->
                <?php include VIEWS_PATH_TRIMMED . '/partials/profile_details_form.php'; ?>

                <div class="form-submit">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="/member/<?php echo $user['ID_alphanum']; ?>" class="btn btn-secondary">Cancel</a> 
                </div>
            </form>
        </div>
    </main>

    <?php include VIEWS_PATH_TRIMMED . '/partials/footer.php'; ?>
</body>
</html>
