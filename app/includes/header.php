<?php
// app/includes/header.php
if (session_status() === PHP_SESSION_NONE) session_start();
$config = @include __DIR__ . '/../config.php';
$pageTitle = $pageTitle ?? 'PG Finder';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($baseUrl . '/public/assets/images/favicon.ico') ?>">
  <link rel="shortcut icon" type="image/x-icon" href="<?= htmlspecialchars($baseUrl . '/public/assets/images/favicon.ico') ?>">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons (moved to head for immediate availability) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

  <!-- Early theme init: apply data-theme before paint to prevent flash -->
  <script>(function(){try{var k='pgfinder_theme';var d=document.documentElement;var s=localStorage.getItem(k);if(s==='dark'){d.setAttribute('data-theme','dark');}else if(s==='light'){d.removeAttribute('data-theme');}else{if(window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){d.setAttribute('data-theme','dark');}}}catch(e){}})();</script>

  <!-- Your styles -->
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/public/assets/css/styles.css') ?>">
  
  <!-- Leaflet CSS (for maps) -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" 
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" 
        crossorigin=""/>
  
  <!-- Map custom styles -->
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/public/assets/css/map.css') ?>">
  
  <!-- Autocomplete styles -->
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl . '/public/assets/css/autocomplete.css') ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container-xxl d-flex align-items-center">
    <a class="navbar-brand" href="<?= htmlspecialchars(app_url('')) ?>">
      <img src="<?= htmlspecialchars($baseUrl . '/public/assets/images/logo-removebg.png') ?>" alt="PG Finder" class="brand-logo brand-logo-light">
      <img src="<?= htmlspecialchars($baseUrl . '/public/assets/images/logo-white-removebg.png') ?>" alt="PG Finder" class="brand-logo brand-logo-dark">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('listings')) ?>">Listings</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('about')) ?>">About</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('contact')) ?>">Contact</a></li>
        <!-- Refer & Earn -->
        <li class="nav-item d-flex align-items-center ms-2">
          <a class="btn btn-primary btn-sm text-white" href="<?= htmlspecialchars(app_url('refer')) ?>">
            <i class="bi bi-gift me-1"></i> Refer &amp; Earn
          </a>
        </li>
      </ul>

      <ul class="navbar-nav ms-auto">
        <li class="nav-item d-flex align-items-center ms-2">
        <button id="themeToggle" type="button" class="btn btn-sm btn-outline-secondary" aria-label="Toggle dark mode" title="Toggle dark mode" aria-pressed="false">
            <i id="themeIcon" class="bi bi-moon-stars"></i>
            <span class="visually-hidden">Toggle dark mode</span>
        </button>
        </li>

        

        <?php if(!empty($_SESSION['user_id'])): ?>
          <!-- User Dropdown -->
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle fs-5 me-2"></i>
              <span class="d-none d-md-inline"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User')?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
              <li>
                <a class="dropdown-item" href="<?= htmlspecialchars(app_url('profile')) ?>">
                  <i class="bi bi-person me-2"></i>Profile
                </a>
              </li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item text-danger" href="<?= htmlspecialchars(app_url('logout')) ?>">
                  <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
              </li>
            </ul>
          </li>
        <?php else: ?>
          <!-- Login opens modal -->
          <li class="nav-item">
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Login</a>
          </li>

          <!-- Sign up opens modal -->
          <li class="nav-item">
            <button class="btn btn-primary ms-2" data-bs-toggle="modal" data-bs-target="#registerModal">Sign up</button>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<div class="container-xxl py-4">

