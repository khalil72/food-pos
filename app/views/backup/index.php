<?php $fmtSize = fn(float $b) => $b >= 1048576 ? num($b / 1048576, 1) . ' MB' : num($b / 1024, 0) . ' KB'; ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card kpi accent mb-3">
            <div class="kpi-label">Database size</div>
            <div class="kpi-value"><?= $fmtSize($dbSize) ?></div>
            <div class="kpi-foot"><?= number_format((int)$counts['orders']) ?> orders · <?= number_format((int)$counts['products']) ?> products · <?= number_format((int)$counts['customers']) ?> customers</div>
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h6 fw-bold">Create a backup</h2>
                <p class="small text-muted">Saves a complete SQL snapshot of all data on the server. Download it and keep a copy somewhere safe.</p>
                <form method="post" action="<?= url('backup/create') ?>"><?= csrf_field() ?><button class="btn btn-primary w-100"><i class="bi bi-database-add me-1"></i>Back up now</button></form>
            </div>
        </div>
        <div class="card border-danger-subtle">
            <div class="card-body">
                <h2 class="h6 fw-bold text-danger"><i class="bi bi-exclamation-octagon me-1"></i>Restore from file</h2>
                <p class="small text-muted">Replaces <strong>all</strong> current data. A safety backup is taken automatically first.</p>
                <form method="post" action="<?= url('backup/restore') ?>" enctype="multipart/form-data" data-confirm="This will overwrite all current data with the backup. Continue?" data-confirm-ok="Restore">
                    <?= csrf_field() ?>
                    <input type="file" class="form-control form-control-sm mb-2" name="sql_file" accept=".sql" required aria-label="Backup file">
                    <input class="form-control form-control-sm mb-2" name="confirm_text" placeholder="Type RESTORE to confirm" required autocomplete="off" aria-label="Type RESTORE to confirm">
                    <button class="btn btn-danger w-100 btn-sm">Upload &amp; restore</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header"><h2>Saved backups</h2><span class="small text-muted">Stored in <code>/backups</code> (web access blocked)</span></div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead><tr><th>File</th><th>Created</th><th class="text-end">Size</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($files as $f): ?>
                        <tr>
                            <td><i class="bi bi-filetype-sql text-muted me-2"></i><span class="cell-title"><?= e($f['name']) ?></span></td>
                            <td class="small"><?= fmt_datetime(date('Y-m-d H:i:s', $f['time'])) ?></td>
                            <td class="text-end small"><?= $fmtSize((float)$f['size']) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="<?= url('backup/download', ['file' => $f['name']]) ?>" class="btn-icon btn-sm" title="Download" aria-label="Download"><i class="bi bi-download"></i></a>
                                    <button class="btn-icon btn-sm" title="Restore" aria-label="Restore" data-bs-toggle="modal" data-bs-target="#restoreModal" data-file="<?= e($f['name']) ?>"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <form method="post" action="<?= url('backup/delete', ['file' => $f['name']]) ?>" data-confirm="Delete backup <?= e($f['name']) ?>?" data-confirm-ok="Delete"><?= csrf_field() ?><button class="btn-icon btn-sm danger" aria-label="Delete"><i class="bi bi-trash3"></i></button></form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$files): ?><tr><td colspan="4"><div class="empty-state"><div class="empty-icon"><i class="bi bi-database"></i></div><p class="mt-2 mb-0">No backups yet. Create one now.</p></div></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="restoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content" method="post" action="<?= url('backup/restore') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="file" id="restoreFile">
            <div class="modal-body p-4 text-center">
                <div class="confirm-icon mb-3"><i class="bi bi-arrow-counterclockwise"></i></div>
                <h5>Restore this backup?</h5>
                <p class="small text-muted" id="restoreName"></p>
                <p class="small">All current data will be replaced. Type <strong>RESTORE</strong> to confirm.</p>
                <input class="form-control text-center" name="confirm_text" required autocomplete="off" aria-label="Type RESTORE to confirm">
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger">Restore</button></div>
        </form>
    </div>
</div>
<script>
document.getElementById('restoreModal').addEventListener('show.bs.modal', (e) => {
    const f = e.relatedTarget.dataset.file;
    document.getElementById('restoreFile').value = f;
    document.getElementById('restoreName').textContent = f;
});
</script>
