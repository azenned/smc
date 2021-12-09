<?php

namespace Blueflame\Cache;

use Exception;

defaults('SMC_DEBUG', FALSE);
defaults('SMC_CONSISTENT_LOCK', FALSE);

class SHMStorage {

  const CACHE_PERMANENT = -1;

  const IndexCounter = 0;

  const IndexMAP = 1;

  const IndexUSAGE = 2;

  const IndexDeletedIDs = 3;

  const IndexStats = 4;

  const SHM_FILENAME = 1;

  const IndexKey = 2;

  const stateKey = 3;

  static $counter = 0;

  private $currentIndex = [];

  private $currentRev = 1;

  /**
   * Bin used for storing the data in the shared memory.
   *
   * @var string
   */
  private $bin;

  private $key;

  private $file;

  private $shm;

  private $IndexShm;

  private $semaphore;

  private $lock_count;

  private $updated_index;

  /**
   * Constructs a PHP Storage CacheSHM backend.
   *
   * @param array $configuration
   *   (optional) Configuration used to configure this object.
   */
  public function __construct($id, $byinstance = FALSE, $maxsize = 128) {
    if (!function_exists('shm_attach')) {
      throw new Exception('Missing shm extension.');
    }
    // Allocated Shared Memory , 128Mb by default
    $this->size = intval($maxsize ?? 128) * 1024 * 1024;
    if (is_int($id)) {
      // Load by Id
      $this->bin = (int) $id;
      $this->key = (int) $id;
    }
    else {
      $this->bin = $id;
      $parts = ['cache.shm', $this->bin];
      $parts[] = posix_getuid();
      $parts[] = posix_getgid();
      $parts[] = (php_sapi_name() == "cli") ? 'cli' : 'fpm';
      if ($byinstance) {
        $parts[] = $this->bin .= self::$counter++;
      }
      $this->file = '/tmp/' . implode('.', $parts) . '.bin';
      touch($this->file);
      $this->key = ftok($this->file, 'D');
    }
    if (SMC_DEBUG) {
      echo "Loading shared memory for : " . $this->bin . ' : ' . $this->file ?? $this->key . "\n";
    }
    if (0 == (int) $this->key) {
      throw new \Exception('SHM key cant be zero');
    }
    $this->__init();
  }


  private function __init($recreate_semaphore = TRUE) {
    if (0 == (int) $this->key) {
      throw new \Exception('SHM key cant be zero');
    }
    // Attach Or Create file cache shared memory
    try {
      $this->shm = @shm_attach((int) $this->key, $this->size);
      if (!$this->shm) {
        throw new Exception();
      }
    } catch (Exception $exception) {
      if (SMC_DEBUG) {
        echo "Unable to allocate or access to shared memory : " . $this->bin . ' : ' . $this->file ?? $this->key . " " . $exception->getMessage() . "\n";
        return;
      }
      throw new Exception("Unable to allocate or access to shared memory : " . $this->bin . ' : ' . $this->file ?? $this->key . " " . $exception->getMessage() . "\n");
    }
    if (!$recreate_semaphore) {
      return;
    }
    $this->semaphore = @sem_get((int) $this->key, 1);
    if (empty($this->semaphore)) {
      if (SMC_DEBUG) {
        if (php_sapi_name() == "cli") {
          echo "Unable to get semaphore on shared memory for : " . $this->bin . ' : ' . $this->file . "\n";
          return;
        }
      }
      throw new Exception("Unable to get semaphore on shared memory for : " . $this->bin . ' : ' . $this->file);
    }
    $this->__checkIndex();
  }

  private function __checkIndex() {
    $rev = @shm_get_var($this->shm, self::stateKey);
    if ($rev == FALSE) {
      if (empty($this->currentIndex)) {
        $this->__newIndex();
        $rev = @shm_get_var($this->shm, self::stateKey);
        if ($rev == FALSE) {
          // Still failing de use memory
          $this->clear_cache();
        }
      }
      else {
        //$this->__saveIndex();
      }
    }
    if (isset($this->currentRev)
      and ($rev === FALSE
        or $rev === $this->currentRev)) {
      return;
    }
    // New index revision !!
    $this->currentRev = $rev;
    $this->currentIndex = @shm_get_var($this->shm, self::IndexKey);
    if (empty($this->file)) {
      $this->file = @shm_get_var($this->shm, self::SHM_FILENAME);
    }
    if (is_null($this->currentIndex) or $this->currentIndex === FALSE) {
      $this->__newIndex();
    }
  }

