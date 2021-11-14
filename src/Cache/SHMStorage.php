<?php

namespace Blueflame\Cache;

use Exception;

class SHMStorage {

  const CACHE_PERMANENT = -1;

  const IndexKey = -1;

  const stateKey = -2;

  const TagsMapKey = -3;

  static $counter = 0;

  private $currentIndex = [];

  private $currentTagsMap = [];

  private $currentRev = 0;

  /**
   * Bin used for storing the data in the shared memory.
   *
   * @var string
   */
  private $bin;

  private $key;

  private $shm;

  private $IndexShm;

  private $semaphore;

  private $lock_count;

  /**
   * Constructs a PHP Storage CacheSHM backend.
   *
   * @param array $configuration
   *   (optional) Configuration used to configure this object.
   */
  public function __construct($id, $byinstance = TRUE, $maxsize = 128) {
    if (!function_exists('shm_attach')) {
      throw new Exception('Missing shm extension.');
    }
    $this->bin = $id;
    // Allocated Shared Memory , 128Mb by default
    $this->size = intval($maxsize ?? 128) * 1024 * 1024;
    $parts = [__DIR__, 'cache.shm', $this->bin];

    if ($byinstance) {
      $parts[] = posix_getuid();
      $parts[] = posix_getgid();
      $parts[] = $this->bin .= self::$counter++;
    }
    $this->file = implode('.', $parts);
    $this->key = $this->fake_ftok($this->file, 'data');
    $this->indexShmkey = $this->fake_ftok($this->file, 'index');
    if (isset($GLOBALS['smc_debug'])) {
      if (php_sapi_name() == "cli") {
        echo "Create shared memory for : " . $this->bin . ' : ' . $this->file . "\n";
      }
    }
    $this->__init();
  }

  /**
   * @param string $filename
   * @param string $proj
   *
   * @return int|string
   */
  function fake_ftok($filename = "", $proj = "") {
    $filename = $filename . (string) $proj;
    for ($key = []; sizeof($key) < strlen($filename); $key[] = ord(substr($filename, sizeof($key), 1))) {
    }
    return (int) dechex(array_sum($key));
  }

  private function __init($recreate_semaphore = TRUE) {

    // Attach Or Create file cache shared memory
    $this->shm = shm_attach($this->key, $this->size, 0666);
    if (!$this->shm) {
      if (isset($GLOBALS['smc_debug'])) {
        if (php_sapi_name() == "cli") {
          echo "Unable to allocate shared memory for : " . $this->bin . ' : ' . $this->file . "\n";
          return;
        }
      }
      throw new Exception("Unable to allocate shared memory for : " . $this->bin . ' : ' . $this->file);
    }
    $this->IndexShm = shm_attach($this->indexShmkey, 12 * 1024 * 1024, 0666);
    if (!$this->IndexShm) {
      if (isset($GLOBALS['smc_debug'])) {
        if (php_sapi_name() == "cli") {
          echo "Unable to allocate shared memory index for : " . $this->bin . ' : ' . $this->file . "\n";
          return;
        }
      }
      throw new Exception("Unable to allocate shared memory index for : " . $this->bin . ' : ' . $this->file);
    }
    if (!$recreate_semaphore) {
      return;
    }
    $this->semaphore = sem_get($this->key, 1, 0666, TRUE);

    if (empty($this->semaphore)) {
      if (isset($GLOBALS['smc_debug'])) {
        if (php_sapi_name() == "cli") {
          echo "Unable to get semaphore on shared memory for : " . $this->bin . ' : ' . $this->file . "\n";
          return;
        }
      }
      throw new Exception("Unable to get semaphore on shared memory for : " . $this->bin . ' : ' . $this->file);
    }
  }

  function hasKey($key) {
    $this->__checkIndex();
    if (!is_array($key)) {
      return (bool) isset($this->currentIndex[$key]);
    }
    return array_intersect($key, array_keys($this->currentIndex));
  }

  private function __checkIndex() {

    if (!shm_has_var($this->IndexShm, self::stateKey)) {
      // Init
      $this->currentRev = 0;
      $this->currentIndex = [-1 => 0];
      $this->currentTagsMap = [];
      $this->__saveIndex();
      return;
    }

    $rev = shm_get_var($this->IndexShm, self::stateKey);
    if (isset($this->currentRev)
      and $rev === $this->currentRev) {
      return;
    }
    // New index revision !!
    $this->currentRev = $rev;
    if (shm_has_var($this->IndexShm, self::IndexKey)) {
      $this->currentIndex = unserialize(shm_get_var($this->IndexShm, self::IndexKey));
    }
    else {
      // Currupted index !!
    }
    if (shm_has_var($this->IndexShm, self::TagsMapKey)) {
      $this->currentTagsMap = shm_get_var($this->IndexShm, self::TagsMapKey);
    }
    else {
      // Currupted index !!
    }

  }

