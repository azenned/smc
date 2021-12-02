<?php

namespace Blueflame\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PluginInterface;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class PluginConfiguration implements PluginInterface {

  protected $composer;

  protected $io;

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
