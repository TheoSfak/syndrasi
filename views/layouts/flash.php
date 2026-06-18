<?php foreach (flash_get() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show d-flex align-items-start gap-2" role="alert" data-flash-type="<?= e($f['type']) ?>">
    <?php
      $icon = match($f['type']) {
          'success' => 'bi-check-circle-fill',
          'danger'  => 'bi-x-circle-fill',
          'warning' => 'bi-exclamation-triangle-fill',
          default   => 'bi-info-circle-fill',
      };
    ?>
    <i class="bi <?= $icon ?> flex-shrink-0 mt-1"></i>
    <span><?= e($f['message']) ?></span>
    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Κλείσιμο"></button>
  </div>
<?php endforeach; ?>
<script>
(function () {
  document.querySelectorAll('.alert[data-flash-type]').forEach(function (el) {
    if (el.dataset.flashType !== 'danger') {
      setTimeout(function () {
        var bs = typeof bootstrap !== 'undefined' && bootstrap.Alert ? new bootstrap.Alert(el) : null;
        if (bs) { bs.close(); } else { el.remove(); }
      }, 5000);
    }
  });
})();
</script>
