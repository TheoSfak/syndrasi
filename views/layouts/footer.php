    </main>
  </div>
</div>

<footer class="text-center text-muted small py-3 border-top bg-white">
  <?= e($config['footer_text']) ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="<?= e(url('/assets/js/app.js')) ?>"></script>
<script src="<?= e(url('/assets/js/maps.js')) ?>"></script>
<script src="<?= e(url('/assets/js/charts.js')) ?>"></script>
<script src="<?= e(url('/assets/js/pwa.js')) ?>"></script>
<?php if (is_log