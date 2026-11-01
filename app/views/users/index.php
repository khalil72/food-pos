<?php
$perms = [
    'admin'   => 'Everything, including users, settings, audit log and backups.',
    'manager' => 'Dashboard, POS, menu, inventory, purchases, expenses, refunds and reports.',
    'cashier' => 'POS, own orders and receipts, customers and their own cash register.',
];
?>
<div class="row g-3 mb-3">
    <?php foreach ($perms as $role => $desc): ?>
        <div class="col-md-4"><div class="card kpi"><div class="d-flex justify-content-between align-items-center mb-2"><?= role_badge($role) ?><span class="small text-muted"><?= count(array_filter($users, fn($u) => $u['role'] === $role)) ?> users</span></div><p class="small text-muted mb-0"><?= $desc ?></p></div></div>
    <?php endforeach; ?>
</div>
<div class="card table-card">
    <div class="card-header"><h2>Staff accounts</h2><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal"><i class="bi bi-person-plus me-1"></i>Add user</button></div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>User</th><th>Role</th><th>Contact</th><th class="text-center">Orders</th><th>Last login</th><th class="text-center">Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr class="<?= $u['is_active'] ? '' : 'opacity-50' ?>">
                    <td><div class="d-flex align-items-center gap-2"><span class="avatar"><?= e(initials($u['name'])) ?></span><div><div class="cell-title"><?= e($u['name']) ?><?= (int)$u['id'] === Auth::id() ? ' <span class="badge badge-soft-info">You</span>' : '' ?></div><div class="cell-sub">@<?= e($u['username']) ?></div></div></div></td>
                    <td><?= role_badge($u['role']) ?></td>
                    <td class="small"><?= e($u['email'] ?? '') ?><div class="cell-sub"><?= e($u['phone'] ?? '') ?></div></td>
                    <td class="text-center"><?= (int)$u['orders'] ?></td>
                    <td class="small"><?= fmt_datetime($u['last_login']) ?><?= $u['open_reg'] ? '<div><span class="badge badge-soft-success">Register open</span></div>' : '' ?></td>
                    <td class="text-center"><?= $u['is_active'] ? '<span class="badge badge-soft-success">Active</span>' : '<span class="badge badge-soft-secondary">Disabled</span>' ?></td>
                    <td>
                        <div class="actions">
                            <button class="btn-icon btn-sm" data-bs-toggle="modal" data-bs-target="#userModal" data-fill='<?= e(json_encode(array_intersect_key($u, array_flip(['id', 'name', 'username', 'email', 'phone', 'role', 'is_active'])))) ?>' aria-label="Edit"><i class="bi bi-pencil"></i></button>
                            <?php if ((int)$u['id'] !== Auth::id()): ?>
                                <form method="post" action="<?= url('users/delete', ['id' => $u['id']]) ?>" data-confirm="Delete <?= e($u['name']) ?>? Accounts with history are disabled instead." data-confirm-ok="Delete"><?= csrf_field() ?><button class="btn-icon btn-sm danger" aria-label="Delete"><i class="bi bi-trash3"></i></button></form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('users/save') ?>" data-fill-form autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="0" data-reset="0">
            <div class="modal-header"><h5 class="modal-title" data-modal-title data-create="Add user" data-edit="Edit user">Add user</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-6"><label class="form-label required" for="uName">Full name</label><input class="form-control" id="uName" name="name" maxlength="100" required></div>
                    <div class="col-6"><label class="form-label required" for="uUser">Username</label><input class="form-control" id="uUser" name="username" maxlength="50" pattern="[A-Za-z0-9_.\-]+" required autocomplete="off"></div>
                    <div class="col-6"><label class="form-label" for="uEmail">Email</label><input type="email" class="form-control" id="uEmail" name="email" maxlength="150"></div>
                    <div class="col-6"><label class="form-label" for="uPhone">Phone</label><input class="form-control" id="uPhone" name="phone" maxlength="30"></div>
                    <div class="col-12">
                        <label class="form-label required">Role</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php foreach (['cashier', 'manager', 'admin'] as $r): ?>
                                <input type="radio" class="btn-check" name="role" value="<?= $r ?>" id="role<?= $r ?>" <?= $r === 'cashier' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary btn-sm" for="role<?= $r ?>"><?= ucfirst($r) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-6"><label class="form-label" for="uPass">Password</label><input type="password" class="form-control" id="uPass" name="password" minlength="8" maxlength="72" autocomplete="new-password"></div>
                    <div class="col-6"><label class="form-label" for="uPass2">Confirm password</label><input type="password" class="form-control" id="uPass2" name="password_confirm" autocomplete="new-password"></div>
                    <div class="col-12"><div class="form-text">At least 8 characters. Leave blank when editing to keep the current password.</div></div>
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="uActive" checked><label class="form-check-label" for="uActive">Account active</label></div></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save user</button></div>
        </form>
    </div>
</div>
