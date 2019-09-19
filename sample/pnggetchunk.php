<?php

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/PNG.php';
}

$options = getopt("f:t:u");

function usage() {
    echo "Usage: php pnggetchunk -f <pngfile> [-t <chunktype>] [-u]".PHP_EOL;
    echo "Usage: php pnggetchunk -f test.png".PHP_EOL;
    echo "Usage: php pnggetchunk -f test.png -t gAMA".PHP_EOL;
    echo "Usage: php pnggetchunk -f test.png -t iCCP -u".PHP_EOL;
    echo "Usage: php pnggetchunk -f test.png -t IDAT -u".PHP_EOL;
}

if ((isset($options['f']) === false) ||
    (is_readable($options['f']) === false)) {
    usage();
    exit(1);
}

$pngfile = $options['f'];
$pngdata = file_get_contents($pngfile);

$uncompress = isset($options['u']);

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
    $data = "";
    foreach ($png->_chunkList as $idx => $chunk) {
        $chunkName = $chunk['Name'];
        if ($chunkName !== $typeArg)  {
            continue;
        }
        switch ($chunkName) {
        case "IDAT":
            $data .= $chunk['Data'];
            break;
        case "iCCP":
            $data = substr($chunk['Data'], 5);
            break;
        case "IDAT":
        default: // "gAMA"
            $data = $chunk['Data'];
            break;
        }
    }
    if ($uncompress) {
        $data = gzuncompress($data);
    }
    echo $data;
}
