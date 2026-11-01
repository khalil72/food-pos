<?php /** @var array $p from paginate() */ if ($p['pages'] <= 1) {
    if ($p['total'] > 0) echo '<div class="table-footer"><span>' . $p['total'] . ' record' . ($p['total'] === 1 ? '' : 's') . '</span></div>';
    return;
}
$start = max(1, $p['page'] - 2);
$end = min($p['pages'], $p['page'] + 2);
?>
<div class="table-footer">
    <span>Showing <?= $p['offset'] + 1 ?>–<?= min($p['offset'] + $p['per_page'], $p['total']) ?> of <?= $p['total'] ?></span>
    <nav aria-label="Pagination">
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $p['page'] <= 1 ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(query_with(['page' => $p['page'] - 1])) ?>" aria-label="Previous">&lsaquo;</a></li>
            <?php if ($start > 1): ?><li class="page-item"><a class="page-link" href="<?= e(query_with(['page' => 1])) ?>">1</a></li><?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; endif; ?>
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i === $p['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e(query_with(['page' => $i])) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <?php if ($end < $p['pages']): if ($end < $p['pages'] - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?><li class="page-item"><a class="page-link" href="<?= e(query_with(['page' => $p['pages']])) ?>"><?= $p['pages'] ?></a></li><?php endif; ?>
            <li class="page-item <?= $p['page'] >= $p['pages'] ? 'disabled' : '' ?>"><a class="page-link" href="<?= e(query_with(['page' => $p['page'] + 1])) ?>" aria-label="Next">&rsaquo;</a></li>
        </ul>
    </nav>
</div>
