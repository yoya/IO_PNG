<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/PNG.php';
}

function usage() {
    echo "Usage: php pngcat.php <pngchunk> [<pngchunk2> [...] ]".PHP_EOL;
}

if ($argc < 2) {
    usage();
    exit (1);
}

echo IO_PNG::SIGNATURE;
foreach (array_slice($argv, 1) as $chunkfile) {
    $chunkNameData = file_get_contents($chunkfile);
    $chunkLen = pack("N", strlen($chunkNameData) - 4);
    $crc = crc32($chunkNameData);
    $chunkCRC = pack("N", $crc);
    echo $chunkLen . $chunkNameData . $chunkCRC;
}

exit(0);
