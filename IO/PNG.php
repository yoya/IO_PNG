<?php

/*
  IO_PNG class - version 1.0.0
  (c) 2011/12/30 yoya@awm.jp
 */

require_once 'IO/Bit.php';

class IO_PNG {
    var $_chunkList = null;
    var $_pngdata = null;
    const SIGNATURE = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
    function parse($pngdata) {
        $bit = new IO_Bit();
        $bit->input($pngdata);
        $this->_pngdata = $pngdata;
        if ($bit->hasNextData(8) === false) {
            throw new Exception ("Not PNG FILE (too short)");
        }
        $signature = $bit->getData(8);
        if ($signature != self::SIGNATURE) {
            throw new Exception ("Not PNG FILE ($sigunature)");
        }
        while ($bit->hasNextData(8)) {
            list($offset, $dummy) = $bit->getOffset();
            $dataSize = $bit->getUI32BE();
            $chunkName = $bit->getData(4);
            $data = $bit->getData($dataSize);
            $crc = $bit->getUI32BE();
//            $data_crc32 = sprintf("%u", crc32($chunkName.$data));
//            echo "data_crc32=$data_crc32\n";
            switch ($chunkName) {
            case 'IHDR':
                $bit_chunk = new IO_Bit();
                $bit_chunk->input($data);
                $width = $bit_chunk->getUI32BE();
                $height = $bit_chunk->getUI32BE();
                $bitDepth = $bit_chunk->getUI8();
                $colorType = $bit_chunk->getUI8();
                $compression = $bit_chunk->getUI8();
                $filter = $bit_chunk->getUI8();
                $interlace = $bit_chunk->getUI8();
                $data = array(
                    'Width' => $width,
                    'Height' => $height,
                    'BitDepth' => $bitDepth,
                    'ColorType' => $colorType,
                    'Compression' => $compression,
                    'Filter' => $filter,
                    'Interlace' => $interlace,
                    );

                break;
//            case 'IDAT':
//                $data = gzuncompress($data);
                break;
            }
            list($offset2, $dummy) = $bit->getOffset();
            $this->_chunkList []= array('Size' => $dataSize,
                                        'Name' => $chunkName,
                                        'Data' => $data,
                                        'CRC' => $crc,
                                        '_offset' => $offset,
                                        '_length' => $offset2 - $offset);
            if ($chunkName === 'IEND') {
                break;
            }
        }
    }
    static $colorTypeNameTable = array(
        0 => 'GRAY',
        2 => 'RGB',
        3 => 'PALETTE',
        4 => 'GRAY_ALPHA',
        6 => 'RGB_ALPHA',
        );
    function dump($opts = Array()) {
        if (empty($opts['hexdump']) === false) {
            $bitio = new IO_Bit();
            $bitio->input($this->_pngdata);
        }
        $idat_data = '';
        $colorType = null;
        foreach ($this->_chunkList as $chunk) {
            echo "Name:{$chunk['Name']} Size={$chunk['Size']} CRC={$chunk['CRC']}\n";
            $data = $chunk['Data'];
            switch ($chunk['Name']) {
            case 'IHDR':
                $colorType = $data['ColorType'];
                $colorTypeName = self::$colorTypeNameTable[$colorType];
                echo "  Width:{$data['Width']} Height{$data['Height']} BitDepth:{$data['BitDepth']}";
                echo " ColorType:{$data['ColorType']}($colorTypeName)";
                echo " Compression:{$data['Compression']} Filter:{$data['Filter']} Interlate:{$data['Interlace']}";
                echo "\n";
                break;
            case 'gAMA':
                $bit_idat = new IO_Bit();
                $bit_idat->input($chunk['Data']);
                $gamma = $bit_idat->getUI32BE();
                printf("    Gamma:%.5f\n", $gamma/100000);
                break;
            case 'PLTE':
                $bit_idat = new IO_Bit();
                $bit_idat->input($chunk['Data']);
                $i = 0;
                $unit = 8;
                while ($bit_idat->hasNextData(3)) {
                    if (($i % $unit) === 0) {
                        printf("    0x%02x:", $i);
                    }
                    $r = $bit_idat->getUI8();
                    $g = $bit_idat->getUI8();
                    $b = $bit_idat->getUI8();
                    printf(" %02x%02x%02x", $i, $r, $g, $b);
                    $i++;
                    if (($i > 0) && (($i % $unit) === 0)) {
                        echo "\n";
                    }
                }
                if (($i % $unit) !== 0) {
                    echo "\n";
                }
                break;
            case 'tRNS':
                switch ($colorType) {
                case 0: // Gray
                case 3: // PALETTE
                    $nComp = 1;
                    $unit = 24;
                    break;
                case 2: // RGB
                    $nComp = 3;
                    $unit = 8;
                    break;
                }
                $bit_idat = new IO_Bit();
                $bit_idat->input($chunk['Data']);
                $i = 0;
                while ($bit_idat->hasNextData($nComp)) {
                    if (($i % $unit) === 0) {
                        printf("    0x%02x:", $i);
                    }
                    printf(" ");
                    for ($nc = 0; $nc < $nComp ; $nc++) {
                        printf("%02x", $bit_idat->getUI8());
                    }
                    $i++;
                    if (($i > 0) && (($i % $unit) === 0)) {
                        echo "\n";
                    }
                }
                if (($i % $unit) !== 0) {
                    echo "\n";
                }
                break;
            case 'IDAT':
                $bit_idat = new IO_Bit();
                $bit_idat->input($chunk['Data']);
//                $bit_idat->hexdump(0, strlen($chunk['Data']));
                $bit_idat->hexdump(0, 0x10);
                echo "...\n";
                if ($chunk['Name'] === 'IDAT') {
                    $idat_data .= $chunk['Data'];
                }
                break;
            case 'IEND':
                $idat_inflated = gzuncompress($idat_data);
                $bit_idat = new IO_Bit();
                $bit_idat->input($idat_inflated);
                $bit_idat->hexdump(0, strlen($idat_inflated));
                break;
            }
            if (empty($opts['hexdump']) === false) {
                $bitio->hexdump($chunk['_offset'], $chunk['_length']);
            }
        }
    }

}