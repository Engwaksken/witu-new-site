</main>
    </div>
    
    <script src="../assets/js/admin.js"></script>
    <?php if (isset($extra_js)): ?>
        <?php echo $extra_js; ?>
    <?php endif; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const stats = document.querySelector('.page-stats-grid');
        const header = document.querySelector('.admin-content > .content-header');
        if (stats && header && stats.previousElementSibling !== header) {
            header.insertAdjacentElement('afterend', stats);
        }
    });
    </script>
</body>
</html>