  private function __saveIndex() {
    $this->lock();
    $this->currentRev++;
    $ret = @shm_remove_var($this->IndexShm, self::IndexKey);
    $ret = shm_put_var($this->IndexShm, self::IndexKey, serialize($this->currentIndex));
    shm_put_var($this->IndexShm, self::TagsMapKey, $this->currentTagsMap);
    shm_put_var($this->IndexShm, self::stateKey, $this->currentRev);
    $this->unlock();
    return $ret;
  }

  /**
   * Require lock from semaphore
   */
  public function lock() {
    if ($this->lock_count++) {
      return;
    }
    if (!sem_acquire($this->semaphore)) {
      throw new Exception("Unable to get lock on shared memory for : " . $this->bin . ' : ' . $this->file);
    }
  }

  public function unlock($ignore = FALSE) {
    if (--$this->lock_count > 0) {
      return;
    }
    if ($this->lock_count < 0) {
      return;
    }
    if (!sem_release($this->semaphore)) {
      throw new Exception("Unable to unlock write on shared memory for : " . $this->bin . ' : ' . $this->file);
    }
  }

  function find($regex, $limit = 0, $return_keys = FALSE) {
    if (!$this->shm) {
      return;
    }
    $this->__checkIndex();
    $list = preg_filter($regex, '$0', array_keys($this->currentIndex) ?? [], $limit <= 0 ? -1 : $limit);
    sort($list, SORT_NATURAL);
    if ($limit > 0 && count($list) > $limit) {
      $list = array_slice($list, 0, $limit, TRUE);
    }
    if ($return_keys) {
      return $list;
    }
    return $this->get($list);
  }

  function get($key, &$success = NULL) {
    if (!$this->shm) {
      return;
    }

    $this->__checkIndex();
    $success = TRUE;
    $values = [];
    $keys = is_array($key) ? $key : [$key];
    $t = time();
    foreach ($keys as $k) {
      if (!isset($this->currentIndex[$k])
        or ($this->currentIndex[$k][1] > 0 and $this->currentIndex[$k][1] < $t)
        or !$this->__checkMem($this->currentIndex[$k][0])
      ) {
        $success = FALSE;
      }
      else {
        $values[$k] = $this->__read($this->currentIndex[$k][0]);
      }
    }
    return is_array($key) ? $values : ($success ? $values[$key] : FALSE);
  }

  // Start HERE

  private function __checkMem($index) {
    return shm_has_var($this->shm, $index);
  }

  private function __read($index) {
    return @shm_get_var($this->shm, $index);
  }

  function deleteFiltred($regex) {
    if (!$this->shm) {
      return;
    }
    $this->__checkIndex();
    $list = preg_filter($regex, '$0', array_keys($this->currentIndex) ?? []);
    return $this->deleteMultiple($list);
  }

  function deleteMultiple($keys) {
    if (!$this->shm) {
      return;
    }
    $this->lock();
    $this->__checkIndex();
    foreach ($keys as $key) {
      if (isset($this->currentIndex[$key])) {
        if ($this->__checkMem($this->currentIndex[$key][1])) {
          @shm_remove_var($this->shm, $this->currentIndex[$key][1]);
        }
        unset($this->currentIndex[$key]);
      }
    }
    $this->__saveIndex();
    $this->unlock();
  }

  function store($key, $value = NULL, $ttl = 0) {
    if (!$this->shm) {
      return;
    }
    if (is_array($key)) {
      return $this->setMultiple($key, $ttl, TRUE, TRUE);
    }
    return $this->set($key, $value, $ttl, TRUE, TRUE);
  }

  /**
   * set multiple items at once
   *  $data must be an array of items
   *  [ id, item-data , $permanent ]
   *
   *
   * @param $data
   * @param bool $allow_override
   * @param false $permanent
   *
   * @return array|void
   * @throws \Exception
   */
  function setMultiple($data, $ttl = 0, $allow_override = TRUE, $permanent = FALSE) {
    if (!$this->shm) {
      return;
    }
    // This reference does not exist.
    // Create new temporary shm key
    $this->lock();
    $this->__checkIndex();
    $result = [];
    foreach ($data as $id => $item) {
      $idx = $this->getNextAvailableID();
      if (isset($this->currentIndex[$id]) && !$allow_override) {
        // Ignore this item
        $result[$id] = -1;
        continue;
      }
      // Store data
      $this->currentIndex[$id] = [
        $idx,           // Key
        $ttl > 0 ? time() + $ttl : $ttl,  // expire
        $permanent     // type
      ];
      $ret = $this->__write($idx, $item);
      if ($ret) {
        $result[$id] = -1;
      }
    }
    $this->__saveIndex();
    $this->unlock();
    return $result;
  }

  function getNextAvailableID() {
    return $this->currentIndex[-1]++;
    //
    if ($this->currentIndex[-1] > count($this->currentIndex[-1] + 1)) {
      // Some items has been deleted !!
      $ref = range(1, $this->currentIndex[-1]);
      $current = [];
      foreach ($this->currentIndex as $index) {
        $current[] = $index[0];
      }
      $diff = array_diff($ref, $current);
      // return first one, ontherwise increment counter
      return (count($diff) > 1) ? $diff[0] : $this->currentIndex[-1]++;
    }
    return $this->currentIndex[-1]++;
  }

