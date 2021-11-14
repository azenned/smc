<?php


namespace Blueflame\Drupal;

use Drupal\Core\Cache\ChainedFastBackendFactory;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\Site\Settings;

class SHMChainedFastBackendFactory extends ChainedFastBackendFactory {

  public function __construct(Settings $settings = NULL, $consistent_service_name = NULL, $fast_service_name = NULL) {
    if (!isset($consistent_service_name)) {
      $cache_settings = isset($settings) ? $settings->get('cache') : [];
      $consistent_service_name = isset($cache_settings['default']) ? $cache_settings['default'] : 'cache.backend.database';
    }
    if (!isset($fast_service_name) && function_exists('shm_attach')) {
      $fast_service_name = 'cache.backend.shm';
    }
    elseif (!isset($fast_service_name) && function_exists('apcu_fetch')) {
      $fast_service_name = 'cache.backend.apcu';
    }
    $this->consistentServiceName = $consistent_service_name;
    if (!InstallerKernel::installationAttempted()) {
      $this->fastServiceName = $fast_service_name;
    }
  }

  /**
   * Instantiates a chained, fast cache backend class for a given cache bin.
   *
   * @param string $bin
   *   The cache bin for which a cache backend object should be returned.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The cache backend object associated with the specified bin.
   */
  public function get($bin) {
    // Use the chained backend only if there is a fast backend available;
    // otherwise, just return the consistent backend directly.
    if (isset($this->fastServiceName)) {
      return new SHMChainedFastBackend(
        $this->container->get($this->consistentServiceName)->get($bin),
        $this->container->get($this->fastServiceName)->get($bin),
        $bin
      );
    }
    else {
      return $this->container->get($this->consistentServiceName)->get($bin);
    }
  }

}
