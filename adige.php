#!/usr/bin/env php
<?php

include 'vendor/autoload.php';

use Adige\cli\Console;

global $_argv, $_argc;
$_argv = $argv;
$_argc = $argc;

new Console();