  private function __write($index, $value) {
    return @shm_put_var($this->shm, $index, $value);
  }

  /**
   * @param $key
   * @param null $value
   * @param int $ttl
   * @param false $permanent
   *
   * @return int|mixed|void
   */
  function set($key, $value = NULL, $ttl = 0, $allow_override = TRUE, $permanent = FALSE) {
    if (!$this->shm) {
      return FALSE;
    }
    if (isset($GLOBALS['smc_debug'])) {
      if (php_sapi_name() == "cli") {
        echo "Setting cache for " . $key . " in " . $this->bin . "\n";
      }
    }
    // This reference does not exist.
    // Create new temporary shm key
    $this->lock();
    $this->__checkIndex();

    if (isset($this->currentIndex[$key]) && !$allow_override) {
      $this->unlock();
      return FALSE;
    }

    $idx = $this->getNextAvailableID();
    $this->currentIndex[$key] = [
      $idx,           // Key
      $ttl > 0 ? time() + $ttl : $ttl,  // expire
      $permanent      // type
    ];
    $ret = $this->__write($idx, $value);
    $this->__saveIndex();
    $this->unlock();
    return $ret;
  }

  function getCacheInfo($limited = FALSE) {
    $infos = [
      'num_slots' => 1,
      'ttl' => 99999,
      'num_hits' => 99999,
      'num_misses' => 99999,
      'start_time' => 99999,
      'cache_list' => [],
    ];

    return $infos;
  }

  function cas($key, $old, $new) {
    if (!$this->shm) {
      return FALSE;
    }
    $this->lock();
    $this->__checkIndex();
    $success = FALSE;
    $value = $this->get($key, $success);
    if ($value !== $old) {
      $this->unlock();
      return FALSE;
    }
    if ($success) {
      $success = $this->set($key, $new);
    }
    $this->unlock();
    return $success;
  }

  function clear_cache($permanent = TRUE) {
    if (!$this->shm) {
      return;
    }
    $this->lock();
    $new_storage_index = [-1 => 0];

    if (!$permanent) {
      // we create new index.
      $this->__checkIndex();
      $t = time();
      foreach ($this->currentIndex as $key => $item) {
        if (!$item[2] or ($item[1] && $item[1] < $t)) {
          // Delete this var
          @shm_remove_var($this->shm, $item[0]);
        }
        else {
          $new_storage_index[$key] = $item;
        }
      }
    }
    else {
      // remove shm are reallocate new one;
      shm_remove($this->shm);
      $this->__init(FALSE);
    }
    // Write the new index then unlock
    $this->currentIndex = $new_storage_index;
    $this->__saveIndex();
    $this->unlock();
  }

  function decrement($key, $step = 1, &$success = FALSE) {
    if (!$this->shm) {
      return FALSE;
    }
    $this->lock();
    $this->__checkIndex();
    if (!isset($this->currentIndex[$key])) {
      $success = FALSE;
      $this->unlock();
      return FALSE;
    }
    $def = &$this->currentIndex[$key];
    if ($def[1] > 0 && $def < time()) {
      $this->delete($key);
      $success = FALSE;
      $this->unlock();
      return FALSE;
    }
    $value = $this->__read($def[0]);
    $value -= $step;
    $success = $this->__write($def[0], $value);
    $this->unlock();
    return $value;
  }

  /**
   * delete
   *
   * @param $key
   */
  function delete($key) {
    if (!$this->shm) {
      return FALSE;
    }
    if (empty($key)) {
      return FALSE;
    }
    if (is_array($key)) {
      return $this->deleteMultiple($key);
    }

    $this->lock();
    $this->__checkIndex();
    if (!isset($this->currentIndex[$key])) {
      $this->unlock();
      return FALSE;
    }
    $shm_key = $this->currentIndex[$key][0];
    $ret_1 = shm_remove_var($this->shm, $shm_key);
    unset($this->currentIndex[$key]);
    $ret_2 = $this->__saveIndex();
    $this->unlock();
    if (!$ret_1) {
      // Faut pas s'arreter avant de mettre à jour l'index.
      throw new Exception("Failing delete value " . $key . " for : " . $this->bin . ' : ' . $this->file);
    }
    if (!$ret_2) {
      throw new Exception("Failing updating memory index for : " . $this->bin . ' : ' . $this->file);
    }
    return TRUE;
  }

  function increment($key, $step = 1, &$success = FALSE) {
    if (!$this->shm) {
      return;
    }
    $this->lock();
    $this->__checkIndex();
    if (!isset($this->currentIndex[$key])) {
      $success = FALSE;
      $this->unlock();
      return FALSE;
    }
    $def = &$this->currentIndex[$key];
    if ($def[1] > 0 && $def[1] < time()) {
      $this->delete($key);
      $success = FALSE;
      $this->unlock();
      return FALSE;
    }
    $value = $this->__read($def[0]);
    $value += $step;
    $success = $this->__write($def[0], $value);
    $this->unlock();
    return $value;
  }

  function getMemoryInfo($limited = FALSE) {
  }

  function __destruct() {
    shm_detach($this->shm);
    shm_detach($this->IndexShm);
  }
}
