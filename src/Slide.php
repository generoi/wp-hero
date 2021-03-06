<?php

namespace GeneroWP\Hero;

use Timber;

abstract class Slide extends Timber\Core implements Timber\CoreInterface
{
    /** @var int $index Index of the slide */
    protected $index;

    /** @var string $css_id Unique CSS ID for the slide */
    public $css_id;

    /** @var int $counter Static counter of slide items */
    protected static $counter = 0;

    /**
     * @param array $values ACF field contents for a `hero_slide` item.
     */
    public function __construct(array $values = null)
    {
        $this->import(array_merge($this->get_defaults(), $values));

        $this->index = self::$counter++;
        $this->css_id = "wp-hero-slide-{$this->index}";
    }

    /**
     * Get default values to merge into all slide objects created.
     *
     * @return array
     */
    public function get_defaults()
    {
        $defaults = [
            'slide_theme' => 'none',
            'slide_overlay' => null,
            'slide_link' => null,
            'slide_title' => null,
            'slide_content' => null,
            'force_crop' => true,
        ];
        return apply_filters('wp-hero/slide/defaults', $defaults);
    }

    /** @inheritdoc */
    public function can_edit()
    {
        return false;
    }

    /** @inheritdoc */
    public function meta($key)
    {
        if (strpos('slide_', $key) === 0) {
            $key = substr($key, 6);
            return $this->{$key};
        }
        return null;
    }
}
