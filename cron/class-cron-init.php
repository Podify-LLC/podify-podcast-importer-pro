<?php
namespace PodifyPodcast\Core\Cron;

class CronInit {
    const HOOK = 'podify_podcast_sync';
    const HOOK_FEED = 'podify_podcast_sync_feed';
    public static function register() {
        add_action(self::HOOK, [self::class,'run']);
        add_action(self::HOOK_FEED, [self::class,'run_feed'], 10, 2);
        add_filter('cron_schedules', [self::class,'schedules']);
    }
    public static function schedule() {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) {
            \PodifyPodcast\Core\Logger::log('WP Cron functions not available');
            return;
        }
        if(!wp_next_scheduled(self::HOOK)) wp_schedule_event(time(),'daily',self::HOOK);
    }
    public static function schedule_feed($feed_id, $interval) {
        if (!function_exists('wp_next_scheduled') || !function_exists('wp_schedule_event')) return;
        $feed_id = intval($feed_id);
        if (!$interval || $interval === 'manual') {
            self::clear_feed($feed_id);
            return;
        }
        $args = [$feed_id];
        $next = wp_next_scheduled(self::HOOK_FEED, $args);
        if (!$next) {
            wp_schedule_event(time() + 60, $interval, self::HOOK_FEED, $args);
        }
    }
    public static function clear_feed($feed_id) {
        if (!function_exists('wp_clear_scheduled_hook')) return;
        wp_clear_scheduled_hook(self::HOOK_FEED, [intval($feed_id)]);
    }
    public static function schedule_all() {
        $feeds = \PodifyPodcast\Core\Database::get_feeds();
        if (!$feeds) return;
        foreach ($feeds as $f) {
            $full = \PodifyPodcast\Core\Database::get_feed($f['id']);
            $opts = [];
            if (!empty($full['options'])) {
                $opts = json_decode($full['options'], true) ?: [];
            }
            $interval = isset($opts['interval']) ? $opts['interval'] : 'hourly';
            self::schedule_feed($f['id'], $interval);
        }
    }
    public static function schedules($schedules) {
        $schedules['every_15_minutes'] = ['interval' => 15*60, 'display' => 'Every 15 Minutes'];
        $schedules['every_30_minutes'] = ['interval' => 30*60, 'display' => 'Every 30 Minutes'];
        $schedules['twice_daily'] = ['interval' => 12*3600, 'display' => 'Twice Daily'];
        return $schedules;
    }
    public static function clear() {
        if (!function_exists('wp_clear_scheduled_hook')) {
            \PodifyPodcast\Core\Logger::log('WP Cron clear function not available');
            return;
        }
        wp_clear_scheduled_hook(self::HOOK);
    }
    public static function run() {
        self::schedule_all(); // Ensure per-feed crons are still active
        $feeds = \PodifyPodcast\Core\Database::get_feeds();
        if (!$feeds) return;
        foreach ($feeds as $f) {
            \PodifyPodcast\Core\Importer::import_feed($f['id']);
        }
    }
    public static function run_feed($feed_id, $force = false) {
        \PodifyPodcast\Core\Importer::import_feed($feed_id, $force);
    }
}
