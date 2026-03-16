<?php
/**
 * Plugin Name: Podify Podcast Importer Pro
 * Plugin URI: https://github.com/Podify-LLC/podify-podcast-importer-pro
 * Description: Advanced podcast importer for WordPress.
 * Version: 1.0.39
 * Author: Podify Inc.
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: podify-podcast-importer-pro
 */

defined('ABSPATH') || exit;

define('PODIFY_PODCAST_VERSION', '1.0.39');
define('PODIFY_PODCAST_PATH', plugin_dir_path(__FILE__));
define('PODIFY_PODCAST_URL', plugin_dir_url(__FILE__));

require_once PODIFY_PODCAST_PATH . 'includes/class-loader.php';

register_activation_hook(__FILE__, ['PodifyPodcast\\Core\\Loader', 'activate']);
register_deactivation_hook(__FILE__, ['PodifyPodcast\\Core\\Loader', 'deactivate']);

add_action('init', ['PodifyPodcast\\Core\\Loader', 'init']);

if (is_admin()) {
    require_once PODIFY_PODCAST_PATH . 'includes/class-podify-github-updater.php';
    require_once PODIFY_PODCAST_PATH . 'includes/class-podify-updater-settings.php';
    new \PodifyPodcast\Core\Podify_Github_Updater(__FILE__);
    // \PodifyPodcast\Core\Podify_Updater_Settings::register();
}
