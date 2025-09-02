<?php

/**
 * Aria Labels
 *
 * @link              https://github.com/Silver0034/Aria-Labels
 * @since             1.0.0
 * @package           Aria_Labels
 *
 * @wordpress-plugin
 * Plugin Name:       Aria Labels
 * Plugin URI:        https://github.com/sunmorgn/aria-labels
 * Description:       Enhances accessibility by adding `aria-hidden` and `aria-label` attributes to Gutenberg blocks. Forked from the original by Jacob Lodes.
 * Version:           2.0.4
 * Author:            Sunny Morgan
 * Author URI:        
 * Text Domain:       aria-labels
 */

namespace Aria_Labels;

// Stop if this file is called directly.
if (!defined('WPINC')) die;

require_once plugin_dir_path(__FILE__) . 'includes/class-aria-attributes.php';
new Includes\Aria_Attributes();

if (is_admin()) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-updater.php';
    new Includes\Updater();
}
