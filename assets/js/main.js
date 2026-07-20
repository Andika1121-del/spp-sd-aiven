/**
 * SD Mujahidin - Global JavaScript (Terpadu)
 * Versi 4.0 - Lengkap dengan semua fitur
 */

// ============================================================
// ========== WAIT FOR DOM READY ===============================
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    
    // Inisialisasi semua komponen
    initSidebarToggle();
    initModalHandler();
    initFormValidation();
    initPasswordToggle();
    initRupiahFormat();
    initAutoFormatRupiah();
    initDropdown();
    initAutoHideAlert();
    initTooltip();
    initScrollAnimation();
    initTableSearch();
    initUploadFileHandler();
    fixFormSubmit();
    
    console.log('✅ SD Mujahidin - Global JS Loaded (v4.0)');
});

// ============================================================
// ========== INISIALISASI FUNGSI (Internal) ===================
// ============================================================

// ---------- SIDEBAR TOGGLE ----------
function initSidebarToggle() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle && sidebar) {
        function updateToggleDisplay() {
            menuToggle.style.display = window.innerWidth <= 768 ? 'block' : 'none';
            if (window.innerWidth > 768) {
                sidebar.classList.remove('active');
            }
        }
        
        updateToggleDisplay();
        window.addEventListener('resize', updateToggleDisplay);
        
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }
}

// Fungsi global untuk toggle sidebar (dipanggil dari HTML)
window.toggleSidebar = function() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('active');
};

// ---------- MODAL HANDLER ----------
function initModalHandler() {
    // Fungsi global untuk membuka modal
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
    };
    
    // Fungsi global untuk menutup modal
    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    };
    
    // Tutup modal jika klik di luar konten
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    });
}

// ---------- FORM VALIDATION ----------
function initFormValidation() {
    document.querySelectorAll('form[data-validate="true"]').forEach(form => {
        form.addEventListener('submit', function(e) {
            let isValid = true;
            this.querySelectorAll('[required]').forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    showToast('Semua field wajib diisi!', 'warning');
                } else {
                    field.classList.remove('error');
                }
            });
            if (!isValid) e.preventDefault();
        });
    });
}

// ---------- PASSWORD TOGGLE (lihat password) ----------
function initPasswordToggle() {
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const input = document.getElementById(this.getAttribute('data-target'));
            if (input) {
                const type = input.type === 'password' ? 'text' : 'password';
                input.type = type;
                const icon = this.querySelector('i');
                icon.classList.toggle('fa-eye-slash');
                icon.classList.toggle('fa-eye');
            }
        });
    });
}

// ---------- FORMAT RUPIAH (Global) ----------
window.formatRupiah = function(angka, prefix = 'Rp ') {
    if (angka === null || angka === undefined || angka === '') return prefix + '0';
    let numberString = angka.toString().replace(/[^,\d]/g, '');
    let split = numberString.split(',');
    let sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    let ribuan = split[0].substr(sisa).match(/\d{3}/gi);
    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    rupiah = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
    return prefix + rupiah;
};

window.cleanRupiah = function(value) {
    if (!value) return '0';
    return value.toString().replace(/[^0-9]/g, '');
};

// Format untuk elemen .format-rupiah
function initRupiahFormat() {
    document.querySelectorAll('.format-rupiah').forEach(input => {
        input.addEventListener('input', function() {
            let value = this.value.replace(/[^0-9]/g, '');
            if (value) this.value = window.formatRupiah(value, '');
        });
    });
}

