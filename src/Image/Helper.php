<?php

namespace GeneroWP\Hero\Image;

use Timber\ImageHelper;
use Timber\URLHelper;

class Helper
{
    /**
     * Get the destination URL of an image to be manipulated.
     *
     * @param string $source URL of the source image
     * @param string $filename The filename of the destination image.
     * @return string
     */
    public static function get_destination_url($source, $filename)
    {
        $url_parts = parse_url($source);
        $url_parts['path'] = self::get_destination_path($url_parts['path'], $filename);
        $scheme = isset($url_parts['scheme']) ? $url_parts['scheme'] . '://' : '';
        $host   = isset($url_parts['host'])   ? $url_parts['host'] : '';
        $port   = isset($url_parts['port'])   ? ':' . $url_parts['port'] : '';
        $path   = isset($url_parts['path'])   ? $url_parts['path'] : '';
        return "$scheme$host$port$path";
    }

    /**
     * Get the destination path of an image to be manipulated.
     *
     * @param string $source File path of the source image
     * @param string $filename The filename of the destination image.
     * @return string
     */
    public static function get_destination_path($source, $filename)
    {
        $parts = pathinfo($source);
        return $parts['dirname'] . '/' . $filename;
    }

    /**
     * Crop in image and return it's URL.
     *
     * @param string $src Path to source image.
     * @param int $x
     * @param int $y
     * @param int $width
     * @param int $height
     * @return string
     */
    public static function crop($src, $x, $y, $width, $height)
    {
        $op = new Image\Operation\Crop($x, $y, $width, $height);
        return self::operate($src, $op);
    }

    /**
     * Perform an operation and return tile file path of the new images.
     *
     * @see Timber\ImageHelper::_operate().
     * @param string $src File path to source image.
     * @param string $destination File path to destination image.
     * @param Timber\Image\Operatetion $op
     * @return string
     */
    public static function operate($src, $destination, $op)
    {
        if (empty($src)) {
            return '';
        }
        if (URLHelper::is_external_content($src)) {
            $src = ImageHelper::sideload_image($src);
        }

        if (file_exists($src) && file_exists($destination)) {
            if (filemtime($src) > filemtime($destination)) {
                unlink($destination);
            } else {
                return $destination;
            }
        }

        if ($op->run($src, $destination)) {
            return $destination;
        }
        return $src;
    }

    /**
     * Generate and get the retina URL of an image.
     *
     * @param Timber\Image $image Original un-resized image.
     * @param array $dimensions Size dimensions.
     * @return string
     */
    public static function retina($image, $dimensions)
    {
        $source = $image->src();
        $width = $dimensions['width'] * 2;
        $height = $dimensions['height'] * 2;
        $max_width = $image->width();
        $max_height = $image->height();
        $aspect_ratio = $height / $width;

        if ($width > $max_width) {
            $width = $max_width;
            $height = $width * $aspect_ratio;
        }

        if ($height > $max_height) {
            $height = $max_height;
            $width = $height / $aspect_ratio;
        }
        $width = round($width);
        $height = round($height);

        $crop = $dimensions['crop'];
        $src = ImageHelper::resize($source, $width, $height, $crop);
        return $src;
    }
}
