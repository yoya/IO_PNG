<?php

/*
  IO_PNG class - 2.0.3
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
            $namedata_crc32 = crc32($chunkName.$data);
            if ($crc !== $namedata_crc32) {
                fprintf(STDERR, "Warning: chunkName:$chunkName crc:$crc namedata_crc32:$namedata_crc32\n");
            }
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
            case 'cHRM':
                $bit_chrm = new IO_Bit();
                $bit_chrm->input($data);
                $data = array(
                    'WhitePoint_x' => $bit_chrm->getSI32BE() / 100000,
                    'WhitePoint_y' => $bit_chrm->getSI32BE() / 100000,
                    'Red_x' => $bit_chrm->getSI32BE() / 100000,
                    'Red_y' => $bit_chrm->getSI32BE() / 100000,
                    'Green_x' => $bit_chrm->getSI32BE() / 100000,
                    'Green_y' => $bit_chrm->getSI32BE() / 100000,
                    'Blue_x' => $bit_chrm->getSI32BE() / 100000,
                    'Blue_y' => $bit_chrm->getSI32BE() / 100000,
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
            case 'iDOT':  // apple
                $bit_idot = new IO_Bit();
                $bit_idot->input($data);
                $count = bit_idot->getUI32BE();
                $data = array(
                    "count" => $count,
                    'reserved' => $bit_idot->getUI32BE(),
                    'height' => $bit_idot->getUI32BE(),
                    'chunkSize' => $bit_idot->getUI32BE(),
                    'entries' => [],
                );
                for ($i = 1; $i < $count ; $i++) {
                    $data['entries'][] = array(
                        'offset' => $bit_idot->getUI32BE(),
                        'height' => $bit_idot->getUI32BE(),
                        'idatOffset' => $bit_idot->getUI32BE(),
                    );
                }
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
            echo "Name:{$chunk['Name']} Size={$chunk['Size']} CRC={$chunk['CRC']}";
            if (empty($opts['detail'])) {
                if (isset($chunk['Data'])) {
                    echo " ";
                    $chunkData = $chunk['Data'];
                    if (is_array($chunkData)) {
                        echo "(".implode(",", $chunkData).")";
                    } else if (is_string($chunk['Data'])) {
                        if (ctype_print($chunkData)) {
                            echo "($chunkLen)";
                        }
                    } else {
                        echo "($chunkData)";
                    }
                }
                echo PHP_EOL;
                continue;
            }
            echo PHP_EOL;
            $data = $chunk['Data'];
            switch ($chunk['Name']) {
            case 'IHDR':
                $colorType = $data['ColorType'];
                if (isset(self::$colorTypeNameTable[$colorType])) {
                    $colorTypeName  = self::$colorTypeNameTable[$colorType];
                } else {
                    $colorTypeName  = "UnknownType";
                }
                echo "  Width:{$data['Width']} Height:{$data['Height']} BitDepth:{$data['BitDepth']}";
                echo " ColorType:{$data['ColorType']}($colorTypeName)";
                echo " Compression:{$data['Compression']} Filter:{$data['Filter']} Interlate:{$data['Interlace']}";
                echo "\n";
                break;
            case 'cHRM':
                $chrm = $data;
                printf("    WhitePoint:%.3f,%.3f\n",
                       $chrm['WhitePoint_x'], $chrm['WhitePoint_y']);
                printf("    Red:%.3f,%.3f Green:%.3f,%.3f Blue:%.3f,%.3f\n",
                       $chrm['Red_x'], $chrm['Red_y'],
                       $chrm['Green_x'], $chrm['Green_y'],
                       $chrm['Blue_x'], $chrm['Blue_y']);
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
                if ($unitSpec === 1) {  // Metre
                    if ($pixelsX === $pixelsY) {
                        printf("    (inch pixelsX,pixelsY:%d)\n",
                               $pixelsX / 39.37);
                    } else {
                        printf("    (inch pixelsX:%.3f, pixelsY:%.3f)\n",
                               $pixelsX / 39.37, $pixelsY / 39.37);
                    }
                }
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
            case 'iDOT':  // apple
                $count     = $data["count"];
                $reserved  = $data["reserved"];
                $height    = $data["height"];
                $chunkSize = $data["chunkSize"];
                $entries   = $data["entries"];
                echo "    count:$count reserved:$reserved height:$height chunkSize:$chunkSize]\n";
                if ($count !== count($entries) + 1) {
                    fprintf(STDERR, "Warning: count:$count !== count(entries):%d + 1\n", count($entries));
                }
                foreach ($entries as $i => $entry) {
                    $offset = $entry['offset'];
                    $height = $entry['height'];
                    $idatOffset = $entry['idatOffset'];
                    echo "      [".($i+1)."/$count]: offset:$offset height:$height idatOffset:$idatOffset\n";
                };
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
            case 'cHRM':
                $bit_chrm = new IO_Bit();
                $bit_chrm->putSI32BE($data['WhitePoint_x'] * 100000);
                $bit_chrm->putSI32BE($data['WhitePoint_y'] * 100000);
                $bit_chrm->putSI32BE($data['Red_x'] * 100000);
                $bit_chrm->putSI32BE($data['Red_y'] * 100000);
                $bit_chrm->putSI32BE($data['Green_x'] * 100000);
                $bit_chrm->putSI32BE($data['Green_y'] * 100000);
                $bit_chrm->putSI32BE($data['Blue_x'] * 100000);
                $bit_chrm->putSI32BE($data['Blue_y'] * 100000);
                $data = $bit_chrm->output();
                break;
            case 'gAMA':
                $bit_gama = new IO_Bit();
                $gamma = $data * 100000;
                $bit_gama->putUI32BE($gamma);
                $data = $bit_gama->output();
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
            case 'iDOT':  // apple
                $bit_idot = new IO_Bit();
                $count   = $data["count"];
                $entries = $data["entries"];
                if ($count !== count($entries) + 1) {
                    fprintf(STDERR, "Warning: count:$count !== count(entries):%d + 1\n", count($entries));
                    $count = count($entries) + 1;
                }
                $bit_idot->putUI32BE($count);
                $bit_idot->putUI32BE($data["reserved"]);
                $bit_idot->putUI32BE($data["height"]);
                $bit_idot->putUI32BE($data["chunkSize"]);
                $entries = $data["entries"];
                if ($count !== count($entries)) {
                    fprintf(STDERR, "Warning: count:$count count(entries):%d\n", count($entries));
                    $count = count($entries);
                }
                foreach ($entry as $entries) {
                    $bit_idot->putUI32BE($entry["offset"]);
                    $bit_idot->putUI32BE($entry["height"]);
                    $bit_idot->putUI32BE($entry["idatOffset"]);
                }
                $data = $bit_idot->output();
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
    function getIHDRdata() {
        foreach ($this->_chunkList as $chunk) {
            if ($chunk['Name'] === 'IHDR') {
                return $chunk['Data'];
            }
        }
        return false;
    }
    function getIDATdata() {
        $idat_data = "";
        foreach ($this->_chunkList as $chunk) {
            if ($chunk['Name'] === 'IDAT') {
                $idat_data .= $chunk['Data'];
            }
        }
        return $idat_data;
    }
    function deleteChunks($name) {
        foreach ($this->_chunkList as $idx => $chunk) {
            if ($chunk['Name'] === $name) {
                unset($this->_chunkList[$idx]);
            }
        }
    }
    static function getNCompByColorType($colortype) {
        switch ($colortype) {
        case 0:  // GRAY
            $ncomp = 1;
            break;
        case 2:  // RGB (RGB24)
            $ncomp = 3;
            break;
        case 3:  // PALETTE
            $ncomp = 1;
            break;
        case 4:  // GRAY_ALPHA
            $ncomp = 2;
            break;
        case 6:  // RGB_ALPHA (RGB32)
            $ncomp = 4;
            break;
        default:
            throw new Exception("unknown colortype:$colortype");
        }
        return $ncomp;
    }
    function dumpFilter($opts = Array()) {
        $summalize = $opts["summalize"];
        $ihdr = $this->getIHDRdata();
        if ($ihdr === false) {
            fprintf(stderr, "Error: not found IDHR chunk\n");
            return ;
        }
        $width     = $ihdr['Width'];
        $height    = $ihdr['Height'];
        $bitdepth  = $ihdr['BitDepth'];
        $colortype = $ihdr['ColorType'];
        $idat_data = $this->getIDATdata();
        if ($ihdr === false) {
            fprintf(stderr, "Error: not found IDAT chunk or zero length\n");
            return ;
        }
        $idat_inflated = gzuncompress($idat_data);
        //
        $ncomp = self::getNCompByColorType($colortype);
        $stride = 1 + (int) ceil($width * $ncomp * $bitdepth / 8);
        var_dump($stride);
        $offset = 0;
        $filterTable = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0];
        for ($y = 0 ; $y < $height ; $y++) {
            $filter = ord($idat_inflated[$offset]);
            $offset += $stride;
            if ($summalize) {
                $filterTable[$filter]++;
            } else {
                echo "$filter ";
            }
        }
        if ($summalize) {
            ksort($filterTable);
            foreach ($filterTable as $i => $f) {
                echo "$i:$f".PHP_EOL;
            }
        } else {
            echo PHP_EOL;
        }
    }
    function changeFilter($filter) {
        $ihdr = $this->getIHDRdata();
        if ($ihdr === false) {
            fprintf(stderr, "Error: not found IDHR chunk\n");
            return ;
        }
        $width     = $ihdr['Width'];
        $height    = $ihdr['Height'];
        $bitdepth  = $ihdr['BitDepth'];
        $colortype = $ihdr['ColorType'];
        $idat_data = $this->getIDATdata();
        if ($ihdr === false) {
            fprintf(stderr, "Error: not found IDAT chunk or zero length\n");
            return ;
        }
        $idat_inflated = gzuncompress($idat_data);
        //
        $ncomp = self::getNCompByColorType($colortype);
        $stride = 1 + (int) ceil($width * $ncomp * $bitdepth / 8);
        $offset = 0;
        for ($y = 0 ; $y < $height ; $y++) {
            $idat_inflated[$offset] = chr($filter);
            $offset += $stride;
        }
        $data = gzcompress($idat_inflated);
        $dataSize = strlen($data);
        $crc =  crc32("IDAT" . $data);
        $IDATchunk = array('Size' => $dataSize,
                           'Name' => "IDAT",
                           'Data' => $data,
                           'CRC' => $crc);
        $this->deleteChunks("IDAT");
        array_splice($this->_chunkList, -1, 0, [$IDATchunk]);
    }
}
