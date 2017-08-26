<?php

namespace GeneroWP\Hero;

class SlideFactory
{
    /**
     * Factory to create Slider objects of different types.
     *
     * @param array $values ACF field contents for a `hero_slide` item.
     * @return SlideInterface
     */
    public static function create($values)
    {
        $type = isset($values['slide_type']) ? $values['slide_type'] : 'image';
        switch ($type) {
            case 'image':
                return new SlideImage($values);
            case 'video':
                return new SlideVideo($values);
        }
        throw new Exception('Slide type not found.');
    }
}
