<?php

namespace Blueflame\Cache;

use ArrayIterator;
use Iterator;

class APCuSHMIterator implements Iterator {

  private $search;

  private $format;

  private $chunk_size;

  private $list;

  public function __construct(
    $search = NULL,
    int $format = APC_ITER_ALL,
    int $chunk_size = 100,
    int $list = APC_LIST_ACTIVE
  ) {
    $this->search = $search;
    $this->format = $format;
    $this->chunk_size = $chunk_size;
    $this->list = $list;
    // Check if regex expression.
    if (is_string($search) && preg_match("/^\/.+\/[a-z]*$/i", $search)) {
      $this->result = APCuSHM::getInstance()->find($search, $this->chunk_size, TRUE);
    }
    elseif (is_array($search) or is_scalar($search)) {
      $this->result = APCuSHM::getInstance()->hasKey($search);
    }
    $this->iterator = new ArrayIterator($this->result);
  }

  public function current() {
    $current_key = $this->iterator->current();
    $masks = [
      APC_ITER_ALL,
      APC_ITER_VALUE,
      APC_ITER_KEY,
      //      APC_ITER_ATIME,
      //      APC_ITER_CTIME,
      //      APC_ITER_DEVICE,
      //      APC_ITER_DTIME,
      //      APC_ITER_FILENAME,
      //      APC_ITER_INODE,
      //      APC_ITER_MD5,
      //      APC_ITER_MEM_SIZE,
      //      APC_ITER_MTIME,
      //      APC_ITER_NONE,
      //      APC_ITER_NUM_HITS,
      //      APC_ITER_REFCOUNT,
      //      APC_ITER_TTL,
      //      APC_ITER_TYPE,
    ];
    $value = APCuSHM::getInstance()->get($current_key);
    $data = [];
    foreach ($masks as $current_mask) {
      if ($this->format & $current_mask) {
        switch ($current_mask) {
          case APC_ITER_ALL:
            $data['atime'] = NULL;
            $data['ctime'] = NULL;
            $data['device'] = NULL;
            $data['dtime'] = NULL;
            $data['filename'] = NULL;
            $data['inode'] = NULL;
            $data['key'] = $current_key;
            $data['md5'] = NULL;
            $data['mem_size'] = NULL;
            $data['mtime'] = NULL;
            $data['num_hits'] = 1;
            $data['refcount'] = 1;
            $data['ttl'] = 0;
            $data['value'] = $value;
            $data['type'] = 0;
            break;
          case APC_ITER_ATIME:
            $data['atime'] = NULL;
            break;
          case APC_ITER_CTIME:
            $data['ctime'] = NULL;
            break;
          case APC_ITER_DEVICE:
            $data['device'] = NULL;
            break;
          case APC_ITER_DTIME:
            $data['dtime'] = NULL;
            break;
          case APC_ITER_FILENAME:
            $data['filename'] = NULL;
            break;
          case APC_ITER_INODE:
            $data['inode'] = NULL;
            break;
          case APC_ITER_KEY:
            $data['key'] = $current_key;
            break;
          case APC_ITER_MD5:
            $data['md5'] = NULL;
            break;
          case APC_ITER_MEM_SIZE:
            $data['mem_size'] = NULL;
            break;
          case APC_ITER_MTIME:
            $data['mtime'] = NULL;
            break;
          case APC_ITER_NONE:
            $data = NULL;
            break;
          case APC_ITER_NUM_HITS:
            $data['num_hits'] = 1;
            break;
          case APC_ITER_REFCOUNT:
            $data['refcount'] = 1;
            break;
          case APC_ITER_TTL:
            $data['ttl'] = 0;
            break;
          case APC_ITER_VALUE:
            $data['value'] = $value;
            break;
          case APC_ITER_TYPE:
            $data['type'] = 0;
            break;
        }
      }
    }
    return $data;
  }

  public function getTotalCount(): int {
    return $this->iterator->count();
  }

  public function getTotalHits(): int {
    return 1;
  }

  public function getTotalSize(): int {
    return 1;
  }

  public function key(): string {
    return $this->iterator->current();
  }

  public function next(): bool {
    return (bool) $this->iterator->next();
  }

  public function rewind(): void {
    $this->iterator->rewind();
  }

  public function valid(): bool {
    return $this->iterator->valid();
  }

  public function getKeys() {
    return (array) $this->result;
  }

}
