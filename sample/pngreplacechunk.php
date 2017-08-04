<?php

require_once 'IO/PNG.php';

$options = getopt("f:t:d:");

function usage() {
    echo "Usage: php pngreplacechunk -f <pngfile> [-t <chunktype> -d <chunkdata>]".PHP_EOL;
    echo "Usage: php pngreplacechunk -f test.png".PHP_EOL;
    echo "Usage: php pngreplacechunk -f test.png -t iCCP -d sRGB.icc".PHP_EOL;
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
} else {
    $typeArg = $options['t'];
    $dataArg = $options['d'];
    foreach ($png->_chunkList as $idx => $chunk) {
        $chunkName = $chunk['Name'];
        if ($chunkName !== $typeArg)  {
            continue;
        }
        switch ($chunkName) {
        case "iCCP":
            $chunkData = $chunk['Data'];
            $chunkData_head5 = substr($chunkData, 0, 5);
            $iccData = file_get_contents($dataArg);
            $png->_chunkList[$idx]['Data'] = $chunkData_head5 . gzcompress($iccData);
            break;
        }
    }
    echo $png->build();
}
