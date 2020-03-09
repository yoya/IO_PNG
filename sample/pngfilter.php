<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/PNG.php';
}

$options = getopt("f:n:");

function usage() {
    fprintf(STDERR, "Usage: php pngfilter.php -f <png_file> [-n <filter>]\n");
    fprintf(STDERR, "ex) php pngfilter.php -f test.png -n 4 > retult.png\n");
}

if (isset($options['f']) === false) {
    usage();
    exit(1);
}

$pngfile = $options['f'];
$filter = isset($options['n'])? intval($options['n']): null;

if ($pngfile === "-") {
    $pngfile = "php://stdin";
} else if (is_readable($pngfile) === false) {
    usage();
    exit(1);
}

$pngdata = file_get_contents($pngfile);

$png = new IO_PNG();

try {
    $png->parse($pngdata);
} catch (Exception $e) {
    echo "Exception".$e->getMessage().PHP_EOL;
}

if (is_null($filter)) {
    $png->dumpFilter();
} else {
    $png->changeFilter($filter);
    echo $png->build();
}
