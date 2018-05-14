#!/usr/local/php/bin/php
<?php
/**
 * Created by PhpStorm.
 * User: Red
 * Date: 2018/5/12
 * Time: 15:13
 */
for ($i = 0; $i < 2; $i++) {
    $ret = pcntl_fork();
    // 以下内容父进程和子进程都会执行
    if ($ret === 0) {
        // 子进程的pid为0，所以这里的逻辑是子进程的逻辑
        sleep(3);
        echo 'PID is ' . posix_getpid();
        // 必须退出
        exit();
    } else if ($ret) {
        // 这里是父进程的逻辑， 父进程获取的是进程号
    } else {}
}