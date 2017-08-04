<?php

require_once 'IO/PNG.php';

$options = getopt("f:t:d:");

function usage() {
    echo "Usage: php pnggetchunk -f <pngfile> [-t <chunktype>]".PHP_EOL;
    echo "Usage: php pnggetchunk -f test.png".PHP_EOL;
    echo "Usage: php pnggetchunk -f test.png -t iCCP".PHP_EOL;
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
    foreach ($png->_chunkList as $idx => $chunk) {
        $chunkName = $chunk['Name'];
        if ($chunkName !== $typeArg)  {
            continue;
        }
        switch ($chunkName) {
        case "iCCP":
            $iccCompData = substr($chunk['Data'], 5);
            echo gzuncompress($iccCompData);
            break;
        }
    }
}
