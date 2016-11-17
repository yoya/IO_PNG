<?php

require_once 'IO/PNG.php';

$options = getopt("f:p:");

function usage() {
    echo "Usage: php pngextract.php -f <file> -p <prefix>".PHP_EOL;
    echo "ex) php pngextract.php -f input.dat -p output".PHP_EOL;
}

if ((isset($options['f']) === false) ||
    (is_readable($options['f']) === false) || 
    (isset($options['p']) === false)) {
    usage();
    exit(1);
}

$file = $options['f'];
$prefix = $options['p'];
$data = file_get_contents($file);

$offset = 0;
$i = 0;
for ($i = 0 ; true ; $i++) {
    $offset = strpos($data, IO_PNG::SIGNATURE, $offset);
    if ($offset === false) {
        break;
    }
    $pngdata = substr($data, $offset);
    $png = new IO_PNG();
    $png->parse($pngdata);
    $outputdata = $png->build($pngdata);
    $outputFilename = sprintf("%s%04d.png", $prefix, $i);
    echo "$outputFilename\n";
    file_put_contents($outputFilename, $outputdata);
    $offset += strlen(IO_PNG::SIGNATURE);
}

exit(0);
