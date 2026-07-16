<?php
$terms = authority_context(current_municipality_id());
$eventSingular = $terms['event_singular'] ?? 'Δράση';
?>
<h1 class="h3 mb-1"><?= e(t('team/applications.001', 'Οι Δηλώσεις μας')) ?></h1>
<p class="text-muted"><?= e(t('team/applications.002', 'Ιστορικό δηλώσεων συμμετοχής της ομάδας.')) ?></p>

<div class="card shadow-sm">
  <?php if (!$applications): ?>
    <div class="card-body text-muted"><?= e(t('team/applications.003', 'Δεν έχετε υποβάλει ακόμη δηλώσεις συμμετοχής.')) ?></div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th><?= e($eventSingular) ?></th><th><?= e(t('team/applications.004', 'Ημερομηνία')) ?> <?= e(mb_strtolower($eventSingular, 'UTF-8')) ?></th><th><?= e(t('team/applications.005', 'Προσφορά')) ?></th><th><?= e(t('team/applications.006', 'Εγκεκριμένα')) ?></th><th><?= e(t('team/applications.007', 'Κατάσταση')) ?></th><th></th></tr></thead>
        <tbody>
          <?php foreach ($applications as $a): ?>
            <tr>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= e(url('/team/events/' . $a['event_id'])) ?>"><?= e($a['event_title']) ?></a>
                <div class="small text-muted"><?= e($a['location_name'] ?: '') ?></div>
              </td>
              <td><?= e(gr_datetime($a['start_datetime'])) ?></td>
              <td><?= (int) $a['offered_people'] ?> <?= e(t('team/applications.008', 'άτομα')) ?></td>
              <td><?= $a['approved_people'] !== null ? (int) $a['approved_people'] : '—' ?></td>
              <td><?= status_badge($a['status']) ?></td>
              <td class="text-end">
                <?php if ($a['status'] === 'pending'): ?>
                  <form method="post" action="<?= e(url('/team/applications/' . $a['id'] . '/cancel')) ?>"
                        onsubmit="return confirm('Να ακυρωθεί η δήλωση συμμετοχής;')">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger"><?= e(t('team/applications.009', 'Ακύρωση')) ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
