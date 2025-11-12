<?php
// app/includes/owner_header.php
if (session_status() === PHP_SESSION_NONE) session_start();
$config = @include __DIR__ . '/../config.php';
// Set baseUrl
if (!isset($baseUrl)) {
    $baseUrl = '';
}
$baseUrl = rtrim((string)$baseUrl, '/');
// Load functions if available
if (file_exists(__DIR__ . '/../functions.php') && !function_exists('getSetting')) {
    require_once __DIR__ . '/../functions.php';
}
$siteName = function_exists('getSetting') ? getSetting('site_name', 'Livonto') : 'Livonto';
$pageTitle = $pageTitle ?? 'Owner Dashboard - ' . $siteName;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <!-- Favicon -->
  <?php 
  $faviconPath = ($baseUrl === '' || $baseUrl === '/') ? '/public/assets/images/favicon.ico' : ($baseUrl . '/public/assets/images/favicon.ico');
  if (substr($faviconPath, 0, 1) !== '/') {
      $faviconPath = '/' . ltrim($faviconPath, '/');
  }
  ?>
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($faviconPath) ?>">
  <link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars($faviconPath) ?>">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <!-- Your styles -->
  <?php 
  $cssBasePath = ($baseUrl === '' || $baseUrl === '/') ? '/public/assets/css/' : ($baseUrl . '/public/assets/css/');
  if (substr($cssBasePath, 0, 1) !== '/') {
      $cssBasePath = '/' . ltrim($cssBasePath, '/');
  }
  ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($cssBasePath . 'styles.css') ?>">
  
  <style>
    :root {
      --primary: #8b6bd1;
      --primary-700: #6f55b2;
    }
    
    .owner-header {
      background: linear-gradient(90deg, var(--primary) 0%, var(--primary-700) 100%);
      color: white;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      position: relative;
      z-index: 1030;
    }
    .owner-header .navbar-brand,
    .owner-header .navbar-brand span,
    .owner-header .nav-link {
      color: white !important;
    }
    .owner-header .navbar-brand img {
      filter: brightness(0) invert(1);
    }
    .owner-header .nav-link:hover {
      color: rgba(255,255,255,0.8) !important;
    }
    .owner-header .navbar-toggler {
      border-color: rgba(255,255,255,0.3);
    }
    .owner-header .navbar-toggler-icon {
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }
    .owner-header .dropdown-menu {
      z-index: 1050;
      margin-top: 0.5rem;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
      border: 1px solid rgba(0,0,0,0.1);
    }
    .owner-header .nav-item.dropdown {
      position: relative;
    }
  </style>
</head>
<body>
<nav class="navbar navbar-expand-lg owner-header">
  <div class="container-xxl">
    <a class="navbar-brand fw-bold d-flex align-items-center" href="<?= htmlspecialchars(app_url('owner/dashboard')) ?>">
      <?php 
      // Ensure logo path is always absolute
      $logoPath = ($baseUrl === '' || $baseUrl === '/') ? '/public/assets/images/logo-white-removebg.png' : ($baseUrl . '/public/assets/images/logo-white-removebg.png');
      if (substr($logoPath, 0, 1) !== '/') {
          $logoPath = '/' . ltrim($logoPath, '/');
      }
      ?>
      <img src="<?= htmlspecialchars($logoPath) ?>" 
           alt="Livonto" 
           class="me-2"
           style="max-height: 40px; width: auto;">
      <span>Owner Portal</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#ownerNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="ownerNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="<?= htmlspecialchars(app_url('owner/dashboard')) ?>">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= htmlspecialchars(app_url('owner/listings/edit')) ?>">
            <i class="bi bi-pencil-square me-1"></i>Update Availability
          </a>
        </li>
      </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="ownerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle fs-5 me-2"></i>
            <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['owner_name'] ?? 'Owner') ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="ownerDropdown">
            <li>
              <a class="dropdown-item" href="<?= htmlspecialchars(app_url('owner/dashboard')) ?>">
                <i class="bi bi-speedometer2 me-2"></i>Dashboard
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item" href="<?= htmlspecialchars(app_url('')) ?>">
                <i class="bi bi-house me-2"></i>Back to Website
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item text-danger" href="<?= htmlspecialchars(app_url('owner/logout')) ?>">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
              </a>
            </li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-xxl py-4">

