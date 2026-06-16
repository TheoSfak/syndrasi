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

<!-- App-wide live notifications: bell badge + toast popups -->
<div id="toastZone" style="position:fixed;top:70px;right:16px;z-index:3000;display:flex;flex-direction:column;gap:8px;max-width:340px"></div>
<script>
(function () {
  'use strict';
  var BASE  = window.baseUrl || '';
  var badge = document.getElementById('navNotifBadge');
  var zone  = document.getElementById('toastZone');
  var seenMax = 0, primed = false;

  function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

  function iconFor(type) {
    if (type === 'sos') return 'bi-exclamation-octagon-fill';
    if (type === 'photo_request') return 'bi-camera-fill';
    if (type === 'ops_message') return 'bi-chat-dots-fill';
    if (type === 'shortage' || type === 'shortage_update') return 'bi-exclamation-triangle-fill';
    return 'bi-bell-fill';
  }

  function showToast(n) {
    var el = document.createElement('div');
    el.style.cssText = 'background:#0f766e;color:#fff;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.25);overflow:hidden;animation:none';
    if (n.type === 'sos') { el.style.background = '#b91c1c'; }
    el.innerHTML = '<div class="d-flex align-items-start">' +
      '<div class="p-2 ps-3 pt-2"><i class="bi ' + iconFor(n.type) + '" style="font-size:1.1rem"></i></div>' +
      '<div class="p-2 flex-grow-1"><div class="fw-bold small">' + esc(n.title) + '</div>' +
      '<div class="small" style="opacity:.92">' + esc(n.message) + '</div></div>' +
      '<button type="button" class="btn-close btn-close-white m-2" aria-label="Close"></button></div>';
    el.querySelector('.btn-close').addEventListener('click', function () { el.remove(); });
    el.addEventListener('click', function (e) { if (!e.target.closest('.btn-close')) { window.location.href = BASE + '/notifications'; } });
    el.style.cursor = 'pointer';
    zone.appendChild(el);
    setTimeout(function () { el.remove(); }, 9000);
  }

  function poll() {
    fetch(BASE + '/notifications/poll', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) return;
        if (badge) {
          if (d.count > 0) { badge.textContent = d.count; badge.style.display = ''; }
          else { badge.style.display = 'none'; }
        }
        var items = d.items || [];
        var maxId = seenMax;
        items.forEach(function (n) { if (n.id > maxId) maxId = n.id; });
        if (primed) {
          items.filter(function (n) { return n.id > seenMax; })
               .sort(function (a, b) { return a.id - b.id; })
               .forEach(showToast);
        }
        seenMax = maxId; primed = true;
      })
      .catch(function () {});
  }
  poll();
  setInterval(poll, 15000);
})();
</script>
<?php endif; ?>
</body>
</html>
