
    </div><!-- .content-area -->
</div><!-- .main-content -->
</div><!-- .wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const SITE_URL   = '<?= SITE_URL ?>';
    const CSRF_TOKEN = '<?= csrfToken() ?>';
    const IS_ADMIN   = <?= isAdmin() ? 'true' : 'false' ?>;
</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<?php if (!empty($extraJs)) echo $extraJs; ?>
</body>
</html>
