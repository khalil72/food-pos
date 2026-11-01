<?php
$extra = '<select name="user" class="form-select form-select-sm" style="width:auto" aria-label="User"><option value="">All users</option>'
    . implode('', array_map(fn($u) => '<option value="' . $u['id'] . '" ' . selected(input('user'), $u['id']) . '>' . e($u['name']) . '</option>', $users)) . '</select>'
    . '<select name="action" class="form-select form-select-sm" style="width:auto" aria-label="Action"><option value="">All actions</option>'
    . implode('', array_map(fn($a) => '<option value="' . e($a) . '" ' . selected(input('action'), $a) . '>' . e(ucwords(str_replace('_', ' ', $a))) . '</option>', $actions)) . '</select>'
    . '<input type="search" name="q" value="' . e(input('q', '')) . '" class="form-control form-control-sm" style="width:160px" placeholder="Search details / IP">';
View::partial('date_filter', compact('from', 'to', 'preset', 'extra'));
$tone = fn(string $a) => match (true) {
    str_contains($a, 'delete') || str_contains($a, 'failed') || str_contains($a, 'refund') || str_contains($a, 'restore') => 'danger',
    str_contains($a, 'login') || str_contains($a, 'logout') => 'info',
    str_contains($a, 'order') => 'success',
    str_contains($a, 'register') || str_contains($a, 'cash') => 'warning',
    default => 'secondary',
};
?>
<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover table-sm">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="small text-nowrap"><?= fmt_datetime($l['created_at']) ?></td>
                    <td><?= e($l['user_name'] ?? 'System / guest') ?></td>
                    <td><span class="badge badge-soft-<?= $tone($l['action']) ?>"><?= e(ucwords(str_replace('_', ' ', $l['action']))) ?></span><?php if ($l['entity']): ?><div class="cell-sub"><?= e($l['entity']) ?><?= $l['entity_id'] ? ' #' . (int)$l['entity_id'] : '' ?></div><?php endif; ?></td>
                    <td class="small"><?= e($l['details'] ?: '—') ?></td>
                    <td class="small text-muted"><?= e($l['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="5"><div class="empty-state">No activity recorded for these filters.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['p' => $p]); ?>
</div>
