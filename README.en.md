IO_PNG
======

Japanese version: [README.md](README.md)

This is a library for interpreting, dumping and editing the PNG binary chunk by chunk. It requires IO_Bit.
It can list chunks and show their contents, add, delete and replace chunks, fix CRCs,
and extract PNG images embedded in another file.
Not limited in use, free to copy and modify by MIT license.
Please contact me if it doesn't work in your environment or you want new features.

# License

MIT License

# Install

```
% composer require yoya/io_png
```

# Usage

- pngdump.php (dump of the chunk structure and contents)

```
% php vendor/yoya/io_png/sample/pngdump.php
Usage: php pngdump.php -f <png_file> [-h]
ex) php pngdump.php -f test.png -h 
% php vendor/yoya/io_png/sample/pngdump.php -f input.png
Name:IHDR Size=13 CRC=647170345
  Width:4 Height:4 BitDepth:8 ColorType:2(RGB) Compression:0 Filter:0 Interlate:0
Name:gAMA Size=4 CRC=201089285
    Gamma:0.45455
Name:cHRM Size=32 CRC=2629456188
    WhitePoint:0.313,0.329
    Red:0.640,0.330 Green:0.300,0.600 Blue:0.150,0.060
Name:pHYs Size=9 CRC=10132504
    pixelsX:2835, pixelsY:2835, unitSpecName:Metre
    (inch pixelsX,pixelsY:72)
Name:tEXt Size=13 CRC=3875515940
             0  1  2  3  4  5  6  7   8  9  a  b  c  d  e  f  0123456789abcdef
0x00000000  43 6f 6d 6d 65 6e 74 00  68 65 6c 6c 6f           Comment hello   
...
Name:IDAT Size=42 CRC=662471952
(omit)
```

`-D` prints one line per chunk, and `-h` adds a hex dump of each chunk's bytes.
In the detailed output, at IEND it hex-dumps the zlib-inflated data of all IDAT chunks (each row starts with its filter type).

```
% php vendor/yoya/io_png/sample/pngdump.php -f input.png -D
Name:IHDR Size=13 CRC=647170345 (4,4,8,2,0,0,0)
Name:gAMA Size=4 CRC=201089285 (0.45455)
Name:cHRM Size=32 CRC=2629456188 (0.3127,0.329,0.64,0.33,0.3,0.6,0.15,0.06)
Name:pHYs Size=9 CRC=10132504 (2835,2835,1)
Name:tEXt Size=13 CRC=3875515940 
Name:IDAT Size=42 CRC=662471952 
Name:IEND Size=0 CRC=2923585666 
```

- Delete, replace and extract chunks (edited PNGs are written to standard output)

```
% php vendor/yoya/io_png/sample/pngdelchunk.php -f input.png -t tEXt > output.png
% php vendor/yoya/io_png/sample/pngreplacechunk.php -f input.png -t gAMA -d 0.5 > output.png
% php vendor/yoya/io_png/sample/pngreplacechunk.php -f input.png -t iCCP -d sRGB.icc > output.png
% php vendor/yoya/io_png/sample/pnggetchunk.php -f output.png -t iCCP -u > profile.icc
```

- Check and fix CRCs

```
% php vendor/yoya/io_png/sample/pngcrc.php -f broken.png
Warning: chunkName:gAMA crc:16539909 namedata_crc32:201089285
broken.png[0] IHDR crc:647170345
broken.png[1] gAMA crc:16539909 => 201089285
(omit)
% php vendor/yoya/io_png/sample/pngcrc.php -f broken.png -c > fixed.png
```

- From PHP

```php
require_once 'IO/PNG.php';

$png = new IO_PNG();
$png->parse(file_get_contents('input.png'));
$ihdr = $png->getIHDRdata();
echo "{$ihdr['Width']}x{$ihdr['Height']}\n";   // 4x4
$png->deleteChunks('tEXt');                     // delete tEXt chunks
foreach ($png->_chunkList as $idx => $chunk) {
    if ($chunk['Name'] === 'gAMA') {
        $png->_chunkList[$idx]['Data'] = 0.5;   // gAMA is a number converted from 1/100000 units
    }
}
file_put_contents('output.png', $png->build());
```

# Design overview

| Class | Role |
|---|---|
| `IO_PNG` | The whole PNG. `parse` / `build` / `dump` of the chunk list, and showing / changing the IDAT filter types (`dumpFilter` / `changeFilter`) |
| `IO_Bit` (separate package) | Byte-level reading and writing |

- `parse()` reads the chunks after the signature up to IEND, and lists them in `_chunkList` with `Name` `Size` `Data` `CRC` (and the original position `_offset` `_length`).
  It also accepts the JNG signature. A chunk with a wrong CRC is reported as a warning on STDERR and parsing continues.
- `Data` is an associative array for IHDR, cHRM, pHYs and iDOT (Apple), and a number for gAMA (e.g. 0.45455). gAMA and cHRM values are converted from 1/100000 units.
  Other chunks (PLTE, tRNS, iCCP, IDAT, etc.) are kept as bytes.
- `build()` rebuilds the interpreted chunks above from their values and writes the others as bytes. Lengths and CRCs are all recalculated.
  Without edits, `parse()` → `build()` gives the same bytes as the original.
- `getIHDRdata()` / `getIDATdata()` (all IDAT concatenated) / `deleteChunks($name)` cover common operations.

# Features

| Feature | Sample (`sample/`) |
|---|---|
| Structure dump | `pngdump.php` (`-D` short form, `-h` hex dump) |
| List, split and join chunks | `pngchunk.php` (`-s` writes each chunk to `NN_name.pnc`), `pngcat.php` (joins `.pnc` files into a PNG) |
| Extract a chunk | `pnggetchunk.php` (`-t` chunk type, `-u` zlib-inflate: the ICC profile for iCCP, the pixel rows for IDAT) |
| Add, delete and replace chunks | `pngaddchunk.php`, `pngdelchunk.php`, `pngreplacechunk.php` (add / replace support only iCCP and gAMA. Without `-t` they list the chunks) |
| Check and fix CRCs | `pngcrc.php` (`-c` rebuilds) |
| Rebuild | `pngrebuild.php` |
| Show and change filter types | `pngfilter.php` (`-s` summary, `-n <filter>` change) |
| Extract embedded PNGs | `pngextract.php` (finds PNG signatures in a file and writes `<prefix>NNNN.png`) |

# Known limitations

- `build()` of a JNG writes the PNG signature.
- `pngfilter.php -n` (`changeFilter()`) only rewrites the filter type byte of each row and does not re-filter the pixels, so the image changes. It also ignores interlace (so does `dumpFilter()`).
- `pngaddchunk.php` adds a chunk even if one of the same type already exists.
- The tRNS dump groups samples as 1 byte each also for gray and RGB (they are 2 bytes).

# Test

```
% php tests/run.php
```

IO_Bit is loaded from vendor/ (composer install) or include_path.

# Reference

- https://www.w3.org/TR/png/
