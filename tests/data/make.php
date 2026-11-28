<?php
/*
 * generate synthetic PNG / JNG test data (no real images)
 * usage: php tests/data/make.php [outdir]   (default: this directory)
 *   ref) https://www.w3.org/TR/png/ , RFC 1950 (zlib), RFC 1951 (deflate), JNG 1.0
 *   zlib streams are written here (not by gzcompress) so the output does not
 *   depend on the zlib version.
 */

$dir = isset($argv[1]) ? $argv[1] : dirname(__FILE__);

function chunk($name, $data) {
    return pack('N', strlen($data)).$name.$data.pack('N', crc32($name.$data));
}

function ihdr($w, $h, $depth, $type, $interlace = 0) {
    return chunk('IHDR', pack('NNCCCCC', $w, $h, $depth, $type, 0, 0, $interlace));
}

// zlib stream with one stored (uncompressed) deflate block
function zlibStored($data) {
    $len = strlen($data);
    return "\x78\x01"."\x01".pack('vv', $len, $len ^ 0xffff).$data.hex2bin(hash('adler32', $data));
}

// zlib stream with one fixed-Huffman deflate block (literals only)
function zlibFixed($data) {
    $bits = array(1, 1, 0); // BFINAL=1, BTYPE=01 (LSB first)
    $code = function ($c, $n) use (&$bits) {
        for ($i = $n - 1; $i >= 0; $i--) {
            $bits []= ($c >> $i) & 1;  // Huffman codes are packed MSB first
        }
    };
    foreach (str_split($data) as $ch) {
        $v = ord($ch);
        if ($v < 144) {
            $code(0x30 + $v, 8);
        } else {
            $code(0x190 + $v - 144, 9);
        }
    }
    $code(0, 7); // end of block (256)
    $out = '';
    foreach (array_chunk($bits, 8) as $byte) {
        $b = 0;
        foreach ($byte as $i => $bit) {
            $b |= $bit << $i;
        }
        $out .= chr($b);
    }
    return "\x78\x01".$out.hex2bin(hash('adler32', $data));
}

$iend = chunk('IEND', '');
$files = array();

// gray 8bit 3x2: tRNS (gray), gAMA 1/2.2, pHYs 72dpi (metre), filters 0,1
$idat = zlibStored("\x00\x0a\x14\x1e"."\x01\x05\x05\x05");
$files['gray.png'] = pngSignature().ihdr(3, 2, 8, 0).
    chunk('gAMA', pack('N', 45455)).
    chunk('pHYs', pack('NNC', 2835, 2835, 1)).
    chunk('tRNS', "\x00\x14").
    chunk('IDAT', $idat).$iend;

// RGB 8bit 2x2: cHRM (sRGB primaries), tRNS (RGB), pHYs unit unknown x!=y, tEXt, IDAT split in two, filters 1,2
$idat = zlibStored("\x01\xff\x00\x00\x01\x00\x00"."\x02\x00\xff\x00\x00\x00\xff");
$files['rgb.png'] = pngSignature().ihdr(2, 2, 8, 2).
    chunk('cHRM', pack('N*', 31270, 32900, 64000, 33000, 30000, 60000, 15000, 6000)).
    chunk('pHYs', pack('NNC', 3, 2, 0)).
    chunk('tRNS', "\x00\xff\x00\x00\x00\xff").
    chunk('tEXt', "Comment\0hello").
    chunk('IDAT', substr($idat, 0, 7)).
    chunk('IDAT', substr($idat, 7)).$iend;

// palette 8bit 4x2: iCCP (fixed-Huffman zlib profile), PLTE 10 entries, tRNS 3 entries, filters 0,4
$profile = pack('N', 132).'test'."\x02\x10\x00\x00".'mntrGRAYXYZ '.str_repeat("\0", 12).'acsp'.
    str_repeat("\0", 128 - 40).pack('N', 0);
$plte = '';
for ($i = 0; $i < 10; $i++) {
    $plte .= chr($i * 25).chr(255 - $i * 25).chr(0x80 | $i);
}
$files['palette.png'] = pngSignature().ihdr(4, 2, 8, 3).
    chunk('iCCP', "Test Profile\0\0".zlibFixed($profile)).
    chunk('PLTE', $plte).
    chunk('tRNS', "\x00\x80\xff").
    chunk('IDAT', zlibStored("\x00\x00\x01\x02\x03"."\x04\x09\x08\x07\x06")).$iend;

// gray+alpha 16bit 1x1 interlaced, filter 3
$files['grayalpha.png'] = pngSignature().ihdr(1, 1, 16, 4, 1).
    chunk('IDAT', zlibStored("\x03\x12\x34\xff\xff")).$iend;

// RGBA 8bit 2x3: iDOT (Apple, count 3 => 2 entries), two IDAT, filters 0,1,2
$idat = zlibStored("\x00".str_repeat("\x10\x20\x30\x40", 2)."\x01".str_repeat("\x01\x02\x03\x04", 2)."\x02".str_repeat("\x00", 8));
$files['rgba.png'] = pngSignature().ihdr(2, 3, 8, 6).
    chunk('iDOT', pack('N*', 3, 0, 1, 40, 1, 1, 100, 2, 1, 120)).
    chunk('IDAT', substr($idat, 0, 20)).
    chunk('IDAT', substr($idat, 20)).$iend;

// JNG: JHDR (16 bytes) + JDAT (dummy JPEG bytes) + IEND
$files['simple.jng'] = "\x8bJNG\r\n\x1a\n".
    chunk('JHDR', pack('NNCCCCCCCC', 8, 8, 10, 8, 8, 0, 0, 0, 0, 0)).
    chunk('JDAT', "\xff\xd8\xff\xd9").$iend;

// gray.png with a broken gAMA CRC
$gray = $files['gray.png'];
$pos = strpos($gray, 'gAMA') + 8;
$files['badcrc.png'] = substr($gray, 0, $pos).(~$gray[$pos]).substr($gray, $pos + 1);

// gray.png followed by trailing bytes after IEND
$files['trailing.png'] = $gray."TRAILING";

// gray.png cut inside the IDAT data
$files['truncated.png'] = substr($gray, 0, strpos($gray, 'IDAT') + 8);

foreach ($files as $name => $data) {
    file_put_contents($dir.'/'.$name, $data);
}

function pngSignature() {
    return "\x89PNG\r\n\x1a\n";
}
