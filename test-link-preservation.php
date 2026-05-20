<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Testing Link Preservation</h1>";

// First, let's define our functions locally for testing
function test_sanitize_feed_content($content) {
    if (empty($content)) {
        return '';
    }

    $content = wp_kses_post($content);

    if (empty($content)) {
        return '';
    }

    $dom = new DOMDocument();
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

// Replace this with your actual podcast feed URL!
$feed_url = 'https://feeds.megaphone.fm/drlauraberman'; // Example - replace with your real feed

echo "<h2>Fetching Feed: $feed_url</h2>";
$resp = file_get_contents($feed_url);
if (!$resp) {
    echo "<p style='color:red'>Failed to fetch feed!</p>";
    exit;
}
echo "<p style='color:green'>Feed fetched successfully!</p>";

echo "<h2>Parsing RSS Feed</h2>";
libxml_use_internal_errors(true);
$xml = simplexml_load_string($resp, 'SimpleXMLElement', LIBXML_NOCDATA);
if (!$xml) {
    echo "<p style='color:red'>Invalid RSS XML!</p>";
    exit;
}
echo "<p style='color:green'>RSS parsed successfully!</p>";

$channel = isset($xml->channel) ? $xml->channel : $xml;
$total_items = isset($channel->item) ? count($channel->item) : 0;
echo "<p>Found $total_items items!</p>";

echo "<h2>Testing First 3 Items</h2>";
$count = 0;
foreach ($channel->item as $item) {
    $count++;
    if ($count > 3) break;
    
    echo "<hr><h3>Item " . $count . ": " . htmlspecialchars((string)$item->title) . "</h3>";
    
    $desc = '';
    $itunes = $item->children('itunes', true);
    if ($itunes && isset($itunes->summary)) {
        $desc = trim((string)$itunes->summary);
    }
    if (!$desc) {
        $content = $item->children('content', true);
        if ($content && isset($content->encoded)) {
            $desc = trim((string)$content->encoded);
        }
    }
    if (!$desc) {
        $desc = trim((string)$item->description);
    }
    
    echo "<h4>Original Description:</h4>";
    echo "<div style='border:1px solid #ccc; padding:10px; background:#f9f9f9;'>";
    echo $desc;
    echo "</div>";
    
    echo "<h4>Sanitized Description:</h4>";
    $sanitized = test_sanitize_feed_content($desc);
    echo "<div style='border:1px solid #ccc; padding:10px; background:#f0fff0;'>";
    echo $sanitized;
    echo "</div>";
    
    echo "<h4>Anchor Tags Found:</h4>";
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<div>' . $sanitized . '</div>');
    libxml_clear_errors();
    $anchors = $dom->getElementsByTagName('a');
    echo "<p>Found " . count($anchors) . " links!</p>";
    foreach ($anchors as $a) {
        $href = $a->getAttribute('href');
        $text = $a->nodeValue;
        echo "<p><strong>Link:</strong> " . htmlspecialchars($text) . " → <code>" . htmlspecialchars($href) . "</code></p>";
    }
}

echo "<hr><h2>Test Complete!</h2>";
?>