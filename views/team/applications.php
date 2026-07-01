<?php
$terms = authority_context(current_municipality_id());
$eventSingular = $terms['event_singular'] ?? 'Δράση';
?>
<h1 class="h3 mb-1">Οι Δηλώσεις μας</h1>
<p class="text-muted">Ιστορικό δηλώσεων συμμετοχής της ομάδας.</p>

<div class="card shadow-sm">
  <?php if (!$applications): ?>
    <div class="card-body text-muted">Δεν έχετε υποβάλει ακόμη δηλώσεις συμμετοχής.</div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th><?= e($eventSingular) ?></th><th>Ημερομηνία <?= e(mb_strtolower($eventSingular, 'UTF-8')) ?></th><th>Προσφορά</th><th>Εγκεκριμένα</th><th>Κατάσταση</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($applications as $a): ?>
            <tr>
              <td>
                <a class="text-decoration-none fw-semibold" href="<?= e(url('/team/events/' . $a['event_id'])) ?>"><?= e($a['event_title']) ?></a>
                <div class="small text-muted"><?= e($a['location_name'] ?: '') ?></div>
              </td>
              <td><?= e(gr_datetime($a['start_datetime'])) ?></td>
              <td><?= (int) $a['offered_people'] ?> άτομα</td>
              <td><?= $a['approved_people'] !== null ? (int) $a['approved_people'] : '—' ?></td>
              <td><?= status_badge($a['status']) ?></td>
              <td class="text-end">
                <?php if ($a['status'] === 'pending'): ?>
                  <form method="post" action="<?= e(url('/team/applications/' . $a['id'] . '/cancel')) ?>"
                        onsubmit="return confirm('Να ακυρωθεί η δήλωση συμμετοχής;')">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-danger">Ακύρωση</button>
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
