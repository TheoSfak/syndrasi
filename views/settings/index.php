<h1 class="h3 mb-1">Ρυθμίσεις Πλατφόρμας</h1>
<p class="text-muted">Γενικές ρυθμίσεις της εφαρμογής SynDrasi.</p>

<form method="post" action="<?= e(url('/admin/settings')) ?>" class="card shadow-sm" style="max-width:640px">
  <?= csrf_field() ?>
  <div class="card-body">
    <div class="mb-3">
      <label class="form-label">Ανακοίνωση πλατφόρμας</label>
      <textarea name="platform_announcement" class="form-control" rows="3"
                placeholder="Προαιρετικό μήνυμα προς όλους τους χρήστες"><?= e(isset($settings['platform_announcement']) ? $settings['platform_announcement'] : '') ?></textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Email υποστήριξης</label>
      <input type="email" name="support_email" class="form-control"
             value="<?= e(isset($settings['support_email']) ? $settings['support_email'] : '') ?>">
    </div>
  </div>
  <div class="card-footer bg-white">
    <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Αποθήκευση</button>
  </div>
</form>
