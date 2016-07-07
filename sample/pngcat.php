<?php

require_once 'IO/PNG.php';

function usage() {
    echo "Usage: php pngcat.php <pngchunk> [<pngchunk2> [...] ]".PHP_EOL;
}

echo IO_PNG::SIGNATURE;
foreach (array_slice($argv, 1) as $chunkfile) {
    $chunk = file_get_contents($chunkfile);
    $chunkLen = substr($chunk, 0, 4);
    $tmp = unpack("N", $chunkLen);
    $len = $tmp[1];
    $chunkNameData = substr($chunk, 4, 4 + $len);
    $crc = crc32($chunkNameData);
    $chunkCRC = pack("N", $crc);
    //
    $chunkCRCorig = substr($chunk, 4 + 4 + $len, 4);
    $tmp = unpack("N", $chunkCRCorig);
    $origCRC= $tmp[1];
    if ($origCRC !== $crc) {
        fprintf(STDERR, "origCRC($origCRC) !== crc($crc)\n");
    }
    echo $chunkLen . $chunkNameData . $chunkCRC;
}

exit(0);
