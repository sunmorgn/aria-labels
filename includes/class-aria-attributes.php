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
        // Get the block type object to check for supports, if the block has a name.
        $block_type = ! empty( $block['blockName'] ) ? \WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] ) : null;
        if ( ! $block_type ) {
            return $block_content;
        }

        $attrs                         = $block['attrs'] ?? [];
        $has_native_aria_label_support = block_has_support( $block_type, array( 'ariaLabel' ), false );

        // Use the native ariaLabel attribute if it exists, otherwise use our custom one.
        $aria_label_value  = $attrs['ariaLabel'] ?? null;
        $aria_hidden_value = ! empty( $attrs['ariaHidden'] );

        $is_decorative_image = in_array( $block['blockName'], [ 'core/image', 'core/cover' ], true ) &&
                               isset( $attrs['alt'] ) &&
                               '' === $attrs['alt'];

        // Early exit if there's nothing for our plugin to do.
        if (
            empty( $block_content ) && ! $aria_label_value && ! $aria_hidden_value && ! $is_decorative_image
        ) {
            return $block_content;
        }

		// Determine the target for our ARIA attributes.
		$tags       = new \WP_HTML_Tag_Processor( $block_content );
		$link_count = 0;
		while ( $tags->next_tag( array( 'tag_name' => 'a' ) ) ) {
			$link_count ++;
		}

		$target_tag_query = null; // Default to the block wrapper.

		if ( 1 === $link_count ) {
			// If there is exactly one link, it's the primary target.
			$target_tag_query = array( 'tag_name' => 'a' );
		} elseif ( 0 === $link_count && 'core/image' === $block['blockName'] ) {
			// If it's an image with no link, the image itself is the target.
			$target_tag_query = array( 'tag_name' => 'img' );
		}

		// Apply attributes to the determined target.
		$tags = new \WP_HTML_Tag_Processor( $block_content );
		if ( $tags->next_tag( $target_tag_query ) ) {
			if ( $aria_label_value ) {
				// For non-wrapper targets, always apply the label. For wrappers, only if no native support.
				if ( null !== $target_tag_query || ! $has_native_aria_label_support ) {
					$tags->set_attribute( 'aria-label', $aria_label_value );
				}
			}
			if ( $aria_hidden_value ) {
				$tags->set_attribute( 'aria-hidden', 'true' );
			}
		}

		// If we targeted an inner link, we must remove the `aria-label` that core might have added to the wrapper.
		if ( 1 === $link_count ) {
			$wrapper_tags = new \WP_HTML_Tag_Processor( $tags->get_updated_html() );
			if ( $wrapper_tags->next_tag() ) {
				$wrapper_tags->remove_attribute( 'aria-label' );
			}
			$block_content = $wrapper_tags->get_updated_html();
		} else {
			$block_content = $tags->get_updated_html();
		}

        // --- Handle decorative images ---
        // This runs after the main logic so it doesn't interfere.
        if ( $is_decorative_image && ! $aria_hidden_value ) {
            $img_processor = new \WP_HTML_Tag_Processor( $block_content );
            if ( $img_processor->next_tag( [ 'tag_name' => 'img' ] ) ) {
                $img_processor->set_attribute( 'aria-hidden', 'true' );
                $block_content = $img_processor->get_updated_html();
            }
        }

        return $block_content;
    }
}
