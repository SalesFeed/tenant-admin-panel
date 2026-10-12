<?php

// De taken van het panel. Elke taak is een bestand <id>.json met zijn stand en
// een bestand <id>.log met de uitvoer, in een map die alle nodes delen.
// bin/run-job.php voert een taak uit, los van het webverzoek dat hem maakte.
//
// Stand: wacht, bezig, klaar of mislukt.

class Jobs {

  private $dir;

  public function __construct($dir) {
    $this->dir = $dir;
  }

  // Maakt een taak die $command uitvoert en geeft zijn id.
  public function create($action, $accountId, array $command, $by) {
    $id = date('Ymd-His') . '-' . bin2hex(openssl_random_pseudo_bytes(3));
    $this->write($id, array(
      'id' => $id,
      'action' => $action,
      'account_id' => (int) $accountId,
      'command' => $command,
      'by' => $by,
      'created' => time(),
      'status' => 'wacht',
    ));
    return $id;
  }

  public function get($id) {
    if (!preg_match('/^\d{8}-\d{6}-[0-9a-f]{6}$/', $id)) return null;
    $file = "{$this->dir}/$id.json";
    return is_file($file) ? json_decode(file_get_contents($file), true) : null;
  }

  public function update($id, array $fields) {
    $this->write($id, array_merge($this->get($id), $fields));
  }

  // Alle taken, de nieuwste eerst.
  public function all() {
    $jobs = array();
    foreach (glob("{$this->dir}/*.json") ?: array() as $file) {
      $job = json_decode(file_get_contents($file), true);
      if ($job) $jobs[] = $job;
    }
    usort($jobs, function($a, $b) { return strcmp($b['id'], $a['id']); });
    return $jobs;
  }

  // De nieuwste taak per account.
  public function latestPerAccount() {
    $latest = array();
    foreach ($this->all() as $job) {
      if (!isset($latest[$job['account_id']])) $latest[$job['account_id']] = $job;
    }
    return $latest;
  }

  public function isRunning($job) {
    return $job && in_array($job['status'], array('wacht', 'bezig'), true);
  }

  public function logPath($id) {
    return "{$this->dir}/$id.log";
  }

  public function log($id) {
    $file = $this->logPath($id);
    return is_file($file) ? file_get_contents($file) : '';
  }

  // Eerst een tijdelijk bestand, dan hernoemen: wie leest, ziet nooit een half bestand.
  private function write($id, array $job) {
    $tmp = "{$this->dir}/.$id.json." . getmypid();
    file_put_contents($tmp, json_encode($job));
    rename($tmp, "{$this->dir}/$id.json");
  }
}
