<?php
if (extension_loaded('apc') or extension_loaded('apcu')) {
  return;
}

if (!require_once __DIR__ . "/requirements.php") {
  return;
}
defaults('SMC_DEBUG', FALSE);


require_once __DIR__ . '/Cache/SHMStorage.php';
require_once __DIR__ . '/Cache/APCuSHM.php';
require_once __DIR__ . '/Cache/APCuSHMIterator.php';

use Blueflame\Cache\APCuSHM;
use Blueflame\Cache\APCuSHMIterator;


/**
 * implements emulate APC / APCu functions
 */
if (function_exists('shm_attach')
  && !function_exists('apcu_fetch')) {
  try {
    APCuSHM::getInstance();
  } catch (\Exception $exception) {
    return;
  }
  define('APC_ITER_ALL', -1);
  define('APC_ITER_ATIME', 2048);
  define('APC_ITER_CTIME', 512);
  define('APC_ITER_DEVICE', 8);
  define('APC_ITER_DTIME', 1024);
  define('APC_ITER_FILENAME', 4);
  define('APC_ITER_INODE', 16);
  define('APC_ITER_KEY', 2);
  define('APC_ITER_MD5', 64);
  define('APC_ITER_MEM_SIZE', 8192);
  define('APC_ITER_MTIME', 256);
  define('APC_ITER_NONE', 0);
  define('APC_ITER_NUM_HITS', 128);
  define('APC_ITER_REFCOUNT', 4096);
  define('APC_ITER_TTL', 16384);
  define('APC_ITER_TYPE', 1);
  define('APC_ITER_VALUE', 32);
  define('APC_LIST_ACTIVE', 1);
  define('APC_LIST_DELETED', 2);

  class APCuIterator extends APCuSHMIterator {

    public function __construct($search = NULL, $format = APC_ITER_ALL, $chunk_size = 100, $list = APC_LIST_ACTIVE) {
      parent::__construct($search, $format, $chunk_size, $list);
    }

  }

  function apcu_add($key, $value = NULL, $ttl = 0) {
    return APCuSHM::getInstance()->apcu_add($key, $value, $ttl);
  }

  function apcu_cache_info($limited = FALSE) {
    return APCuSHM::getInstance()->apcu_cache_info($limited);
  }

  function apcu_cas($key, $old, $new) {
    return APCuSHM::getInstance()->apcu_cas($key, $old, $new);
  }

  function apcu_clear_cache() {
    return APCuSHM::getInstance()->apcu_clear_cache();
  }

  function apcu_dec($key, $step = 1, &$success = FALSE) {
    return APCuSHM::getInstance()->apcu_dec($key, $step, $success);
  }

  function apcu_delete($key) {
    return APCuSHM::getInstance()->apcu_delete($key);
  }

  function apcu_exists($key) {
    return APCuSHM::getInstance()->apcu_exists($key);
  }

  function apcu_fetch($key, &$success = NULL) {
    return APCuSHM::getInstance()->apcu_fetch($key, $success);
  }

  function apcu_inc($key, $step = 1, &$success = FALSE) {
    return APCuSHM::getInstance()->apcu_inc($key, $step, $success);
  }

  function apcu_sma_info($limited = FALSE) {
    return APCuSHM::getInstance()->apcu_sma_info($limited);
  }

  function apcu_store($key, $value = NULL, $ttl = 0) {
    return APCuSHM::getInstance()->apcu_store($key, $value, $ttl);
  }

  if (function_exists('rename_function')) {
    // rename_function is provided by APD extension
    rename_function('extension_loaded', 'native_extension_loaded');
    function extension_loaded(string $extension): bool {
      if ($extension === 'apcu') {
        return TRUE;
      }
      return native_extension_loaded($extension);
    }
  }
}
elseif (function_exists('shm_attach')
  && function_exists('apcu_fetch')) {
  if (php_sapi_name() == "cli") {
    echo "SMC is used to apc(u) on cli cause apc(u) populate \n";
    echo "and destroy the APC cache on every CLI request even.\n";
    echo "if apc.enable_cli is true.\n";
    echo "Check you php.ini for cli and remove apc(u) extension.\n";
  }
}

defaults('SMC_DASHBOARD', FALSE);
if (SMC_DASHBOARD && ($_SERVER['DOCUMENT_URI'] ?? '') === '/smc_stats') {
  require_once __DIR__ . '/smc_stats.php';
  exit();
}
