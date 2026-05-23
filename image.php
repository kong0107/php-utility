<?php
/**
 * Image Processing and GD
 * @see https://www.php.net/manual/en/book.image.php
 */

function imagecreatefromfile(
    string $filename,
    array &$size = null,
    array &$info = null
) : GdImage|false {
    $size = getimagesize($filename, $info);
    if (!$size) return false;

    $type = match ($size[2]) {
        IMAGETYPE_GIF => 'gif',
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_BMP => 'bmp',
        IMAGETYPE_WBMP => 'wbmp',
        IMAGETYPE_XBM => 'xbm',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_AVIF => 'avif',
        default => null
    };
    if (!$type) return false;

    $size['type'] = $type;
    return call_user_func("imagecreatefrom$type", $filename);
}
