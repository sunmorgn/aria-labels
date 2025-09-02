# Aria Labels WordPress Plugin

## Table of Contents

-   [Overview](#overview)
-   [Features](#features)
-   [Usage](#usage)
-   [Installation](#installation)
-   [Updating](#updating)
-   [Developer Notes](#developer-notes)
-   [Documentation](#documentation)

## Overview

The Aria Labels plugin is designed to enhance accessibility on your WordPress website by adding `aria-hidden` and `aria-label` attributes to Gutenberg blocks. This version is maintained by Sunny Morgan and is based on the original plugin by Jacob Lodes.

## Features

-   Adds `aria-hidden` and `aria-label` attributes to Gutenberg blocks to improve accessibility.
-   Applies attributes to the correct interactive element within a block (e.g., the `<a>` tag inside a button block).
-   Automatically adds `aria-hidden="true"` to decorative images (image blocks with an empty alt attribute).
-   Provides a settings panel in the block editor, which can be moved to the "Advanced" accordion for a cleaner UI.
-   Allows developers to configure which blocks the controls appear on via a PHP filter.
-   The plugin can be updated directly from GitHub using the `Updater` class.

## Usage

### Admin Side

1. After installing and activating the plugin, controls for "Aria Hidden" and "Aria Label" will appear in the block inspector for supported blocks.

## Installation

1. Download the latest release of the plugin from the [GitHub repository](https://github.com/Silver0034/Aria-Labels/releases).
2. Log in to your WordPress admin dashboard.
3. Navigate to Plugins > Add New.
4. Click on the "Upload Plugin" button at the top of the page.
5. Click "Choose File" and select the downloaded zip file.
6. Click "Install Now" and then "Activate Plugin".

## Updating

1. The plugin checks for updates from the GitHub repository automatically.
2. If an update is available, you will see an update notification in your WordPress admin dashboard.
3. Click on the "update now" link to update the plugin.

## Developer Notes

This section contains information for developers who want to contribute to the plugin or understand its structure for maintenance purposes.

-   File Structure: The main plugin file is `aria-labels.php`. The `includes` directory contains the PHP classes for each feature. The `admin` directory contains the code for the admin interface.
-   Key Classes: The `Aria_Attributes` class in `includes/class-aria-attributes.php` handles all server-side logic, including adding user-defined ARIA attributes and automatically handling attributes for decorative images. The `Updater` class in `includes/class-updater.php` handles updates to the plugin.
-   The `Aria_Attributes` class uses the `enqueue_block_editor_assets` action to enqueue the JavaScript file in the Gutenberg editor and the `render_block` filter to modify the block's final HTML.
-   The `Updater` class uses the GitHub API to fetch the latest release of the plugin and update it if necessary. It also adds details to the plugin popup and modifies the transient before updating plugins.

## Documentation

This section contains documentation for configuring the Aria Labels plugin.

### Configuration (via PHP Filter)

You can customize the plugin's behavior by using the `aria_labels_settings` filter in your theme's `functions.php` or a custom plugin.

**Example:**
```php
add_filter( 'aria_labels_settings', function( $settings ) {
    // Move the controls out of the 'Advanced' panel and into their own panel.
    $settings['moveToAdvanced'] = false;

    // Add the 'core/list' block to the allowed list.
    $settings['allowedBlocks'][] = 'core/list';

    // Remove the 'core/image' block from the list.
    $settings['allowedBlocks'] = array_filter( 
        $settings['allowedBlocks'], 
        function( $block_name ) {
            return 'core/image' !== $block_name;
        } 
    );

    return $settings;
} );
```
