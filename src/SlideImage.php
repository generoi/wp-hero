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
            Image\Helper::fix_bedrock_loc($this->image);
        }
    }

    /** @inheritdoc */
    public function type()
    {
        return !empty($this->image->src) ? 'image' : null;
    }

    /** @inheritdoc */
    public function render_media()
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
        $selector = '.wp-hero__responsive-embed';
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

        if ($crop_format) {
            // Cropping is enforced but no thumbnail exists.
            $src = Timber\ImageHelper::resize($image->src(), $dimensions['width'], $dimensions['height'], $dimensions['crop']);
            $src_retina = Image\Helper::retina($image, $dimensions);
        } elseif ($crop) {
            // Cropping is enforced and we can use the WP thumbnail.
            $src = $image->src($breakpoint);
            $src_retina = Image\Helper::retina($image, $dimensions);
        } else {
            // Cropping is not enforced, scale instead.
            // @todo hardcoded breakpoint name.
            if (!$this->has_smartcrop() && $breakpoint === 'mobile') {
                // Mobile usually requires a taller crop to fit content.
                $src = Timber\ImageHelper::resize($image->src(), $dimensions['width'], $dimensions['height']);
                $src_retina = Image\Helper::retina($image, $dimensions);
            } else {
                // WP generates a cropped thumbnail, so it's unusable. Resize the
                // original image by width only.
                $src = Timber\ImageHelper::resize($image->src(), $dimensions['width']);
                $src_retina = Image\Helper::retina($image, [
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
                $op = new Image\Operation\Crop($crop_format['x'], $crop_format['y'], $crop_format['width'], $crop_format['height']);

                // Use the orignial image name in the filename rather than the
                // replacement.
                $au = Timber\ImageHelper::analyze_url($this->image->file_loc);
                $new_filename = $op->filename($au['filename'], $au['extension']);

                $source_path = $image->file_loc;
                $destination_path = Image\Helper::get_destination_path($image->file_loc, $new_filename);
                $destination_url = Image\Helper::get_destination_url($image->src, $new_filename);
                Image\Helper::operate($source_path, $destination_path, $op);
                $image = new Timber\Image($destination_url);
                Image\Helper::fix_bedrock_loc($image);
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
}