<!-- LOGIN MODAL -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="loginForm" method="post" action="<?= htmlspecialchars($baseUrl . '/app/login_action.php') ?>" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="loginModalLabel">Login to PG Finder</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div id="loginAlert"></div>

          <div class="mb-3">
            <label for="loginEmail" class="form-label">Email</label>
            <input type="email" class="form-control modal-input" id="loginEmail" name="email" required autocomplete="username">
            <div class="invalid-feedback" id="loginEmailFeedback"></div>
          </div>

          <div class="mb-3">
            <label for="loginPassword" class="form-label">Password</label>
            <input type="password" class="form-control modal-input" id="loginPassword" name="password" required autocomplete="current-password">
            <div class="invalid-feedback" id="loginPasswordFeedback"></div>
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <div>
              <input type="checkbox" id="remember" name="remember"> <label for="remember" class="small">Remember me</label>
            </div>
            <div>
              <a href="<?= htmlspecialchars(app_url('password-reset')) ?>" class="small">Forgot password?</a>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <div class="me-auto small text-muted">Don't have an account? <a href="#" id="openRegisterFromLogin">Sign up</a></div>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" id="loginSubmit" class="btn btn-primary">
            <span id="loginSpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
            Login
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- REGISTER MODAL -->
<div class="modal fade" id="registerModal" tabindex="-1" aria-labelledby="registerModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="registerForm" method="post" action="<?= htmlspecialchars($baseUrl . '/app/register_action.php') ?>" novalidate>
        <div class="modal-header">
          <h5 class="modal-title" id="registerModalLabel">Create an account</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <div id="registerAlert"></div>

          <div class="mb-3">
            <label for="regName" class="form-label">Full name</label>
            <input type="text" class="form-control modal-input" id="regName" name="name" required>
            <div class="invalid-feedback" id="regNameFeedback"></div>
          </div>

          <div class="mb-3">
            <label for="regEmail" class="form-label">Email</label>
            <input type="email" class="form-control modal-input" id="regEmail" name="email" required>
            <div class="invalid-feedback" id="regEmailFeedback"></div>
          </div>

          <div class="mb-3">
            <label for="regPassword" class="form-label">Password</label>
            <input type="password" class="form-control modal-input" id="regPassword" name="password" required>
            <div class="invalid-feedback" id="regPasswordFeedback"></div>
          </div>

          <div class="mb-3">
            <label for="regReferral" class="form-label">Referral code (optional)</label>
            <input type="text" class="form-control modal-input" id="regReferral" name="referral_code">
            <div class="invalid-feedback" id="regReferralFeedback"></div>
          </div>
        </div>

        <div class="modal-footer">
          <div class="me-auto small text-muted">Already have an account? <a href="#" id="openLoginFromRegister">Login</a></div>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" id="registerSubmit" class="btn btn-primary">
            <span id="registerSpinner" class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
            Register
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  // ----- Utility: show bootstrap alert HTML -----
  function showAlert(containerId, type, html) {
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible" role="alert">' +
                          html +
                          '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                          '</div>';
  }

  // ----- Helper to render field errors -----
  function renderFieldErrors(prefix, errors) {
    // errors is object like { email: "msg", password: "msg" }
    Object.keys(errors || {}).forEach(field => {
      const feedback = document.getElementById(prefix + field.charAt(0).toUpperCase() + field.slice(1) + 'Feedback');
      const input = document.querySelector('#' + (prefix === 'login' ? 'login' : 'reg') + field.charAt(0).toUpperCase() + field.slice(1));
      if (feedback) feedback.textContent = errors[field];
      if (input) {
        input.classList.add('is-invalid');
      }
    });
  }

  // ----- Clear previous validation states -----
  function clearValidation(prefix) {
    const form = (prefix === 'login') ? document.getElementById('loginForm') : document.getElementById('registerForm');
    if (!form) return;
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    // clear feedback nodes
    form.querySelectorAll('.invalid-feedback').forEach(node => node.textContent = '');
    // clear alerts
    const containerId = (prefix === 'login') ? 'loginAlert' : 'registerAlert';
    const container = document.getElementById(containerId);
    if (container) container.innerHTML = '';
  }

  // ----- AJAX submit handler (generic) -----
  async function ajaxSubmit(formEl, submitBtnId, spinnerId, alertContainerId) {
    const submitBtn = document.getElementById(submitBtnId);
    const spinner = document.getElementById(spinnerId);

    // disable UI
    if (submitBtn) submitBtn.disabled = true;
    if (spinner) spinner.classList.remove('d-none');

    const url = formEl.action;
    const method = (formEl.method || 'post').toUpperCase();

    // Build FormData
    const fd = new FormData(formEl);

    try {
      const res = await fetch(url, {
        method,
        body: fd,
        credentials: 'include', // send cookies
        headers: {
          'Accept': 'application/json'
        }
      });

      const data = await res.json();

      if (!data) throw new Error('Invalid server response');

      if (data.status === 'ok' || data.status === 'success') {
        // Success path
        showAlert(alertContainerId, 'success', data.message || 'Success');

        // Close modal after short delay and reload or redirect
        setTimeout(() => {
          const modalEl = formEl.closest('.modal');
          if (modalEl) {
            const modalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modalInstance.hide();
          }
          if (data.redirect) {
            window.location.href = data.redirect;
          } else {
            // reload to update navbar/session state
            window.location.reload();
          }
        }, 900);

      } else {
        // Error path — show field errors or message
        if (data.errors) {
          renderFieldErrors(alertContainerId === 'loginAlert' ? 'login' : 'reg', data.errors);
        }
        const msg = data.message || 'There was an error. Please check your input.';
        showAlert(alertContainerId, 'danger', msg);
      }

    } catch (err) {
      console.error('AJAX error', err);
      showAlert(alertContainerId, 'danger', 'Server error. Please try again later.');
    } finally {
      if (submitBtn) submitBtn.disabled = false;
      if (spinner) spinner.classList.add('d-none');
    }
  }

  // ----- Login form AJAX -----
  (function(){
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
      loginForm.addEventListener('submit', function(e){
        e.preventDefault();
        clearValidation('login');
        ajaxSubmit(loginForm, 'loginSubmit', 'loginSpinner', 'loginAlert');
      });
    }
  })();

  // ----- Register form AJAX -----
  (function(){
    const registerForm = document.getElementById('registerForm');
    if (registerForm) {
      // if incoming URL has ref=, prefill referral input
      try {
        const params = new URLSearchParams(window.location.search);
        const ref = params.get('ref') || params.get('referral') || params.get('ref_code');
        if (ref) {
          const el = document.getElementById('regReferral');
          if (el) el.value = ref;
        }
      } catch (e) {}

      registerForm.addEventListener('submit', function(e){
        e.preventDefault();
        clearValidation('reg');
        ajaxSubmit(registerForm, 'registerSubmit', 'registerSpinner', 'registerAlert');
      });
    }
  })();

  // ----- Modal switching links -----
  (function(){
    const openRegisterFromLogin = document.getElementById('openRegisterFromLogin');
    const openLoginFromRegister = document.getElementById('openLoginFromRegister');

    if (openRegisterFromLogin) {
      openRegisterFromLogin.addEventListener('click', function(e){
        e.preventDefault();
        const loginModalEl = document.getElementById('loginModal');
        const regModalEl = document.getElementById('registerModal');
        const loginModal = bootstrap.Modal.getInstance(loginModalEl) || new bootstrap.Modal(loginModalEl);
        loginModal.hide();
        const regModal = bootstrap.Modal.getOrCreateInstance(regModalEl);
        regModal.show();
      });
    }

    if (openLoginFromRegister) {
      openLoginFromRegister.addEventListener('click', function(e){
        e.preventDefault();
        const regModalEl = document.getElementById('registerModal');
        const loginModalEl = document.getElementById('loginModal');
        const regModal = bootstrap.Modal.getInstance(regModalEl) || new bootstrap.Modal(regModalEl);
        regModal.hide();
        const loginModal = bootstrap.Modal.getOrCreateInstance(loginModalEl);
        loginModal.show();
      });
    }
  })();
</script>
