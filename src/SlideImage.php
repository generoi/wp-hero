<?php

namespace GeneroWP\Hero;

use Timber;

class SlideImage extends Slide implements SlideInterface
{
    /** @var Timber\Image[] */
    protected $images = [];

    /** @inheritdoc */
    public function __construct($values = null)
    {
        parent::__construct($values);

        $this->image = new Timber\Image($this->slide_image);
        if (apply_filters('wp-hero/slide/tojpg', true)) {
            $src = Timber\ImageHelper::img_to_jpg($this->image->src());
            $this->image->init($src);
            $this->fix_bedrock_loc($this->image);
        }
    }

    /** @inheritdoc */
    public function type()
    {
        return !empty($this->image->src) ? 'image' : null;
    }

    /** @inheritdoc */
    public function media_html()
    {
        $breakpoints = $this->get_breakpoint();

        $sources = [];
        foreach ($breakpoints as $breakpoint => $data) {
            if ($data['min-width'] === '0px' || $data['min-width'] === '0') {
                $title = $this->image->title();
                $alt = $this->image->alt() ?: $this->image->title();
                $classes = [];

                if ($this->has_smartcrop()) {
                    $classes[] = 'wp-hero__smartcrop';
                    $focus = unserialize($this->image->_wpsmartcrop_image_focus);
                    $left = round($focus['left'], 2);
                    $top = round($focus['top'], 2);
                    $attributes[] = 'style="object-position: ' . $left . '% ' . $top . '%;"';
                }

                $attributes[] = 'class="' . implode(' ', $classes) . '"';
                $attributes[] = 'alt="' . $alt . '"';
                $attributes[] = 'title="' . $title . '"';
                $attributes[] = 'srcset="' . $this->srcset($breakpoint) . '"';
                $attributes = implode(' ', $attributes);

                $sources[] = "<img $attributes>";
            } else {
                $sources[] = "<source srcset=\"{$this->srcset($breakpoint)}\" media=\"(min-width: {$data['min-width']})\" />";
            }
        }

        $sources = implode('', $sources);

        $content = "<picture class=\"wp-hero__responsive-embed\">$sources</picture>";
        $content .= "<style>{$this->css_inline()}</style>";
        return $content;
    }

    /** @inheritdoc */
    public function css_inline()
    {
        $selector = 'picture';
        $breakpoints = array_reverse($this->get_breakpoint());

        foreach ($breakpoints as $breakpoint => $data) {
            $wrapper = '%s';
            $padding = $this->intrinsic_ratio($breakpoint) * 100;

            if ($data['min-width'] !== '0px' && $data['min-width'] !== '0') {
                $wrapper = "@media screen and (min-width: {$data['min-width']}) { %s } ";
            }

            $css[] = sprintf($wrapper, "#$this->css_id $selector { padding-bottom: $padding%; }");
        }
        return implode('', $css);
    }

    /** @inheritdoc */
    public function get_defaults()
    {
        $defaults = array_merge(parent::get_defaults(), [
            'slide_type' => 'image',
            'slide_image' => null,
        ]);
        return apply_filters('wp-hero/slide/defaults/image', $defaults);
    }

    /**
     * Get the intrinsic ratio used to calculate the responsive image size.
     *
     * @param string $breakpoint The arbitrary name of the breakpoint.
     * @return float
     */
    public function intrinsic_ratio($breakpoint)
    {
        // If we're enforcing the crop, or smartcrop uses object-fit, use the
        // pre-defined dimensions.
        if ($this->force_crop || $this->has_smartcrop()) {
            $dimensions = $this->get_image_dimensions($breakpoint);

            $width = $dimensions['width'];
            $height = $dimensions['height'];
        } else {
            $image = $this->get_image($breakpoint);
            $width = $image->width();
            $height = $image->height();
        }

        return $height / $width;
    }


    /**
     * Get information about a breakpoint or all breakpoints.
     *
     * @param string $breakpoint The arbitrary name of the breakpoint.
     * @return array
     */
    public function get_breakpoint($breakpoint = null)
    {
        $defaults = [
            'desktop' => [
                'size' => 'hero--desktop',
                'min-width' => '1000px',
            ],
            'tablet' => [
                'size' => 'hero--tablet',
                'min-width' => '700px',
            ],
            'mobile' => [
                'size' => 'hero--mobile',
                'min-width' => '0px',
            ],
        ];

        $breakpoints = apply_filters('wp-hero/slide/breakpoints', $defaults);

        if (isset($breakpoint)) {
            return $breakpoints[$breakpoint];
        }
        return $breakpoints;
    }

