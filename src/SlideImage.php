<?php

namespace GeneroWP\Hero;

use Timber;
use TimberExtended;

class SlideImage extends Slide implements SlideInterface
{
    /** @var Timber\Image[] */
    protected $images = [];

    public $ImageClass = 'Timber\Image';
    public $tojpg;

    /** @inheritdoc */
    public function __construct($values = null)
    {
        parent::__construct($values);

        if ($this->ImageClass === 'Timber\Image') {
            $this->ImageClass = TimberExtended::get_object_class('image', null, $this);
        }

        $this->image = new $this->ImageClass($this->slide_image);
        $this->tojpg = apply_filters('wp-hero/slide/tojpg', true);
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
        $crop = $this->force_crop ? 'default' : false;

        $sources = $css = [];
        foreach ($breakpoints as $breakpoint => $data) {
            $size = $data['size'];
            $image = new $this->ImageClass($this->image);

            if ($data['min-width'] === '0px' || $data['min-width'] === '0') {
                // Mobile should always be cropped as it's otherwise too short.
                $image->set_dimensions($size, null, 'default', $this->tojpg);
                $sources[] = $image->tag;
                $css_wrapper = '%s';
            } else {
                $image->set_dimensions($size, null, $crop, $this->tojpg);

                $sources[] = "<source srcset=\"{$image->srcset}\" media=\"(min-width: {$data['min-width']})\" />";
                $css_wrapper = "@media screen and (min-width: {$data['min-width']}) { %s } ";
            }

            $padding = $image->intrinsic_ratio() * 100;
            $css[] = sprintf($css_wrapper, "#$this->css_id { padding-bottom: $padding%; }");
        }

        $sources = implode('', $sources);
        $css = implode('', array_reverse($css));

        $content = "<picture id=\"$this->css_id\" class=\"wp-hero__responsive-embed\">$sources</picture>";
        $content .= "<style>{$css}</style>";
        return $content;
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
}
