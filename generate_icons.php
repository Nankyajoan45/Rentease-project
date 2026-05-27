#!/usr/bin/env php
<?php
// Generate placeholder icons for PWA
$sizes = [72, 96, 128, 192, 512];
$dir = __DIR__ . '/assets/images/';

if (!is_dir($dir)) mkdir($dir, 0755, true);

foreach ($sizes as $size) {
    $img = imagecreatetruecolor($size, $size);

    // Background gradient (blue)
    for ($y = 0; $y < $size; $y++) {
        $ratio = $y / $size;
        $r = (int)(15  + (29  - 15)  * $ratio);
        $g = (int)(76  + (107 - 76)  * $ratio);
        $b = (int)(129 + (181 - 129) * $ratio);
        $col = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $size, $y, $col);
    }

    // Rounded corner mask
    $white = imagecolorallocate($img, 255, 255, 255);
    $radius = (int)($size * 0.22);

    // Draw house icon (simplified)
    $scale  = $size / 192;
    $cx     = $size / 2;

    // Roof triangle
    $roof = [
        (int)($cx),             (int)($size * 0.22),
        (int)($size * 0.18),    (int)($size * 0.50),
        (int)($size * 0.82),    (int)($size * 0.50),
    ];
    imagefilledpolygon($img, $roof, $white);

    // Body rectangle
    imagefilledrectangle($img,
        (int)($size * 0.25), (int)($size * 0.48),
        (int)($size * 0.75), (int)($size * 0.78),
        $white
    );

    // Door (accent orange)
    $orange = imagecolorallocate($img, 249, 115, 22);
    imagefilledrectangle($img,
        (int)($cx - $size * 0.08), (int)($size * 0.60),
        (int)($cx + $size * 0.08), (int)($size * 0.78),
        $orange
    );

    $filename = $dir . 'icon-' . $size . '.png';
    imagepng($img, $filename);
    imagedestroy($img);
    echo "Generated: $filename\n";
}
echo "All icons generated.\n";
