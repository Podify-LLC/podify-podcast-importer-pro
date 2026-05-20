<?php
namespace PodifyPodcast\Core;

class Helpers {
    public static function sanitize_feed_content($content) {
        if (empty($content)) {
            return '';
        }

        if (is_string($content)) {
            $charset = function_exists('get_bloginfo') ? (string)get_bloginfo('charset') : 'UTF-8';
            if ($charset === '') $charset = 'UTF-8';
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, $charset);
        }

        $content = wp_kses_post($content);

        if (empty($content)) {
            return '';
        }

        if (!class_exists('\DOMDocument')) {
            return $content;
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $anchors = $dom->getElementsByTagName('a');
        foreach ($anchors as $anchor) {
            if ($anchor->hasAttribute('target') && $anchor->getAttribute('target') === '_blank') {
                $existing_rel = $anchor->hasAttribute('rel') ? trim($anchor->getAttribute('rel')) : '';
                $rel_parts = array_filter(explode(' ', $existing_rel));
                if (!in_array('noopener', $rel_parts)) {
                    $rel_parts[] = 'noopener';
                }
                if (!in_array('noreferrer', $rel_parts)) {
                    $rel_parts[] = 'noreferrer';
                }
                $anchor->setAttribute('rel', implode(' ', $rel_parts));
            }
        }

        $content = '';
        $body = $dom->getElementsByTagName('div')->item(0);
        if ($body) {
            foreach ($body->childNodes as $child) {
                $content .= $dom->saveHTML($child);
            }
        }

        return $content;
    }

    public static function count_links_in_content($content) {
        if (empty($content)) {
            return 0;
        }

        if (is_string($content)) {
            $charset = function_exists('get_bloginfo') ? (string)get_bloginfo('charset') : 'UTF-8';
            if ($charset === '') $charset = 'UTF-8';
            $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, $charset);
        }

        if (!class_exists('\DOMDocument')) {
            if (!is_string($content) || $content === '') return 0;
            return preg_match_all('/<a\b[^>]*\bhref\s*=\s*(["\']).*?\1/si', $content, $m);
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return $dom->getElementsByTagName('a')->length;
    }
}
