<?php

namespace Blueflame\Drupal;

use Blueflame\Cache\SHMStorage;
use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Exception;
use stdClass;

/**
 * Allows to cache data based on file modification dates in a shared memory cache.
 */
class SHMBackend implements CacheBackendInterface {

  /**
   * The name of the cache bin to use.
   *
   * @var string
   */
  protected $bin;

  /**
   * Prefix for all keys in the storage that belong to this site.
   *
   * @var string
   */
  protected $sitePrefix;

  /**
   * Prefix for all keys in this cache bin.
   *
   * Includes the site-specific prefix in $sitePrefix.
   *
   * @var string
   */
  protected $binPrefix;

  /**
   * The cache tags checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  /**
   * Bin used for storing the data in the shared memory.
   *
   * @var string
   */
  private $store;

  /**
   * Constructs a PHP Storage CacheSHM backend.
   *
   * @param array $configuration
   *   (optional) Configuration used to configure this object.
   */
  public function __construct($bin, $site_prefix, CacheTagsChecksumInterface $checksum_provider) {
    if (!function_exists('shm_attach')) {
      throw new Exception('Missing shm extension.');
    }
    $this->bin = $bin;
    $this->sitePrefix = $site_prefix;
    $this->checksumProvider = $checksum_provider;
    $this->binPrefix = $this->sitePrefix . '_' . $this->bin . '_';
    try {
      $this->store = new SHMStorage($this->bin, FALSE, intval($GLOBALS['smc-memsize-default'] ?? 128));
    } catch (\Exception $e) {
      unset($this->store);
    }

  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    if (!isset($this->store)) {
      return NULL;
    }
    $cache = $this->store->get($this->getKey($cid));
    return $this->prepareItem($cache, $allow_invalid);
  }

  public function getKey($cid) {
    return $this->binPrefix . $cid;
  }

  protected function prepareItem($cache, $allow_invalid) {
    if (!isset($cache->data)) {
      return FALSE;
    }

    $cache->tags = $cache->tags ? explode(' ', $cache->tags) : [];

    // Check expire time.
    $cache->valid = $cache->expire == Cache::PERMANENT || $cache->expire >= REQUEST_TIME;

    // Check if invalidateTags() has been called with any of the entry's tags.
    if (!$this->checksumProvider->isValid($cache->checksum, $cache->tags)) {
      $cache->valid = FALSE;
    }

    if (!$allow_invalid && !$cache->valid) {
      return FALSE;
    }

    return $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    if (!isset($this->store)) {
      return NULL;
    }
    $map = [];
    foreach ($cids as $cid) {
      $map[$this->getKey($cid)] = $cid;
    }

    $result = $this->store->get(array_keys($map));
    $cache = [];
    if ($result) {
      foreach ($result as $key => $item) {
        $item = $this->prepareItem($item, $allow_invalid);
        if ($item) {
          $cache[$map[$key]] = $item;
        }
      }
    }
    unset($result);

    $cids = array_diff($cids, array_keys($cache));
    return $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = []) {
    if (!isset($this->store)) {
      return NULL;
    }
    assert(Inspector::assertAllStrings($tags), 'Cache tags must be strings.');
    $tags = array_unique($tags);
    $cache = new stdClass();
    $cache->cid = $cid;
    $cache->created = round(microtime(TRUE), 3);
    $cache->expire = $expire;
    $cache->tags = implode(' ', $tags);
    $cache->checksum = $this->checksumProvider->getCurrentChecksum($tags);
    $cache->serialized = 0;
    $cache->data = $data;
    $this->store->set($this->getKey($cid), $cache);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items = []) {
    if (!isset($this->store)) {
      return NULL;
    }
    $data = [];
    foreach ($items as $cid => $item) {
      $item['tags'][] = $this->binPrefix;
      $tags = array_unique($item['tags']);
      $cache = new stdClass();
      $cache->cid = $cid;
      $cache->created = round(microtime(TRUE), 3);
      $cache->expire = $item['expire'] ?? CacheBackendInterface::CACHE_PERMANENT;
      $cache->tags = implode(' ', $tags);
      $cache->checksum = $this->checksumProvider->getCurrentChecksum($tags);
      $cache->serialized = 0;
      $cache->data = $item['data'];
      $id = $this->getKey($cid);
      $data[$id] = $cache;
    }
    if ($data) {
      $this->store->setMultiple($data);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    if (!isset($this->store)) {
      return NULL;
    }
    $this->store->delete($this->getKey($cid));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    if (!isset($this->store)) {
      return NULL;
    }
    $this->store->deleteMultiple(array_map([$this, 'getKey'], $cids));
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    if (!isset($this->store)) {
      return NULL;
    }
    $this->store->clear_cache(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    if (!isset($this->store)) {
      return NULL;
    }
    $this->store->clear_cache(FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function removeBin() {
    if (!isset($this->store)) {
      return NULL;
    }
    $this->store->deleteFiltred('/^' . preg_quote($this->binPrefix, '/') . '/');
  }

  /**
   * {@inheritdoc}
   */
  public function invalidate($cid) {
    if (!isset($this->store)) {
      return NULL;
    }
    $this->store->delete($this->getKey($cid));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateMultiple(array $cids) {
    if (!isset($this->store)) {
      return NULL;
    }
    $this->store->delete(array_map([$this, 'getKey'], $cids));
  }

  /**
   * {@inheritdoc}
   */
  public function invalidateAll() {
    if (!isset($this->store)) {
      return NULL;
    }
    $this->store->clear_cache(TRUE);
  }

  protected function getAll($prefix = '') {
    if (!isset($this->store)) {
      return NULL;
    }
    return $this->store->find('/^' . preg_quote($this->getKey($prefix), '/') . '/');
  }

}
