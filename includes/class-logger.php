<?php
namespace PodifyPodcast\Core;

class Logger {
    public static function log($m) {
        // Info messages are only logged when debug mode is on
        if (defined('PODIFY_DEBUG_MODE') && PODIFY_DEBUG_MODE) {
            error_log('[Podify Info] ' . $m);
        }
    }

    public static function warning($m) {
        // Always log warnings for visibility
        error_log('[Podify Warning] ' . $m);
    }

    public static function error($m) {
        // Always log errors for visibility
        error_log('[Podify Error] ' . $m);
    }
}
