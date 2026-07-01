<?php
$terms = authority_context(current_municipality_id());
$eventSingular = $terms['event_singular'] ?? 'Δράση';
$orgLabel = $terms['short_name'] ?? 'Φορέας';
?>
<div class="d-flex align-items-center mb-3 gap-2">
  <a href="<?= e(url('/mobilizations')) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h1 class="h3 mb-0">Νέο Κάλεσμα Έκτακτης Ανάγκης</h1>
    <p class="text-muted small mb-0">Θα σταλεί άμεση ειδοποίηση στους εθελοντές που θα επιλέξετε.</p>
  </div>
</div>

<div class="alert alert-warning small">
  <i class="bi bi-exclamation-triangle me-1"></i>
  Το κάλεσμα ξεκινά <strong>αμέσως μόλις αποθηκευτεί</strong> και ειδοποιεί τους εθελοντές. Χρησιμοποιήστε το μόνο για πραγματικές ανάγκες.
</div>

<form method="post" action="<?= e(url('/mobilizations')) ?>">
  <?= csrf_field() ?>
  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card shadow-sm mb-3">
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Τίτλος / Περιστατικό <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required maxlength="255"
                   value="<?= e(old('title')) ?>" placeholder="π.χ. Πυρκαγιά στον Δήμο — άμεση συνδρομή">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Περιγραφή</label>
            <textarea name="description" class="form-control" rows="3"
                      placeholder="Σύντομες οδηγίες, σημείο συγκέντρωσης, τι να φέρουν…"><?= e(old('description')) ?></textarea>
          </div>
          <div class="row g-3">
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Σοβαρότητα</label>
              <select name="severity" class="form-select">
                <?php foreach (['critical','high','medium','low'] as $sev): ?>
                  <option value="<?= $sev ?>" <?= old('severity','high') === $sev ? 'selected' : '' ?>>
                    <?= e(severity_label($sev)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6">
              <label class="form-label fw-semibold">Σχετική <?= e(mb_strtolower($eventSingular, 'UTF-8')) ?> (προαιρετικά)</label>
              <select name="event_id" class="form-select">
                <option value="">— Καμία —</option>
                <?php foreach ($events as $ev): ?>
                  <option value="<?= (int) $ev['id'] ?>"><?= e($ev['title']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-body">
          <label class="form-label fw-semibold">Τοποθεσία (προαιρετικά)</label>
          <input type="text" name="location_name" class="form-control mb-2"
                 value="<?= e(old('location_name')) ?>" placeholder="Σημείο συγκέντρωσης / περιοχή">
          <div class="row g-2">
            <div class="col"><input type="text" name="latitude" class="form-control" placeholder="Γεωγρ. πλάτος" value="<?= e(old('latitude')) ?>"></div>
            <div class="col"><input type="text" name="longitude" class="form-control" placeholder="Γεωγρ. μήκος" value="<?= e(old('longitude')) ?>"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-semibold">
          <i class="bi bi-people me-1"></i>Ποιους να καλέσω
        </div>
        <div class="card-body">
          <p class="small text-muted">Αφήστε τα όλα κενά για να κληθεί όλος ο φορέας (<?= e($orgLabel) ?>), ή επιλέξτε συγκεκριμένες ομάδες.</p>
          <?php if (empty($teams)): ?>
            <p class="text-muted small mb-0">Δεν υπάρχουν ενεργές ομάδες.</p>
          <?php else: ?>
            <?php foreach ($teams as $t): ?>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="team_ids[]" value="<?= (int) $t['id'] ?>" id="team<?= (int) $t['id'] ?>">
                <label class="form-check-label" for="team<?= (int) $t['id'] ?>"><?= e($t['name']) ?></label>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      <div class="d-grid mt-3">
        <button type="submit" class="btn btn-danger btn-lg">
          <i class="bi bi-broadcast-pin me-1"></i>Έναρξη Καλέσματος
        </button>
      </div>
    </div>
  </div>
</form>
