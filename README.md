# wp-hero

> A hero banner plugin for Wordpress using ACF and Timber.

## Requirements

- Advanced Custom Fields PRO
- Timber Library

## Features

- Provide a Hero banner field on posts and terms
- Multiple slides
- Video or image as well as title and caption
- Theme and overlay options for making content easier to read
- Linking slides to pages
- Force crop ratio
- Adds retina images
- Supports art direction using the [YoImages](https://wordpress.org/plugins/yoimages/) plugin
- Supports focus point cropping using the [WP SmartCrop](https://wordpress.org/plugins/wp-smartcrop/) plugin
- Integrates with [Yoast SEO](https://wordpress.org/plugins/wordpress-seo/) OG images
- Supports fallback slides using Featured Images, parent posts and/or a default image (@todo)
- Maintains aspect ratio without browser reflows

## Usage

Add the hero component to the timber context.

```php
add_filter('timber/context', function ($context) {
    $context['hero'] = new GeneroWP\Hero\Hero();
});
```

See [generoi/sage](https://github.com/generoi/sage/blob/genero/resources/views/layout/hero.twig) for an example timber template.

## API

```php
// @todo acf hook for setting themes, post-types etc.

// Disable slide fallback functionality.
add_filter('wp-hero/fallback', '__return_false');
add_filter('wp-hero/fallback/thumbnail', '__return_false');
add_filter('wp-hero/fallback/parent', '__return_false');
add_filter('wp-hero/fallback/default_image', '__return_false');

// Modify/add breakpoint thumbnail sizes and minimum widths.
add_filter('wp-hero/slide/breakpoints', function ($breakpoints) {
  $breakpoints['desktop']['min-width'] = '1200px';
});

// Whether to convert all slide images to JPGs.
add_filter('wp-hero/slide/tojpg', '__return_false');

// Set default slide values.
add_filter('wp-hero/slide/defaults', function ($defaults) {
  $defaults['slide_title'] = 'Foobar';
  return $defaults;
});
add_filter('wp-hero/slide/defaults/{image|video}', function ($defaults) {
  $defaults['slide_video_poster'] = 'xyz';
  return $defaults;
});
```

## Development

Install dependencies

    composer install
    npm install

Run the tests

    npm run test

Build assets

    # Minified assets which are to be committed to git
    npm run build

    # Development assets while developing the plugin
    npm run build:development

    # Watch for changes and re-compile while developing the plugin
    npm run watch