  private function __newIndex($return = FALSE) {
    $a = 1;
    // Init
    $default = [
      self::IndexDeletedIDs => [],
      self::IndexCounter => 4,
      self::IndexMAP => [],
      self::IndexUSAGE => [],
      self::IndexStats => [
        'start_time' => time(),
        'num_hits' => 0,
        'num_inserts' => 0,
        'num_misses' => 0,
      ],
    ];
    if ($return) {
      return $default;
    }
    $this->currentIndex = $default;
    $this->currentRev = 0;
    $this->__saveIndex(TRUE);
    return;
  }

  private function __saveIndex($final = FALSE) {
    $this->updated_index = TRUE;
    if (!SMC_CONSISTENT_LOCK && !$final) {
      return;
    }

    if (!isset($this->currentIndex[self::IndexCounter]) or $this->currentIndex[self::IndexCounter] < 4) {
      // @fixme need to debug this case !!
      $a = 1;
    }
    $this->lock();
    $this->currentRev++;
    $ret3 = @shm_put_var($this->shm, self::SHM_FILENAME, $this->file);
    $ret2 = @shm_put_var($this->shm, self::stateKey, $this->currentRev);
    $ret1 = @shm_put_var($this->shm, self::IndexKey, $this->currentIndex);
    $this->unlock();
    return $ret1 && $ret1;
  }

  /**
   * Require lock from semaphore
   */
  public function lock() {
    if (!SMC_CONSISTENT_LOCK) {
      return;
    }
    if ($this->lock_count++) {
      return;
    }
    if (!sem_acquire($this->semaphore)) {
      throw new Exception("Unable to get lock on shared memory for : " . $this->bin . ' : ' . $this->file);
    }
  }

  public function unlock() {
    if (!SMC_CONSISTENT_LOCK) {
      return;
    }
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

  function hasKey($keys) {
    $this->__checkIndex();
    if (!is_array($keys)) {
      return (bool) isset($this->currentIndex[self::IndexMAP][$this->__secure_key($keys)]);
    }
    $return = [];
    foreach ($keys as $key) {
      $cid = $this->__secure_key($key);
      if (isset($this->currentIndex[self::IndexMAP][$cid])) {
        $return[] = $this->currentIndex[self::IndexMAP][$cid][3];
      }
    }
    return $return;
  }

  public function __secure_key($cid) {
    $hash = base64_encode(hash('sha256', $cid, TRUE));
    // Modify the hash so it's safe to use in URLs.
    return str_replace(['+', '/', '='], ['-', '_', ''], $hash);
  }

  function find($regex, $limit = 0, $return_keys = FALSE) {
    if (!$this->shm) {
      return;
    }
    $this->__checkIndex();
    $list = [];
    foreach (($this->currentIndex[self::IndexMAP] ?? []) as $item) {
      preg_match($regex, $item[3], $matches);
      if ($matches) {
        $list[] = $item[3];
      }
    }
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
      $cid = $this->__secure_key($k);
      if (!isset($this->currentIndex[self::IndexMAP][$cid])
        // or ($this->currentIndex[self::IndexMAP][$k][1] > 0 and $this->currentIndex[self::IndexMAP][$k][1] < $t)
        // or (isset($this->currentIndex[self::IndexMAP][$k]) && !$this->__checkMem($this->currentIndex[self::IndexMAP][$k][0]))
      ) {
        $success = FALSE;
        $this->currentIndex[self::IndexStats]['num_misses']++;
      }
      else {
        $item = $this->__read($this->currentIndex[self::IndexMAP][$cid][0]) ?? [];
        if ($k == ($item[1] ?? NULL)) {
          $values[$k] = $item[0];
          $this->currentIndex[self::IndexStats]['num_hits']++;
        }
        else {
          $success = FALSE;
          $this->currentIndex[self::IndexStats]['num_misses']++;
        }
      }
    }
    return is_array($key) ? $values : ($success ? $values[$key] : FALSE);
  }

  private function __read($index) {
    return @shm_get_var($this->shm, $index);
  }

  function deleteFiltred($regex) {
    if (!$this->shm) {
      return;
    }
    $this->__checkIndex();
    $list = preg_filter($regex, '$0', array_keys($this->currentIndex[self::IndexMAP]) ?? []);
    $list = [];
    foreach (($this->currentIndex[self::IndexMAP] ?? []) as $item) {
      preg_match($regex, $item[3], $matches);
      if ($matches) {
        $list[] = $item[3];
      }
    }
    if (!count($list)) {
      return;
    }
    return $this->deleteMultiple($list);
  }

