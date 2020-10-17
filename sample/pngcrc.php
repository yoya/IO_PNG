<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/PNG.php';
}

$options = getopt("f:c");

function usage() {
    echo "Usage: php pngcrc.php -f <pngfile> # list".PHP_EOL;
    echo "Usage: php pngcrc.php -f <pngfile> -c # correct".PHP_EOL;
}

if ((isset($options['f']) === false) ||
    (is_readable($options['f']) === false)) {
    usage();
    exit(1);
}

$pngfile = $options['f'];
$pngdata = file_get_contents($pngfile);

$png = new IO_PNG();
$png->parse($pngdata);

if (isset($options['c'])) {
    echo $png->build();  // build method re-calculate the crc value.
} else {
    // list only
    foreach ($png->_chunkList as $idx => $chunk) {
        $chunkName = $chunk['Name'];
        $chunkOffset = $chunk['_offset'];
        $chunkLength = $chunk['_length'];
        $chunkNameData = substr($png->_pngdata, $chunkOffset + 4, $chunkLength - 8);
        $chunkCRC = $chunk['CRC'];
        $crc = crc32($chunkNameData);
        echo "{$pngfile}[$idx] $chunkName crc:$chunkCRC";
        if ($chunkCRC !== $crc) {
            echo " => $crc";
        }
        echo PHP_EOL;
    }
}

exit(0);
