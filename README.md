IO_PNG
=======

PNG parser &amp; dumper

# Usage

```
% composer require yoya/io_png
% php vendor/yoya/io_png/sample/pngdump.php
Usage: php pngdump.php -f <png_file> [-h]
ex) php pngdump.php -f test.png -h
% php vendor/yoya/io_png/sample/pngdump.php -f input.png
Name:IHDR Size=13 CRC=1946785227
  Width:256 Height256 BitDepth:1 ColorType:0(GRAY) Compression:0 Filter:0 Interlate:0
Name:gAMA Size=4 CRC=201089285
    Gamma:0.45455
Name:cHRM Size=32 CRC=2629456188
             0  1  2  3  4  5  6  7   8  9  a  b  c  d  e  f  0123456789abcdef
0x00000000  00 00 7a 26 00 00 80 84  00 00 fa 00 00 00 80 e8    z&
```
