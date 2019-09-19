<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/PNG.php';
}

$options = getopt("f:t:");

function usage() {
    echo "Usage: php pngdelchunk -f <pngfile> [-t <chunktype>]".PHP_EOL;
    echo "Usage: php pngdelchunk -f test.png".PHP_EOL;
    echo "Usage: php pngdelchunk -f test.png -t tEXt".PHP_EOL;
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

if (isset($options['t']) === false) {
    $png->dump(["detail" => false]);
} else {
    $typeArg = $options['t'];
    $chunkList = [];
    foreach ($png->_chunkList as $idx => $chunk) {
        $chunkName = $chunk['Name'];
        if ($chunkName === $typeArg)  {
            continue; // delete chunk
        }
        $chunkList []= $chunk;
    }
    $png->_chunkList = $chunkList;
    echo $png->build();
}