    /**
     * Get the full srcset value of an image in specified breakpoint
     *
     * @param string $breakpoint The arbitrary name of the breakpoint.
     * @return string
     */
    public function srcset($breakpoint)
    {
        // Unresized image object.
        $image = $this->get_image($breakpoint);
        $data = $this->get_breakpoint($breakpoint);
        $crop_format = $this->crop_format($breakpoint);
        $dimensions = $this->get_image_dimensions($breakpoint);
        $crop = $this->force_crop;
        // Do not crop if there's smartcrop enabled as cropping is done with
        // CSS.
        if ($this->has_smartcrop()) {
            $crop = false;
        }
        // If crop formats are used, force crop as the operation will enforce
        // it.
        if ($crop_format) {
            $crop = true;
        }

        if ($crop && !$crop_format) {
            // Cropping is enforced and we can use the WP thumbnail.
            $src = $image->src($breakpoint);
            $src_retina = $this->retina($image, $dimensions);
        } elseif ($crop && $crop_format) {
            // Cropping is enforced but no thumbnail exists.
            $src = Timber\ImageHelper::resize($image->src(), $dimensions['width'], $dimensions['height'], $dimensions['crop']);
            $src_retina = $this->retina($image, $dimensions);
        } elseif (!$crop) {
            // Cropping is not enforced, scale instead.
            // @todo hardcoded breakpoint name.
            if (!$this->has_smartcrop() && $breakpoint === 'mobile') {
                // Mobile usually requires a taller crop to fit content.
                $src = Timber\ImageHelper::resize($image->src(), $dimensions['width'], $dimensions['height']);
                $src_retina = $this->retina($image, $dimensions);
            } else {
                // WP generates a cropped thumbnail, so it's unusable. Resize the
                // original image by width only.
                $src = Timber\ImageHelper::resize($image->src(), $dimensions['width']);
                $src_retina = $this->retina($image, [
                    'width' => $dimensions['width'],
                    'height' => 0,
                    'crop' => $dimensions['crop'],
                ]);
            }
        }

        if ($src_retina) {
            return "$src, $src_retina 2x";
        }
        return $src;
    }

    /**
     * Get the un-resized Timber\Image object of an image in specified
     * breakpoint.
     *
     * @param string $breakpoint The arbitrary name of the breakpoint.
     * @return Timber\Image
     */
    protected function get_image($breakpoint)
    {
        if (!isset($this->images[$breakpoint])) {
            $data = $this->get_breakpoint($breakpoint);
            $crop_format = $this->crop_format($breakpoint);
            // Grab the original image.
            $image = $this->image;

            // If there's a replacement image for this breakpoint.
            if (isset($crop_format['replacement'])) {
                $image = new Timber\Image($crop_format['replacement']);
            }

            // Re-crop the image if YoImages has crop formats defined.
            if ($crop_format) {
                // Use the orignial image name in the filename rather than the
                // replacement.
                $destination_path = $this->crop_destination_path($this->image->file_loc, $crop_format);
                $destination_url = $this->crop_destination_url($this->image->src, $crop_format);
                // Crop the image using the YoImage crop data.
                $result = $this->crop(
                    $image->file_loc,
                    $destination_path,
                    $crop_format
                );
                $image = new Timber\Image($destination_url);
                $this->fix_bedrock_loc($image);
            }

            $this->images[$breakpoint] = $image;
        }

        return $this->images[$breakpoint];
    }

    /**
     * Return if the image has smartcrop enabled.
     *
     * @return bool
     */
    public function has_smartcrop()
    {
        return !empty($this->image->_wpsmartcrop_enabled);
    }

    /**
     * Get the yoimg crop format for a breakpoint.
     *
     * @param string $breakpoint The arbitrary name of the breakpoint.
     * @return array
     */
    public function crop_format($breakpoint)
    {
        if (!empty($this->image->yoimg_attachment_metadata)) {
            $data = $this->get_breakpoint($breakpoint);
            $size = $data['size'];
            $metadata = $this->image->yoimg_attachment_metadata;

            if (!empty($metadata['crop'][$size])) {
                return $metadata['crop'][$size];
            }
        }
        return null;
    }

