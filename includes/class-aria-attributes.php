<?php

namespace Aria_Labels\Includes;

use DOMDocument;
use WP_HTML_Tag_Processor;
// If this file is called directly, abort.
if (!defined('WPINC')) die;

/**
 * Class Aria_Attributes
 *
 * This class handles the addition of aria-hidden and aria-label attributes to Gutenberg blocks.
 *
 * @package Aria_Labels\Includes
 * @version 1.0.0
 */
class Aria_Attributes
{
    /**
     * Aria_Attributes constructor.
     *
     * Adds actions and filters used by the plugin.
     */
    public function __construct()
    {
        // Enqueue the JavaScript file in the Gutenberg editor.
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_script'));

        // Filter the content of each block.
        add_filter('render_block', array($this, 'render_block'), 10, 2);
    }

    /**
     * Enqueue the JavaScript file that adds the aria-hidden and aria-label controls to the block sidebar.
     *
     * @return void
     */
    public function enqueue_script(): void
    {
        wp_enqueue_script(
            'aria-labels-editor',
            plugins_url('../admin.js', __FILE__),
            array('wp-blocks', 'wp-dom-ready', 'wp-edit-post')
        );

        // Define default settings that can be controlled from PHP.
        $default_settings = [
            'moveToAdvanced' => true,
            'allowedBlocks'  => [
                // Interactive Elements
                'core/button',
                'core/file',
                'core/search',
                'core/social-link',

                // Media
                'core/image',
                'core/video',
                'core/cover',
                'core/gallery',

                // Layout & Grouping
                'core/group',
                'core/columns',
                'core/column',
            ],
        ];

        /**
         * Filters the settings for the Aria Labels plugin.
         *
         * @since 2.1.0
         *
         * @param array $settings {
         *     An array of settings.
         *
         *     @type bool   $moveToAdvanced Whether to move the controls to the 'Advanced' panel. Default true.
         *     @type string[] $allowedBlocks  An array of block names to apply the controls to.
         * }
         */
        $settings = apply_filters( 'aria_labels_settings', $default_settings );

        // Pass the settings to the JavaScript file.
        wp_localize_script( 'aria-labels-editor', 'ariaLabelsSettings', $settings );
    }

    /**
     * Add aria-hidden and aria-label attributes to the block's HTML if they are set in the block's attributes.
     *
     * @param string $block_content The block's HTML.
     * @param array  $block         The block's attributes and information.
     *
     * @return string The modified block's HTML.
     */
    public function render_block(string $block_content, array $block): string
    {
        $attrs      = $block['attrs'] ?? [];
        $block_name = $block['blockName'] ?? '';

        $has_custom_aria     = ! empty( $attrs['ariaHidden'] ) || ! empty( $attrs['ariaLabel'] );
        $is_decorative_image = in_array( $block_name, [ 'core/image', 'core/cover' ], true ) &&
                               isset( $attrs['alt'] ) &&
                               '' === $attrs['alt'];

        // Early exit if there is no content or nothing to do.
        if (
            empty( $block_content ) ||
            ( ! $has_custom_aria && ! $is_decorative_image )
        ) {
            return $block_content;
        }

        $tags = new WP_HTML_Tag_Processor($block_content);

        // 1. Handle custom ARIA attributes set by the user.
        if ( $has_custom_aria ) {
            $blocks_to_target_link = [ 'core/button', 'core/file', 'core/social-link' ];
            $target_tag_query      = null;

            if ( in_array( $block_name, $blocks_to_target_link, true ) ) {
                $target_tag_query = [ 'tag_name' => 'a' ];
            }

            // If a specific tag is targeted (like 'a'), find it. Otherwise, find the first tag (the wrapper).
            if ( $tags->next_tag( $target_tag_query ) ) {
                if ( ! empty( $attrs['ariaHidden'] ) ) {
                    $tags->set_attribute( 'aria-hidden', 'true' );
                }
                if ( ! empty( $attrs['ariaLabel'] ) ) {
                    $tags->set_attribute( 'aria-label', $attrs['ariaLabel'] );
                }
            }
        }

        // 2. Handle automatic aria-hidden for decorative images, but only if not set by the user.
        if ( $is_decorative_image && empty( $attrs['ariaHidden'] ) ) {
            // Re-initialize the processor on the (maybe) modified content to find the <img> tag.
            $img_processor = new WP_HTML_Tag_Processor( $tags->get_updated_html() );
            if ( $img_processor->next_tag( [ 'tag_name' => 'img' ] ) ) {
                $img_processor->set_attribute( 'aria-hidden', 'true' );
                return $img_processor->get_updated_html();
            }
        }

        return $tags->get_updated_html();
    }
}
