<?php

require_once 'IO/Bit.php';

class IO_PNG {
    var $_chunkList = null;
    var $_pngdata = null;
    function parse($pngdata) {
        $bit = new IO_Bit();
        $bit->input($pngdata);
        $this->_pngdata = $pngdata;
        $signature = $bit->getData(8);
        if ($signature != "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
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
        foreach ($this->_chunkList as $chunk) {
            echo "Name:{$chunk['Name']} Size={$chunk['Size']} CRC={$chunk['CRC']}\n";
            $data = $chunk['Data'];
            switch ($chunk['Name']) {
            case 'IHDR':
                $colorTypeName = self::$colorTypeNameTable[$data['ColorType']];
                echo "  Width:{$data['Width']} Height{$data['Height']} BitDepth:{$data['BitDepth']}";
                echo " ColorType:{$data['ColorType']}($colorTypeName)";
                echo " Compression:{$data['Compression']} Filter:{$data['Filter']} Interlate:{$data['Interlace']}";
                echo "\n";
                break;
            case 'PLTE':
            case 'tRNS':
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