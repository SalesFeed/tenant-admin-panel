<?php

// Instellingen van het beheerpanel voor de tenants van v2.

return array(
  // De codebase van v2: config/db.php voor de databases, bin/ voor de scripts.
  'v2' => '/var/www/v2',
  'php' => '/usr/bin/php',

  // De stand en het log van elke taak; alle nodes delen deze map.
  'jobs' => '/var/www/shared/admin/jobs',

  // Verhuizen heeft pas zin als switch.php voor alle hosts van de API staat.
  // Tot die bouwstap gedaan is, blijft de knop uit, met deze reden erbij.
  'verhuizen' => false,
  'verhuizen_uit' => 'Het tussenscript staat nog alleen voor api-dev. Zolang api.salesfeed.com en de klantdomeinen rechtstreeks naar de oude API gaan, blijft productie in tracker schrijven.',

  // Wat het panel mag starten: een script uit bin/ van v2 met het account-id.
  'acties' => array(
    'verhuizen' => array('titel' => 'Verhuizen', 'script' => 'bin/move-tenant.php'),
    'weggooien' => array('titel' => 'Halve kopie weggooien', 'script' => 'bin/drop-tenant.php'),
  ),
);
