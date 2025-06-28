<?php
/*
 * PHP QR Code encoder
 *
 * Simplified version by phpgurukul.com
 */
class QRcode {
    
    public static function png($text, $outfile = false, $level = 'L', $size = 3, $margin = 4) {
        $enc = QRencode::encode($text, $level, $size, $margin);
        return self::image($enc, $outfile);
    }
    
    private static function image($frame, $filename = false) {
        $h = count($frame);
        $w = strlen($frame[0]);
        
        $imgW = $w + 8;
        $imgH = $h + 8;
        
        $base_image = imagecreate($imgW, $imgH);
        
        $col[0] = imagecolorallocate($base_image, 255, 255, 255); // BG
        $col[1] = imagecolorallocate($base_image, 0, 0, 0); // FG
        
        imagefill($base_image, 0, 0, $col[0]);
        
        for($y=0; $y<$h; $y++) {
            for($x=0; $x<$w; $x++) {
                if ($frame[$y][$x] == '1') {
                    imagesetpixel($base_image, $x+4, $y+4, $col[1]);
                }
            }
        }
        
        if ($filename) {
            imagepng($base_image, $filename);
            imagedestroy($base_image);
            return true;
        } else {
            header("Content-type: image/png");
            imagepng($base_image);
            imagedestroy($base_image);
            return true;
        }
    }
}

class QRencode {
    
    public static function encode($text, $level = 'L', $size = 3, $margin = 4) {
        $length = strlen($text);
        $data = array();
        
        // Simple encoding for demonstration
        $w = $h = $size * 25; // Fixed size for simplicity
        
        for($y=0; $y<$h; $y++) {
            $data[$y] = str_repeat('0', $w);
        }
        
        // Create a simple pattern (not a real QR code, just for demonstration)
        for($y=0; $y<$h; $y++) {
            for($x=0; $x<$w; $x++) {
                if (($x + $y) % 2 == 0) {
                    $row = $data[$y];
                    $row[$x] = '1';
                    $data[$y] = $row;
                }
            }
        }
        
        return $data;
    }
} 