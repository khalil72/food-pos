<?php
$fmt = function ($v, string $type) {
    return match ($type) {
        'money'  => money($v),
        'int'    => number_format((int)$v),
        'qty'    => qty($v),
        'pct'    => num($v, 1) . '%',
        'date'   => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$v) ? fmt_date($v) : e($v),
        'status' => '<span class="badge badge-soft-' . ($v === 'OK' ? 'success' : ($v === 'Low' ? 'warning' : 'danger')) . '">' . e($v) . '</span>',
        default  => e($v),
    };
};
$pdfName = $slug;
$exports = true;
?>
<div class="d-flex align-items-center gap-2 mb-3 no-print"><a href="<?= url('reports') ?>" class="btn btn-light btn-sm"><i class="bi bi-arrow-left me-1"></i>All reports</a></div>
<?php View::partial('date_filter', compact('from', 'to', 'preset', 'exports', 'pdfName')); ?>

<div id="report-area">
    <div class="print-only mb-3">
        <h2 class="h4 mb-0"><?= e(setting('restaurant_name')) ?> — <?= e($title) ?></h2>
        <div class="text-muted"><?= e(range_label($from, $to)) ?> · Generated <?= fmt_datetime(date('Y-m-d H:i:s')) ?></div>
    </div>
    <div class="row g-3 mb-3">
        <?php foreach ($cards as [$label, $value]): ?>
            <div class="col-6 col-md-4 col-xl"><div class="card kpi"><div class="kpi-label"><?= e($label) ?></div><div class="kpi-value" style="font-size:1.25rem"><?= $value ?></div></div></div>
        <?php endforeach; ?>
    </div>

    <?php if ($chart): ?>
        <div class="card mb-3"><div class="card-body"><div class="chart-box <?= $chart['type'] === 'doughnut' ? 'sm' : '' ?>"><canvas id="reportChart" role="img" aria-label="<?= e($title) ?> chart"></canvas></div></div></div>
    <?php endif; ?>

    <?php foreach ($sections as $i => $s): ?>
        <div class="card table-card mb-3">
            <div class="card-header"><h2><?= e($s['title']) ?></h2><?php if ($i === 0): ?><span class="small text-muted"><?= count($s['rows']) ?> rows</span><?php endif; ?></div>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead><tr><?php foreach ($s['columns'] as [$label, $type]): ?><th class="<?= in_array($type, ['money', 'int', 'qty', 'pct'], true) ? 'text-end' : '' ?>"><?= e($label) ?></th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php foreach ($s['rows'] as $row): ?>
                        <tr><?php foreach ($s['columns'] as $key => [$label, $type]): ?><td class="<?= in_array($type, ['money', 'int', 'qty', 'pct'], true) ? 'text-end' : '' ?>"><?= $fmt($row[$key] ?? '', $type) ?></td><?php endforeach; ?></tr>
                    <?php endforeach; ?>
                    <?php if (!$s['rows']): ?><tr><td colspan="<?= count($s['columns']) ?>"><div class="empty-state py-4">No data for this period.</div></td></tr><?php endif; ?>
                    </tbody>
                    <?php if (!empty($s['footer']) && $s['rows']): ?>
                        <tfoot><tr><?php foreach ($s['columns'] as $key => [$label, $type]): ?><td class="<?= in_array($type, ['money', 'int', 'qty', 'pct'], true) ? 'text-end' : '' ?>"><?= array_key_exists($key, $s['footer']) ? (in_array($type, ['money', 'int', 'qty', 'pct'], true) ? $fmt($s['footer'][$key], $type) : e($s['footer'][$key])) : '' ?></td><?php endforeach; ?></tr></tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if ($note): ?><p class="small text-muted"><i class="bi bi-info-circle me-1"></i><?= e($note) ?></p><?php endif; ?>
</div>

<?php if ($chart): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const c = <?= json_encode($chart, JSON_HEX_TAG) ?>;
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#71685F';
    const doughnut = c.type === 'doughnut';
    new Chart(document.getElementById('reportChart'), {
        type: c.type,
        data: { labels: c.labels, datasets: c.series.map(([label, data, color]) => ({ label, data, backgroundColor: color, borderColor: doughnut ? '#fff' : color, borderWidth: doughnut ? 3 : 0, borderRadius: doughnut ? 0 : 6, maxBarThickness: 26 })) },
        options: {
            maintainAspectRatio: false, indexAxis: c.horizontal ? 'y' : 'x', cutout: doughnut ? '65%' : undefined,
            plugins: { legend: { display: doughnut || c.series.length > 1, position: doughnut ? 'right' : 'top', labels: { usePointStyle: true, boxWidth: 8 } },
                tooltip: { callbacks: { label: (ctx) => ` ${ctx.dataset.label === 'Expenses' || doughnut ? ctx.label : ctx.dataset.label}: ${APP.money(doughnut ? ctx.parsed : (c.horizontal ? ctx.parsed.x : ctx.parsed.y))}` } } },
            scales: doughnut ? {} : { x: { grid: { display: !!c.horizontal, color: '#F1ECE5' }, beginAtZero: true }, y: { grid: { display: !c.horizontal, color: '#F1ECE5' }, beginAtZero: true } },
        },
    });
});
</script>
<?php endif; ?>
