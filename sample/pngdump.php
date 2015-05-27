<?php

require_once('IO/PNG.php');

$options = getopt("f:hv");

if ((isset($options['f']) === false) || (is_readable($options['f']) === false)) {
    fprintf(STDERR, "Usage: php pngdump.php -f <png_file> [-h]\n");
    fprintf(STDERR, "ex) php pngdump.php -f test.png -h \n");
    exit(1);
}

$pngdata = file_get_contents($options['f']);

$png = new IO_PNG();
$png->parse($pngdata);

$opts = array();
if (isset($options['h'])) {
    $opts['hexdump'] = true;
}
if (isset($options['v'])) {
    $opts['verbose'] = true;
}
$png->dump($opts);
