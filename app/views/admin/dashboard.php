<?php
/**
 * Admin Dashboard View
 */
include VIEWS_PATH_TRIMMED . '/admin/partials/header.php';
?>

<div class="admin-layout">
    <?php include VIEWS_PATH_TRIMMED . '/admin/partials/sidebar.php'; ?>

    <main class="admin-main">
        <h1>Welcome to the Admin Dashboard</h1>
        <p>Use the sidebar to navigate through the administrative sections.</p>

        <section class="admin-section">
            <h2>System Maintenance</h2>
            <div class="admin-card">
                <p>Run system-wide maintenance procedures.</p>
                
                <form action="/admin/update-comments" method="POST" class="admin-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <button type="submit" class="btn btn-danger">Run UpdateComments() Procedure</button>
                </form>
            </div>
        </section>
    </main>
</div>

</body>
</html>
