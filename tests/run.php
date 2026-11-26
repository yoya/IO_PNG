<?php
/*
 * regression tests (no dependency)
 * usage: php tests/run.php
 *   IO_Bit is loaded from vendor/ (composer install) or include_path.
 */

$root = dirname(dirname(__FILE__));
if (is_readable($root.'/vendor/autoload.php')) {
    require_once $root.'/vendor/autoload.php';
}
set_include_path($root.PATH_SEPARATOR.get_include_path());
require_once 'IO/PNG.php';

$failed = 0;
$passed = 0;

function check($name, $expect, $actual) {
    global $failed, $passed;
    if ($expect === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL: $name".PHP_EOL;
    echo "  expect: ".var_export($expect, true).PHP_EOL;
    echo "  actual: ".var_export($actual, true).PHP_EOL;
}

function chunk($name, $data) {
    return pack('N', strlen($data)).$name.$data.pack('N', crc32($name.$data));
}

function png($chunks) {
    $ihdr = chunk('IHDR', pack('NN', 2, 2)."\x08\x00\x00\x00\x00");
    $idat = chunk('IDAT', gzcompress("\0\x10\x20\0\x30\x40"));
    return IO_PNG::SIGNATURE.$ihdr.$chunks.$idat.chunk('IEND', '');
}

function parsePNG($data) {
    $png = new IO_PNG();
    $png->parse($data);
    return $png;
}

function findChunk($png, $name) {
    foreach ($png->_chunkList as $chunk) {
        if ($chunk['Name'] === $name) {
            return $chunk;
        }
    }
    return null;
}

/*
 * iDOT (Apple)
 */
$idot = pack('N*', 2, 0, 1, 40, 1, 1, 80);
$png = parsePNG(png(chunk('iDOT', $idot)));
$chunk = findChunk($png, 'iDOT');
check('iDOT parse count', 2, $chunk['Data']['count']);
check('iDOT parse entries',
      [['offset' => 1, 'height' => 1, 'idatOffset' => 80]],
      $chunk['Data']['entries']);

$input = png(chunk('iDOT', $idot));
check('iDOT build', $input, parsePNG($input)->build());

function dumpOf($png, $opts = array('detail' => true)) {
    ob_start();
    $png->dump($opts);
    return ob_get_clean();
}

/*
 * PLTE dump
 */
$dump = dumpOf(parsePNG(png(chunk('PLTE', "\xff\x00\x00\x00\xff\x00"))));
check('PLTE dump RGB', true, strpos($dump, "    0x00: ff0000 00ff00\n") !== false);

/*
 * gAMA, cHRM: parse -> build keeps the values
 */
$input = png(chunk('gAMA', pack('N', 45455)));
check('gAMA build', $input, parsePNG($input)->build());
for ($g = 0; $g <= 200000; $g++) {
    $png = new IO_PNG();
    $png->_chunkList = [['Name' => 'gAMA', 'Data' => $g / 100000]];
    if ($png->build() !== IO_PNG::SIGNATURE.chunk('gAMA', pack('N', $g))) {
        break;
    }
}
check('gAMA build 0..200000', 200001, $g);
$input = png(chunk('cHRM', pack('N*', 31270, 32900, 64000, 33000,
                                30000, 60000, 15000, 6000)));
check('cHRM build', $input, parsePNG($input)->build());

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
