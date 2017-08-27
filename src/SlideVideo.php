<?php

namespace GeneroWP\Hero;

use Timber;

class SlideVideo extends Slide implements SlideInterface
{

    /** @inheritdoc */
    public function __construct($values = null)
    {
        parent::__construct($values);

        $this->video = new Timber\Image($this->slide_video);
    }

    /** @inheritdoc */
    public function type()
    {
        return !empty($this->video->src) ? 'video' : null;
    }

    /** @inheritdoc */
    public function render_media()
    {
        $attributes = [];
        if ($this->slide_video_width) {
            $attributes[] = 'width="' . $this->slide_video_width . '"';
        }
        if ($this->slide_video_height) {
            $attributes[] = 'height="' . $this->slide_video_height . '"';
        }
        if ($this->slide_video_muted) {
            $attributes[] = 'muted';
        }
        if ($this->slide_video_autoplay) {
            $attributes[] = 'autoplay';
            $attributes[] = 'playsinline';
        }
        if ($this->slide_video_poster) {
            $attributes[] = 'poster="' . $this->slide_video_poster . '"';
        }

        $attributes = implode(' ', $attributes);

        return "
            <div class=\"wp-hero__responsive-embed\">
              <video $attributes>
                <source src=\"{$this->video->src()}\" type=\"{$this->video->post_mime_type}\">
              </video>
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
            'slide_video' => null,
            'slide_video_width' => null,
            'slide_video_height' => null,
            'slide_video_poster' => 'data:image/gif;base64,R0lGODlhAQABAIAAAP7//wAAACH5BAAAAAAALAAAAAABAAEAAAICRAEAOw==',
            'slide_video_autoplay' => true,
            'slide_video_muted' => true,
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
        return ($this->slide_video_height / $this->slide_video_width);
    }
}
