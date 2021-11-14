<?php

use Blueflame\Drupal\SHMFileCacheBackend;
use Blueflame\Drupal\SMCServiceProvider;

if (!require __DIR__ . "/requirements.php") {
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