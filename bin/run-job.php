<?php

// Voert een taak van het panel uit: php bin/run-job.php <id>. Het panel start
// dit los van het webverzoek. Zet de taak op bezig, schrijft de uitvoer van het
// script naar het log en sluit af met klaar (exitcode 0) of mislukt.

$config = require __DIR__ . '/../config.php';
require __DIR__ . '/../src/Jobs.php';

$jobs = new Jobs($config['jobs']);
$id = isset($argv[1]) ? $argv[1] : '';
$job = $jobs->get($id);
if (!$job || $job['status'] !== 'wacht') exit(1);

$jobs->update($id, array('status' => 'bezig', 'started' => time(), 'host' => gethostname(), 'pid' => getmypid()));

$log = fopen($jobs->logPath($id), 'a');
$proces = popen(implode(' ', array_map('escapeshellarg', $job['command'])) . ' 2>&1', 'r');
while (($regel = fgets($proces)) !== false) {
  fwrite($log, $regel);
  fflush($log);
}
$code = pclose($proces);
fclose($log);

$jobs->update($id, array('status' => $code === 0 ? 'klaar' : 'mislukt', 'exit' => $code, 'finished' => time()));
