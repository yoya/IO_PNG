<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/PNG.php';
}

$options = getopt("f:n:");

function usage() {
    fprintf(STDERR, "Usage: php pngchangefilter.php -f <png_file> -n [0,1,2,3,4]\n");
    fprintf(STDERR, "ex) php pngchangefilter.php -f test.png -n 4\n");
}

if ((isset($options['f']) === false) || (isset($options['n']) === false)) {
    usage();
    exit(1);
}
$filter = intval($options['n']);
if (($filter < 0) || (4 < $filter)) {
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

try {
    $png->parse($pngdata);
} catch (Exception $e) {
    echo "Exception".$e->getMessage().PHP_EOL;
}

$opts = array(
    'hexdump'  => isset($options['h']),
    'verbose'  => isset($options['v']),
    'detail' => ! isset($options['D']),
);

$png->changeFilter($filter, $opts);
echo $png->build();
