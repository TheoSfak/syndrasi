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
<?php if (is_logged_in()): ?>
<script>
(function () {
  'use strict';
  var BASE = window.baseUrl || '';
  var btn  = document.getElementById('pushBtn');
  var icon = document.getElementById('pushIcon');
  if (!btn) return;
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) { return; }

  var reg = null;

  function urlB64ToUint8Array(b64) {
    var pad = '='.repeat((4 - b64.length % 4) % 4);
    var raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
    var arr = new Uint8Array(raw.length);
    for (var i = 0; i < raw.length; i++) { arr[i] = raw.charCodeAt(i); }
    return arr;
  }

  function setSubscribed(sub) {
    if (sub) {
      icon.className = 'bi bi-bell-fill';
      btn.title = 'Push ΟΝ — κλικ για απενεργοποίηση';
      btn.style.color = '#4ade80';
    } else {
      icon.className = 'bi bi-bell-slash';
      btn.title = 'Ενεργοποίηση push ειδοποιήσεων';
      btn.style.color = '';
    }
  }

  function serverPost(path, sub) {
    fetch(BASE + path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(sub.toJSON ? sub.toJSON() : sub),
    }).catch(function () {});
  }

  navigator.serviceWorker.ready.then(function (swReg) {
    reg = swReg;
    btn.classList.remove('d-none');
    swReg.pushManager.getSubscription().then(setSubscribed);
  });

  btn.addEventListener('click', function () {
    if (!reg) { return; }
    reg.pushManager.getSubscription().then(function (sub) {
      if (sub) {
        sub.unsubscribe().then(function () { setSubscribed(null); serverPost('/push/unsubscribe', sub); });
        return;
      }
      fetch(BASE + '/push/vapid-key')
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (!d.key) { return; }
          return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToUint8Array(d.key) });
        })
        .then(function (newSub) {
          if (!newSub) { return; }
          setSubscribed(newSub);
          serverPost('/push/subscribe', newSub);
        })
        .catch(function (e) {
          if (Notification.permission === 'denied') {
            alert('Έχετε μπλοκάρει τις ειδοποιήσεις. Αλλάξτε το στις ρυθμίσεις του browser.');
          }
        });
    });
  });
})();
</script>
<?php endif; ?>
</body>
</html>
