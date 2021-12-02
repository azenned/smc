<?php

namespace Blueflame\Cache;

/**
 * Class APCu compatibility layer
 *
 * @package Blueflame\Cache
 */
final class APCuSHM extends SHMStorage {


  function __construct() {
    if (SMC_DEBUG) {
      echo "new APCuSHM instance.\n";
    }
    parent::__construct('apcu', FALSE, intval($GLOBALS['smc-memsize-apcu'] ?? 128));
  }

  static function getInstance() {
    static $instance;
    if (is_null($instance)) {
      $instance = new APCuSHM();
    }
    return $instance;
  }

  function apcu_add($key, $value = NULL, $ttl = 0) {
    if (SMC_DEBUG) {
      echo "apcu_add " . print_r($key, TRUE) . "\n";
    }
    if (is_array($key)) {
      return $this->setMultiple($key, $ttl, FALSE, FALSE);
    }
    return $this->set($key, $value, $ttl, FALSE, FALSE);
  }

  function apcu_delete($key) {
    if (SMC_DEBUG) {
      echo "apcu_delete " . print_r($key, TRUE) . "\n";
    }
    if ($key instanceof APCuSHMIterator) {
      return $this->deleteMultiple((array) $key->getKeys());
    }
    if (is_array($key)) {
      return $this->deleteMultiple($key);
    }
    return $this->delete($key);
  }

  function apcu_exists($key) {
    if (is_array($key)) {
      $list = $this->hasKey($key);
      return array_fill_keys($list, TRUE);
    }
    return $this->hasKey($key);
  }

  function apcu_fetch($key, &$success = NULL) {
    return $this->get($key, $success);
  }

  function apcu_store($key, $value = NULL, $ttl = 0) {
    if (SMC_DEBUG) {
      echo "apcu_store " . print_r($key, TRUE) . "\n";
    }
    if (is_array($key)) {
      return $this->setMultiple($key, $ttl, TRUE, FALSE);
    }
    return $this->set($key, $value, $ttl, TRUE, FALSE);
  }

  function apcu_cache_info($limited = FALSE) {
    return $this->getCacheInfo($limited);
  }

  function apcu_cas($key, $old, $new) {
    return $this->cas($key, $old, $new);
  }

  function apcu_clear_cache() {
    if (SMC_DEBUG) {
      echo "apcu_clear_cache \n";
    }
    return $this->clear_cache();
  }

  function apcu_dec($key, $step = 1, &$success = FALSE) {
    return $this->decrement($key, $step, $success);
  }

  function apcu_inc($key, $step = 1, &$success = FALSE) {
    return $this->increment($key, $step, $success);
  }

  function apcu_sma_info($limited = FALSE) {
    return $this->getCacheInfo($limited);
  }
}
