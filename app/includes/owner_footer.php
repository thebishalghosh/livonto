    </div> <!-- End container-xxl -->
    
    <!-- Footer -->
    <footer class="bg-dark text-light mt-5 pt-4 pb-3">
        <div class="container-xxl">
            <div class="row">
                <div class="col-md-6">
                    <p class="small mb-2 text-white">
                        <strong>Owner Portal</strong> - Manage your property listings
                    </p>
                    <p class="small text-muted mb-0">
                        © <?= date('Y') ?> <?= htmlspecialchars(function_exists('getSetting') ? getSetting('site_name', 'Livonto') : 'Livonto') ?>. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="small mb-2">
                        <a href="<?= htmlspecialchars(app_url('')) ?>" class="text-light text-decoration-none">
                            <i class="bi bi-house me-1"></i>Back to Website
                        </a>
                    </p>
                    <?php 
                    $contactEmail = function_exists('getSetting') ? getSetting('contact_email', 'support@livonto.com') : 'support@livonto.com';
                    ?>
                    <p class="small text-muted mb-0">
                        Need help? <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="text-light text-decoration-none">Contact Support</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