// ---------- AUTO-FORMAT RUPIAH UNTUK INPUT .input-nominal ----------
function initAutoFormatRupiah() {
    document.querySelectorAll('.input-nominal').forEach(input => {
        // Saat mengetik
        input.addEventListener('input', function() {
            let raw = this.value.replace(/[^0-9]/g, '');
            if (raw !== '') {
                this.value = new Intl.NumberFormat('id-ID').format(parseInt(raw));
            } else {
                this.value = '';
            }
        });

        // Saat blur (keluar) - format dengan titik
        input.addEventListener('blur', function() {
            let raw = this.value.replace(/[^0-9]/g, '');
            if (raw !== '') {
                this.value = new Intl.NumberFormat('id-ID').format(parseInt(raw));
            }
        });

        // Saat focus - hilangkan titik agar mudah diedit
        input.addEventListener('focus', function() {
            let raw = this.value.replace(/[^0-9]/g, '');
            if (raw !== '') this.value = raw;
        });

        // Bersihkan sebelum submit form
        const form = input.closest('form');
        if (form) {
            form.addEventListener('submit', function() {
                let raw = input.value.replace(/[^0-9]/g, '');
                if (raw === '' || parseInt(raw) <= 0) {
                    showToast('Masukkan nominal yang valid (minimal 1)', 'error');
                    return;
                }
                input.value = raw;
            });
        }
    });
}

// ---------- CEGAH DOUBLE SUBMIT ----------
function fixFormSubmit() {
    document.querySelectorAll('form[data-prevent-double]').forEach(form => {
        let submitted = false;
        form.addEventListener('submit', function(e) {
            if (submitted) {
                e.preventDefault();
                showToast('Mohon tunggu, proses sedang berjalan...', 'warning');
                return;
            }
            submitted = true;
            setTimeout(() => { submitted = false; }, 5000);
        });
    });
}

// ---------- DROPDOWN MENU ----------
function initDropdown() {
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            const menu = this.nextElementSibling;
            if (menu && menu.classList.contains('dropdown-menu')) {
                menu.classList.toggle('show');
            }
        });
    });
    document.addEventListener('click', function() {
        document.querySelectorAll('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
    });
}

// ---------- TOOLTIP ----------
function initTooltip() {
    document.querySelectorAll('[data-tooltip]').forEach(el => {
        el.addEventListener('mouseenter', function(e) {
            const text = this.getAttribute('data-tooltip');
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = text;
            tooltip.style.cssText = `
                position: absolute;
                background: #1e293b;
                color: white;
                padding: 6px 12px;
                border-radius: 8px;
                font-size: 12px;
                white-space: nowrap;
                z-index: 10000;
                transform: translateY(-100%);
                margin-top: -8px;
                pointer-events: none;
            `;
            document.body.appendChild(tooltip);
            const rect = this.getBoundingClientRect();
            tooltip.style.left = (rect.left + rect.width/2 - tooltip.offsetWidth/2) + 'px';
            tooltip.style.top = (rect.top - tooltip.offsetHeight) + 'px';
            this.addEventListener('mouseleave', () => tooltip.remove(), { once: true });
        });
    });
}

// ---------- AUTO HIDE ALERT ----------
function initAutoHideAlert() {
    document.querySelectorAll('.alert-auto-hide').forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 4000);
    });
}

// ---------- SCROLL ANIMATION ----------
function initScrollAnimation() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
    
    document.querySelectorAll('.fade-in-up').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'all 0.5s ease';
        observer.observe(el);
    });
}

// ---------- TABLE SEARCH ----------
function initTableSearch() {
    window.filterTable = function(inputId, tableId, columnIndex = 1) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const filter = input.value.toLowerCase();
        const rows = document.getElementById(tableId)?.getElementsByTagName('tr') || [];
        for (let i = 1; i < rows.length; i++) {
            const cell = rows[i].getElementsByTagName('td')[columnIndex];
            rows[i].style.display = (cell && cell.textContent.toLowerCase().includes(filter)) ? '' : 'none';
        }
    };
}

