<?php

require_once('IO/PNG.php');
  // require_once '/home/yoya/svn/IO_PNG/IO/PNG.php';

$pngdata = file_get_contents($argv[1]);

$zip = new IO_PNG();
$zip->parse($pngdata);
$zip->dump();
