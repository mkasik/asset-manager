<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireLogin();
$pageTitle = 'Categories';
$pdo = db();

$cats = $pdo->query("
    SELECT c.*, SUM(CASE WHEN i.status='active' THEN 1 ELSE 0 END) AS inv_count, COALESCE(SUM(CASE WHEN i.status='active' THEN i.amount ELSE 0 END),0) AS invested
    FROM categories c
    LEFT JOIN investments i ON i.category_id = c.id
    GROUP BY c.id
    ORDER BY c.name
")->fetchAll();

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div><h2>Categories</h2><div class="sub">Investment categories / types</div></div>
    <?php if (isAdmin()): ?>
    <button class="btn btn-primary btn-sm" onclick="openCatModal()">
        <i class="fas fa-plus me-1"></i> Add Category
    </button>
    <?php endif; ?>
</div>

<div class="card">
    <?php if ($cats): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>#</th><th>Category Name</th><th>Description</th>
                <th class="text-center">Active Investments</th><th>Active Amount</th><th>Created</th>
                <?php if (isAdmin()): ?><th>Actions</th><?php endif; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($cats as $i => $cat): ?>
            <tr>
                <td class="text-muted"><?= $i+1 ?></td>
                <td class="fw-600"><?= sanitize($cat['name']) ?></td>
                <td class="text-muted fs-13"><?= sanitize($cat['description'] ?? '—') ?></td>
                <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= $cat['inv_count'] ?></span></td>
                <td class="amount-neutral"><?= formatMoney($cat['invested']) ?></td>
                <td class="text-muted fs-13"><?= formatDate($cat['created_at']) ?></td>
                <?php if (isAdmin()): ?>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn-act btn-edit" onclick="openCatModal(<?= $cat['id'] ?>)" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-act btn-delete" onclick="deleteCat(<?= $cat['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <div class="es-icon"><i class="fas fa-tags"></i></div>
        <p>No categories yet. Add investment categories like FDR, Pond Lease, Land Lease…</p>
    </div>
    <?php endif; ?>
</div>

<!-- Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="catModalTitle">Add Category</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="catForm" onsubmit="submitCatForm(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="catId">
                <div class="mb-3">
                    <label class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="catName" class="form-control" placeholder="e.g. FDR, Pond Lease, Land Lease" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="catDesc" class="form-control" rows="3" placeholder="Brief description…"></textarea>
                </div>
                <div id="catAlert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="catSubmitBtn">Save</button>
            </div>
        </form>
    </div></div>
</div>

<?php
$catsJson = json_encode($cats);
$extraJs = <<<JS
<script>
const CATS_DATA = $catsJson;

function openCatModal(id = null) {
    const modal = new bootstrap.Modal('#catModal');
    document.getElementById('catForm').reset();
    document.getElementById('catAlert').innerHTML = '';
    if (id) {
        const c = CATS_DATA.find(x => x.id == id);
        if (!c) return;
        document.getElementById('catModalTitle').textContent  = 'Edit Category';
        document.getElementById('catSubmitBtn').textContent   = 'Update';
        document.getElementById('catId').value   = c.id;
        document.getElementById('catName').value = c.name;
        document.getElementById('catDesc').value = c.description || '';
    } else {
        document.getElementById('catModalTitle').textContent = 'Add Category';
        document.getElementById('catSubmitBtn').textContent  = 'Save';
        document.getElementById('catId').value = '';
    }
    modal.show();
}

async function submitCatForm(e) {
    e.preventDefault();
    const data = formData(e.target);
    const url  = data.id ? '/ajax/categories.php?action=edit' : '/ajax/categories.php?action=add';
    const btn  = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    const res = await apiPost(url, data);
    btn.disabled = false;
    if (res.success) {
        bootstrap.Modal.getInstance('#catModal').hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('catAlert').innerHTML = '<div class="alert alert-danger py-2 mt-2">' + res.message + '</div>';
    }
}

async function deleteCat(id) {
    if (!confirmAction('Delete this category? Make sure no investments are using it.')) return;
    const res = await apiPost('/ajax/categories.php?action=delete', { id });
    if (res.success) { showToast(res.message, 'success'); setTimeout(reloadPage, 500); }
    else showToast(res.message, 'danger');
}
</script>
JS;
include __DIR__ . '/includes/layout_footer.php';
