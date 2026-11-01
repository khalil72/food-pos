<div class="empty-state py-5">
    <div class="empty-icon"><i class="bi <?= $code === 403 ? 'bi-shield-lock' : 'bi-signpost-split' ?>"></i></div>
    <h2 class="h4 mt-3"><?= $code === 403 ? 'You don’t have access to this page' : 'We couldn’t find that page' ?></h2>
    <p class="text-muted"><?= $code === 403 ? 'Your role does not include this area. Ask an administrator if you need access.' : 'The link may be broken or the item may have been removed.' ?></p>
    <a href="<?= url(Auth::check() ? Auth::homeRoute() : 'login') ?>" class="btn btn-primary">Go back home</a>
</div>
