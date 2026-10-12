// Filteren en zoeken in de accounts, een rij openen, de bevestiging en het meekijken met een lopende taak.
(function () {
  var rijen = document.getElementById('rijen');
  var filter = '';
  var zoek = '';

  function toon() {
    var trs = rijen.getElementsByTagName('tr');
    for (var i = 0; i < trs.length; i++) {
      var tr = trs[i];
      var past = (!filter || tr.getAttribute('data-state') === filter) && (!zoek || tr.getAttribute('data-zoek').indexOf(zoek) !== -1);
      tr.hidden = !past;
    }
  }

  document.querySelectorAll('.chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      filter = chip.getAttribute('data-filter');
      document.querySelectorAll('.chip').forEach(function (c) { c.setAttribute('aria-pressed', String(c === chip)); });
      toon();
    });
  });
  document.getElementById('zoek').addEventListener('input', function (e) {
    zoek = e.target.value.trim().toLowerCase();
    toon();
  });

  // Een klik op de rij doet hetzelfde als de link in de eerste cel.
  rijen.addEventListener('click', function (e) {
    if (e.target.closest('a')) return;
    var tr = e.target.closest('tr');
    if (tr) location.href = tr.querySelector('a').getAttribute('href');
  });

  var gekozen = rijen.querySelector('tr[aria-selected="true"]');
  if (gekozen) gekozen.scrollIntoView({ block: 'nearest' });

  // De knop gaat pas aan als het account-id is getypt.
  document.querySelectorAll('input[data-moet]').forEach(function (input) {
    var knop = input.form.querySelector('button');
    input.addEventListener('input', function () { knop.disabled = input.value.trim() !== input.getAttribute('data-moet'); });
  });

  // Meekijken: het log bijwerken zolang de taak loopt, daarna de pagina opnieuw laden.
  var log = document.getElementById('log');
  if (log && log.getAttribute('data-loopt')) {
    var taak = log.getAttribute('data-taak');
    var kijk = function () {
      fetch('?log=' + encodeURIComponent(taak), { cache: 'no-store' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (!j) return;
          var onder = log.scrollTop + log.clientHeight >= log.scrollHeight - 4;
          log.textContent = j.log || '(nog geen uitvoer)';
          if (onder) log.scrollTop = log.scrollHeight;
          if (j.status === 'wacht' || j.status === 'bezig') setTimeout(kijk, 3000);
          else location.reload();
        })
        .catch(function () { setTimeout(kijk, 10000); });
    };
    log.scrollTop = log.scrollHeight;
    setTimeout(kijk, 3000);
  }
})();
