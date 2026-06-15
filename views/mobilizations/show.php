<?php $refresh = (int) ($config['map_refresh_seconds'] ?? 45); ?>
<div class="d-flex flex-wrap align-items-center mb-3 gap-2">
  <a href="<?= e(url('/mobilizations')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1">
    <h1 class="h3 mb-0">
      <?= e($mob['title']) ?>
      <span class="badge text-bg-<?= e(status_color($mob['severity'])) ?> align-middle"><?= e(severity_label($mob['severity'])) ?></span>
    </h1>
    <p class="text-muted small mb-0">
      <?php if ($mob['location_name']): ?><i class="bi bi-geo-alt me-1"></i><?= e($mob['location_name']) ?> · <?php endif; ?>
      Έναρξη <?= e(gr_datetime($mob['started_at'])) ?>
      <?php if ($mob['creator_name']): ?> · από <?= e($mob['creator_name']) ?><?php endif; ?>
    </p>
  </div>
  <?php if ($mob['status'] !== 'stood_down'): ?>
    <span class="badge text-bg-danger fs-6"><i class="bi bi-broadcast me-1"></i>Ενεργό</span>
    <form method="post" action="<?= e(url('/mobilizations/' . $mob['id'] . '/stand-down')) ?>"
          onsubmit="return confirm('Κλείσιμο του καλέσματος;');">
      <?= csrf_field() ?>
      <button class="btn btn-outline-danger"><i class="bi bi-stop-circle me-1"></i>Λήξη Καλέσματος</button>
    </form>
  <?php else: ?>
    <span class="badge text-bg-secondary fs-6">Έκλεισε <?= e(gr_time($mob['ended_at'])) ?></span>
  <?php endif; ?>
</div>

<?php if ($mob['description']): ?>
  <div class="alert alert-light border small mb-3"><?= nl2br(e($mob['description'])) ?></div>
<?php endif; ?>

<!-- Live counters -->
<div class="row g-2 mb-4" id="counters">
  <?php
    $cards = [
      ['confirmed', 'Δήλωσαν «Έρχομαι»', 'success'],
      ['en_route',  'Καθ’ οδόν',          'info'],
      ['on_site',   'Στο σημείο',         'primary'],
      ['departed',  'Αποχώρησαν',         'dark'],
      ['declined',  'Δεν μπορούν',        'secondary'],
      ['no_reply',  'Χωρίς απάντηση',     'warning'],
    ];
    $c = $snapshot['counts'] ?? [];
  ?>
  <?php foreach ($cards as [$key, $label, $color]): ?>
    <div class="col-6 col-md-2">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body py-3">
          <div class="display-6 fw-bold text-<?= $color ?>" data-count="<?= $key ?>"><?= (int) ($c[$key] ?? 0) ?></div>
          <div class="small text-muted"><?= $label ?></div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h2 class="h5 mb-0">Εθελοντές (<span data-count="targeted"><?= (int) ($c['targeted'] ?? 0) ?></span>)</h2>
  <span class="small text-muted">Αυτόματη ανανέωση μετρητών · <span id="updatedAt"><?= e($snapshot['at'] ?? '') ?></span></span>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Εθελοντής</th><th>Ομάδα</th><th>Απάντηση</th><th class="text-center">ETA</th><th>Κατάσταση</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach (($snapshot['roster'] ?? []) as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e($r['member_name']) ?></td>
            <td class="small text-muted"><?= e($r['team_name']) ?></td>
            <td>
              <?php if ($r['response'] === 'coming'): ?><span class="badge text-bg-success">Έρχεται</span>
              <?php elseif ($r['response'] === 'cant'): ?><span class="badge text-bg-secondary">Δεν μπορεί</span>
              <?php elseif ($r['response'] === 'maybe'): ?><span class="badge text-bg-info">Ίσως</span>
              <?php else: ?><span class="badge text-bg-warning">Αναμονή</span><?php endif; ?>
            </td>
            <td class="text-center"><?= $r['eta_minutes'] !== null ? (int) $r['eta_minutes'] . '′' : '—' ?></td>
            <td>
              <?php if ($r['departed_at']): ?><span class="text-muted small">Αποχώρησε <?= e(gr_time($r['departed_at'])) ?></span>
              <?php elseif ($r['checked_in_at']): ?><span class="text-primary small"><i class="bi bi-check-circle-fill"></i> Στο σημείο <?= e(gr_time($r['checked_in_at'])) ?></span>
              <?php else: ?><span class="text-muted small">—</span><?php endif; ?>
            </td>
            <td class="text-end">
              <?php if ($mob['status'] !== 'stood_down'): ?>
                <?php if (!$r['checked_in_at']): ?>
                  <form method="post" action="<?= e(url('/mobilizations/' . $mob['id'] . '/checkin')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="response_id" value="<?= (int) $r['id'] ?>">
                    <button class="btn btn-xs btn-outline-primary btn-sm py-0">Άφιξη</button>
                  </form>
                <?php elseif (!$r['departed_at']): ?>
                  <form method="post" action="<?= e(url('/mobilizations/' . $mob['id'] . '/checkin')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="response_id" value="<?= (int) $r['id'] ?>">
                    <input type="hidden" name="action" value="depart">
                    <button class="btn btn-outline-dark btn-sm py-0">Αποχώρηση</button>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var STREAM = <?= json_encode(url('/mobilizations/' . $mob['id'] . '/stream')) ?>;
  var EVERY  = <?= $refresh ?> * 1000;
  function poll() {
    fetch(STREAM, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (s) {
        if (!s || !s.ok) return;
        Object.keys(s.counts || {}).forEach(function (k) {
          document.querySelectorAll('[data-count="' + k + '"]').forEach(function (el) { el.textContent = s.counts[k]; });
        });
        var u = document.getElementById('updatedAt');
        if (u && s.at) u.textContent = s.at;
      })
      .catch(function () {});
  }
  setInterval(poll, EVERY);
})();
</script>
