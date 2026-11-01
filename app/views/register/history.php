<?php View::partial('date_filter', compact('from', 'to', 'preset')); ?>
<div class="card table-card">
    <div class="card-header"><h2>Register sessions</h2></div>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead><tr><th>#</th><th>Staff</th><th>Opened</th><th>Closed</th><th class="text-center">Orders</th><th class="text-end">Sales</th><th class="text-end">Expected</th><th class="text-end">Counted</th><th class="text-end">Difference</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= (int)$r['id'] ?></td>
                    <td class="cell-title"><?= e($r['user_name']) ?></td>
                    <td class="small"><?= fmt_datetime($r['opened_at']) ?></td>
                    <td class="small"><?= $r['status'] === 'open' ? status_badge('open') : fmt_datetime($r['closed_at']) ?></td>
                    <td class="text-center"><?= (int)$r['orders'] ?></td>
                    <td class="text-end"><?= money($r['sales']) ?></td>
                    <td class="text-end"><?= $r['expected_cash'] !== null ? money($r['expected_cash']) : '—' ?></td>
                    <td class="text-end"><?= $r['closing_cash'] !== null ? money($r['closing_cash']) : '—' ?></td>
                    <td class="text-end fw-semibold <?= (float)$r['difference'] < 0 ? 'text-danger' : ((float)$r['difference'] > 0 ? 'text-primary' : 'text-success') ?>"><?= $r['difference'] !== null ? money($r['difference']) : '—' ?></td>
                    <td class="text-end"><a href="<?= url('register/view', ['id' => $r['id']]) ?>" class="btn-icon btn-sm" aria-label="View"><i class="bi bi-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="10"><div class="empty-state">No register sessions in this period.</div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php View::partial('pagination', ['p' => $p]); ?>
</div>
