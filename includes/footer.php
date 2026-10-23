<?php
/**
 * Footer Template
 * Menutup tag layout dan memuat script Bootstrap 5 serta JS kustom
 */
?>
<?php if (empty($isAuthPage)): ?>
        </main>
        <footer class="bg-white border-top py-3 px-4 text-center text-muted small mt-auto">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
                <span>&copy; <?= date('Y'); ?> <strong><?= APP_NAME; ?></strong>. Tugas Praktik Pemrograman Berbasis API.</span>
                <span class="text-secondary mt-1 mt-sm-0"><i class="bi bi-shield-check text-success me-1"></i>Integrasi WhatsApp Gateway Fonnte</span>
            </div>
        </footer>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap 5 Bundle JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Custom App JS -->
<script src="<?= BASE_URL; ?>assets/js/app.js"></script>
</body>
</html>

