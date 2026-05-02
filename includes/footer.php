
<footer class="footer mt-auto py-3 bg-dark text-white-50">
    <div class="container text-center">
        <small>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Build ATS-ready resumes that get noticed.</small>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="<?= ASSETS_URL ?>/js/app.js"></script>
<?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
<script src="<?= e($js) ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
