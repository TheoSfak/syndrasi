/* SynDrasi - Leaflet maps (event location + operational map) */

var syndrasiOpMap = null;
var syndrasiOpMarkers = [];
var syndrasiOpEventId = null;

document.addEventListener('DOMContentLoaded', function () {
  initSimpleEventMap();
  initOperationalMap();
});

/** Static map with one marker (event detail pages). */
function initSimpleEventMap() {
  var el = document.getElementById('eventMap');
  if (!el || typeof L === 'undefined') { return; }
  var lat = parseFloat(el.dataset.lat);
  var lng = parseFloat(el.dataset.lng);
  if (isNaN(lat) || isNaN(lng)) { return; }

  var map = L.map(el).setView([lat, lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(map);
  L.marker([lat, lng]).addTo(map).bindPopup(el.dataset.title || '');
}

/** Live operational map with team pings, auto-refresh. */
function initOperationalMap() {
  var el = document.getElementById('operationalMap');
  if (!el || typeof L === 'undefined') { return; }

  syndrasiOpEventId = el.dataset.eventId;
  var lat = parseFloat(el.dataset.lat);
  var lng = parseFloat(el.dataset.lng);
  var zoom = 15;
  // If event has no coordinates, fall back to municipality defaults, then Greece
  if (isNaN(lat) || isNaN(lng)) {
    lat  = parseFloat(el.dataset.defaultLat)  || 38.0;
    lng  = parseFloat(el.dataset.defaultLng)  || 23.7;
    zoom = parseInt(el.dataset.defaultZoom, 10) || 13;
  }

  syndrasiOpMap = L.map(el).setView([lat, lng], zoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap'
  }).addTo(syndrasiOpMap);

  if (!isNaN(parseFloat(el.dataset.lat))) {
    L.marker([lat, lng], { title: el.dataset.title })
      .addTo(syndrasiOpMap)
      .bindPopup('<strong>' + (el.dataset.title || 'Δράση') + '</strong><br>Σημείο δράσης');
  }

  refreshOperationalMap();

  var refreshSeconds = parseInt(el.dataset.refresh || '45', 10);
  if (refreshSeconds > 0) {
    setInterval(refreshOperationalMap, refreshSeconds * 1000);
  }
}

function refreshOperationalMap() {
  if (!syndrasiOpMap || !syndrasiOpEventId) { return; }

  fetch(window.baseUrl + '/operations/events/' + syndrasiOpEventId + '/locations', {
    headers: { 'Accept': 'application/json' }
  })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (!data.success) { return; }

      syndrasiOpMarkers.forEach(function (m) { syndrasiOpMap.removeLayer(m); });
      syndrasiOpMarkers = [];

      data.teams.forEach(function (t) {
        if (t.latitude === null || t.longitude === null) { return; }
        var marker = L.marker([t.latitude, t.longitude], { title: t.team_name });
        var html = '<strong>' + t.team_name + '</strong><br>' +
          t.status_label + '<br>' +
          'Άτομα: ' + (t.present_people !== null ? t.present_people : '—') + ' / ' + t.approved_people + '<br>' +
          '<span class="text-muted">Στίγμα: ' + (t.last_ping_at || '—') + '</span>';
        marker.bindPopup(html);
        marker.addTo(syndrasiOpMap);
        syndrasiOpMarkers.push(marker);
      });
    })
    .catch(function () { /* silent */ });
}
