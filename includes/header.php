<?php
require_once __DIR__ . '/auth.php';
require_login();
$page_title = $page_title ?? 'CoreInventory';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title) ?> - CoreInventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark px-3">
  <span class="navbar-brand mb-0 h1"><i class="bi bi-box-seam"></i> CoreInventory</span>
  <div class="text-white">
    <i class="bi bi-person-circle"></i> <?= e($_SESSION['name']) ?>
    <span class="badge bg-secondary ms-1"><?= e(ucfirst($_SESSION['role'])) ?></span>
    <a href="logout.php" class="btn btn-outline-light btn-sm ms-3">Logout</a>
  </div>
</nav>
<div class="app-wrap">
  <?php require __DIR__ . '/sidebar.php'; ?>
  <main class="content">
    <?php if (!empty($_SESSION['flash'])): [$ft, $fm] = $_SESSION['flash']; unset($_SESSION['flash']); ?>
      <div class="alert alert-<?= e($ft) ?> alert-dismissible fade show"><?= $fm ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
