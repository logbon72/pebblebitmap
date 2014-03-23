<?php

/**
 * 
 * This script is provided as-is without any guarantee.
 * 
 */

/**
 * Description of PebbleBitmap
 * ===========================
 * PebbleBitmap is a PHP port of the core functionality of Pebble toolkit's 
 * @link https://github.com/pebble/pebblekit/blob/master/Pebble/sdk/tools/bitmapgen.py bitmapgen.py
 * It can be used to convert standard images supported by PHP  to Pebble Binary Image (.pbi) file
 * The converted image can then be used as raw bytes with 
 * @link https://developer.getpebble.com/2/api-reference/group___graphics_types.html#ga0c01fd1816c6c0fde05310141f293cc5 description
 * gbitmap_create_with_data	(const uint8_t * data)	of the pebble API
 * 
 *
 * @author Joseph Taiwo Orilogbon <joseph at orilogbon.me>
 */
class PebbleBitmap {

    protected $name;
    protected $x, $y, $w, $h;
    protected $path;
    protected $version = 1;
    protected $is32Bit = false;
    public static $WHITE_COLOR_MAP = array(
        'white' => 1,
        'black' => 0,
        'transparent' => 1, //this is a deviation from the normal pebble bitmap gen
    );
    public static $BLACK_COLOR_MAP = array(
        'white' => 0,
        'black' => 1,
        'transparent' => 0,
    );

    const WHITE_MAP = 1;
    const BLACK_MAP = 2;

    protected $totalPixels = 0;
    protected $colorMap;
    protected $distinct = array();
    protected $image;

    /**
     * 
     * Instantiate the PebbleBitmap Class
     * 
     * @param string $imagePath, full or relative path to the original image, 
     * an exception is thrown if not found.
     * @param string $map The color mapping to use, the default is PebbleBitmap::WHITE_MAP, 
     * but can also be changed to PebbleBitmap::BLACK_MAP
     * @throws Exception if file was not found or image could not be loaded.
     * 
     */
    public function __construct($imagePath, $map = self::WHITE_MAP) {
        if (!file_exists($imagePath)) {
            throw new Exception("Path was not found.");
        }

        //var_dump(realpath($imagePath));exit;
        $this->name = basename(realpath($imagePath));
        $this->x = 0;
        $this->y = 0;
        $imageInfo = getimagesize($imagePath);
        $this->w = $imageInfo[0];
        $this->h = $imageInfo[1];
        $this->path = $imagePath;
        $this->colorMap = $map === self::BLACK_MAP ? self::$BLACK_COLOR_MAP : self::$WHITE_COLOR_MAP;
        $this->image = imagecreatefromstring(file_get_contents($imagePath));
        $this->is32Bit = imageistruecolor($this->image);
        imagepalettetotruecolor($this->image);
        imagealphablending($this->image, false);
        imagesavealpha($this->image, true);
    }

    /**
     * 
     * @param null|string $outPath the output path of the pbi file, leave this parameter
     * as null if you wish to return raw bytes.
     * 
     * @return int|string the length of the output file if specified or the raw contents other wise
     * 
     */
    public function convertToPbi($outPath = null) {
        $data = $this->pbiHeader() . $this->imageBits();
        return $outPath ? file_put_contents($outPath, $data) : $data;
    }

//    def row_size_bytes(self):
//        """
//        Return the length of the bitmap's row in bytes.
//
//        Row lengths are rounded up to the nearest word, padding up to
//        3 empty bytes per row.
//        """
//
//        row_size_padded_words = (self.w + 31) / 32
//        return row_size_padded_words * 4

    /**
     * Get row size bytes
     * @return int
     */
    private function rowSizeBytes() {
        return floor((($this->w + 31) / 32)) * 4;
    }

//    
//        def info_flags(self):
//        """Returns the type and version of bitmap."""
//
//        return self.version << 12
    /**
     * 
     * @return int image version
     */
    private function infoFlags() {
        return $this->version << 12;
    }

//    def pbi_header(self):
//        return struct.pack('<HHhhhh',
//                           self.row_size_bytes(),
//                           self.info_flags(),
//                           self.x,
//                           self.y,
//                           self.w,
//                           self.h)    

