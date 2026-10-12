<?php

// Beheerpanel voor de tenants van v2: welke accounts nog in tracker staan, welke
// een halve kopie hebben en welke live zijn, en het verhuizen zelf. Het panel
// heeft geen eigen logica voor de schema's: het start de scripts uit bin/ van
// v2 als taak (bin/run-job.php) en toont hun log.
//
//   GET  /?account=<id>   overzicht, met dat account geopend
//   GET  /?log=<taak>     stand en log van een taak als JSON, om mee te kijken
//   POST /                een taak starten: actie, account en de bevestiging

date_default_timezone_set('Europe/Amsterdam');

$config = require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Jobs.php';
require __DIR__ . '/../src/Tenants.php';

$db = require $config['v2'] . '/config/db.php';
$opties = array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC);
$tenants = new Tenants(
  new PDO("mysql:host={$db['central']['host']};charset=utf8mb4", $db['central']['user'], $db['central']['password'], $opties),
  new PDO("mysql:host={$db['admin']['host']};charset=utf8mb4", $db['admin']['user'], $db['admin']['password'], $opties)
);
$jobs = new Jobs($config['jobs']);

function h($s) {
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

if (isset($_GET['log'])) {
  $job = $jobs->get((string) $_GET['log']);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($job ? array('status' => $job['status'], 'log' => $jobs->log($job['id'])) : null);
  exit;
}

$accounts = array();
foreach ($tenants->accounts() as $a) $accounts[(int) $a['id']] = $a;
$latest = $jobs->latestPerAccount();

// Start een taak; geeft een foutmelding, of null en stuurt dan door.
function start($config, $accounts, $latest, $jobs) {
  // Alleen een formulier van deze pagina zelf.
  $bron = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
  $p = parse_url($bron);
  $herkomst = isset($p['host']) ? $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '') : '';
  if ($herkomst !== $_SERVER['HTTP_HOST']) return 'Dit verzoek kwam niet van deze pagina.';

  $actie = isset($_POST['actie']) ? (string) $_POST['actie'] : '';
  $id = isset($_POST['account']) ? (string) $_POST['account'] : '';
  if (!isset($config['acties'][$actie])) return 'Onbekende actie.';
  if (!ctype_digit($id) || !isset($accounts[(int) $id])) return 'Onbekend account.';
  if ((isset($_POST['bevestiging']) ? trim($_POST['bevestiging']) : '') !== $id) return "Typ $id om te bevestigen.";

  $account = $accounts[(int) $id];
  $job = isset($latest[(int) $id]) ? $latest[(int) $id] : null;
  if ($jobs->isRunning($job)) return 'Voor dit account loopt al een taak.';
  if ($account['live']) return 'Dit account staat live.';
  if ($actie === 'verhuizen' && !$config['verhuizen']) return $config['verhuizen_uit'];
  if ($actie === 'weggooien' && !$account['dbname']) return 'Dit account heeft geen schema.';

  $wie = !empty($_SERVER['REMOTE_USER']) ? $_SERVER['REMOTE_USER'] : $_SERVER['REMOTE_ADDR'];
  $taak = $jobs->create($actie, $id, array($config['php'], $config['v2'] . '/' . $config['acties'][$actie]['script'], $id), $wie);
  exec(escapeshellarg($config['php']) . ' ' . escapeshellarg(__DIR__ . '/../bin/run-job.php') . ' ' . escapeshellarg($taak) . ' > /dev/null 2>&1 &');

  header('Location: ?account=' . $id, true, 303);
  return null;
}

$fout = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fout = start($config, $accounts, $latest, $jobs);
  if ($fout === null) exit;
}

// Het geopende account: uit de url of het formulier, anders het eerste met een schema.
$gekozen = isset($_REQUEST['account']) ? (int) $_REQUEST['account'] : 0;
if (!isset($accounts[$gekozen])) {
  $gekozen = 0;
  foreach ($accounts as $id => $a) if ($a['dbname']) { $gekozen = $id; break; }
  if (!$gekozen) $gekozen = key($accounts);
}

$standen = array('tracker' => 'In tracker', 'half' => 'Halve kopie', 'pauze' => 'Pauze', 'bezig' => 'Bezig', 'live' => 'Live in v2');
$telling = array_fill_keys(array_keys($standen), 0);
foreach ($accounts as $id => $a) {
  $accounts[$id]['state'] = Tenants::state($a, isset($latest[$id]) ? $latest[$id] : null);
  $telling[$accounts[$id]['state']]++;
}

$account = $accounts[$gekozen];
$job = isset($latest[$gekozen]) ? $latest[$gekozen] : null;
$log = $job ? $jobs->log($job['id']) : '';
$liveRecordings = $account['live'] ? null : $tenants->liveRecordings($gekozen);

$perServer = array();
foreach ($accounts as $a) {
  if (!$a['server']) continue;
  if (!isset($perServer[$a['server']])) $perServer[$a['server']] = array('schemas' => 0, 'bytes' => 0);
  $perServer[$a['server']]['schemas']++;
  $perServer[$a['server']]['bytes'] += (int) $a['size'];
}

require __DIR__ . '/../views/page.php';
