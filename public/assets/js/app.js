/* SynDrasi - shared application JS */

/**
 * Send the team location manually (Geolocation API).
 * Called from the team operational page button.
 */
function sendTeamLocation(eventId) {
  var resultEl = document.getElementById('locationResult');
  var btn = document.getElementById('sendLocationBtn');

  if (!navigator.geolocation) {
    alert('Η συσκευή δεν υποστηρίζει αποστολή τοποθεσίας.');
    return;
  }

  if (btn) { btn.disabled = true; }
  if (resultEl) { resultEl.textContent = 'Λήψη θέσης...'; resultEl.className = 'small mt-2 text-muted'; }

  navigator.geolocation.getCurrentPosition(function (position) {
    fetch(window.baseUrl + '/team/operations/events/' + eventId + '/send-location', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-Token': window.csrfToken
      },
      body: JSON.stringify({
        event_id: eventId,
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy: position.coords.accuracy
      })
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (btn) { btn.disabled = false; }
        if (data.success) {
          if (resultEl) {
            resultEl.textContent = 'Το στίγμα στάλθηκε επιτυχώς (' + new Date().toLocaleTimeString('el-GR') + ').';
            resultEl.className = 'small mt-2 text-success';
          } else {
            alert('Το στίγμα στάλθηκε επιτυχώς.');
          }
        } else {
          var msg = data.message || 'Δεν ήταν δυνατή η αποστολή στίγματος.';
          if (resultEl) { resultEl.textContent = msg; resultEl.className = 'small mt-2 text-danger'; }
          else { alert(msg); }
        }
      })
      .catch(function () {
        if (btn) { btn.disabled = false; }
        var msg = 'Σφάλμα σύνδεσης. Προσπαθήστε ξανά.';
        if (resultEl) { resultEl.textContent = msg; resultEl.className = 'small mt-2 text-danger'; }
        else { alert(msg); }
      });
  }, function () {
    if (btn) { btn.disabled = false; }
    var msg = 'Δεν δόθηκε άδεια πρόσβασης στην τοποθεσία.';
    if (resultEl) { resultEl.textContent = msg; resultEl.className = 'small mt-2 text-danger'; }
    else { alert(msg); }
  }, { enableHighAccuracy: true, timeout: 15000 });
}
