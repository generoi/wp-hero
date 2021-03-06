<?php
/*
Plugin Name:        Hero
Plugin URI:         http://genero.fi
Description:        A Hero banner plugin for Wordpress
Version:            0.1-alpha.4
Author:             Genero
Author URI:         http://genero.fi/
License:            MIT License
License URI:        http://opensource.org/licenses/MIT
*/
namespace GeneroWP\Hero;

use WPSEO_Options;
use Timber;
use Puc_v4_Factory;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin
{

    private static $instance = null;
    public $version = '0.1-alpha.3';
    public $plugin_name = 'wp-hero';
    public $github_url = 'https://github.com/generoi/wp-hero';

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct()
    {
        register_activation_hook(__FILE__, [__CLASS__, 'activate']);
        register_deactivation_hook(__FILE__, [__CLASS__, 'deactivate']);

        Puc_v4_Factory::buildUpdateChecker($this->github_url, __FILE__, $this->plugin_name);

        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init()
    {
        add_action('acf/init', [$this, 'register_acf_fields']);
        add_filter('acf/location/rule_types', [$this, 'acf_location_rule_type']);
        add_filter('acf/location/rule_match/hero', [$this, 'acf_location_rule_match'], 10, 3);
        add_action('wpseo_add_opengraph_additional_images', [$this, 'wpseo_og_image']);
        add_filter('the_seo_framework_og_image_after_featured', [$this, 'seoframework_og_image']);
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
     * Define a custom ACF location rule type.
     */
    public function acf_location_rule_type($choices)
    {
        $choices['WP Hero']['hero'] = 'Custom match';
        return $choices;
    }

    /**
     * Return if custom location rule is matched on particular screen.
     *
     * @param bool $match
     * @param array $rule
     * @param array $options
     * @return bool
     */
    public function acf_location_rule_match($match, $rule, $options)
    {
        $match = false;

        if (isset($options['taxonomy'])) {
            $match = in_array($options['taxonomy'], get_taxonomies(['public' => true]));
            $match = apply_filters('wp-hero/visible/taxonomy', $match, $options['taxonomy']);
        }

        if (isset($options['post_type'])) {
            $match = in_array($options['post_type'], get_post_types(['public' => true]));

            if ($match && $options['post_type'] === 'attachment') {
                $match = false;
            }

            $match = apply_filters('wp-hero/visible/post_type', $match, $options['post_type']);
        }

        return apply_filters('wp-hero/visible', $match, $options);
    }

    /**
     * Yoast integration for setting a default og:image based on the hero.
     *
     * @param string $image the URL to the image.
     * @return string
     */
    public function wpseo_og_image($og)
    {
        $object = get_queried_object();
        if ($object instanceof \WP_Post_Type) {
            // basic acf-genero-components integration
            $pages = get_pages([
                'meta_key' => 'archive__post_type',
                'meta_value' => "post_type:$object->name",
                'meta_compare' => 'LIKE',
                'number' => 1,
            ]);

            if (!empty($pages)) {
                $object = reset($pages);
            } elseif (function_exists('is_product_category')) {
                // woocommerce
                $object = get_post(get_option('woocommerce_shop_page_id'));
            }
        }

        $hero = get_field('hero_slide', $object);
        if ($object instanceof \WP_Post && has_post_thumbnail($object)) {
            $image = get_the_post_thumbnail_url($object);
        } elseif ($object instanceof \WP_Term) {
            foreach (['thumbnail_id', 'thumbnail', 'product_cat_thumbnail'] as $field) {
                if ($thumbnail = get_field($field, $object)) {
                    if (is_numeric($thumbnail)) {
                        $image = wp_get_attachment_url($thumbnail);
                    } elseif (is_string($thumbnail)) {
                        $image = $thumbnail;
                    } elseif (is_array($thumbnail) && isset($thumbnail['url'])) {
                        $image = $thumbnail['url'];
                    }
                    break;
                }
            }
        } elseif (!empty($hero[0]['slide_image'])) {
            $image = wp_get_attachment_url($hero[0]['slide_image']);
        }

        if (!empty($image)) {
            $og->add_image($image);
        }
    }

    /**
     * SEO Framework integration for setting a default og:image based on the hero.
     *
     * @return string
     */
    public function seoframework_og_image()
    {
        // Use the first hero slide's image if available.
        $hero = get_field('hero_slide', get_queried_object_id());
        if (!empty($hero[0]['slide_image'])) {
            return wp_get_attachment_url($hero[0]['slide_image']);
        }
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
            $parts = pathinfo($attachment->file_loc);
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
            'wp-timber-extended/wp-timber-extended.php' => 'WP Timber Extended',
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

Plugin::get_instance();
