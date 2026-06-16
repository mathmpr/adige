<?php

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/Fixtures')) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    require_once $file->getPathname();
}

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/Support')) as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    require_once $file->getPathname();
}
