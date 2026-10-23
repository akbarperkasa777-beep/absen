/**
 * JavaScript Utama Aplikasi
 * Mengatur interaktivitas UI, Realtime Clock, Validasi Interaktif, dan Konfirmasi Modal
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Inisialisasi Jam Realtime
    initRealtimeClock();

    // 2. Inisialisasi Toggle Tampilkan / Sembunyikan Password
    initPasswordToggles();

    // 3. Inisialisasi Sidebar Mobile Toggle
    initSidebarToggle();

    // 4. Cegah Pengiriman Formulir Berulang (Double Submit Prevention)
    initFormSubmitProtection();

    // 5. Inisialisasi Modal Konfirmasi Hapus Data
    initDeleteConfirmModal();
});

/**
 * Jam Realtime dengan Zona Waktu Asia/Jakarta (WIB)
 */
function initRealtimeClock() {
    const clockElement = document.getElementById('liveClock');
    if (!clockElement) return;

    function updateClock() {
        const now = new Date();
        const options = {
            timeZone: 'Asia/Jakarta',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        const timeString = new Intl.DateTimeFormat('id-ID', options).format(now);
        clockElement.textContent = timeString + ' WIB';
    }

    updateClock();
    setInterval(updateClock, 1000);
}

/**
 * Toggle Show / Hide Password
 */
function initPasswordToggles() {
    const toggleButtons = document.querySelectorAll('.btn-toggle-password');
    toggleButtons.forEach(btn => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const targetInput = document.getElementById(targetId);
            const icon = this.querySelector('i');

            if (!targetInput) return;

            if (targetInput.type === 'password') {
                targetInput.type = 'text';
                if (icon) {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            } else {
                targetInput.type = 'password';
                if (icon) {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }
        });
    });
}

/**
 * Mobile Sidebar Navigation
 */
function initSidebarToggle() {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.app-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    if (!toggleBtn || !sidebar) return;

    function toggleMenu() {
        sidebar.classList.toggle('show');
        if (overlay) {
            overlay.classList.toggle('show');
        }
    }

    toggleBtn.addEventListener('click', toggleMenu);

    if (overlay) {
        overlay.addEventListener('click', toggleMenu);
    }
}

/**
 * Mencegah Klik Berulang pada Tombol Submit (Double Click Protection)
 */
function initFormSubmitProtection() {
    const forms = document.querySelectorAll('form.protect-submit');
    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                const originalHtml = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                    Memproses...
                `;

                // Kembalikan jika respons lambat atau jika batal
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }, 8000);
            }
        });
    });
}

/**
 * Modal Konfirmasi Hapus Dinamis
 */
function initDeleteConfirmModal() {
    const modalElement = document.getElementById('modalKonfirmasiHapus');
    if (!modalElement) return;

    modalElement.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const id = button.getAttribute('data-id');
        const nama = button.getAttribute('data-nama');
        const nik = button.getAttribute('data-nik');

        const inputId = modalElement.querySelector('#hapusKaryawanId');
        const spanNama = modalElement.querySelector('#hapusKaryawanNama');
        const spanNik = modalElement.querySelector('#hapusKaryawanNik');

        if (inputId) inputId.value = id;
        if (spanNama) spanNama.textContent = nama;
        if (spanNik) spanNik.textContent = nik;
    });
}

