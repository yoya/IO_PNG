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

function checkThrows($name, $func) {
    global $failed, $passed;
    try {
        $func();
    } catch (Exception $e) {
        $passed++;
        return;
    }
    $failed++;
    echo "FAIL: $name (exception not thrown)".PHP_EOL;
}

// run a php script in a child process (same include_path, E_ALL), killed after $timeout seconds
function runPHP($args, $cwd = null, $timeout = 10) {
    $cmd = escapeshellarg(PHP_BINARY).' -d include_path='.escapeshellarg(get_include_path()).
         ' -d error_reporting=-1 -d display_errors=stderr '.join(' ', array_map('escapeshellarg', $args));
    $proc = proc_open($cmd, array(0 => array('file', '/dev/null', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, $cwd);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = $err = '';
    $deadline = microtime(true) + $timeout;
    while (true) {
        $out .= stream_get_contents($pipes[1]);
        $err .= stream_get_contents($pipes[2]);
        $st = proc_get_status($proc);
        if (! $st['running']) {
            break;
        }
        if (microtime(true) > $deadline) {
            proc_terminate($proc, 9);
            $err .= 'TIMEOUT';
            break;
        }
        usleep(10000);
    }
    $out .= stream_get_contents($pipes[1]);
    $err .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    return array($st['running'] ? -1 : $st['exitcode'], $out, $err);
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

/*
 * iCCP with a profile name other than 3 bytes
 */
$icc = str_repeat("ICCDATA-", 8);
$dump = dumpOf(parsePNG(png(chunk('iCCP', "ICC Profile\0\0".gzcompress($icc)))));
check('iCCP dump long name', true, strpos($dump, "    ICCDATA-ICCDATA-") !== false);

/*
 * dumpFilter
 */
ob_start();
parsePNG(png(''))->dumpFilter(['summalize' => true]);
check('dumpFilter', "0:2\n1:0\n2:0\n3:0\n4:0\n", ob_get_clean());
$png = new IO_PNG();
$png->_chunkList = [];
ob_start();
$png->dumpFilter(['summalize' => true]);  // no IHDR: error message only
check('dumpFilter no IHDR', '', ob_get_clean());
$png = parsePNG(png(''));
$png->deleteChunks('IDAT');
ob_start();
$png->dumpFilter(['summalize' => true]);  // no IDAT: error message only
check('dumpFilter no IDAT', '', ob_get_clean());

/*
 * dump without detail: length of a printable chunk
 */
$dump = dumpOf(parsePNG(png(chunk("teSt", "hello"))), array());
check('dump printable chunk length', true, strpos($dump, "Name:teSt Size=5 CRC=") !== false &&
      strpos($dump, " (5)\n") !== false);

/*
 * build keeps the parsed signature (JNG stays JNG)
 */
$input = IO_PNG::SIGNATURE_JNG.substr(png(''), 8);
check('JNG signature build', bin2hex($input), bin2hex(parsePNG($input)->build()));
$png = new IO_PNG();
$png->_chunkList = parsePNG(png(''))->_chunkList;
check('build without parse: PNG signature', png(''), $png->build());

/*
 * tRNS dump: 16-bit samples for gray and RGB, 1 byte per palette entry
 */
function trnsDump($colorType, $trns) {
    $ihdr = chunk('IHDR', pack('NN', 2, 2)."\x08".chr($colorType)."\x00\x00\x00");
    $plte = ($colorType === 3) ? chunk('PLTE', "\xff\x00\x00\x00\xff\x00") : '';
    $data = IO_PNG::SIGNATURE.$ihdr.$plte.chunk('tRNS', $trns).chunk('IDAT', gzcompress(str_repeat("\0", 14))).chunk('IEND', '');
    ob_start();
    parsePNG($data)->dump(array('detail' => true));
    $out = ob_get_clean();
    preg_match('/Name:tRNS[^\n]*\n(.*?)Name:/s', $out, $m);
    return $m[1];
}
check('tRNS dump gray', "    0x00: 00ff\n", trnsDump(0, "\x00\xff"));
check('tRNS dump RGB', "    0x00: 001100220033\n", trnsDump(2, "\x00\x11\x00\x22\x00\x33"));
check('tRNS dump palette', "    0x00: 00 ff\n", trnsDump(3, "\x00\xff"));

/*
 * test data (tests/data/make.php)
 */
$dataDir = $root.'/tests/data';
function dataFile($name) {
    global $dataDir;
    return file_get_contents($dataDir.'/'.$name);
}
function chunkNames($png) {
    return array_values(array_map(function ($c) { return $c['Name']; }, $png->_chunkList));
}
// call $func and collect PHP warnings / notices it raises
function errorsOf($func) {
    $errors = array();
    set_error_handler(function ($no, $str) use (&$errors) { $errors []= $str; return true; });
    ob_start();
    try {
        $func();
    } finally {
        ob_end_clean();
        restore_error_handler();
    }
    return $errors;
}
$tmpDir = sys_get_temp_dir().'/io_png_test_'.getmypid();
mkdir($tmpDir);
function cleanTmp() {
    global $tmpDir;
    foreach (glob("$tmpDir/*") as $f) {
        unlink($f);
    }
}

// make.php regenerates the committed files byte-identically
$dataNames = array('gray.png', 'rgb.png', 'palette.png', 'grayalpha.png', 'rgba.png', 'simple.jng',
                   'badcrc.png', 'trailing.png', 'truncated.png');
list($status, $out, $err) = runPHP(array($dataDir.'/make.php', $tmpDir));
check('make.php status', array(0, ''), array($status, $err));
foreach ($dataNames as $name) {
    check("make.php $name", md5(dataFile($name)), md5((string) @file_get_contents("$tmpDir/$name")));
}
cleanTmp();

// gray.png
$png = parsePNG(dataFile('gray.png'));
check('gray signature', IO_PNG::SIGNATURE, $png->_signature);
check('gray chunks', array('IHDR', 'gAMA', 'pHYs', 'tRNS', 'IDAT', 'IEND'), chunkNames($png));
check('gray IHDR', array('Width' => 3, 'Height' => 2, 'BitDepth' => 8, 'ColorType' => 0,
                         'Compression' => 0, 'Filter' => 0, 'Interlace' => 0), $png->getIHDRdata());
check('gray IHDR chunk', array(13, 8, 25), array($png->_chunkList[0]['Size'], $png->_chunkList[0]['_offset'], $png->_chunkList[0]['_length']));
check('gray IHDR CRC', crc32('IHDR'.pack('NNCCCCC', 3, 2, 8, 0, 0, 0, 0)), $png->_chunkList[0]['CRC']);
check('gray gAMA', 0.45455, $png->_chunkList[1]['Data']);
check('gray pHYs', array('PixelsX' => 2835, 'PixelsY' => 2835, 'UnitSpec' => 1), $png->_chunkList[2]['Data']);
check('gray tRNS', "\x00\x14", $png->_chunkList[3]['Data']);
check('gray IEND', array(0, ''), array($png->_chunkList[5]['Size'], $png->_chunkList[5]['Data']));
check('gray IDAT inflate', "\x00\x0a\x14\x1e\x01\x05\x05\x05", gzuncompress($png->getIDATdata()));

// rgb.png
$png = parsePNG(dataFile('rgb.png'));
check('rgb chunks', array('IHDR', 'cHRM', 'pHYs', 'tRNS', 'tEXt', 'IDAT', 'IDAT', 'IEND'), chunkNames($png));
check('rgb IHDR ColorType', 2, $png->getIHDRdata()['ColorType']);
check('rgb cHRM', array('WhitePoint_x' => 0.3127, 'WhitePoint_y' => 0.329, 'Red_x' => 0.64, 'Red_y' => 0.33,
                        'Green_x' => 0.3, 'Green_y' => 0.6, 'Blue_x' => 0.15, 'Blue_y' => 0.06), $png->_chunkList[1]['Data']);
check('rgb pHYs', array('PixelsX' => 3, 'PixelsY' => 2, 'UnitSpec' => 0), $png->_chunkList[2]['Data']);
check('rgb tRNS', "\x00\xff\x00\x00\x00\xff", $png->_chunkList[3]['Data']);
check('rgb tEXt', "Comment\0hello", $png->_chunkList[4]['Data']);
check('rgb IDAT sizes', array(7, 18), array($png->_chunkList[5]['Size'], $png->_chunkList[6]['Size']));
check('rgb IDAT inflate (2 chunks)', "\x01\xff\x00\x00\x01\x00\x00\x02\x00\xff\x00\x00\x00\xff", gzuncompress($png->getIDATdata()));

// palette.png
$png = parsePNG(dataFile('palette.png'));
check('palette chunks', array('IHDR', 'iCCP', 'PLTE', 'tRNS', 'IDAT', 'IEND'), chunkNames($png));
check('palette IHDR', array(4, 2, 8, 3), array_values(array_slice($png->getIHDRdata(), 0, 4)));
$iccp = $png->_chunkList[1]['Data'];
check('palette iCCP name', "Test Profile\0\0", substr($iccp, 0, 14));
$profile = gzuncompress(substr($iccp, 14));
check('palette iCCP profile', array(132, 132, 'mntr', 'GRAY', 'acsp'),
      array(strlen($profile), unpack('N', $profile)[1], substr($profile, 12, 4), substr($profile, 16, 4), substr($profile, 36, 4)));
check('palette PLTE', array(30, "\x00\xff\x80", "\xe1\x1e\x89"),
      array(strlen($png->_chunkList[2]['Data']), substr($png->_chunkList[2]['Data'], 0, 3), substr($png->_chunkList[2]['Data'], 27, 3)));
check('palette tRNS', "\x00\x80\xff", $png->_chunkList[3]['Data']);

// grayalpha.png
$png = parsePNG(dataFile('grayalpha.png'));
check('grayalpha IHDR', array('Width' => 1, 'Height' => 1, 'BitDepth' => 16, 'ColorType' => 4,
                              'Compression' => 0, 'Filter' => 0, 'Interlace' => 1), $png->getIHDRdata());
check('grayalpha IDAT inflate', "\x03\x12\x34\xff\xff", gzuncompress($png->getIDATdata()));

// rgba.png
$png = parsePNG(dataFile('rgba.png'));
check('rgba chunks', array('IHDR', 'iDOT', 'IDAT', 'IDAT', 'IEND'), chunkNames($png));
check('rgba iDOT', array('count' => 3, 'reserved' => 0, 'height' => 1, 'chunkSize' => 40,
                         'entries' => array(array('offset' => 1, 'height' => 1, 'idatOffset' => 100),
                                            array('offset' => 2, 'height' => 1, 'idatOffset' => 120))),
      $png->_chunkList[1]['Data']);

// simple.jng
$png = parsePNG(dataFile('simple.jng'));
check('jng signature', IO_PNG::SIGNATURE_JNG, $png->_signature);
check('jng chunks', array('JHDR', 'JDAT', 'IEND'), chunkNames($png));
check('jng JHDR bytes', pack('NNCCCCCCCC', 8, 8, 10, 8, 8, 0, 0, 0, 0, 0), $png->_chunkList[0]['Data']);
check('jng JDAT bytes', "\xff\xd8\xff\xd9", $png->_chunkList[1]['Data']);

// trailing.png: parsing stops at IEND
$png = parsePNG(dataFile('trailing.png'));
check('trailing chunks', array('IHDR', 'gAMA', 'pHYs', 'tRNS', 'IDAT', 'IEND'), chunkNames($png));
check('trailing build drops bytes after IEND', bin2hex(dataFile('gray.png')), bin2hex($png->build()));

// build round trips
foreach (array('gray.png', 'rgb.png', 'palette.png', 'grayalpha.png', 'rgba.png', 'simple.jng') as $name) {
    check("build $name", bin2hex(dataFile($name)), bin2hex(parsePNG(dataFile($name))->build()));
}

// dump(): no warnings, detail and summary
foreach (array('gray.png', 'rgb.png', 'palette.png', 'grayalpha.png', 'rgba.png') as $name) {
    $png = parsePNG(dataFile($name));
    check("dump detail $name", array(), errorsOf(function () use ($png) { $png->dump(array('detail' => true)); }));
    check("dump hexdump $name", array(), errorsOf(function () use ($png) { $png->dump(array('detail' => true, 'hexdump' => true)); }));
}
foreach (array('gray.png', 'rgb.png', 'palette.png', 'grayalpha.png', 'simple.jng') as $name) {
    $png = parsePNG(dataFile($name));
    check("dump summary $name", array(), errorsOf(function () use ($png) { $png->dump(array()); }));
}
$dump = dumpOf(parsePNG(dataFile('gray.png')));
check('dump gray IHDR', true, strpos($dump, "  Width:3 Height:2 BitDepth:8 ColorType:0(GRAY) Compression:0 Filter:0 Interlate:0\n") !== false);
check('dump gray gAMA', true, strpos($dump, "    Gamma:0.45455\n") !== false);
check('dump gray pHYs', true, strpos($dump, "    pixelsX:2835, pixelsY:2835, unitSpecName:Metre\n    (inch pixelsX,pixelsY:72)\n") !== false);
check('dump gray tRNS', true, strpos($dump, "Name:tRNS Size=2 CRC=1816729925\n    0x00: 0014\n") !== false);
$dump = dumpOf(parsePNG(dataFile('rgb.png')));
check('dump rgb cHRM', true, strpos($dump, "    WhitePoint:0.313,0.329\n    Red:0.640,0.330 Green:0.300,0.600 Blue:0.150,0.060\n") !== false);
check('dump rgb pHYs unknown unit', true, strpos($dump, "    pixelsX:3, pixelsY:2, unitSpecName:Unknown\nName:") !== false);
$dump = dumpOf(parsePNG(dataFile('palette.png')));
check('dump palette PLTE 10 entries', true, strpos($dump, "    0x00: 00ff80 19e681 32cd82 4bb483 649b84 7d8285 966986 af5087\n    0x08: c83788 e11e89\n") !== false);
check('dump palette iCCP', true, strpos($dump, "    Test Profile\0\0\n") !== false);
check('dump palette IEND inflated', true, strpos($dump, "0x00000000  00 00 01 02 03 04 09 08  07 06") !== false);
$dump = dumpOf(parsePNG(dataFile('rgba.png')));
check('dump rgba iDOT', true, strpos($dump, "    count:3 reserved:0 height:1 chunkSize:40]\n      [1/3]: offset:1 height:1 idatOffset:100\n      [2/3]: offset:2 height:1 idatOffset:120\n") !== false);
$dump = dumpOf(parsePNG(dataFile('gray.png')), array());
$expect = "Name:IHDR Size=13 CRC=3089054150 (3,2,8,0,0,0,0)\nName:gAMA Size=4 CRC=201089285 (0.45455)\nName:pHYs Size=9 CRC=10132504 (2835,2835,1)\n";
check('dump summary gray', $expect, substr($dump, 0, strlen($expect)));

// edit APIs
$png = parsePNG(dataFile('gray.png'));
$png->deleteChunks('tRNS');
check('deleteChunks names', array('IHDR', 'gAMA', 'pHYs', 'IDAT', 'IEND'), chunkNames($png));
$gray = dataFile('gray.png');
$trnsPos = strpos($gray, 'tRNS') - 4;
check('deleteChunks build', bin2hex(substr($gray, 0, $trnsPos).substr($gray, $trnsPos + 14)), bin2hex($png->build()));
$png = parsePNG(dataFile('rgb.png'));
$png->deleteChunks('IDAT');
check('deleteChunks multiple', array('IHDR', 'cHRM', 'pHYs', 'tRNS', 'tEXt', 'IEND'), chunkNames($png));
check('getIDATdata none', '', $png->getIDATdata());
$png = new IO_PNG();
$png->_chunkList = array();
check('getIHDRdata none', false, $png->getIHDRdata());
$png = parsePNG(dataFile('gray.png'));
$png->_chunkList[1]['Data'] = 1 / 2.2;
$png->_chunkList[2]['Data']['UnitSpec'] = 0;
$png->_chunkList[0]['Data']['Width'] = 0x12345678;
$out = parsePNG($png->build());
check('edit gAMA', 0.45455, $out->_chunkList[1]['Data']);
check('edit pHYs', 0, $out->_chunkList[2]['Data']['UnitSpec']);
check('edit IHDR', 0x12345678, $out->getIHDRdata()['Width']);
// iDOT build: a count that does not match the entries is fixed, with a warning on STDERR (child process)
$code = 'require_once "IO/PNG.php"; $png = new IO_PNG(); $png->_chunkList = [["Name" => "iDOT", "Data" => '.
      '["count" => 5, "reserved" => 0, "height" => 1, "chunkSize" => 40, "entries" => [["offset" => 1, "height" => 1, "idatOffset" => 80]]]]];'.
      ' echo bin2hex($png->build());';
list($status, $out, $err) = runPHP(array('-r', $code));
check('iDOT build count fix', array(0, bin2hex(IO_PNG::SIGNATURE.chunk('iDOT', pack('N*', 2, 0, 1, 40, 1, 1, 80)))), array($status, $out));
check('iDOT build count warning', 1, preg_match('/^Warning: count:5 !== count\(entries\):1 \+ 1\n$/', $err));

foreach (array(array(0, 1), array(2, 3), array(3, 1), array(4, 2), array(6, 4)) as $t) {
    check("getNCompByColorType {$t[0]}", $t[1], IO_PNG::getNCompByColorType($t[0]));
}
checkThrows('getNCompByColorType 5', function () { IO_PNG::getNCompByColorType(5); });

// dumpFilter / changeFilter
function filterOf($name, $summalize) {
    ob_start();
    parsePNG(dataFile($name))->dumpFilter(array('summalize' => $summalize));
    return ob_get_clean();
}
check('dumpFilter gray', "0:1\n1:1\n2:0\n3:0\n4:0\n", filterOf('gray.png', true));
check('dumpFilter rgb', "0:0\n1:1\n2:1\n3:0\n4:0\n", filterOf('rgb.png', true));
check('dumpFilter palette', "0 4 \n", filterOf('palette.png', false));
check('dumpFilter grayalpha 16bit', "3 \n", filterOf('grayalpha.png', false));
check('dumpFilter rgba', "0 1 2 \n", filterOf('rgba.png', false));
$png = parsePNG(dataFile('rgb.png'));
$png->changeFilter(4);
$out = parsePNG($png->build());
check('changeFilter chunks', array('IHDR', 'cHRM', 'pHYs', 'tRNS', 'tEXt', 'IDAT', 'IEND'), chunkNames($out));
check('changeFilter rows', "\x04\xff\x00\x00\x01\x00\x00\x04\x00\xff\x00\x00\x00\xff", gzuncompress($out->getIDATdata()));
$png = parsePNG(dataFile('rgba.png'));
$png->changeFilter(0);
check('changeFilter rgba rows', "\x00".str_repeat("\x10\x20\x30\x40", 2)."\x00".str_repeat("\x01\x02\x03\x04", 2)."\x00".str_repeat("\x00", 8),
      gzuncompress($png->getIDATdata()));

// broken input
checkThrows('parse empty', function () { parsePNG(''); });
checkThrows('parse short', function () { parsePNG(substr(IO_PNG::SIGNATURE, 0, 7)); });
checkThrows('parse bad signature', function () { parsePNG("GIF89a\0\0".substr(dataFile('gray.png'), 8)); });
checkThrows('parse truncated IDAT', function () { parsePNG(dataFile('truncated.png')); });
checkThrows('parse truncated CRC', function () { parsePNG(substr(dataFile('gray.png'), 0, -2)); });
checkThrows('parse truncated chunk data', function () { parsePNG(substr(dataFile('gray.png'), 0, 8 + 25 + 9)); });
checkThrows('parse short IHDR', function () { parsePNG(IO_PNG::SIGNATURE.chunk('IHDR', pack('NN', 1, 1)."\x08\x00\x00\x00")); });
checkThrows('parse short gAMA', function () { parsePNG(IO_PNG::SIGNATURE.chunk('gAMA', "\x00\x01")); });
checkThrows('parse short cHRM', function () { parsePNG(IO_PNG::SIGNATURE.chunk('cHRM', str_repeat("\0", 28))); });
checkThrows('parse short pHYs', function () { parsePNG(IO_PNG::SIGNATURE.chunk('pHYs', str_repeat("\0", 8))); });
checkThrows('parse iDOT count > entries', function () { parsePNG(IO_PNG::SIGNATURE.chunk('iDOT', pack('N*', 3, 0, 1, 40, 1, 1, 100))); });
$png = parsePNG(IO_PNG::SIGNATURE.chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 0, 0, 0, 0)));
check('parse without IEND', array('IHDR'), chunkNames($png));

rmdir($tmpDir);

echo "passed: $passed, failed: $failed".PHP_EOL;
exit($failed ? 1 : 0);
