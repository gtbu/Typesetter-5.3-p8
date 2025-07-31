<?php

// polyfills/bootstrap.php loader

require_once __DIR__.'/Ctype/bootstrap.php';
require_once __DIR__.'/Mbstring/bootstrap.php';
require_once __DIR__.'/Intl/bootstrap.php'; 
require_once __DIR__.'/Apcu/bootstrap.php';
require_once __DIR__.'/Iconv/bootstrap.php';
require_once __DIR__.'/Uuid/bootstrap.php';

if (\PHP_VERSION_ID < 80000) {
    require_once __DIR__.'/Php80/bootstrap.php';
}
if (\PHP_VERSION_ID < 80100) {
    require_once __DIR__.'/Php81/bootstrap.php';
}
if (\PHP_VERSION_ID < 80200) {
    require_once __DIR__.'/Php82/bootstrap.php';
}
if (\PHP_VERSION_ID < 80300) {
    require_once __DIR__.'/Php83/bootstrap.php';
}
if (\PHP_VERSION_ID < 80400) {
    require_once __DIR__.'/Php84/bootstrap.php';
}
if (\PHP_VERSION_ID < 80500) {
    require_once __DIR__.'/Php85/bootstrap.php';
}