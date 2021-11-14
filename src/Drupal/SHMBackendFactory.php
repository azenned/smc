<?php

namespace Blueflame\Drupal;

use Drupal\Core\Cache\CacheFactoryInterface;
use Drupal\Core\Cache\CacheTagsChecksumInterface;
use Drupal\Core\Site\Settings;

class SHMBackendFactory implements CacheFactoryInterface {

  /**
   * The site prefix string.
   *
   * @var string
   */
  protected $sitePrefix;

  /**
   * The cache tags checksum provider.
   *
   * @var \Drupal\Core\Cache\CacheTagsChecksumInterface
   */
  protected $checksumProvider;

  /**
   * The SHM backend class to use.
   *
   * @var string
   */
  protected $backendClass;

  /**
   * Constructs an SHMBackendFactory object.
   *
   * @param string $root
   *   The app root.
   * @param string $site_path
   *   The site path.
   * @param \Drupal\Core\Cache\CacheTagsChecksumInterface $checksum_provider
   *   The cache tags checksum provider.
   */
  public function __construct($root, $site_path, CacheTagsChecksumInterface $checksum_provider) {
    $this->sitePrefix = Settings::getApcuPrefix('apcu_backend', $root, $site_path);
    $this->checksumProvider = $checksum_provider;
    if (function_exists('shm_attach')) {
      $this->backendClass = 'Blueflame\Drupal\SHMBackend';
    }
    elseif (function_exists('apcu_fetch')) {
      $this->backendClass = 'Drupal\Core\Cache\ApcuBackend';
    }
  }

  /**
   * Gets new SHMBackend instance for the specified cache bin.
   *
   * @param $bin
   *   The cache bin for which the object is created.
   *
   * @return \Blueflame\Drupal\SHMBackend
   *   The cache backend object for the specified cache bin.
   */
  public function get($bin) {
    return new $this->backendClass($bin, $this->sitePrefix, $this->checksumProvider);
  }

}
