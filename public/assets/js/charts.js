/* SynDrasi - Chart.js dashboard charts */

document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') { return; }

  var monthsEl = document.getElementById('eventsByMonthChart');
  if (monthsEl && monthsEl.dataset.values) {
    var values = JSON.parse(monthsEl.dataset.values);
    new Chart(monthsEl, {
      type: 'bar',
      data: {
        labels: ['Ιαν', 'Φεβ', 'Μαρ', 'Απρ', 'Μαϊ', 'Ιουν', 'Ιουλ', 'Αυγ', 'Σεπ', 'Οκτ', 'Νοε', 'Δεκ'],
        datasets: [{
          label: 'Δράσεις',
          data: values,
          backgroundColor: '#0f766e'
        }]
      },
      options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  var catEl = document.getElementById('eventsByCategoryChart');
  if (catEl && catEl.dataset.labels) {
    var labels = JSON.parse(catEl.dataset.labels);
    var catValues = JSON.parse(catEl.dataset.values);
    new Chart(catEl, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: catValues,
          backgroundColor: ['#0f766e', '#0ea5e9', '#f59e0b', '#ef4444', '#8b5cf6', '#10b981', '#64748b']
        }]
      },
      options: { plugins: { legend: { position: 'bottom' } } }
    });
  }
});
