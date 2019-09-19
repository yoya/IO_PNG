<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/PNG.php';
}

$options = getopt("f:t:d:");

function usage() {
    echo "Usage: php pngaddchunk -f <pngfile> [-t <chunktype> -d <chunkdata>]".PHP_EOL;
    echo "Usage: php pngaddchunk -f test.png".PHP_EOL;
    echo "Usage: php pngaddchunk -f test.png -t iCCP -d sRGB.icc".PHP_EOL;
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

if (isset($options['t']) === false) {
    foreach ($png->_chunkList as $idx => $chunk) {
        echo $chunk['Name'].":";
        if (is_string($chunk['Data'])) {
            $chunkData = $chunk['Data'];
            $chunkLen = strlen($chunkData);
            echo "($chunkLen)";
        }
        echo PHP_EOL;
    }
} else if (isset($options['d']) === false) {
    fwrite(STDERR, "t(chunk type) & d(chunk data) parameters required in set.\n");
    exit (1);
} else {
    $typeArg = $options['t'];
    $dataArg = $options['d'];
    switch ($typeArg) {
    case "iCCP":
        foreach ($png->_chunkList as $idx => $chunk) {
            if (in_array($chunk["Name"], ["IHDR"]) === false) {
                break;
            }
        }
        $chunkData_head5 = "icc\0\0";
        $iccData = file_get_contents($dataArg);
        $chunk = ["Name" => "iCCP",
                  "Data" => $chunkData_head5 . gzcompress($iccData)];
        array_splice($png->_chunkList, $idx, 0, [$chunk]);
        break;
    default:
        echo "Unknown type:$typeArg\n";
        exit (1);
    }
    echo $png->build();
}
