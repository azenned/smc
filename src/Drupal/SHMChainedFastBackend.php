<?php

namespace Blueflame\Drupal;

use Drupal\Core\Cache\ChainedFastBackend;
use Exception;

class SHMChainedFastBackend extends ChainedFastBackend {

  /**
   * {@inheritdoc}
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    $cids_copy = $cids;
    $cache = [];
    $last_write_timestamp = $this->getLastWriteTimestamp();
    if ($last_write_timestamp) {
      try {
        $items = $this->fastBackend->getMultiple($cids, $allow_invalid);
      } catch (Exception $e) {
        $cids = $cids_copy;
        $items = [];
      }
      foreach ($items as $item) {
        if ($item->created < $last_write_timestamp) {
          $cids[array_search($item->cid, $cids_copy)] = $item->cid;
        }
        else {
          $cache[$item->cid] = $item;
        }
      }
    }
    if ($cids) {
      $new = [];
      foreach ($this->consistentBackend->getMultiple($cids, $allow_invalid) as $item) {
        $cache[$item->cid] = $item;
        $new[$item->cid] = [
          'data' => $item->data,
          'expire' => $item->expire,
        ];
      }
      $this->fastBackend->setMultiple($new);
    }
    return $cache;
  }
}
