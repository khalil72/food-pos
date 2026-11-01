<?php
/** @var string $from @var string $to @var string $preset  Optional: $extra (html), $exports (bool) */
$presets = ['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => '7 days', '30d' => '30 days', 'month' => 'This month', 'last_month' => 'Last month', 'year' => 'This year'];
$keep = array_diff_key($_GET, array_flip(['range', 'from', 'to', 'page', 'export']));
?>
<form method="get" action="<?= e(base_url() . '/index.php') ?>" class="filter-bar">
    <?php foreach ($keep as $k => $v): if (is_string($v)): ?>
        <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
    <?php endif; endforeach; ?>
    <div class="segmented" role="group" aria-label="Date range">
        <?php foreach ($presets as $key => $label): ?>
            <a href="<?= e(query_with(['range' => $key, 'from' => null, 'to' => null, 'page' => null])) ?>" class="<?= $preset === $key ? 'active' : '' ?>"><?= $label ?></a>
        <?php endforeach; ?>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <input type="date" name="from" value="<?= e($from) ?>" class="form-control form-control-sm" aria-label="From date" style="width:auto">
        <span class="text-muted small">to</span>
        <input type="date" name="to" value="<?= e($to) ?>" class="form-control form-control-sm" aria-label="To date" style="width:auto">
        <?= $extra ?? '' ?>
        <button class="btn btn-sm btn-dark"><i class="bi bi-funnel"></i> Apply</button>
        <?php if (!empty($exports)): ?>
            <a href="<?= e(query_with(['export' => 'csv'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-filetype-csv"></i> CSV</a>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-pdf-export="#report-area" data-pdf-name="<?= e(($pdfName ?? 'report') . "-$from-$to") ?>"><i class="bi bi-filetype-pdf"></i> PDF</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
        <?php endif; ?>
    </div>
</form>