    /**
     * 
     * @return the headers for the PBI
     */
    private function pbiHeader() {
        return pack('vvssss', $this->rowSizeBytes(), $this->infoFlags(), $this->x, $this->y, $this->w, $this->h);
    }

//        def get_monochrome_value_for_pixel(pixel):
//            if pixel[3] < 127:
//                return self.color_map['transparent']
//            if ((pixel[0] + pixel[1] + pixel[2]) / 3) < 127:
//                return self.color_map['black']
//            return self.color_map['white']

    /**
     * Returns monochrome color mapping for the application
     * @param int $pixel color bit for that pixel
     * @return type
     */
    public function getMonochromeValueForPixel($pixel) {

        $color = $this->int2rgba($pixel);
        if ($this->is32Bit && $color['a'] < 127) {//only 32 bit images have alpha.
            return $this->colorMap['transparent'];
        }

        return (($color['r'] + $color['b'] + $color['g']) / 3) < 127 ?
                $this->colorMap['black'] : $this->colorMap['white'];
    }

    /**
     * Converts integer to RGBA values as specified on 
     * http://us1.php.net/manual/en/function.imagecolorat.php#85849
     * @param type $int
     * @return associative array of RGBA values with keys r => Red, g => Green, b => Blue
     * a => alpha 
     */
    public function int2rgba($int) {
        $a = ($int >> 24) & 0xFF;
        $r = ($int >> 16) & 0xFF;
        $g = ($int >> 8) & 0xFF;
        $b = $int & 0xFF;
        return array('r' => $r, 'g' => $g, 'b' => $b, 'a' => $a);
    }

    /**
     * Convert RGBA to integer
     * @param int $r
     * @param int $g
     * @param int $b
     * @param int $a
     * @return int representing the input colors
     * 
     */
    public function rgba2int($r, $g, $b, $a = 1) {
        return ($a << 24) + ($b << 16) + ($g << 8) + $r;
    }

    /**
     * Pack the bits in a row.
     * @param int $row
     * @param int $xFrom
     * @param int $xTo
     * @return string packed bits
     * 
     */
    private function getPixelsToBitBlt($row, $xFrom, $xTo) {
        $word = 0;
        for ($column = $xFrom; $column < ($xTo); $column++) {
            $this->totalPixels++;
            $colorIdx = imagecolorat($this->image, $column, $row);
            if (!in_array($colorIdx, $this->distinct)) {
                $this->distinct[] = $colorIdx;
            }
            $shiftBy = $column - $xFrom;
            $word |= ($this->getMonochromeValueForPixel($colorIdx) << $shiftBy);
        }

        return pack('I', $word);
    }

    /**
     * Get raw image bits
     * 
     * @return string of bytes
     */
    private function imageBits() {
        $output = [];
        $sizeOfWords = $this->rowSizeBytes() / 4;
        for ($row = $this->y; $row < ($this->y + $this->h); $row++) {
            //$yOffset $row * $this->
            //$xMax = ($)
            for ($columnWord = 0; $columnWord < $sizeOfWords; $columnWord++) {
                //pixels
                $xFrom = $this->x + $columnWord * 32;
                $xTo = $this->x + ($columnWord + 1) * 32;
                if ($xTo > $this->w) {
                    $xTo = $this->w;
                }
                $output[] = $this->getPixelsToBitBlt($row, $xFrom, $xTo);
            }
        }

        return join('', $output);
    }

    /**
     * This returns the number of pixels touched during conversion, useful for debugging.
     * @return int number of pixels converted
     * 
     */
    public function getTotalPixels() {
        return $this->totalPixels;
    }

}

if (!function_exists('imagepalettetotruecolor')) {

    /**
     * Converts a palete image to 32-bit true color image.
     * This function is part of PHP >= 5.5, but ported version was added for backward compatibility
     * http://us1.php.net/manual/en/function.imagepalettetotruecolor.php
     * @param resource $src
     * @return void
     */
    function imagepalettetotruecolor(&$src) {
        if (imageistruecolor($src)) {
            return(true);
        }

        $dst = imagecreatetruecolor(imagesx($src), imagesy($src));

        imagecopy($dst, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        imagedestroy($src);

        $src = $dst;

        return(true);
    }

}