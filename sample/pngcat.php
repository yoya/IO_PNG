<?php

require_once 'IO/PNG.php';

function usage() {
    echo "Usage: php pngcat.php <pngchunk> [<pngchunk2> [...] ]".PHP_EOL;
}

echo IO_PNG::SIGNATURE;
foreach (array_slice($argv, 1) as $chunkfile) {
    echo file_get_contents($chunkfile);
}

exit(0);
