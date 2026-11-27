IO_PNG
======

English version: [README.en.md](README.en.md)

PNG バイナリをチャンク単位で解釈・ダンプ・編集する為のライブラリです。IO_Bit が必要です。
チャンクの一覧や中身の表示、チャンクの追加・削除・差し替え、CRC の修正、
別のファイルに埋め込まれた PNG の取り出しなどができます。
利用に制限はかけません。コピーも改変もご自由にどうぞ。MIT ライセンスにしました。
自分の環境だと動かないとか、機能が欲しいとかいった要望があれば連絡下さい。

# License

MIT License

# Install

```
% composer require yoya/io_png
```

# Usage

- pngdump.php (チャンクの構造と中身のダンプ)

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

`-D` で 1 チャンク 1 行の簡易表示、`-h` で各チャンクのバイト列の 16 進ダンプを加えます。
詳細表示では、IEND の位置で全 IDAT を zlib 展開したデータ (各行の先頭にフィルタ種別が付いたもの) を 16 進ダンプします。

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

- チャンクの削除・差し替え・取り出し (編集結果は標準出力に PNG で出る)

```
% php vendor/yoya/io_png/sample/pngdelchunk.php -f input.png -t tEXt > output.png
% php vendor/yoya/io_png/sample/pngreplacechunk.php -f input.png -t gAMA -d 0.5 > output.png
% php vendor/yoya/io_png/sample/pngreplacechunk.php -f input.png -t iCCP -d sRGB.icc > output.png
% php vendor/yoya/io_png/sample/pnggetchunk.php -f output.png -t iCCP -u > profile.icc
```

- CRC の確認と修正

```
% php vendor/yoya/io_png/sample/pngcrc.php -f broken.png
Warning: chunkName:gAMA crc:16539909 namedata_crc32:201089285
broken.png[0] IHDR crc:647170345
broken.png[1] gAMA crc:16539909 => 201089285
(omit)
% php vendor/yoya/io_png/sample/pngcrc.php -f broken.png -c > fixed.png
```

- PHP から使う

```php
require_once 'IO/PNG.php';

$png = new IO_PNG();
$png->parse(file_get_contents('input.png'));
$ihdr = $png->getIHDRdata();
echo "{$ihdr['Width']}x{$ihdr['Height']}\n";   // 4x4
$png->deleteChunks('tEXt');                     // tEXt チャンクを削除
foreach ($png->_chunkList as $idx => $chunk) {
    if ($chunk['Name'] === 'gAMA') {
        $png->_chunkList[$idx]['Data'] = 0.5;   // gAMA は 1/100000 単位を換算した数値
    }
}
file_put_contents('output.png', $png->build());
```

# 設計概要

| クラス | 役割 |
|---|---|
| `IO_PNG` | PNG 全体。チャンク列の `parse` / `build` / `dump`、IDAT のフィルタ種別の表示・変更 (`dumpFilter` / `changeFilter`) |
| `IO_Bit` (別パッケージ) | バイト単位の読み書き |

- `parse()` はシグネチャの後のチャンクを IEND まで読み、`_chunkList` に `Name` `Size` `Data` `CRC` (と元の位置 `_offset` `_length`) を並べる。
  JNG のシグネチャも受け付け、読んだシグネチャは `_signature` に持つ (`build()` はそれを書く)。CRC が合わないチャンクは STDERR に警告を出して読み進める。
- `Data` は、IHDR・cHRM・pHYs・iDOT (Apple) が連想配列、gAMA が数値 (例 0.45455)。gAMA と cHRM の値は 1/100000 単位を換算したもの。
  それ以外のチャンク (PLTE, tRNS, iCCP, IDAT など) はバイト列のまま持つ。
- `build()` は上の解釈したチャンクを値から作り直し、それ以外はバイト列のまま書く。長さと CRC はすべて計算し直す。
  編集していなければ、`parse()` → `build()` で元と同じバイト列になる。
- `getIHDRdata()` / `getIDATdata()` (全 IDAT の連結) / `deleteChunks($name)` で、よく使う操作をする。

# 機能

| 機能 | サンプル (`sample/`) |
|---|---|
| 構造のダンプ | `pngdump.php` (`-D` 簡易表示, `-h` 16 進ダンプ) |
| チャンクの一覧・分割・結合 | `pngchunk.php` (`-s` でチャンクごとに `NN_name.pnc` に書き出す), `pngcat.php` (`.pnc` を並べて PNG にする) |
| チャンクの取り出し | `pnggetchunk.php` (`-t` で種類を指定、`-u` で zlib 展開。iCCP なら ICC プロファイル、IDAT なら展開した画素列) |
| チャンクの追加・削除・差し替え | `pngaddchunk.php`, `pngdelchunk.php`, `pngreplacechunk.php` (追加・差し替えは iCCP と gAMA のみ。`-t` を省くと一覧を表示) |
| CRC の確認・修正 | `pngcrc.php` (`-c` で作り直す) |
| 作り直し | `pngrebuild.php` |
| フィルタ種別の表示・変更 | `pngfilter.php` (`-s` で集計, `-n <filter>` で変更) |
| 埋め込まれた PNG の取り出し | `pngextract.php` (ファイル中の PNG シグネチャを探して `<prefix>NNNN.png` に書き出す) |

# 既知の制限

- `pngfilter.php -n` (`changeFilter()`) は各行のフィルタ種別のバイトを書き換えるだけで画素を再フィルタしないので、画像が変わる。インターレースも考慮しない (`dumpFilter()` も同じ)。
- `pngaddchunk.php` は同じ種類のチャンクが既にあっても追加する。

# Test

```
% php tests/run.php
```

IO_Bit は vendor/ (composer install) か include_path から読み込みます。

# Reference

- https://www.w3.org/TR/png/

## IO_Bit の利用箇所

チャンクの長さは `getUI32BE()` で読みます。チャンクを書き出す際は `putUI32BE()` で長さと CRC を書きます。

[IO_Bit Wiki](https://github.com/yoya/IO_Bit/wiki)
