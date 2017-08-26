<?php

namespace GeneroWP\Hero;

use GeneroWP\Hero\Slide;
use Timber;

class Hero extends Timber\Core implements Timber\CoreInterface
{
    /**
     * @param $object WP_Post|WP_Term Queried object from where to retrieve fields.
     */
    public function __construct($object = null)
    {
        if (!isset($object)) {
            $object = get_queried_object();
        }
        $this->object = $object;
    }

    /**
     * Get the slides for the hero banner.
     *
     * @return SlideInterface[]
     */
    public function slides()
    {
        if (isset($this->object->term_id)) {
            $this->object = new Timber\Term($this->object);
            $this->object_type = 'term';
        } elseif (isset($this->object->post_type)) {
            $this->object = new Timber\Post($this->object);
            $this->object_type = 'post';
        } else {
            // @todo error handling?
        }

        $this->ID = $this->object->ID;
        $this->slides = [];

        if ($slides = get_field('hero_slide', $this->ID)) {
            $force_crop = get_field('hero_crop', $this->ID);

            foreach ($slides as $slide) {
                $this->slides[] = SlideFactory::create(array_merge($slide, [
                    'force_crop' => $force_crop,
                ]));
            }
        }

        if (count($this->slides) === 0 && apply_filters('wp-hero/fallback', true)) {
            if ($slide = $this->getFallbackSlide()) {
                $this->slides[] = $slide;
            }
        }

        return $this->slides;
    }

    /**
     * Get a fallback slide if possible.
     *
     * @return SlideInterface
     */
    public function getFallbackSlide()
    {
        if ($this->object_type === 'post') {
            if (has_post_thumbnail($this->ID) && apply_filters('wp-hero/fallback/thumbnail', true)) {
                // Post Thumbnail
                return SlideFactory::create([
                    'slide_type' => 'image',
                    'slide_image' => get_post_thumbnail_id($this->ID),
                    'force_crop' => get_field('hero_crop', $this->ID),
                ]);
            } elseif ($this->object->post_parent && apply_filters('wp-hero/fallback/parent', true)) {
                // Inherit from parent post
                $parent_id = $this->object->post_parent;
                $slides = get_field('hero_slide', $parent_id);

                if (!empty($slides)) {
                    // Parent Hero slides
                    $slide = reset($slides);

                    // @todo
                    return SlideFactory::create(array_merge($slide, [
                        'slide_title' => null,
                        'slide_content' => null,
                        'slide_theme' => null,
                        'slide_overlay' => null,
                        'slide_link' => null,
                        'force_crop' => get_field('hero_crop', $parent_id),
                    ]));
                } elseif (has_post_thumbnail($parent_id)) {
                    // Parent Post thumbnail
                    return SlideFactory::create([
                        'slide_type' => 'image',
                        'slide_image' => get_post_thumbnail_id($parent_id),
                        'force_crop' => get_field('hero_crop', $parent_id),
                    ]);
                }
            }
        }

        // Default image.
        // @todo implement
        // if (empty($this->slides) && apply_filters('wp-hero/fallback/default_image', true)) {
        //     if ($image = get_field('hero_default_image', 'option')) {
        //         return SlideFactory::create([
        //             'slide_type' => 'image',
        //             'slide_image' => $image,
        //         ]);
        //     }
        // }
    }

    /** @inheritdoc */
    public function meta($key)
    {
        return null;
    }
}
