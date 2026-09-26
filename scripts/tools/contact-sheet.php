<?php
/**
 * Build a contact sheet of theme/assets/images so the set can be reviewed
 * visually rather than trusted blindly. Dev-only helper.
 */
declare(strict_types=1);

$base = dirname(__DIR__, 2) . '/theme/assets/images';

$files = [];
foreach (['hero', 'destinations', 'tours', 'guides', 'events', 'backgrounds', 'auth'] as $dir) {
    foreach (glob($base . '/' . $dir . '/*.jpg') ?: [] as $f) {
        $files[] = $f;
    }
}
sort($files);

if (!$files) {
    fwrite(STDERR, "No images found under $base\n");
    exit(1);
}

$cols   = 5;
$cell   = 300;
$label  = 26;
$rows   = (int) ceil(count($files) / $cols);
$sheetW  = $cols * $cell;
$sheetH  = $rows * ($cell + $label);

$sheet = imagecreatetruecolor($sheetW, $sheetH);
$bg    = imagecolorallocate($sheet, 20, 20, 20);
$fg    = imagecolorallocate($sheet, 255, 255, 255);
imagefilledrectangle($sheet, 0, 0, $sheetW, $sheetH, $bg);

foreach ($files as $i => $file) {
    $col = $i % $cols;
    $row = intdiv($i, $cols);
    $x   = $col * $cell;
    $y   = $row * ($cell + $label);

    $im = @imagecreatefromjpeg($file);
    if ($im) {
        $w = imagesx($im);
        $h = imagesy($im);
        $scale = min($cell / $w, $cell / $h);
        $nw = (int) ($w * $scale);
        $nh = (int) ($h * $scale);
        $thumb = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($thumb, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagecopy($sheet, $thumb, $x + intdiv($cell - $nw, 2), $y + intdiv($cell - $nh, 2), 0, 0, $nw, $nh);
        imagedestroy($thumb);
        imagedestroy($im);
    }

    $name = basename($file, '.jpg');
    imagestring($sheet, 3, $x + 6, $y + $cell + 6, $name, $fg);
}

$out = __DIR__ . '/contact-sheet.jpg';
imagejpeg($sheet, $out, 80);
echo "wrote $out  (", $sheetW, 'x', $sheetH, ", ", count($files), " files)\n";
echo implode("\n", array_map('basename', $files)), "\n";
