<?php
// De pagina van het panel. Variabelen uit public/index.php.

function mb($bytes) {
  return $bytes === null ? '' : number_format($bytes / 1048576, 0, ',', '.') . ' MB';
}
function datum($ts, $tijd = false) {
  return $ts ? date($tijd ? 'd-m-Y H:i' : 'd-m-Y', $ts) : '';
}
function pill($state, $standen) {
  return '<span class="pill s-' . $state . '">' . h($standen[$state]) . '</span>';
}
function taakregel($job, $config) {
  if (!$job) return '';
  $titel = isset($config['acties'][$job['action']]) ? $config['acties'][$job['action']]['titel'] : $job['action'];
  return $titel . ', ' . $job['status'] . ' ' . date('d-m H:i', $job['created']);
}

$stap = $job && $job['action'] === 'verhuizen' ? Tenants::stepInLog($log) : 0;
$loopt = $jobs->isRunning($job);
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tenants</title>
<link rel="stylesheet" href="assets/admin.css">
</head>
<body>
<div class="wrap">

  <header class="top">
    <div>
      <h1>Tenants</h1>
      <p>Accounts verhuizen van <code>tracker</code> naar een eigen schema in v2</p>
    </div>
    <?php foreach ($tenants->servers() as $s): $ps = isset($perServer[$s['name']]) ? $perServer[$s['name']] : array('schemas' => 0, 'bytes' => 0); ?>
    <div class="server">
      <span><span class="dot <?= $s['accepting'] ? '' : 'uit' ?>" aria-hidden="true"></span><b><?= h($s['name']) ?></b></span>
      <span class="k"><?= $s['accepting'] ? 'neemt nieuwe schema\'s aan' : 'neemt geen nieuwe schema\'s aan' ?></span>
      <span class="num"><span class="k">schema's</span> <?= $ps['schemas'] ?>, <?= mb($ps['bytes']) ?></span>
      <span class="num"><span class="k">vrij houden</span> <?= (int) $s['minfree_gb'] ?> GB</span>
    </div>
    <?php endforeach ?>
  </header>

  <section class="summary" aria-label="Stand van de accounts">
    <div class="legend num">
      <span><b><?= number_format(count($accounts), 0, ',', '.') ?></b> accounts</span>
      <?php foreach ($standen as $k => $titel): ?>
      <span><span class="sw s-<?= $k ?>"></span><b><?= number_format($telling[$k], 0, ',', '.') ?></b> <?= h(strtolower($titel)) ?></span>
      <?php endforeach ?>
    </div>
  </section>

  <div class="main">
    <section class="panel" aria-label="Accounts">
      <div class="panel-head">
        <div class="filters" role="group" aria-label="Filter op stand">
          <button class="chip" data-filter="" aria-pressed="true">Alle</button>
          <?php foreach ($standen as $k => $titel): ?>
          <button class="chip" data-filter="<?= $k ?>" aria-pressed="false"><?= h($titel) ?></button>
          <?php endforeach ?>
        </div>
        <input class="search" id="zoek" type="search" placeholder="Zoek op id, naam of aid" aria-label="Zoek een account">
      </div>
      <div class="tablewrap">
        <table>
          <thead><tr><th>Account</th><th>Stand</th><th>Schema</th><th class="num">Grootte</th><th>Sessies vanaf</th><th>Laatste taak</th></tr></thead>
          <tbody id="rijen">
          <?php foreach ($accounts as $id => $a): ?>
            <tr data-state="<?= $a['state'] ?>" data-zoek="<?= h(strtolower($id . ' ' . $a['companyname'] . ' ' . $a['aid'])) ?>"<?= $id === $gekozen ? ' aria-selected="true"' : '' ?>>
              <td><a href="?account=<?= $id ?>" class="name"><?= $id ?> · <?= h($a['companyname']) ?></a><div class="sub"><?= h($a['aid']) ?><?= $a['active'] ? '' : ' · niet actief' ?></div></td>
              <td><?= pill($a['state'], $standen) ?></td>
              <td><?= $a['dbname'] ? '<code>' . h($a['dbname']) . '</code><div class="sub">' . h($a['server']) . '</div>' : '<span class="sub">–</span>' ?></td>
              <td class="num"><?= $a['dbname'] ? mb($a['size']) : '<span class="sub">–</span>' ?></td>
              <td class="num"><?= $a['copysince'] ? datum($a['copysince']) : '<span class="sub">–</span>' ?></td>
              <td><?= isset($latest[$id]) ? h(taakregel($latest[$id], $config)) : '<span class="sub">–</span>' ?></td>
            </tr>
          <?php endforeach ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="panel" aria-label="Gekozen account">
      <div class="detail">
        <div class="detail-head">
          <h2><?= $gekozen ?> · <?= h($account['companyname']) ?></h2>
          <?= pill($account['state'], $standen) ?>
        </div>

        <dl class="facts num">
          <dt>aid</dt><dd><?= h($account['aid']) ?></dd>
          <?php if ($account['dbname']): ?>
          <dt>Schema</dt><dd><code><?= h($account['dbname']) ?></code> op <?= h($account['server']) ?>, <?= mb($account['size']) ?></dd>
          <?php endif ?>
          <?php if ($account['copysince']): ?><dt>Sessies vanaf</dt><dd><?= datum($account['copysince']) ?></dd><?php endif ?>
          <?php if ($account['copystartedon']): ?><dt>Kopie gestart</dt><dd><?= datum($account['copystartedon'], true) ?></dd><?php endif ?>
          <?php if ($liveRecordings !== null): ?><dt>LIVE-recordings</dt><dd><?= $liveRecordings ?> in tracker</dd><?php endif ?>
        </dl>

        <?php if ($fout): ?><div class="note danger" role="alert"><?= h($fout) ?></div><?php endif ?>
        <?php if ($gekozen === 3123): ?>
        <div class="note info">Dit schema is het origineel van <code>structure.sql</code> en <code>data.sql</code>. Een wijziging aan de opbouw die alleen hier staat, gaat bij weggooien verloren; daarom begint elke verhuizing met stap 1.</div>
        <?php endif ?>
        <?php if ($account['state'] === 'half'): ?>
        <div class="note half">Halve kopie: wat sinds de kopie uit tracker is verdwenen, staat hier nog. Bij verhuizen gaat dit schema weg en wordt het opnieuw opgebouwd.</div>
        <?php elseif ($account['state'] === 'pauze' && !$loopt): ?>
        <div class="note danger">Dit account staat op pauze (migrating = 1) zonder lopende taak. Tracking krijgt een leeg antwoord tot migrating weer 0 is.</div>
        <?php endif ?>

        <?php if ($account['live']): ?>
        <div class="note info">Dit account draait op v2. Terugzetten zit niet in het panel: wat na de omzetting in v2 is geschreven, staat niet in tracker.</div>
        <?php else: ?>
        <div><h3>Verhuizen</h3></div>
        <ol class="steps">
          <?php foreach (Tenants::STAPPEN as $n => $s):
            $cls = '';
            if ($stap) $cls = $n < $stap ? 'done' : ($n === $stap ? ($loopt ? 'busy' : ($job['status'] === 'mislukt' ? 'fail' : 'done')) : '');
            if (!$stap && $n === 4 && !$account['dbname']) $cls = 'skip';
          ?>
          <li class="<?= $cls ?>"><span class="mark" data-n="<?= $n ?>" aria-hidden="true"></span><div>
            <div class="step-title"><?= h($s[0]) ?><?= $cls === 'skip' ? ' <span class="step-text">· overgeslagen, geen schema</span>' : '' ?></div>
            <div class="step-text"><?= h($s[1]) ?></div>
          </div></li>
          <?php endforeach ?>
        </ol>

        <div class="actions">
          <?php if ($loopt): ?>
          <div class="feedback">Er loopt een taak voor dit account; de knoppen komen terug als hij klaar is.</div>
          <?php else: ?>
          <form method="post" class="confirm">
            <input type="hidden" name="actie" value="verhuizen"><input type="hidden" name="account" value="<?= $gekozen ?>">
            <label for="bevestig-verhuizen">Typ <?= $gekozen ?> om te bevestigen</label>
            <input id="bevestig-verhuizen" name="bevestiging" inputmode="numeric" autocomplete="off" data-moet="<?= $gekozen ?>"<?= $config['verhuizen'] ? '' : ' disabled' ?>>
            <button class="btn primary" disabled>Verhuizen naar v2</button>
          </form>
          <?php if (!$config['verhuizen']): ?><div class="feedback"><?= h($config['verhuizen_uit']) ?></div><?php endif ?>
          <?php if ($account['state'] === 'half'): ?>
          <form method="post" class="confirm">
            <input type="hidden" name="actie" value="weggooien"><input type="hidden" name="account" value="<?= $gekozen ?>">
            <label for="bevestig-weggooien">Typ <?= $gekozen ?> om alleen de halve kopie weg te gooien</label>
            <input id="bevestig-weggooien" name="bevestiging" inputmode="numeric" autocomplete="off" data-moet="<?= $gekozen ?>">
            <button class="btn danger" disabled>Halve kopie weggooien</button>
          </form>
          <?php endif ?>
          <?php endif ?>
        </div>
        <?php endif ?>

        <?php if ($job): ?>
        <div class="joblog">
          <h3><?= h(taakregel($job, $config)) ?><?= isset($job['exit']) && $job['exit'] !== 0 ? ', exitcode ' . (int) $job['exit'] : '' ?></h3>
          <pre class="log" id="log" data-taak="<?= h($job['id']) ?>" data-loopt="<?= $loopt ? '1' : '' ?>"><?= h($log !== '' ? $log : '(nog geen uitvoer)') ?></pre>
        </div>
        <?php endif ?>
      </div>
    </section>
  </div>

  <section class="panel" aria-label="Taken">
    <div class="panel-head"><h2>Taken</h2><span class="feedback">Elke taak draait als eigen proces; het log blijft bewaard.</span></div>
    <div class="tablewrap">
      <table class="jobs">
        <thead><tr><th>Taak</th><th>Account</th><th>Gestart</th><th class="num">Duur</th><th>Stand</th><th>Door</th></tr></thead>
        <tbody>
        <?php $alle = array_slice($jobs->all(), 0, 25); if (!$alle): ?>
          <tr><td colspan="6" class="sub">Nog geen taken.</td></tr>
        <?php endif; foreach ($alle as $j): $eind = isset($j['finished']) ? $j['finished'] : time(); $begin = isset($j['started']) ? $j['started'] : $j['created']; ?>
          <tr>
            <td><?= h(isset($config['acties'][$j['action']]) ? $config['acties'][$j['action']]['titel'] : $j['action']) ?></td>
            <td><a href="?account=<?= (int) $j['account_id'] ?>"><?= (int) $j['account_id'] ?><?= isset($accounts[$j['account_id']]) ? ' · ' . h($accounts[$j['account_id']]['companyname']) : '' ?></a></td>
            <td class="num"><?= datum($j['created'], true) ?></td>
            <td class="num"><?= round(($eind - $begin) / 60) ?> min</td>
            <td><span class="pill j-<?= h($j['status']) ?>"><?= h($j['status']) ?></span></td>
            <td><?= h($j['by']) ?></td>
          </tr>
        <?php endforeach ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<script src="assets/admin.js"></script>
</body>
</html>