    /**
     * Get image dimensions speicified by the WordPress size.
     *
     * @param string $breakpoint The arbitrary name of the breakpoint.
     * @return array
     */
    protected function get_image_dimensions($breakpoint)
    {
        global $_wp_additional_image_sizes;
        $data = $this->get_breakpoint($breakpoint);
        $size = $data['size'];

        $sizes = [];
        foreach (get_intermediate_image_sizes() as $_size) {
            if (in_array($_size, ['thumbnail', 'medium', 'medium_large', 'large'])) {
                $sizes[$_size]['width']  = get_option("{$_size}_size_w");
                $sizes[$_size]['height'] = get_option("{$_size}_size_h");
                $sizes[$_size]['crop']   = get_option("{$_size}_crop");
            } elseif (isset($_wp_additional_image_sizes[$_size])) {
                $sizes[$_size] = [
                    'width'  => $_wp_additional_image_sizes[$_size]['width'],
                    'height' => $_wp_additional_image_sizes[$_size]['height'],
                    'crop'   => $_wp_additional_image_sizes[$_size]['crop'],
                ];
            }
        }
        if (isset($sizes[$size])) {
            return $sizes[$size];
        }
        return null;
    }

    /**
     * Generate and get the retina URL of an image.
     *
     * @param Timber\Image $image Original un-resized image.
     * @param array $dimensions Size dimensions.
     * @return string
     */
    protected function retina($image, $dimensions)
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
        $src = Timber\ImageHelper::resize($source, $width, $height, $crop);
        return $src;
    }

    /**
     * Get the destination URL of a cropped image.
     *
     * @param string $source URL of the source image
     * @param array $crop Crop values set by YoImages
     * @return string
     */
    protected function crop_destination_url($source, $crop)
    {
        $url_parts = parse_url($source);
        $url_parts['path'] = $this->crop_destination_path($url_parts['path'], $crop);
        $scheme = isset($url_parts['scheme']) ? $url_parts['scheme'] . '://' : '';
        $host   = isset($url_parts['host'])   ? $url_parts['host'] : '';
        $port   = isset($url_parts['port'])   ? ':' . $url_parts['port'] : '';
        $path   = isset($url_parts['path'])   ? $url_parts['path'] : '';
        return "$scheme$host$port$path";
    }

    /**
     * Get the destination path of a cropped image.
     *
     * @param string $source File path of the source image
     * @param array $crop Crop values set by YoImages
     * @return string
     */
    protected function crop_destination_path($source, $crop)
    {
        $parts = pathinfo($source);
        $parts['filename'] = $this->crop_filename($parts['filename'], $crop);
        return $parts['dirname'] . '/' . $parts['filename'] . '.' . $parts['extension'];
    }


    /**
     * Get the filename of a cropped image.
     *
     * @param string $filename Filename without extension.
     * @param array $crop Crop values set by YoImages
     * @return string
     */
    protected function crop_filename($filename, $crop)
    {
        // We use a custom pattern which we clean out using a hook when the
        // original is deleted.
        return $filename . '-wp-hero-crop'
            . '-' . $crop['width'] . 'x' . $crop['height']
            . '-' . $crop['x'] . 'x' . $crop['y'];
    }

    /**
     * Crop an image.
     *
     * @param string $source File path to source image
     * @param string $destination File path to destination image
     * @param array $crop Crop values set by YoImages
     * @return bool
     */
    protected function crop($source, $destination, $crop)
    {
        if (file_exists($source) && file_exists($destination)) {
            if (filemtime($source) > filemtime($destination)) {
                unlink($destination);
            }
            return true;
        }

        $image = wp_get_image_editor($source);
        if (!is_wp_error($image)) {
            $image->crop(
                $crop['x'],
                $crop['y'],
                // source size
                $crop['width'],
                $crop['height'],
                // target size
                $crop['width'],
                $crop['height']
            );
            $result = $image->save($destination);
            if (is_wp_error($result)) {
                Timber\Helper::error_log('Error cropping image (wp-hero)');
                Timber\Helper::error_log($result);
            } else {
                return true;
            }
        } elseif (isset($image->error_data['error_loading_image'])) {
            Timber\Helper::error_log('Error loading (wp-hero) ' . $image->error_data['error_loading_image']);
        } else {
            Timber\Helper::error_log($image);
        }
        return false;
    }
}
