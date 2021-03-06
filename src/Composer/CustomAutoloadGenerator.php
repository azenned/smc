<?php

namespace Blueflame\Composer;

use Composer\Autoload\AutoloadGenerator;
use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\Installer\InstallationManager;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;


final class  CustomAutoloadGenerator extends AutoloadGenerator {

  private $composer;

  public function __construct(EventDispatcher $eventDispatcher, IOInterface $io = NULL, Composer $composer) {
    parent::__construct($eventDispatcher, $io);
    $this->composer = $composer;
  }

  public function dump(Config $config, InstalledRepositoryInterface $localRepo, RootPackageInterface $mainPackage, InstallationManager $installationManager, $targetDir, $scanPsrPackages = FALSE, $suffix = '') {
    $count = parent::dump($config, $localRepo, $mainPackage, $installationManager, $targetDir, $scanPsrPackages, $suffix);
    // Override composer default ClassLoader.php
    $filesystem = new Filesystem();
    $vendorPath = $filesystem->normalizePath(realpath(realpath($config->get('vendor-dir'))));
    $targetDir = $vendorPath . '/' . $targetDir;
    $this->safeCopy($vendorPath . '/azenned/smc/src/Composer/ClassLoader.php', $targetDir . '/ClassLoader.php');
    return $count;
  }

  /**
   * Copy file using stream_copy_to_stream to work around https://bugs.php.net/bug.php?id=6463
   *
   * @param string $source
   * @param string $target
   */
  protected function safeCopy($source, $target) {
    if (!file_exists($target) || !file_exists($source) || !$this->filesAreEqual($source, $target)) {
      $source = fopen($source, 'r');
      $target = fopen($target, 'w+');

      stream_copy_to_stream($source, $target);
      fclose($source);
      fclose($target);
    }
  }

  /**
   * compare 2 files
   * https://stackoverflow.com/questions/3060125/can-i-use-file-get-contents-to-compare-two-files
   */
  private function filesAreEqual($a, $b) {
    // Check if filesize is different
    if (filesize($a) !== filesize($b)) {
      return FALSE;
    }

    // Check if content is different
    $ah = fopen($a, 'rb');
    $bh = fopen($b, 'rb');

    $result = TRUE;
    while (!feof($ah)) {
      if (fread($ah, 8192) != fread($bh, 8192)) {
        $result = FALSE;
        break;
      }
    }

    fclose($ah);
    fclose($bh);

    return $result;
  }

  protected function getAutoloadFile($vendorPathToTargetDirCode, $suffix) {

    $lastChar = $vendorPathToTargetDirCode[strlen($vendorPathToTargetDirCode) - 1];
    if ("'" === $lastChar || '"' === $lastChar) {
      $vendorPathToTargetDirCode = substr($vendorPathToTargetDirCode, 0, -1) . '/autoload_real.php' . $lastChar;
    }
    else {
      $vendorPathToTargetDirCode .= " . '/autoload_real.php'";
    }

    $autoloadFile = <<<AUTOLOAD
<?php

// autoload.php @generated by SMC

require_once $vendorPathToTargetDirCode;
AUTOLOAD;

    $extra = $this->composer->getPackage()->getExtra();

    if (isset($extra['smc-debug']) && $extra['smc-debug']) {
      $autoloadFile .= "\nrequire_once __DIR__ . '/azenned/smc/src/debug.php'; \n";
    }
    if (isset($extra['smc-enable-apcu']) && $extra['smc-enable-apcu']) {
      $autoloadFile .= "\nrequire_once __DIR__ . '/azenned/smc/src/init.php'; \n";
    }
    $autoloadFile .= <<<AUTOLOAD

return ComposerAutoloaderInit$suffix::getLoader();

AUTOLOAD;

    return $autoloadFile;
  }

}
