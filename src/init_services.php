<?php
if (!defined('SMC_DEBUG')) {
  define('SMC_DEBUG', FALSE);
}

if (!require __DIR__ . "/requirements.php") {
  return;
}
require_once __DIR__ . '/Cache/SHMStorage.php';
require_once __DIR__ . '/Cache/APCuSHM.php';
require_once __DIR__ . '/Cache/APCuSHMIterator.php';

use Blueflame\Cache\APCuSHM;
use Blueflame\Drupal\SHMFileCacheBackend;
use Blueflame\Drupal\SMCServiceProvider;

try {
  APCuSHM::getInstance();
} catch (\Exception $exception) {
  return;
}

// Override Drupal services
$GLOBALS['conf']['container_service_providers'][] = SMCServiceProvider::class;

// Intercept Cache rebuild.
function file_rebuild() {
  if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
  }
  SHMFileCacheBackend::reset();
}