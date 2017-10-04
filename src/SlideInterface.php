<?php

namespace GeneroWP\Hero;

interface SlideInterface
{
    /**
     * Return a HTML string for outputing the slide's media content.
     *
     * @return string
     */
    public function render_media();

    /**
     * Get the type of slider item, eg. image, video.
     *
     * @return string
     */
    public function type();
}
