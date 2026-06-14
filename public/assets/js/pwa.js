/* SynDrasi - PWA service worker registration */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    var swPath = (window.baseUrl || '') + '/service-worker.js';
    navigator.serviceWorker.register(swPath).catch(function (err) {
      console.warn('Service worker registration failed:', err);
    });
  });
}
