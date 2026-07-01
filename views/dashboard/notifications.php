<?php
$terms = authority_context(current_municipality_id());
$eventPluralLc = $terms['event_plural_lc'] ?? 'δράσεις';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-1">
  <h1 class="h3 mb-0">Ειδοποιήσεις</h1>
  <form method="post" action="<?= e(url('/notifications/read-all')) ?>">
    <?= csrf_field() ?>
    <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-check2-all me-1"></i>Όλες ως αναγνωσμένες</button>
  </form>
</div>
<p class="text-muted">Ενημερώσεις για <?= e($eventPluralLc) ?>, δηλώσεις και επιχειρησιακά συμβάντα.</p>

<div class="card shadow-sm">
  <?php if (!$notifications): ?>
    <div class="card-body text-muted">Δεν υπάρχουν ειδοποιήσεις.</div>
  <?php else: ?>
    <ul class="list-group list-group-flush">
      <?php foreach ($notifications as $n): ?>
        <li class="list-group-item <?= $n['is_read'] ? '' : 'bg-info-subtle' ?>">
          <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
              <strong><?= e($n['title']) ?></strong>
              <?php if (!$n['is_read']): ?><span class="badge text-bg-info ms-1">Νέα</span><?php endif; ?>
              <div class="small text-muted mt-1" style="white-space:pre-line"><?= e($n['message']) ?></div>
              <div class="small text-muted mt-1"><?= e(gr_datetime($n['created_at'])) ?></div>
            </div>
            <?php if (!$n['is_read']): ?>
              <form method="post" action="<?= e(url('/notifications/' . $n['id'] . '/read')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-secondary">Αναγνωσμένη</button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