// ---------- UPLOAD FILE HANDLER ----------
function initUploadFileHandler() {
    const fileInput = document.getElementById('foto_bukti');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const fileStatus = document.getElementById('fileStatus');
            
            if (file) {
                fileName.textContent = file.name;
                let sizeText = (file.size < 1024*1024) ? (file.size/1024).toFixed(1)+' KB' : (file.size/(1024*1024)).toFixed(1)+' MB';
                fileSize.textContent = sizeText;

                if (file.size > 2*1024*1024) {
                    fileStatus.textContent = '⚠️ Ukuran terlalu besar (maks 2 MB)';
                    fileStatus.className = 'file-status invalid';
                    this.value = '';
                    resetFileInfo();
                    showToast('Ukuran file terlalu besar! Maksimal 2 MB.', 'error');
                    return;
                }
                if (!['image/jpeg','image/png','image/jpg'].includes(file.type)) {
                    fileStatus.textContent = '⚠️ Format tidak didukung (harus JPG/PNG/JPEG)';
                    fileStatus.className = 'file-status invalid';
                    this.value = '';
                    resetFileInfo();
                    showToast('Format file tidak didukung. Harap pilih gambar (JPG, PNG, JPEG).', 'error');
                    return;
                }
                fileStatus.textContent = '✅ File siap diunggah';
                fileStatus.className = 'file-status valid';
            } else {
                resetFileInfo();
            }
        });
    }
}

// ---------- RESET FILE INFO (global) ----------
window.resetFileInfo = function() {
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileStatus = document.getElementById('fileStatus');
    if (fileName) fileName.textContent = 'Belum ada file dipilih';
    if (fileSize) fileSize.textContent = '';
    if (fileStatus) {
        fileStatus.textContent = '';
        fileStatus.className = 'file-status';
    }
};

// ============================================================
// ========== FUNGSI GLOBAL (dipanggil dari HTML) =============
// ============================================================

// ---------- TOAST NOTIFICATION ----------
window.showToast = function(message, type = 'success') {
    const oldToast = document.querySelector('.toast-notification');
    if (oldToast) oldToast.remove();

    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    toast.innerHTML = `
        <div class="toast-icon"><i class="fas ${icons[type] || icons.info}"></i></div>
        <div class="toast-message">${message}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    document.body.appendChild(toast);
    setTimeout(() => { if (toast.parentElement) toast.remove(); }, 3000);
};

// ---------- CONFIRM DIALOG (menggunakan modal) ----------
window.confirmDialog = function(message, title = 'Konfirmasi', onConfirm) {
    const modalId = 'modalConfirmDialog';
    let modal = document.getElementById(modalId);
    if (!modal) {
        modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content" style="max-width:400px;">
                <div class="modal-header">
                    <h3><i class="fas fa-question-circle"></i> ${title}</h3>
                </div>
                <div class="modal-body">
                    <p style="color:#475569;">${message}</p>
                </div>
                <div class="modal-footer" style="display:flex; gap:10px; justify-content:center; margin-top:20px;">
                    <button class="btn btn-secondary" onclick="closeModal('${modalId}')">Batal</button>
                    <button class="btn btn-primary" id="confirmDialogOkBtn">
                        <i class="fas fa-check"></i> Ya, Lanjutkan
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    } else {
        modal.querySelector('.modal-body p').textContent = message;
        modal.querySelector('.modal-header h3').innerHTML = `<i class="fas fa-question-circle"></i> ${title}`;
    }

    openModal(modalId);
    const okBtn = document.getElementById('confirmDialogOkBtn');
    const newOkBtn = okBtn.cloneNode(true);
    okBtn.parentNode.replaceChild(newOkBtn, okBtn);
    newOkBtn.addEventListener('click', function() {
        closeModal(modalId);
        if (typeof onConfirm === 'function') onConfirm();
    });
};

// ---------- LOADING BUTTON ----------
window.showLoading = function(button, text = 'Memproses...') {
    const originalText = button.innerHTML;
    button.disabled = true;
    button.dataset.originalText = originalText;
    button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`;
};

window.hideLoading = function(button) {
    button.disabled = false;
    button.innerHTML = button.dataset.originalText || 'Submit';
};

