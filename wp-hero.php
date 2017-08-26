<?php
/*
Plugin Name:        Hero
Plugin URI:         http://genero.fi
Description:        A Hero banner plugin for Wordpress
Version:            1.0.0
Author:             Genero
Author URI:         http://genero.fi/
License:            MIT License
License URI:        http://opensource.org/licenses/MIT
*/
namespace GeneroWP\Hero;

use WPSEO_Options;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{

    private static $instance = null;
    public $version = '1.0.0';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);

        add_action('acf/init', [$this, 'register_acf_fields']);
        add_filter('wpseo_opengraph_image', [$this, 'set_og_image']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('delete_attachment', [$this, 'delete_timber_variations']);
        add_filter('wp_generate_attachment_metadata', [$this, 'generate_attachment_metadata'], 10, 2);
    }

    public function register_assets()
    {
        $path = plugin_dir_url(__FILE__);
        wp_register_style('wp-hero/css', $path . 'dist/main.css', [], $this->version);
    }

    public function enqueue_assets()
    {
        wp_enqueue_style('wp-hero/css');
    }

    /**
     * Yoast integration for setting a default og:image based on the hero.
     *
     * @param string $image the URL to the image.
     */
    public function set_og_image($image)
    {
        $options = WPSEO_Options::get_option('wpseo_social');
        if ($image != $options['og_default_image']) {
            return $image;
        }
        $object = get_queried_object();
        // If it's a real thumbnail, use it.
        if ($object instanceof WP_Post && has_post_thumbnail($object)) {
            return $image;
        }
        // Use the first hero slide's image if available.
        $hero = get_field('hero_slide', $object);
        if (!empty($hero[0]['slide_image'])) {
            return $hero[0]['slide_image'];
        }
        return $image;
    }

    /**
     * Register required ACF Fields.
     */
    public function register_acf_fields()
    {
        include_once __DIR__ . '/acf/acf-export.php';
    }

    /**
     * When regenerating attachment metadata and thumbnails, delete old crops.
     *
     * @param array $metadata
     * @param int $post_id
     * @return array
     */
    public function generate_attachment_metadata($metadata, $post_id)
    {
        $this->delete_timber_variations($post_id);
        return $metadata;
    }

    /**
     * When deleting an attachment, delete image crops.
     *
     * @param int $post_id
     */
    public function delete_timber_variations($post_id)
    {
        if (!wp_attachment_is_image($post_id)) {
            return;
        }
        $attachment = new Timber\Image($post_id);
        if ($attachment->file_loc) {
            $parts = pathinfo($local_file);
            $dirname = $parts['dirname'];
            $ext = $parts['extension'];
            $filename = $parts['filename'];

            $image_glob = $parts['dirname'] . '/' . $parts['filename'] . '-wp-hero-crop-*';
            array_map('unlink', glob($image_glob));
        }
    }

    /**
     * Activate plugin.
     */
    public static function activate()
    {
        foreach ([
            'advanced-custom-fields-pro/acf.php' => 'Advanced Custom Fields PRO',
            'timber-library/timber.php' => 'Timber Library',
            // 'wp-timber-extended/wp-timber-extended.php' => 'WP Timber Extended',
        ] as $plugin => $name) {
            if (!is_plugin_active($plugin) && current_user_can('activate_plugins')) {
                wp_die(sprintf(
                    __('Sorry, but this plugin requires the %s plugin to be installed and active. <br><a href="%s">&laquo; Return to Plugins</a>', 'wp-hero'),
                    $name,
                    admin_url('plugins.php')
                ));
            }
        }
    }

    /**
     * Deactivate plugin.
     */
    public static function deactivate()
    {
    }
}

if (file_exists($composer = __DIR__ . '/vendor/autoload.php')) {
    require_once $composer;
}

Plugin::get_instance()->init();