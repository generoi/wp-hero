<?php

namespace GeneroWP\Hero;

interface SlideInterface
{
    /**
     * Return a HTML string for outputing the slide's media content.
     *
     * @return string
     */
    public function media_html();

    /**
     * Return a string of CSS that sets slide's ratio before the the browser
     * has managed to calculate it's size.
     *
     * @return string
     */
    public function css_inline();

    /**
     * Get the type of slider item, eg. image, video.
     *
     * @return string
     */
    public function type();
}
