/* Asset Manager – Main JS */

// ── Sidebar toggle ──────────────────────────────────────────────
(function () {
    const sidebar  = document.getElementById('sidebar');
    const toggle   = document.getElementById('sidebarToggle');
    const overlay  = document.getElementById('sidebarOverlay');

    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay && overlay.classList.toggle('show');
        });
    }
    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('show');
        });
    }
})();

// ── Toast notifications ──────────────────────────────────────────
function showToast(message, type = 'success') {
    const area = document.getElementById('toastArea');
    if (!area) return;
    const id = 'toast_' + Date.now();
    const icons = { success: 'fa-check-circle', danger: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const html = `<div id="${id}" class="alert alert-${type} alert-dismissible fade show d-flex align-items-center gap-2 mb-2 shadow-sm" role="alert" style="min-width:260px">
        <i class="fas ${icons[type] || icons.info}"></i>
        <span>${message}</span>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>`;
    area.insertAdjacentHTML('beforeend', html);
    setTimeout(() => { const el = document.getElementById(id); if (el) el.remove(); }, 4000);
}

// ── AJAX helper ──────────────────────────────────────────────────
async function apiPost(url, data) {
    data.csrf_token = CSRF_TOKEN;
    const res = await fetch(SITE_URL + url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN },
        body: JSON.stringify(data)
    });
    return res.json();
}

// ── Form → plain object ──────────────────────────────────────────
function formData(form) {
    const obj = {};
    new FormData(form).forEach((v, k) => { obj[k] = v; });
    return obj;
}

// ── Reset form & clear errors ────────────────────────────────────
function resetForm(form) {
    form.reset();
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(el => el.textContent = '');
}

// ── Show field error ─────────────────────────────────────────────
function fieldError(form, name, msg) {
    const el = form.querySelector('[name="' + name + '"]');
    if (!el) return;
    el.classList.add('is-invalid');
    let fb = el.nextElementSibling;
    if (!fb || !fb.classList.contains('invalid-feedback')) {
        fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        el.after(fb);
    }
    fb.textContent = msg;
}

// ── Confirm dialog ───────────────────────────────────────────────
function confirmAction(msg) {
    return window.confirm(msg || 'Are you sure?');
}

// ── Currency format ──────────────────────────────────────────────
function fmtMoney(n) {
    return '৳ ' + parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ── Auto-calculate profit in investment form ─────────────────────
document.addEventListener('change', function (e) {
    const form = e.target.closest('#investForm, #renewForm');
    if (!form) return;

    const amount     = parseFloat(form.querySelector('[name="amount"]')?.value || 0);
    const rate       = parseFloat(form.querySelector('[name="profit_rate"]')?.value || 0);
    const type       = form.querySelector('[name="profit_type"]')?.value;
    const profitEl   = form.querySelector('[name="expected_profit"]');

    if (profitEl && (e.target.name === 'amount' || e.target.name === 'profit_rate' || e.target.name === 'profit_type')) {
        if (type === 'percent' && amount > 0 && rate > 0) {
            profitEl.value = (amount * rate / 100).toFixed(2);
        } else if (type === 'fixed') {
            profitEl.value = rate.toFixed(2);
        }
    }
});

// ── Reload table after CRUD without full page refresh ────────────
function reloadPage() { window.location.reload(); }
