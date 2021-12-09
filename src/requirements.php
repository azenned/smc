<?php
// "define if not defined"
function defaults($d, $v) {
  if (!defined($d)) {
    define($d, $v);
  } // or just @define(...)
}

if (stristr(PHP_OS, 'DAR')) {
  $max = exec("sysctl kern.sysv.shmmax| awk '{ print $2 }'");
}
elseif (stristr(PHP_OS, 'LINUX')) {
  $max = exec("cat /proc/sys/kernel/shmmax");
  if (empty($max)) {
    $max = exec("sysctl kernel.shmmax| awk '{ print $3 }'");
  }
}
else {
  // Windows is not supported.
  return FALSE;
}

if (intval($max) < (128 * 1024 * 1024)) {
  if (php_sapi_name() == "cli") {
    $precision = 2;
    $base = log($max, 1024);
    $suffixes = ['', 'Kb', 'Mb', 'Gb', 'Tb'];
    $formated = round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    echo "The server is configured to use " . $formated . ", see kernel.shmmax in /proc/sys/kernel/shmmax\n";
    echo "SMC require shm memory 128MB at least .\n";
  }
  return FALSE;
}

return TRUE;
