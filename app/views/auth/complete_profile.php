<?php
/**
 * Complete Profile View for ORCID users
 * 
 * @var array $pending Session data from ORCID callback
 * @var string $preFirstName Pre-filled first name from ORCID
 * @var string $preFamilyName Pre-filled family name from ORCID
 * @var array $institutions List of institutions for the dropdown
 * @var array $researchBranches List of branches for the checkbox lists
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> - Complete Your Profile</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/header.php'; ?>

    <main>
        <div class="main-container auth-container">
            <h1>Welcome, <?php echo htmlspecialchars($pending['name']); ?>!</h1>
            <p>Your ORCID (<?php echo htmlspecialchars($pending['orcid']); ?>) has been verified.</p>
            <p>Please provide an email and password to complete your CORE registration. This allows you to log in with either ORCID or your email in the future.</p>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="/complete_profile" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="email">Email Address (Required):</label>
                    <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_email_public" name="is_email_public" value="1" <?php echo isset($_POST['is_email_public']) ? 'checked' : ''; ?>>
                        <label for="is_email_public">Make my email address public</label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password (Optional, min 8 chars):</label>
                    <input type="password" id="password" name="password" 
                        pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}"
                        title="Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.">
                    <small>Leave blank if you only want to use ORCID login.</small>
                </div>

                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? $preFirstName); ?>">
                </div>

                <div class="form-group">
                    <label for="family_name">Family Name (Required):</label>
                    <input type="text" id="family_name" name="family_name" required value="<?php echo htmlspecialchars($_POST['family_name'] ?? $preFamilyName); ?>">
                </div>

                <?php include rtrim(VIEWS_PATH, '/') . '/partials/profile_details_form.php'; ?>

                <button type="submit" class="btn btn-primary">Complete Registration</button>
            </form>
        </div>
    </main>

    <?php include rtrim(VIEWS_PATH, '/') . '/partials/footer.php'; ?>
</body>
</html>
