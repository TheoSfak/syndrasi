/* SynDrasi - PWA: service worker registration + install banner (bottom-right) */
(function () {
  'use strict';

  /* ---- 1. Register the service worker --------------------------------- */
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      var swPath = (window.baseUrl || '') + '/service-worker.js';
      navigator.serviceWorker.register(swPath).catch(function (err) {
        console.warn('Service worker registration failed:', err);
      });
    });
  }

  /* ---- 2. Install banner ---------------------------------------------- */
  var DISMISS_KEY = 'syndrasi_install_dismissed';
  var DISMISS_DAYS = 7;             // re-offer after a week if dismissed
  var deferredPrompt = null;

  function isStandalone() {
    return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) ||
           window.navigator.standalone === true;
  }

  function dismissedRecently() {
    try {
      var t = parseInt(localStorage.getItem(DISMISS_KEY), 10);
      if (!t) { return false; }
      return (Date.now() - t) < DISMISS_DAYS * 864e5;
    } catch (e) { return false; }
  }

  function rememberDismissal() {
    try { localStorage.setItem(DISMISS_KEY, String(Date.now())); } catch (e) {}
  }

  function isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
  }

  function removeBanner() {
    var el = document.getElementById('syndrasi-install');
    if (el) { el.parentNode.removeChild(el); }
  }

  function buildBanner(opts) {
    // opts: { body, actionLabel|null, onAction|null }
    removeBanner();

    var wrap = document.createElement('div');
    wrap.id = 'syndrasi-install';
    wrap.className = 'syndrasi-install-banner';
    wrap.setAttribute('role', 'dialog');
    wrap.setAttribute('aria-label', 'Εγκατάσταση εφαρμογής SynDrasi');

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'syndrasi-install-close';
    close.setAttribute('aria-label', 'Κλείσιμο');
    close.innerHTML = '&times;';
    close.addEventListener('click', function () { rememberDismissal(); removeBanner(); });

    var icon = document.createElement('div');
    icon.className = 'syndrasi-install-icon';
    icon.innerHTML = '<i class="bi bi-phone"></i>';

    var content = document.createElement('div');
    content.className = 'syndrasi-install-content';

    var title = document.createElement('div');
    title.className = 'syndrasi-install-title';
    title.textContent = 'Εγκατάσταση SynDrasi';

    var body = document.createElement('div');
    body.className = 'syndrasi-install-body';
    body.innerHTML = opts.body;

    content.appendChild(title);
    content.appendChild(body);

    if (opts.actionLabel) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'syndrasi-install-btn';
      btn.innerHTML = '<i class="bi bi-download me-1"></i>' + opts.actionLabel;
      btn.addEventListener('click', opts.onAction);
      content.appendChild(btn);
    }

    wrap.appendChild(close);
    wrap.appendChild(icon);
    wrap.appendChild(content);
    document.body.appendChild(wrap);

    // Animate in
    requestAnimationFrame(function () { wrap.classList.add('is-visible'); });
  }

  function showInstallBanner() {
    buildBanner({
      body: 'Προσθέστε την εφαρμογή στην αρχική οθόνη για γρήγορη πρόσβαση χωρίς browser.',
      actionLabel: 'Εγκατάσταση',
      onAction: function () {
        if (!deferredPrompt) { return; }
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(function (choice) {
          if (choice && choice.outcome === 'dismissed') { rememberDismissal(); }
          deferredPrompt = null;
          removeBanner();
        });
      }
    });
  }

  function showIosBanner() {
    buildBanner({
      body: 'Πατήστε <i class="bi bi-box-arrow-up"></i> <strong>Κοινή χρήση</strong> και μετά ' +
            '<strong>«Προσθήκη στην αρχική οθόνη»</strong>.',
      actionLabel: null,
      onAction: null
    });
  }

  function maybeShow() {
    if (isStandalone() || dismissedRecently()) { return; }
    if (isIos()) {
      // iOS Safari fires no beforeinstallprompt — show manual instructions.
      setTimeout(showIosBanner, 1500);
    }
  }

  // Android / desktop Chromium: capture the install prompt.
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    if (isStandalone() || dismissedRecently()) { return; }
    showInstallBanner();
  });

  // Clean up once installed.
  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    rememberDismissal();
    removeBanner();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', maybeShow);
  } else {
    maybeShow();
  }
})();
