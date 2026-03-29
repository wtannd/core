<?php
/**
 * Public Member Profile View
 * 
 * @var array $member The sanitized member data array.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_TITLE; ?> Profile - <?php echo htmlspecialchars($member['pub_name']); ?></title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <?php include rtrim(VIEWS_PATH, '/') . '/partials/header.php'; ?>

    <main>
        <div class="profile-container">
            <header class="profile-header">
                <?php if (isset($_SESSION['mID']) && (int)$_SESSION['mID'] === (int)$member['mID']): ?>
                    <a href="/profile/edit" class="edit-profile-btn">Edit Profile</a>
                <?php endif; ?>

                <h1><?php echo htmlspecialchars($member['fullName']); ?></h1>
                
                <?php if (!empty($member['iname'])): ?>
                    <p class="institution-subtitle"><?php echo htmlspecialchars($member['iname']); ?></p>
                <?php endif; ?>

                <div class="profile-badges">
                    <span class="id-badge">CORE-ID: <?php echo htmlspecialchars($member['formatted_id']); ?></span>
                    <?php if (!empty($member['ORCID'])): ?>
                        <a href="https://orcid.org/<?php echo htmlspecialchars($member['ORCID']); ?>" target="_blank" rel="noopener noreferrer" class="id-badge id-badge-orcid">
                            ORCID: <?php echo htmlspecialchars($member['ORCID']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </header>

            <div class="profile-content">
                <div class="status-alerts">
                    <?php if (!(int)$member['is_active']): ?>
                        <span class="status-badge status-inactive">Inactive Member</span>
                    <?php endif; ?>
                    <?php if ((int)$member['is_deceased']): ?>
                        <span class="status-badge status-deceased">Deceased</span>
                    <?php endif; ?>
                </div>

                <div class="info-grid">
                    <div class="primary-info">
                        <!-- Metrics Section -->
                        <div class="info-group">
                            <span class="info-label">Metrics</span>
                            <div class="info-value">
                                <strong>AL:</strong> <?php echo htmlspecialchars((string)$member['AL']); ?> | 
                                <strong>ALS:</strong> <?php echo htmlspecialchars((string)$member['ALS']); ?> | 
                                <strong>ECP:</strong> <?php echo htmlspecialchars((string)$member['ECP']); ?>
                            </div>
                        </div>

                        <div class="info-group">
                            <span class="info-label">Name in Publications</span>
                            <div class="info-value"><?php echo htmlspecialchars($member['pub_name']); ?></div>
                        </div>

                        <?php if (isset($member['email'])): ?>
                        <div class="info-group">
                            <span class="info-label">Email Address</span>
                            <div class="info-value">
                                <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>" class="link-academic">
                                    <?php echo htmlspecialchars($member['email']); ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="research-info">
                        <?php if (!empty($member['work_areas_display'])): ?>
                        <div class="info-group">
                            <span class="info-label">Work Areas</span>
                            <div class="pill-container">
                                <?php foreach ($member['work_areas_display'] as $area): ?>
                                    <span class="pill"><?php echo htmlspecialchars($area); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($member['interest_areas_display'])): ?>
                        <div class="info-group">
                            <span class="info-label">Interest Areas</span>
                            <div class="pill-container">
                                <?php foreach ($member['interest_areas_display'] as $area): ?>
                                    <span class="pill"><?php echo htmlspecialchars($area); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($member['metadata'])): ?>
                <section class="meta-section">
                    <h2>Professional Background</h2>
                    <div class="info-grid">
                        <?php foreach ($member['metadata'] as $key => $value): ?>
                            <div class="meta-item">
                                <span class="info-label"><?php echo htmlspecialchars(str_replace('_', ' ', $key)); ?></span>
                                <div class="info-value">
                                    <?php if (strpos($key, 'url') !== false || $key === 'cv'): ?>
                                        <a href="<?php echo htmlspecialchars($value); ?>" target="_blank" rel="noopener noreferrer" class="link-academic">
                                            <?php echo htmlspecialchars($value); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo nl2br(htmlspecialchars($value)); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include rtrim(VIEWS_PATH, '/') . '/partials/footer.php'; ?>
</body>
</html>
