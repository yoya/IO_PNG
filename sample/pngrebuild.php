<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/PNG.php';
}

$options = getopt("f:");

function usage() {
    echo "Usage: php pngrebuild.php -f <pngfile>".PHP_EOL;
    echo "Usage: php pngrebuild.php -f test.png".PHP_EOL;
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
echo $png->build();
