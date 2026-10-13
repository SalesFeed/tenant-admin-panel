<?php

// De stand van de accounts voor het panel: de actieve accounts uit tracker, met
// hun schema uit shared.tenantconfig en de grootte daarvan. $central is de
// gebruiker v2 (tracker en shared), $admin de gebruiker v2admin (de schema's).

class Tenants {

  // De stappen van bin/move-tenant.php, in volgorde.
  const STAPPEN = array(
    1 => array('Opbouw controleren', 'schema/structure.sql moet overeenkomen met sf_3123, anders stopt de verhuizing.'),
    2 => array('Pauze', 'migrating = 1. Tracking krijgt een leeg antwoord, de rest van de API 503.'),
    3 => array('Wachten op de archiver', 'Tot het account in tracker geen LIVE-recordings meer heeft. Meestal binnen een uur.'),
    4 => array('Halve kopie weggooien', 'Schema, MySQL-gebruiker en de rij in shared.tenantconfig.'),
    5 => array('Schema aanmaken', 'Opbouw uit schema/structure.sql.'),
    6 => array('Kopiëren', 'Configuratie en parties volledig, sessies van het laatste jaar.'),
    7 => array('Omzetten', 'version = 2 en migrating = 0. Vanaf dan gaat het account naar v2.'),
  );

  private $central;
  private $admin;

  public function __construct(PDO $central, PDO $admin) {
    $this->central = $central;
    $this->admin = $admin;
  }

  // Elk actief account, en elk account met een schema, op id.
  public function accounts() {
    $rows = $this->central->query(
      'SELECT a.id, a.aid, a.companyname, a.active, a.migrating, a.version,
              s.dbname, s.copysince, s.copystartedon, d.name AS server
       FROM tracker.account a
       LEFT JOIN shared.tenantconfig s ON s.account_id = a.id
       LEFT JOIN shared.dbserver d ON d.id = s.dbserver_id
       WHERE a.active = 1 OR s.account_id IS NOT NULL
       ORDER BY a.id'
    )->fetchAll();
    $sizes = $this->sizes();
    foreach ($rows as &$row) {
      $row['size'] = $row['dbname'] && isset($sizes[$row['dbname']]) ? $sizes[$row['dbname']] : null;
    }
    return $rows;
  }

  // De stand van een account: live (op v2), pauze, bezig, half of tracker.
  public static function state(array $account, $job) {
    if ($account['version'] == 2) return 'live';
    if ($account['migrating']) return 'pauze';
    if ($job && in_array($job['status'], array('wacht', 'bezig'), true)) return 'bezig';
    if ($account['dbname']) return 'half';
    return 'tracker';
  }

  public function servers() {
    return $this->central->query('SELECT id, name, minfree_gb, accepting FROM shared.dbserver ORDER BY id')->fetchAll();
  }

  public function liveRecordings($accountId) {
    $q = $this->central->prepare("SELECT COUNT(*) FROM tracker.session_recording WHERE accountid = ? AND status = 'LIVE'");
    $q->execute(array($accountId));
    return (int) $q->fetchColumn();
  }

  // De grootte van elk tenant-schema in bytes, op naam.
  private function sizes() {
    $sizes = array();
    $q = $this->admin->query(
      "SELECT table_schema, SUM(data_length + index_length) AS bytes
       FROM information_schema.tables WHERE table_schema LIKE 'sf\\_%' GROUP BY table_schema"
    );
    foreach ($q as $row) $sizes[$row['table_schema']] = (int) $row['bytes'];
    return $sizes;
  }

  // Hoe ver een verhuizing is, uit de regels "<tijd>  <stap> ..." van
  // bin/move-tenant.php: de hoogste stap die begonnen is.
  public static function stepInLog($log) {
    $stap = 0;
    foreach (explode("\n", $log) as $regel) {
      if (preg_match('/^\d\d:\d\d:\d\d  ([1-7]) /', $regel, $m)) $stap = max($stap, (int) $m[1]);
    }
    return $stap;
  }
}
