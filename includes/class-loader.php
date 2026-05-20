<?php
namespace PodifyPodcast\Core;

class Loader {
    public static function init() {
        self::includes();
        Database::ensure_installed();
        Admin\AdminInit::register();
        Frontend\FrontendInit::register();
        Cron\CronInit::register();
        API\RestInit::register();
    }

    private static function includes() {
        require_once PODIFY_PODCAST_PATH . 'includes/class-database.php';
        require_once PODIFY_PODCAST_PATH . 'includes/class-logger.php';
        require_once PODIFY_PODCAST_PATH . 'includes/class-settings.php';
        require_once PODIFY_PODCAST_PATH . 'includes/class-helpers.php';
        require_once PODIFY_PODCAST_PATH . 'includes/class-importer.php';
        require_once PODIFY_PODCAST_PATH . 'includes/class-capabilities.php';

        require_once PODIFY_PODCAST_PATH . 'admin/class-admin-init.php';
        require_once PODIFY_PODCAST_PATH . 'frontend/class-frontend-init.php';
        require_once PODIFY_PODCAST_PATH . 'cron/class-cron-init.php';
        require_once PODIFY_PODCAST_PATH . 'api/class-rest-init.php';
    }

    public static function activate() {
        self::includes();
        Database::install();
        Cron\CronInit::schedule();
        Cron\CronInit::schedule_all();
    }

    public static function deactivate() {
        Cron\CronInit::clear();
    }
}
