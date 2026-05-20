<?php
require_once('../../../wp-load.php');

echo "<h1>Podify Diagnostics</h1>";

// Check if tables exist
global $wpdb;
$tables = [
    'podify_podcast_feeds',
    'podify_podcast_episodes',
    'podify_podcast_categories',
    'podify_podcast_episode_categories',
    'podify_podcast_logs'
];

echo "<h2>Database Tables</h2>";
foreach ($tables as $table) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'");
    $status = $exists ? '<span style="color:green; font-weight:bold;">✓ EXISTS</span>' : '<span style="color:red; font-weight:bold;">✗ MISSING</span>';
    echo "<p><strong>$full_table:</strong> $status</p>";
}

// Try to create missing tables
echo "<h2>Creating Missing Tables...</h2>";
require_once(PODIFY_PODCAST_PATH . 'includes/class-database.php');
PodifyPodcast\Core\Database::install();
echo "<p>Database installation completed!</p>";

// Check tables again
echo "<h2>Database Tables (After Installation)</h2>";
foreach ($tables as $table) {
    $full_table = $wpdb->prefix . $table;
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table'");
    $status = $exists ? '<span style="color:green; font-weight:bold;">✓ EXISTS</span>' : '<span style="color:red; font-weight:bold;">✗ MISSING</span>';
    echo "<p><strong>$full_table:</strong> $status</p>";
}

// Test logging
echo "<h2>Testing Logger...</h2>";
require_once(PODIFY_PODCAST_PATH . 'includes/class-logger.php');
PodifyPodcast\Core\Logger::log('Test info log from diagnostics');
PodifyPodcast\Core\Logger::warning('Test warning log from diagnostics');
PodifyPodcast\Core\Logger::error('Test error log from diagnostics');
echo "<p>Test logs written!</p>";

// Check if logs are in DB
echo "<h2>Checking Logs in Database...</h2>";
$logs_table = $wpdb->prefix . 'podify_podcast_logs';
$log_count = $wpdb->get_var("SELECT COUNT(*) FROM $logs_table");
echo "<p>Total logs in database: <strong>$log_count</strong></p>";

if ($log_count > 0) {
    $latest_logs = $wpdb->get_results("SELECT * FROM $logs_table ORDER BY id DESC LIMIT 5", ARRAY_A);
    echo "<h3>Latest 5 Logs:</h3>";
    echo "<ul>";
    foreach ($latest_logs as $log) {
        echo "<li><strong>{$log['created_at']}</strong> [{$log['level']}] - {$log['message']}</li>";
    }
    echo "</ul>";
}

echo "<h2>Diagnostics Complete!</h2>";
echo "<p>Please refresh your WordPress admin and try again.</p>";
