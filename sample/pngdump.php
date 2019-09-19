<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/PNG.php';
}

$options = getopt("f:hv");

function usage() {
    fprintf(STDERR, "Usage: php pngdump.php -f <png_file> [-h]\n");
    fprintf(STDERR, "ex) php pngdump.php -f test.png -h \n");
}

if (isset($options['f']) === false) {
    usage();
    exit(1);
}
$pngfile = $options['f'];

if ($pngfile === "-") {
    $pngfile = "php://stdin";
} else if (is_readable($pngfile) === false) {
    usage();
    exit(1);
}

$pngdata = file_get_contents($pngfile);

$png = new IO_PNG();
$png->parse($pngdata);

$opts = array(
    'hexdump' => isset($options['h']),
    'verbose' => isset($options['v']),
);

$png->dump($opts);
