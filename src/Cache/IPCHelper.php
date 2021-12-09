<?php

namespace Blueflame\Cache;

final class IPCHelper {

  public static function __getAllInstancesStatus($id = NULL) {

    $status = [];
    if (stristr(PHP_OS, 'DAR')) {
      $max = 0;//  exec("cat /proc/sysvipc/shm| awk '{ print $2 }'");
    }
    elseif (stristr(PHP_OS, 'LINUX')) {
      exec("cat /proc/sysvipc/shm", $buffer);
      //['key','shmid','perms','size','cpid','lpid','nattch','uid','gid','cuid','cgid','atime','dtime','ctime','rss','swap'];
      $head = preg_split('/\s+/', trim($buffer[0]));
      unset($buffer[0]);
      foreach ($buffer as $line) {
        $cols = preg_split('/\s+/', trim($line));
        $data = array_combine($head, $cols);
        $status[(int) $data['key']] = $data;
        $status[$cols[0]]['name'] = $cols[0];
        $status[$cols[0]]['user'] = posix_getpwuid($data['uid']);
        $status[$cols[0]]['group'] = posix_getgrgid($data['gid']);
        $status[$cols[0]]['access'] = self::__icanread($data);
      }
    }

    if (isset($id, $status[$id])) {
      return ($status[$id] ?? []);
    }
    return $status;
  }

  //
  private static function __icanread($data) {
    if (0 == (int) $data['key']) {
      return FALSE;
    }
    // try to attach
    try {
      $tmp = @shm_attach((int) $data['key'], (int) $data['size']);
      return $tmp !== FALSE;
    } catch (\Exception $exception) {
      return FALSE;
    }

    /*
     * The mode member of the ipc_perm structure defines,
     * with its lower 9 bits, the access permissions to the resource
     * for a process executing an IPC system call.
     * The permissions are interpreted as follows:
     * 0400    Read by user.
     * 0200    Write by user.
     * 0040    Read by group.
     * 0020    Write by group.
     * 0004    Read by others.
     * 0002    Write by others.
     */
    $perm = strrev($data['perms']);
    if ($data['uid'] == posix_getuid()) {
      // My Cache , I can read
      return $perm[2] >= 4;
    }
    if ($data['gid'] == posix_getgid()) {
      // My Cache , I can read
      return $perm[1] >= 4;
    }
    return $perm[0] >= 4;
  }
}
