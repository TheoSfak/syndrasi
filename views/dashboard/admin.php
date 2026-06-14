<div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
  <div>
    <h1 class="h3 mb-0">Πίνακας Ελέγχου Πλατφόρμας</h1>
    <p class="text-muted mb-0">Συνολική εικόνα χρήσης της πλατφόρμας SynDrasi.</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= e(url('/admin/municipalities')) ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-building me-1"></i>Δήμοι
    </a>
    <a href="<?= e(url('/admin/users')) ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-people me-1"></i>Χρήστες
    </a>
  </div>
</div>

<!-- ── Global stat cards ─────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-primary-subtle text-primary mb-2"><i class="bi bi-building"></i></div>
        <div class="stat-value"><?= (int) $counts['municipalities'] ?></div>
        <div class="text-muted small">Δήμοι
          <span class="badge text-bg-success ms-1"><?= (int) $counts['active_municipalities'] ?> ενεργοί</span>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-success-subtle text-success mb-2"><i class="bi bi-people"></i></div>
        <div class="stat-value"><?= (int) $counts['teams'] ?></div>
        <div class="text-muted small">Εθελοντικές ομάδες</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-info-subtle text-info mb-2"><i class="bi bi-person-check"></i></div>
        <div class="stat-value"><?= (int) $counts['users'] ?></div>
        <div class="text-muted small">Χρήστες</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-warning-subtle text-warning mb-2"><i class="bi bi-calendar-event"></i></div>
        <div class="stat-value"><?= (int) $counts['events'] ?></div>
        <div class="text-muted small">Δράσεις συνολικά</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-primary-subtle text-primary mb-2"><i class="bi bi-calendar-check"></i></div>
        <div class="stat-value"><?= (int) $counts['events_year'] ?></div>
        <div class="text-muted small">Δράσεις φέτος</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-2">
    <div class="card stat-card h-100">
      <div class="card-body">
        <div class="stat-icon bg-success-subtle text-success mb-2"><i class="bi bi-clipboard-check"></i></div>
        <div class="stat-value"><?= (int) $counts['applications'] ?></div>
        <div class="text-muted small">Δηλώσεις συμμετοχής</div>
      </div>
    </div>
  </div>
</div>

<!-- ── Per-municipality usage + audit log ─────────────────────────── -->
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-building me-1"></i> Χρήση ανά δήμο</span>
        <a href="<?= e(url('/admin/municipalities')) ?>" class="btn btn-sm btn-outline-primary">Διαχείριση</a>
  