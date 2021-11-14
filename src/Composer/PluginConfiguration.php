<?php

namespace Blueflame\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class PluginConfiguration implements PluginInterface, EventSubscriberInterface {

  protected $composer;

  protected $io;

  /**
   * Returns an array of event names this subscriber wants to listen to.
   */
  public static function getSubscribedEvents() {
    return [
      ScriptEvents::PRE_AUTOLOAD_DUMP => ['onPreAutoloadDump', 10],
    ];
  }

  public static function onPreAutoloadDump(Event $event) {
    $vendor_dir = $event->getComposer()->getConfig()->get('vendor-dir');
    $package = $event->getComposer()->getPackage();
    $extra = $package->getExtra();
    $autoload = $package->getAutoload();
    if (!isset($autoload['files'])) {
      $autoload['files'] = [];
    }
    if (isset($extra['smc-enable-services']) && $extra['smc-enable-services']) {
      $autoload['files'] = array_merge($autoload['files'], [
        $vendor_dir . '/azenned/smc/src/init_services.php',
      ]);
    }
  }

  public function activate(Composer $composer, IOInterface $io) {
    $this->composer = $composer;
    $this->io = $io;
    $defaultGenerator = $composer->getAutoloadGenerator();
    $this->io->write('SMC Overriding autoload generator');
    $generator = new CustomAutoloadGenerator($composer->getEventDispatcher(), $io, $composer);
    $composer->setAutoloadGenerator($generator);
  }

  /**
   * {@inheritdoc}
   */
  public function deactivate(Composer $composer, IOInterface $io) {
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall(Composer $composer, IOInterface $io) {
  }
}
