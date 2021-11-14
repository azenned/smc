<?php

namespace Blueflame\Drupal;


use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Symfony\Component\DependencyInjection\Reference;

class SMCServiceProvider extends ServiceProviderBase {

  const SHM_BACKEND_SERVICES = 'cache.backend.shm';

  const SHM_CHAINEDFAST_SERVICES = 'cache.backend.shmchainedfast';

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    $definition = $container->getDefinition('cache.backend.chainedfast');
    $definition->setClass("Blueflame\Drupal\SHMChainedFastBackendFactory");


    $container->register(self::SHM_BACKEND_SERVICES, "Blueflame\Drupal\SHMBackendFactory")
      ->addArgument(new Reference('app.root'))
      ->addArgument(new Reference('site.path'))
      ->addArgument(new Reference('cache_tags.invalidator.checksum'));

    $container->register(self::SHM_CHAINEDFAST_SERVICES, "Blueflame\Drupal\SHMChainedFastBackendFactory")
      ->addArgument(new Reference('settings'))
      ->setMethodCalls([['setContainer', [new Reference('service_container')]]]);
  }

}
