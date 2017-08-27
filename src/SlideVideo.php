<?php

namespace GeneroWP\Hero;

use Timber;

class SlideVideo extends Slide implements SlideInterface
{
    /** @var Timber\Image[] */
    protected $videos = [];

    /** @inheritdoc */
    public function __construct($values = null)
    {
        parent::__construct($values);

        foreach ($this->slide_video_sources as $source) {
            $this->videos[] = new Timber\Image($source['video']);
        }
    }

    /** @inheritdoc */
    public function type()
    {
        return !empty($this->videos[0]->src) ? 'video' : null;
    }

    /** @inheritdoc */
    public function render_media()
    {
        $attributes = [];
        if (!empty($this->slide_video_dimensions['width'])) {
            $attributes[] = 'width="' . $this->slide_video_dimensions['width'] . '"';
        }
        if (!empty($this->slide_video_dimensions['height'])) {
            $attributes[] = 'height="' . $this->slide_video_dimensions['height'] . '"';
        }
        if (!empty($this->slide_video_options['loop'])) {
            $attributes[] = 'loop';
        }
        if (!empty($this->slide_video_options['muted'])) {
            $attributes[] = 'muted';
        }
        if (!empty($this->slide_video_options['autoplay'])) {
            $attributes[] = 'autoplay';
            $attributes[] = 'playsinline';
        }
        if ($this->slide_video_poster) {
            $attributes[] = 'poster="' . $this->slide_video_poster . '"';
        }

        foreach ($this->videos as $video) {
            $sources[] = "<source src=\"{$video->src()}\" type=\"{$video->post_mime_type}\">";
        }

        $attributes = implode(' ', $attributes);
        $sources = implode('', $sources);

        return "
            <div class=\"wp-hero__responsive-embed\">
              <video $attributes>$sources</video>
            </div>
            <style>{$this->css_inline()}</style>
        ";
    }

    /** @inheritdoc */
    public function css_inline()
    {
        $selector = '.wp-hero__responsive-embed';
        $padding = $this->intrinsic_ratio() * 100;
        return "#{$this->css_id} $selector { padding-bottom: $padding%; }";
    }

    /** @inheritdoc */
    public function get_defaults()
    {
        $defaults = array_merge(parent::get_defaults(), [
            'slide_type' => 'video',
            'slide_video_sources' => [],
            'slide_video_dimensions' => [
                'width' => null,
                'heigth' => null,
            ],
            'slide_video_options' => [
                'loop' => true,
                'autoplay' => true,
                'muted' => true,
            ],
            'slide_video_poster' => 'data:image/gif;base64,R0lGODlhAQABAIAAAP7//wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==',
        ]);
        return apply_filters('wp-hero/slide/defaults/video', $defaults);
    }

    /**
     * Get the intrinsic ratio used to calculate the responsive video size.
     *
     * @return float
     */
    public function intrinsic_ratio()
    {
        return ($this->slide_video_dimensions['height'] / $this->slide_video_dimensions['width']);
    }
}
