<?php
namespace PodifyPodcast\Core;

class Logger {
    public static function log($m) {
        error_log('[Podify Info] ' . $m);
    }

    public static function warning($m) {
        error_log('[Podify Warning] ' . $m);
    }

    public static function error($m) {
        error_log('[Podify Error] ' . $m);
    }
}
