<?php

namespace Blueflame\Drupal;

use Blueflame\Cache\APCuSHM;
use Blueflame\Cache\SHMStorage;
use Drupal\Component\FileCache\FileCacheBackendInterface;

/**
 * Allows to cache data based on file modification dates in a shared memory cache.
 */
class SHMFileCacheBackend implements FileCacheBackendInterface {

  /**
   * Bin used for storing the data in the shared memory.
   *
   * @var APCuSHM
   */
  private $store;

  /**
   * Constructs a PHP Storage CacheSHM backend.
   *
   * @param array $configuration
   *   (optional) Configuration used to configure this object.
   */
  public function __construct($configuration) {
    if (!require_once __DIR__ . "/../requirements.php") {
      return;
    }
    try {
      $this->store = APCuSHM::getInstance(); // new SHMStorage('SHMFileCacheBackend', FALSE, intval($GLOBALS['smc-memsize-default'] ?? 128));
    } catch (\Exception $e) {
      // Error
      unset($this->store);
    }
  }

  public static function reset() {
    if (!require_once __DIR__ . "/../requirements.php") {
      return;
    }
    if (php_sapi_name() == "cli") {
      echo "SMC reset file_cache .\n";
    }
    try {
      $store = new SHMStorage('SHMFileCacheBackend', FALSE, intval($GLOBALS['smc-memsize-default'] ?? 128));
      $store->clear_cache(TRUE);
      APCuSHM::getInstance()->clear_cache(TRUE);
    } catch (\Exception $e) {
    }
  }

  public function fetch(array $cids) {
    if (!isset($this->store)) {
      return NULL;
    }
    return $this->store->get($cids);
  }

  public function store($cid, $data) {
    if (!isset($this->store)) {
      return NULL;
    }
    return $this->store->set($cid, $data);
  }

  public function delete($cid) {
    if (!isset($this->store)) {
      return NULL;
    }
    return $this->store->delete($cid);
  }
}
