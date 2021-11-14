<?php

namespace Blueflame\Cache\Tests;

class BasicTest extends \PHPUnit\Framework\TestCase {

  function setUp(): void {
  }

  public function testApcu() {
    $key = __CLASS__;
    apcu_delete($key);

    $this->assertFalse(apcu_exists($key));
    $this->assertTrue(apcu_add($key, 123));
    $this->assertTrue(apcu_exists($key));
    $this->assertSame([$key => -1], apcu_add([$key => 123]));
    $this->assertSame(123, apcu_fetch($key));
    $this->assertTrue(apcu_store($key, 124));
    $this->assertSame(124, apcu_fetch($key));
    $this->assertSame(125, apcu_inc($key));
    $this->assertSame(124, apcu_dec($key));
    $this->assertTrue(apcu_cas($key, 124, 123));
    $this->assertFalse(apcu_cas($key, 124, 123));
    $this->assertTrue(apcu_delete($key));
    $this->assertFalse(apcu_delete($key));
    $this->assertArrayHasKey('cache_list', apcu_cache_info());
  }

  public function testArrayCompatibility() {
    $data = [
      'key1' => 'value1',
      'key2' => 'value2',
    ];
    apcu_delete(array_keys($data));
    apcu_add($data);

    foreach ($data as $key => $value) {
      $this->assertEquals($value, apcu_fetch($key));
    }

    $data = [
      'key1' => 'value2',
      'key2' => 'value3',
    ];
    apcu_store($data);
    $this->assertEquals($data, apcu_fetch(array_keys($data)));
    $this->assertSame(['key1' => TRUE, 'key2' => TRUE], apcu_exists(['key1', 'key2', 'key3']));
    apcu_delete(array_keys($data));
    $this->assertSame([], apcu_exists(array_keys($data)));
  }

  public function testAPCuIterator() {
    $key = __CLASS__;
    $this->assertTrue(apcu_store($key, 456));
    $this->assertTrue(apcu_store($key . '_' . 1, 9));
    $this->assertTrue(apcu_store($key . '_' . 2, 99));
    $this->assertTrue(apcu_store($key . '_' . 3, 999));
    $this->assertTrue(apcu_store($key . '_' . 4, 9999));

    $entries = iterator_to_array(new \APCuIterator('/^' . preg_quote($key, '/') . '$/', APC_ITER_KEY | APC_ITER_VALUE));

    $this->assertSame([$key], array_keys($entries));
    $this->assertSame($key, $entries[$key]['key']);

    $entries = iterator_to_array(new \APCuIterator('/^' . preg_quote($key, '/') . '(.*)$/', APC_ITER_KEY | APC_ITER_VALUE, 4));
    $this->assertSame([
      $key,
      $key . '_' . 1,
      $key . '_' . 2,
      $key . '_' . 3,
    ], array_keys($entries));
    $this->assertSame(456, $entries[$key]['value']);
    $this->assertSame(9, $entries[$key . '_' . 1]['value']);
    $this->assertSame(99, $entries[$key . '_' . 2]['value']);
    $this->assertSame(999, $entries[$key . '_' . 3]['value']);
    $ret = apcu_delete(new \APCuIterator('/^' . preg_quote($key, '/') . '\_(\d*)$/', APC_ITER_KEY | APC_ITER_VALUE));
    $entries = iterator_to_array(new \APCuIterator('/^' . preg_quote($key, '/') . '$/', APC_ITER_KEY | APC_ITER_VALUE));
    $this->assertSame([$key], array_keys($entries));
  }
}
