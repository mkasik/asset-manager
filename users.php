<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
requireAdmin();
$pageTitle = 'User Management';
$pdo = db();

$users = $pdo->query("SELECT id, username, email, full_name, role, status, created_at FROM users ORDER BY created_at DESC")->fetchAll();

include __DIR__ . '/includes/layout_header.php';
?>

<div class="page-actions">
    <div><h2>Users</h2><div class="sub">Manage admin and viewer accounts</div></div>
    <button class="btn btn-primary btn-sm" onclick="openUserModal()">
        <i class="fas fa-plus me-1"></i> Add User
    </button>
</div>

<div class="card">
    <?php if ($users): ?>
    <div class="table-responsive">
        <table class="table">
            <thead><tr>
                <th>#</th><th>Full Name</th><th>Username</th><th>Email</th>
                <th>Role</th><th>Status</th><th>Created</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($users as $idx => $u): ?>
            <tr>
                <td class="text-muted"><?= $idx+1 ?></td>
                <td class="fw-600"><?= sanitize($u['full_name'] ?? $u['username']) ?></td>
                <td class="text-muted"><?= sanitize($u['username']) ?></td>
                <td class="text-muted fs-13"><?= sanitize($u['email']) ?></td>
                <td>
                    <span class="badge <?= $u['role']==='admin' ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary' ?>">
                        <?= ucfirst($u['role']) ?>
                    </span>
                </td>
                <td>
                    <span class="badge <?= $u['status'] ? 'status-active' : 'bg-secondary-subtle text-secondary' ?>">
                        <?= $u['status'] ? 'Active' : 'Inactive' ?>
                    </span>
                </td>
                <td class="text-muted fs-13"><?= formatDate($u['created_at']) ?></td>
                <td>
                    <div class="d-flex gap-1">
                        <button class="btn-act btn-edit" onclick="openUserModal(<?= $u['id'] ?>)" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($u['id'] != currentUser()['id']): ?>
                        <button class="btn-act btn-delete" onclick="deleteUser(<?= $u['id'] ?>)" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state"><div class="es-icon"><i class="fas fa-users"></i></div><p>No users found.</p></div>
    <?php endif; ?>
</div>

<!-- User Modal -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="userModalTitle">Add User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="userForm" onsubmit="submitUserForm(event)">
            <div class="modal-body">
                <input type="hidden" name="id" id="userId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" id="userFullName" class="form-control" placeholder="Full name">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" id="userUsername" class="form-control" placeholder="username" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" id="userEmail" class="form-control" placeholder="email@example.com" required>
                    </div>
                    <div class="col-12" id="pwdGroup">
                        <label class="form-label">Password <span class="text-danger" id="pwdRequired">*</span></label>
                        <input type="password" name="password" id="userPwd" class="form-control" placeholder="Min 6 characters">
                        <div class="form-text" id="pwdHint"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Role <span class="text-danger">*</span></label>
                        <select name="role" id="userRole" class="form-select" required>
                            <option value="viewer">Viewer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" id="userStatus" class="form-select">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div id="userAlert" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="userSubmitBtn">Save User</button>
            </div>
        </form>
    </div></div>
</div>

<?php
$usersJson = json_encode($users);
$extraJs = <<<JS
<script>
const USERS_DATA = $usersJson;

function openUserModal(id = null) {
    const modal = new bootstrap.Modal('#userModal');
    const form  = document.getElementById('userForm');
    form.reset();
    document.getElementById('userAlert').innerHTML = '';
    document.getElementById('userId').value = '';
    document.getElementById('pwdRequired').style.display = '';
    document.getElementById('pwdHint').textContent = '';

    if (id) {
        const u = USERS_DATA.find(x => x.id == id);
        if (!u) return;
        document.getElementById('userModalTitle').textContent  = 'Edit User';
        document.getElementById('userSubmitBtn').textContent   = 'Update User';
        document.getElementById('pwdRequired').style.display  = 'none';
        document.getElementById('pwdHint').textContent        = 'Leave blank to keep current password';
        document.getElementById('userId').value        = u.id;
        document.getElementById('userFullName').value  = u.full_name || '';
        document.getElementById('userUsername').value  = u.username;
        document.getElementById('userEmail').value     = u.email;
        document.getElementById('userRole').value      = u.role;
        document.getElementById('userStatus').value    = u.status;
    } else {
        document.getElementById('userModalTitle').textContent = 'Add User';
        document.getElementById('userSubmitBtn').textContent  = 'Save User';
    }
    modal.show();
}

async function submitUserForm(e) {
    e.preventDefault();
    const data = formData(e.target);
    const url  = data.id ? '/ajax/users.php?action=edit' : '/ajax/users.php?action=add';
    const btn  = e.target.querySelector('[type=submit]');
    btn.disabled = true;
    const res = await apiPost(url, data);
    btn.disabled = false;
    if (res.success) {
        bootstrap.Modal.getInstance('#userModal').hide();
        showToast(res.message, 'success');
        setTimeout(reloadPage, 500);
    } else {
        document.getElementById('userAlert').innerHTML = '<div class="alert alert-danger py-2 mt-2">' + res.message + '</div>';
    }
}

async function deleteUser(id) {
    if (!confirmAction('Delete this user? This cannot be undone.')) return;
    const res = await apiPost('/ajax/users.php?action=delete', { id });
    if (res.success) { showToast(res.message, 'success'); setTimeout(reloadPage, 500); }
    else showToast(res.message, 'danger');
}
</script>
JS;
include __DIR__ . '/includes/layout_footer.php';
