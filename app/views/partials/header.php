<?php
/**
 * Global Header Partial
 */
$isLoggedIn = isset($_SESSION['mID']);
$isAdmin = isset($_SESSION['admin_role']) && (int)$_SESSION['admin_role'] >= ADMIN_ROLE_MIN;
?>
</head>
<body>
	<header class="site-header">
		<div class="header-container">
			<div class="header-left">
				<a href="/" class="logo-link">
					<img src="/favicon.svg" alt="CORE Logo" width="48" height="48">
					<span class="site-title">
						<span class="brand-name"><?php echo SITE_NAME; ?></span>
						<span class="brand-suffix">
						  <span class="bracket">(</span>
						  <?php echo str_replace('CORE', '<span class="core-text">COR<span class="triple-e">E</span></span>', SITE_SUFFIX); ?>
						  <span class="bracket">)</span>
						</span>
					</span>
				</a>
			</div>
			
			<div class="header-right">
				<form action="/search" method="GET" class="search-form">
					<input type="text" name="q" placeholder="Search research..." class="search-input">
				</form>
				
				<nav class="auth-nav">
					<?php if ($isLoggedIn): ?>
						<div class="user-dropdown">
							<button class="dropdown-toggle" aria-haspopup="true">
								<?php echo htmlspecialchars($_SESSION['display_name'] ?? 'Member'); ?>
								<span class="caret">&#x25BC;</span>
							</button>
							<div class="dropdown-menu">
								<a href="/upload" class="dropdown-item">Upload Document</a>
								<a href="/mydocs" class="dropdown-item">My Documents</a>
								<a href="/mydrafts" class="dropdown-item">My Drafts</a>
								<a href="/profile/edit" class="dropdown-item">Edit Profile</a>
								
								<?php if ($isAdmin): ?>
									<div class="dropdown-divider"></div>
									<a href="/admin/dashboard" class="dropdown-item view-switch">Admin Dashboard</a>
								<?php endif; ?>
								
								<div class="dropdown-divider"></div>
								<a href="/logout" class="dropdown-item text-danger">Logout</a>
							</div>
						</div>
					<?php else: ?>
						<!-- Logged Out State -->
						<a href="/login" class="nav-link">Login</a>
						<span class="nav-sep">/</span>
						<a href="/register" class="nav-link">Register</a>
					<?php endif; ?>
				</nav>
			</div>
		</div>
	</header>

	<div class="global-alerts-container">
		<?php if (isset($_SESSION['success_message'])): ?>
			<div class="alert alert-success">
				<?php echo htmlspecialchars($_SESSION['success_message']); ?>
				<?php unset($_SESSION['success_message']); ?>
			</div>
		<?php endif; ?>

		<?php if (isset($_SESSION['error_message'])): ?>
			<div class="alert alert-danger">
				<?php echo htmlspecialchars($_SESSION['error_message']); ?>
				<?php unset($_SESSION['error_message']); ?>
			</div>
		<?php endif; ?>
	</div>
