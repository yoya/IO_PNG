<?php

/*
  IO_PNG class
  (c) 2011/12/30 yoya@awm.jp
 */

if (is_readable('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'IO/Bit.php';
}

class IO_PNG {
    var $_chunkList = null;
    var $_pngdata = null;
    const SIGNATURE = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
    const SIGNATURE_JNG = "\x8B\x4A\x4E\x47\x0D\x0A\x1A\x0A";
    function parse($pngdata) {
        $bit = new IO_Bit();
        $bit->input($pngdata);
        $this->_pngdata = $pngdata;
        if ($bit->hasNextData(8) === false) {
            throw new Exception ("Not PNG FILE (too short)");
        }
        $signature = $bit->getData(8);
        if (($signature != self::SIGNATURE) &&
            ($signature != self::SIGNATURE_JNG)) {
            throw new Exception ("Not PNG,JNG FILE ($signature)");
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
            case 'gAMA':
                $bit_gama = new IO_Bit();
                $bit_gama->input($data);
                $gamma = $bit_gama->getUI32BE();
                $data = $gamma /100000;
                break;
            case 'pHYs':
                $bit_phys = new IO_Bit();
                $bit_phys->input($data);
                $data = array(
                    'PixelsX' => $bit_phys->getUI32BE(),
                    'PixelsY' => $bit_phys->getUI32BE(),
                    'UnitSpec' => $bit_phys->getUI8(),
                );
                break;
            case 'PLTE':
            case 'tRNS':
            case 'IEND':
            case 'IDAT':
            default:
                // $data = $data;
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
                $gamma = $data;
                printf("    Gamma:%.5f\n", $gamma);
                break;
            case 'pHYs':
                $pixelsX = $data['PixelsX'];
                $pixelsY = $data['PixelsY'];
                $unitSpec = $data['UnitSpec'];
                $unitSpecName = ["Unknown", "Metre"][$unitSpec];
                printf("    pixelsX:%d, pixelsY:%d, unitSpecName:%s\n",
                       $pixelsX, $pixelsY, $unitSpecName);
                break;
            case 'PLTE':
                $bit_idat = new IO_Bit();
                $bit_idat->input($data);
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
                $bit_idat->input($data);
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
            case 'iCCP':
                $bit_idat = new IO_Bit();
                $bit_idat->input($data);
                printf("    %s\n", $bit_idat->getData(5));
                $compressedICCP =$bit_idat->getDataUntil(false);
                $iccpData = gzuncompress($compressedICCP);
                printf("    %s\n", substr($iccpData, 0, 0x20));
                break;
            case 'IDAT':
            default:
                $bit_data = new IO_Bit();
                $bit_data->input($data);
                $bit_data->hexdump(0, 0x10);
                echo "...\n";
                if ($chunk['Name'] === 'IDAT') {
                    $idat_data .= $data;
                }
                break;
            case 'IEND':
                // print IDAT inflated data
                $idat_inflated = gzuncompress($idat_data);
                $bit_iend = new IO_Bit();
                $bit_iend->input($idat_inflated);
                $bit_iend->hexdump(0, strlen($idat_inflated));
                break;
            }
            if (empty($opts['hexdump']) === false) {
                $bitio->hexdump($chunk['_offset'], $chunk['_length']);
            }
        }
    }
    function build($opts = array()) {
        $bit = new IO_Bit();
        $bit->putData(self::SIGNATURE);
        foreach ($this->_chunkList as $chunk) {
            $chunkName = $chunk['Name'];
            $data = $chunk['Data'];
            switch ($chunkName) {
            case 'IHDR':
                $bit_ihdr = new IO_Bit();
                $bit_ihdr->putUI32BE($data['Width']);
                $bit_ihdr->putUI32BE($data['Height']);
                $bit_ihdr->putUI8($data['BitDepth']);
                $bit_ihdr->putUI8($data['ColorType']);
                $bit_ihdr->putUI8($data['Compression']);
                $bit_ihdr->putUI8($data['Filter']);
                $bit_ihdr->putUI8($data['Interlace']);
                $data = $bit_ihdr->output();
                break;
            case 'gAMA':
                $bit_gama = new IO_Bit();
                $gamma = $data * 100000;
                $bit_gama->putUI32BE($gamma);
                $data = $bit_ihdr->output();
                break;
            case 'pHYs':
                $bit_phys = new IO_Bit();
                $bit_phys->putUI32BE($data['PixelsX']);
                $bit_phys->putUI32BE($data['PixelsY']);
                $bit_phys->putUI8($data['UnitSpec']);
                $data = $bit_phys->output();
                break;
            case 'PLTE':
                break;
            case 'tRNS':
                break;
            case 'IDAT':
                break;
            default:
                break;
            }
            // build chunk
            $dataSize = strlen($data);
            $crc = crc32($chunkName . $data);
            //
            $bit->putUI32BE($dataSize);
            $bit->putData($chunkName);
            $bit->putData($data);
            $bit->putUI32BE($crc);
        }
        return $bit->output();
    }
}