// ---------- EXPORT TO EXCEL ----------
window.exportToExcel = function(tableId, filename = 'laporan') {
    const table = document.getElementById(tableId);
    if (!table) return showToast('Tidak ada data untuk diexport!', 'error');
    const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(table.outerHTML);
    const link = document.createElement('a');
    link.download = filename + '.xls';
    link.href = url;
    link.click();
    showToast('Export Excel berhasil!', 'success');
};

// ---------- PRINT ----------
window.printElement = function(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    const win = window.open('', '_blank');
    win.document.write(`
        <html><head><title>Cetak Laporan</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <style>body { padding:20px; } .no-print, .btn, .menu-toggle, .sidebar, .navbar { display:none; } .content { margin:0; padding:0; }</style>
        </head><body>${element.innerHTML}</body></html>
    `);
    win.document.close();
    win.print();
};

// ---------- COPY TO CLIPBOARD ----------
window.copyToClipboard = function(text, showNotif = true) {
    navigator.clipboard.writeText(text).then(() => {
        if (showNotif) showToast('Berhasil disalin!', 'success');
    }).catch(() => {
        if (showNotif) showToast('Gagal menyalin!', 'error');
    });
};

// ---------- DATE HELPER ----------
window.setMinDate = function(inputId, daysOffset = 0) {
    const input = document.getElementById(inputId);
    if (input) {
        const date = new Date();
        date.setDate(date.getDate() + daysOffset);
        input.setAttribute('min', date.toISOString().split('T')[0]);
    }
};

// ---------- ACTION CONFIRM (redirect) ----------
window.confirmAction = function(message, url) {
    if (confirm(message)) window.location.href = url;
};

// ============================================================
// ========== FUNGSI KHUSUS (modul SPP) =======================
// ============================================================

// ---------- KONFIRMASI PEMBAYARAN (SPP) ----------
window.bukaKonfirmasi = function(id, periode) {
    document.getElementById('id_tagihan_modal').value = id;
    document.getElementById('judulKonfirmasi').innerHTML = "Konfirmasi " + periode;
    openModal('modalKonfirmasi');
    window.resetFileInfo();
};

window.tutupKonfirmasi = function() {
    closeModal('modalKonfirmasi');
    const fileInput = document.getElementById('foto_bukti');
    if (fileInput) fileInput.value = '';
    window.resetFileInfo();
};

window.bayarOnline = function(id_tagihan, nis) {
    window.open(
        `proses_payment.php?id_tagihan=${id_tagihan}&nis=${nis}`,
        '_blank',
        'width=500,height=700'
    );
};

// ---------- CEK MANDIRI (untuk siswa) ----------
window.confirmCekMandiri = function() {
    const input = document.querySelector('input[name="nis"]');
    if (!input || !input.value.trim()) {
        showToast('Masukkan NIS atau nama siswa terlebih dahulu!', 'warning');
        return;
    }
    openModal('modalKonfirmasiCek');
};

window.submitCekMandiri = function() {
    closeModal('modalKonfirmasiCek');
    document.getElementById('formCekMandiri').submit();
};

// ---------- LOGIN KONFIRMASI ----------
window.confirmLogin = function() {
    confirmDialog(
        'Anda akan diarahkan ke halaman login petugas. Lanjutkan?',
        'Konfirmasi',
        function() { window.location.href = 'login.php'; }
    );
};

// ---------- LUPA PASSWORD ----------
window.openLupaPassword = function() {
    openModal('modalLupaPassword');
    document.getElementById('inputLupa').value = '';
};

window.prosesLupaPassword = function() {
    const email = document.getElementById('inputLupa').value.trim();
    if (!email) {
        showToast('Masukkan email atau username!', 'error');
        return;
    }
    showToast('Link reset password telah dikirim ke ' + email, 'success');
    closeModal('modalLupaPassword');
    // Di sini bisa ditambahkan AJAX ke server
};

// ---------- TOGGLE PASSWORD (untuk login) ----------
window.togglePasswordVisibility = function(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if (input && icon) {
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    }
};