  function deleteMultiple($keys) {
    if (!$this->shm) {
      return;
    }
    $this->lock();
    $this->__checkIndex();
    foreach ($keys as $key) {
      $cid = $this->__secure_key($key);
      if (isset($this->currentIndex[self::IndexMAP][$cid])) {
        $shm_key = $this->currentIndex[self::IndexMAP][$cid][0];
        $ret_1 = @shm_remove_var($this->shm, $shm_key);
        $this->currentIndex[self::IndexDeletedIDs][] = $shm_key;
        unset($this->currentIndex[self::IndexMAP][$cid], $this->currentIndex[self::IndexUSAGE][$shm_key]);
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

      $cid = $this->__secure_key($id);
      $idx = $this->getNextAvailableID();
      if (isset($this->currentIndex[self::IndexMAP][$cid]) && !$allow_override) {
        // Ignore this item
        $result[$id] = -1;
        continue;
      }
      $expire = $ttl > 0 ? time() + $ttl : $ttl;
      // Store data
      $this->currentIndex[self::IndexMAP][$cid] = [
        $idx,           // memory index
        $expire,  // expire
        $permanent,     // type
        $id   // key
      ];
      $this->currentIndex[self::IndexUSAGE][$idx] = 1;
      $ret = $this->__write($idx, [$item, $id, $expire]);
      $this->currentIndex[self::IndexStats]['num_inserts']++;

      if ($ret) {
        $result[$id] = -1;
      }
    }
    $this->__saveIndex();
    $this->unlock();
    return $result;
  }

  function getNextAvailableID() {
    if (!empty($this->currentIndex[self::IndexDeletedIDs])
      && ($idx = array_shift($this->currentIndex[self::IndexDeletedIDs]))) {
      // Check for data in
      if (!isset($this->currentIndex[self::IndexUSAGE][$idx])) {
        return $idx;
      }
    }
    if ($this->currentIndex[self::IndexCounter] < 4) {
      $this->currentIndex[self::IndexCounter] = 4;
    }
    return ++$this->currentIndex[self::IndexCounter];
  }

  private function __write($index, $value) {
    if ($index < 4) {
      return;
    }
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
    // This reference does not exist.
    // Create new temporary shm key
    $this->lock();
    $this->__checkIndex();

    if (isset($this->currentIndex[self::IndexMAP][$key]) && !$allow_override) {
      $this->unlock();
      return FALSE;
    }

    $cid = $this->__secure_key($key);
    $idx = $this->getNextAvailableID();
    $expire = $ttl > 0 ? time() + $ttl : $ttl;
    $this->currentIndex[self::IndexMAP][$cid] = [
      $idx,
      $expire,
      $permanent,
      $key,
    ];
    $this->currentIndex[self::IndexUSAGE][$idx] = 1;
    $ret = $this->__write($idx, [$value, $key, $expire]);
    $this->currentIndex[self::IndexStats]['num_inserts']++;

    if (SMC_DEBUG) {
      echo "Setting cache for " . $key . "(" . $idx . ") in " . $this->bin . "\n";
    }
    $this->__saveIndex();
    $this->unlock();
    return $ret;
  }

  function getSMAInfo($limited = FALSE) {
    $usage = IPCHelper::__getAllInstancesStatus($this->key)['rss'] ?? 0;
    $infos = [
      'num_seg' => 1,
      'seg_size' => $this->size,
      'avail_mem' => $this->size - $usage,
      'block_lists' => [
        [
          ['size' => $this->size - $usage, 'offset' => 0],
        ],
      ],
    ];
    return $infos;
  }

  function getCacheInfo($limited = FALSE, $start = 0, $max = -1, $read_data = TRUE) {
    $this->__checkIndex();
    $count = count($this->currentIndex[self::IndexMAP] ?? []);
    $infos = [
      'avail_mem' => $this->size - IPCHelper::__getAllInstancesStatus($this->key)['rss'] ?? 0,
      'cache_list' => [],
      'deleted_list' => [],
      'expunges' => 0,
      'mem_size' => $this->size,
      'memory_type' => 'shm',
      'num_entries' => $count,
      'num_hits' => $this->currentIndex[self::IndexStats]['num_hits'] ?? 0,
      'num_inserts' => $this->currentIndex[self::IndexStats]['num_inserts'] ?? 0,
      'num_misses' => $this->currentIndex[self::IndexStats]['num_misses'] ?? 0,
      'num_slots' => 1,
      'seg_size' => $this->size,
      'start_time' => $this->currentIndex[self::IndexStats]['start_time'] ?? time() - 1,
      'ttl' => 99999,
      'id' => (@shm_get_var($this->shm, self::SHM_FILENAME)),
      'key' => $this->key,
    ];
    if ($limited) {
      return $infos;
    }
    if ($start > $count) {
      $start = $count;
    }

    $view_port = array_slice($this->currentIndex[self::IndexMAP] ?? [], (int) $start, ($max == -1) ? $count : (int) $max, TRUE);
    foreach ($view_port ?? [] as $k => $v) {
      $infos['cache_list'][] = [
        'info' => $v[3],
        'data' => $read_data ? ($this->__read($v[0] ?? 0)[0]) : NULL,
        'inode' => $v[0] ?? 0,
        'ttl' => $v[1] ?? 0,
        'num_hits' => 1,
        'num_misses' => 1,
        'mem_size' => 1,
        'mtime' => 1,
        'access_time' => 1,
        'creation_time' => 1,
        'deletion_time' => 1,
      ];
    }
    return $infos;
  }

  function cas($key, $old, $new) {
    if (!$this->shm) {
      return FALSE;
    }
    $this->lock();
    $this->__checkIndex();
    $success = FALSE;
    $item = $this->get($key, $success);
    if ($old !== $item) {
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
    $new_storage_index = $this->__newIndex(TRUE);

    if (!$permanent) {
      // we create new index.
      $this->__checkIndex();
      $t = time();
      foreach ($this->currentIndex[self::IndexMAP] as $cid => $item) {
        if (!$item[2] or ($item[1] && $item[1] < $t)) {
          // Delete this var
          shm_remove_var($this->shm, $item[0]);
        }
        else {
          $new_storage_index[self::IndexMAP][$cid] = $item;
          $new_storage_index[self::IndexUSAGE][$item[0]] = 1;
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
    $this->__saveIndex(TRUE);
    $ret = @shm_put_var($this->shm, self::SHM_FILENAME, $this->file);
    $this->unlock();
  }

  function decrement($key, $step = 1, &$success = FALSE) {
    if (!$this->shm) {
      return FALSE;
    }
    $this->lock();
    $this->__checkIndex();
    $cid = $this->__secure_key($key);
    if (!isset($this->currentIndex[self::IndexMAP][$cid])) {
      $success = FALSE;
      $this->unlock();
      return FALSE;
    }
    $def = &$this->currentIndex[self::IndexMAP][$cid];
    if ($def[1] > 0 && $def < time()) {
      $this->delete($key);
      $success = FALSE;
      $this->unlock();
      return FALSE;
    }
    $value = $this->__read($def[0]);
    if ($key !== ($value[1] ?? NULL)) {
      $success = FALSE;
      $this->unlock();
      return FALSE;
    }
    $value[0] -= $step;
    $success = $this->__write($def[0], $value);
    $this->unlock();
    return $value[0];
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
    $cid = $this->__secure_key($key);
    if (!isset($this->currentIndex[self::IndexMAP][$cid])) {
      $this->unlock();
      return FALSE;
    }
    $shm_key = $this->currentIndex[self::IndexMAP][$cid][0];
    $ret_1 = @shm_remove_var($this->shm, $shm_key);
    $this->currentIndex[self::IndexDeletedIDs][] = $shm_key;
    unset($this->currentIndex[self::IndexMAP][$cid], $this->currentIndex[self::IndexUSAGE][$shm_key]);
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
    $cid = $this->__secure_key($key);
    if (!isset($this->currentIndex[self::IndexMAP][$cid])) {
      $success = FALSE;
      $this->unlock();
      return FALSE;
    }
    $def = &$this->currentIndex[self::IndexMAP][$cid];
    if ($def[1] > 0 && $def[1] < time()) {
      $this->delete($key);
      $success = FALSE;
      $this->unlock();
      return FALSE;
    }
    $value = $this->__read($def[0]);
    if ($key !== ($value[1] ?? NULL)) {
      $success = FALSE;
      $this->unlock();
      return FALSE;
    }
    $value[0] += $step;
    $success = $this->__write($def[0], $value);
    $this->unlock();
    return $value[0];
  }

  function getMemoryInfo($limited = FALSE) {
  }

  function __destruct() {

    $this->__saveIndex((bool) $this->updated_index);
    if ($this->shm) {
      @shm_detach($this->shm);
    }
    if ($this->semaphore) {
      @sem_release($this->semaphore);
    }

  }

  private function __checkMem($index) {
    return @shm_has_var($this->shm, $index);
  }

}
