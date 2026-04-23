<?php
/**
 * Admin Header Partial
 */
$adminName = $_SESSION['display_name'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CORE Admin - Dashboard</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="alternate icon" type="image/png" href="/favicon.ico">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>
<body class="admin-body">
    <header class="admin-header">
    <img src="/favicon.svg" alt="CORE Logo" width="48" height="48">
        <div class="admin-brand">CORE Admin Panel</div>
        <div class="admin-user-nav">
            <span class="admin-welcome">Hello, <?php echo htmlspecialchars($adminName); ?></span>
            <a href="/" class="admin-link">View Public Site</a>
            <a href="/logout" class="admin-link text-danger">Logout</a>
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
