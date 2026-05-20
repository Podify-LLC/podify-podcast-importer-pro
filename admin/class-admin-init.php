<?php
namespace PodifyPodcast\Core\Admin;

class AdminInit {
    const SLUG = 'podify-podcast-importer';
    public static function register() {
        add_action('admin_menu', [self::class,'menu']);
        add_action('admin_enqueue_scripts', [self::class,'enqueue']);
    }
    public static function menu() {
        // Position 11 places it right after 'Media' (10) and before 'Links' (15) or 'Pages' (20)
        // This moves it away from the bottom and likely near content-related items like Image Carousel
        add_menu_page('Podcast Importer','Podcast Importer','manage_options',self::SLUG,[self::class,'page'], 'dashicons-microphone', 11);
        add_submenu_page(self::SLUG,'Podcast Importer','Dashboard','manage_options',self::SLUG,[self::class,'page']);
    }
    public static function enqueue($hook) {
        if (isset($_GET['page']) && $_GET['page'] === self::SLUG) {
            wp_enqueue_style('podify_admin', \PODIFY_PODCAST_URL . 'assets/css/admin.css', [], \PODIFY_PODCAST_VERSION);
            wp_enqueue_style('wp-color-picker');
            wp_enqueue_script('wp-color-picker');
            wp_enqueue_script('jquery');
            wp_enqueue_script('wp-api');
        }
    }
    public static function page() {
        if (!current_user_can('manage_options')) return;
        $notice = '';
        $intervals = [
            'manual' => 'Manual',
            'every_15_minutes' => 'Every 15 Minutes',
            'every_30_minutes' => 'Every 30 Minutes',
            'hourly' => 'Hourly',
            'twice_daily' => 'Twice Daily',
            'daily' => 'Daily'
        ];
        $post_types = get_post_types(['public' => true], 'objects');
        $post_statuses = ['publish'=>'Publish','draft'=>'Draft','private'=>'Private','pending'=>'Pending'];
        $authors = get_users(['capability' => ['edit_posts']]);
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'add_feed') {
            check_admin_referer('podify_add_feed');
            $url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
            if ($url) {
                $options = [
                    'post_type' => sanitize_text_field($_POST['post_type'] ?? 'post'),
                    'post_status' => sanitize_text_field($_POST['post_status'] ?? 'publish'),
                    'post_author' => intval($_POST['post_author'] ?? 0),
                    'interval' => sanitize_text_field($_POST['interval'] ?? 'hourly'),
                    'transcript_tag' => sanitize_text_field($_POST['transcript_tag'] ?? ''),
                    'audio_field' => sanitize_text_field($_POST['audio_field'] ?? ''),
                    'import_categories' => !empty($_POST['import_categories']) ? 1 : 0,
                    'import_tags' => !empty($_POST['import_tags']) ? 1 : 0,
                    'featured_image' => esc_url_raw($_POST['featured_image'] ?? ''),
                    'append_episode_number' => !empty($_POST['append_episode_number']) ? 1 : 0,
                    'read_more_text' => sanitize_text_field($_POST['read_more_text'] ?? ''),
                    'load_more_text' => sanitize_text_field($_POST['load_more_text'] ?? ''),
                ];
                $new_id = \PodifyPodcast\Core\Database::add_feed($url, $options);
                if ($new_id) {
                    \PodifyPodcast\Core\Cron\CronInit::schedule_feed($new_id, $options['interval']);
                    wp_safe_redirect( admin_url('admin.php?page='.self::SLUG.'&tab=scheduled&podify_msg=added') );
                    exit;
                } else {
                    $err = \PodifyPodcast\Core\Database::last_error();
                    $notice = $err ? ('Error adding feed: '.$err) : 'Unable to add feed';
                }
                $notice = 'Feed added';
            }
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'remove_feed') {
            check_admin_referer('podify_remove_feed');
            $id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
            if ($id) {
                \PodifyPodcast\Core\Database::remove_feed($id);
                $notice = 'Feed removed';
                wp_safe_redirect( admin_url('admin.php?page='.self::SLUG.'&tab=scheduled&podify_msg=removed') );
                exit;
            }
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'update_feed_options') {
            check_admin_referer('podify_update_feed');
            $id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
            if ($id) {
                $options = [
                    'interval' => sanitize_text_field($_POST['interval'] ?? 'hourly'),
                    'featured_image' => esc_url_raw($_POST['featured_image'] ?? ''),
                    'append_episode_number' => !empty($_POST['append_episode_number']) ? 1 : 0,
                    'import_categories' => !empty($_POST['import_categories']) ? 1 : 0,
                    'import_tags' => !empty($_POST['import_tags']) ? 1 : 0,
                    'show_category_badge' => !empty($_POST['show_category_badge']) ? 1 : 0,
                    'pill_color_category_id' => isset($_POST['pill_color_category_id']) ? intval($_POST['pill_color_category_id']) : 0,
                    'read_more_text' => sanitize_text_field($_POST['read_more_text'] ?? ''),
                    'load_more_text' => sanitize_text_field($_POST['load_more_text'] ?? ''),
                    'default_layout' => sanitize_key($_POST['default_layout'] ?? ''),
                    'default_card_bg_color' => sanitize_hex_color($_POST['default_card_bg_color'] ?? ''),
                    'default_card_border_color' => sanitize_hex_color($_POST['default_card_border_color'] ?? ''),
                    'default_title_color' => sanitize_hex_color($_POST['default_title_color'] ?? ''),
                    'default_desc_color' => sanitize_hex_color($_POST['default_desc_color'] ?? ''),
                    'default_meta_color' => sanitize_hex_color($_POST['default_meta_color'] ?? ''),
                    'default_button_bg_color' => sanitize_hex_color($_POST['default_button_bg_color'] ?? ''),
                    'default_button_text_color' => sanitize_hex_color($_POST['default_button_text_color'] ?? ''),
                    'default_button_bg_hover_color' => sanitize_hex_color($_POST['default_button_bg_hover_color'] ?? ''),
                    'default_button_text_hover_color' => sanitize_hex_color($_POST['default_button_text_hover_color'] ?? ''),
                    'default_pill_bg_color' => sanitize_hex_color($_POST['default_pill_bg_color'] ?? ''),
                    'default_pill_text_color' => sanitize_hex_color($_POST['default_pill_text_color'] ?? ''),
                    'default_pill_bg_hover_color' => sanitize_hex_color($_POST['default_pill_bg_hover_color'] ?? ''),
                    'default_pill_text_hover_color' => sanitize_hex_color($_POST['default_pill_text_hover_color'] ?? ''),
                ];
                $existing_opts = [];
                $existing_feed = \PodifyPodcast\Core\Database::get_feed($id);
                if ($existing_feed && !empty($existing_feed['options'])) {
                    $decoded = json_decode($existing_feed['options'], true);
                    if (is_array($decoded)) $existing_opts = $decoded;
                }
                $merged_opts = array_merge($existing_opts, $options);
                \PodifyPodcast\Core\Database::update_feed_options($id, $merged_opts);
                \PodifyPodcast\Core\Cron\CronInit::clear_feed($id);
                \PodifyPodcast\Core\Cron\CronInit::schedule_feed($id, $options['interval']);
                $notice = 'Feed options saved';
            }
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'save_settings') {
            check_admin_referer('podify_save_settings');
            $data = [
                'sticky_player_enabled' => !empty($_POST['sticky_player_enabled']) ? 1 : 0,
                'sticky_player_position' => sanitize_text_field($_POST['sticky_player_position'] ?? 'bottom'),
                'custom_css' => isset($_POST['custom_css']) ? sanitize_textarea_field($_POST['custom_css']) : '',
            ];
            \PodifyPodcast\Core\Settings::update($data);
            $notice = 'Settings saved';
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'add_category') {
            check_admin_referer('podify_add_category');
            $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
            $name = isset($_POST['category_name']) ? sanitize_text_field($_POST['category_name']) : '';
            if ($feed_id && $name !== '') {
                $cid = \PodifyPodcast\Core\Database::add_category($feed_id, $name);
                if ($cid) {
                    wp_safe_redirect( admin_url('admin.php?page='.self::SLUG.'&tab=categories&podify_msg=cat_added') );
                    exit;
                } else {
                    $notice = 'Unable to add category';
                }
            }
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'clear_cache') {
            check_admin_referer('podify_clear_cache');
            delete_site_transient('update_plugins');
            delete_transient('podify_updater_check');
            $notice = 'Cache cleared';
        }
        if (!empty($_POST['podify_action']) && $_POST['podify_action'] === 'refresh_updater') {
            check_admin_referer('podify_refresh_updater');
            wp_clean_plugins_cache();
            wp_update_plugins();
            // Removed global notice to show in widget instead
        }
        if (isset($_GET['podify_action']) && $_GET['podify_action'] === 'refresh_updater_ajax') {
            wp_clean_plugins_cache();
            wp_update_plugins();
            
            // Re-calculate status for the response
            $local_ver = \PODIFY_PODCAST_VERSION;
            $updater_status = get_option('podify_updater_status', []);
            $st = 'unknown';
            $st_color = '#f0b849';
            
            if (!empty($updater_status) && is_array($updater_status)) {
                $st = $updater_status['status'] ?? 'unknown';
                $remote_ver = $updater_status['version'] ?? '';
                if ($st === 'success' && $remote_ver) {
                    if (version_compare($local_ver, $remote_ver, '>')) {
                        $st = 'Dev'; $st_color = '#10b981';
                    } elseif (version_compare($local_ver, $remote_ver, '<')) {
                        $st = 'Update Available'; $st_color = '#f59e0b';
                    } else {
                        $st = 'Up to Date'; $st_color = '#10b981';
                    }
                } else {
                    $st_color = ($st === 'success') ? '#10b981' : (($st === 'error') ? '#dc3232' : '#f0b849');
                }
            }
            
            wp_send_json_success([
                'status_label' => $st,
                'status_color' => $st_color
            ]);
        }
        $feeds = \PodifyPodcast\Core\Database::get_feeds();
        $episodes = \PodifyPodcast\Core\Database::get_episodes(null, 10, 0);
        $sync_url = esc_url_raw( rest_url('podify/v1/sync') );
        $resync_url = esc_url_raw( rest_url('podify/v1/resync') );
        $progress_url = esc_url_raw( rest_url('podify/v1/progress') );
        $nonce = wp_create_nonce('wp_rest');
        echo '<div class="wrap podify-admin-wrap-main">';
        
        // Tab specific headers
        if ($tab === 'episodes') {
            echo '<div class="podify-page-header">';
            echo '<h1><span class="dashicons dashicons-playlist-audio"></span> Episodes Management</h1>';
            echo '<p>View and manage all imported podcast episodes across your feeds.</p>';
            echo '</div>';
        }
        
        $msg = isset($_GET['podify_msg']) ? sanitize_key($_GET['podify_msg']) : '';
        if ($msg === 'added') $notice = 'Feed added';
        if ($msg === 'removed') $notice = 'Feed removed';
        if ($msg === 'cat_added') $notice = 'Category added';
        if ($notice) echo '<div class="updated" style="border-left-color:#46b450;"><p style="color:#1d2327; font-weight:600;">'.esc_html($notice).'</p></div>';
        
        $base = admin_url('admin.php?page='.self::SLUG);
        
        echo '<div class="podify-admin-layout">';
        
        // Sidebar
        echo '<div class="podify-sidebar">';
            echo '<div class="podify-sidebar-header">';
                echo '<div class="podify-sidebar-logo-container">';
                    echo '<div class="podify-sidebar-logo"><img src="' . \PODIFY_PODCAST_URL . 'assets/images/logo_cropped.png" alt="Podify"></div>';
                    echo '<div class="podify-version-container">';
                        echo '<div class="podify-plugin-version">v'.\PODIFY_PODCAST_VERSION.'</div>';
                        echo '<span class="podify-pro-badge">PRO</span>';
                    echo '</div>';
                echo '</div>';
            echo '</div>';
            echo '<div class="podify-nav-links">';
                $tabs = [
                    'dashboard' => ['icon' => 'dashicons-dashboard', 'label' => 'Dashboard'],
                    'import' => ['icon' => 'dashicons-plus-alt2', 'label' => 'Import Feed'],
                    'scheduled' => ['icon' => 'dashicons-calendar-alt', 'label' => 'Podcast Feed'],
                    'episodes' => ['icon' => 'dashicons-playlist-audio', 'label' => 'Episodes'],
                    'categories' => ['icon' => 'dashicons-category', 'label' => 'Categories'],
                    'settings' => ['icon' => 'dashicons-admin-settings', 'label' => 'Settings'],
                    'changelog' => ['icon' => 'dashicons-list-view', 'label' => 'Changelog'],
                ];
                foreach ($tabs as $k => $t) {
                    $active = $tab === $k ? ' active' : '';
                    echo '<a href="'.$base.'&tab='.$k.'" class="podify-nav-item'.$active.'">';
                    echo '<span class="dashicons '.$t['icon'].'"></span> <span class="podify-nav-text">'.$t['label'].'</span>';
                    echo '</a>';
                }
            echo '</div>';
        echo '</div>'; // End sidebar

        // Main Content Area
        echo '<div class="podify-content">';
        if ($tab === 'dashboard') {
              echo '<div class="podify-dashboard-hero">';
              echo '<div class="podify-hero-content">';
              echo '<h2>Welcome to Podify Podcast Importer Pro <div class="podify-hero-badges"><span class="podify-version-badge">v'.\PODIFY_PODCAST_VERSION.'</span> <span class="podify-pro-badge hero-pro">PRO</span></div></h2>';
              echo '<p>The ultimate solution for importing and managing podcasts in WordPress. Automated imports, modern players, and seamless integration.</p>';
              echo '<a href="'.$base.'&tab=import" class="podify-btn-modern podify-btn-primary podify-btn-hero"><span class="dashicons dashicons-plus-alt2" style="margin-right:8px; font-size:20px; width:20px; height:20px; line-height:20px;"></span> Import a Podcast</a>';
              echo '</div>';
              echo '</div>';
            
            echo '<div class="podify-dashboard-grid">';
            
            echo '<div class="podify-dashboard-card podify-updater-card">';
            echo '<h3><span class="dashicons dashicons-update"></span> Updater Status</h3>';
            $updater_status = get_option('podify_updater_status', []);
            
            $st = 'unknown';
            $st_color = '#f0b849';
            $context_msg = 'No update activity recorded yet.';
            $local_ver = \PODIFY_PODCAST_VERSION;

            if (!empty($updater_status) && is_array($updater_status)) {
                $st = $updater_status['status'] ?? 'unknown';
                $remote_ver = $updater_status['version'] ?? '';
                
                if ($st === 'success' && $remote_ver) {
                    if (version_compare($local_ver, $remote_ver, '>')) {
                        $st = 'Dev';
                        $st_color = '#10b981';
                    } elseif (version_compare($local_ver, $remote_ver, '<')) {
                        $st = 'Update Available';
                        $st_color = '#f59e0b';
                    } else {
                        $st = 'Up to Date';
                        $st_color = '#10b981'; // Match green in reference
                    }
                } else {
                     $st_color = ($st === 'success') ? '#10b981' : (($st === 'error') ? '#dc3232' : '#f0b849');
                }
            }

            echo '<div class="podify-updater-body">';
                echo '<div class="podify-status-badge-wrap"><span class="podify-status-badge" id="podify-updater-status-label" style="background-color:'.esc_attr($st_color).'15; color:'.esc_attr($st_color).'; border:1px solid '.esc_attr($st_color).'30;">'.esc_html(strtoupper($st)).'</span></div>';
                echo '<p class="podify-running-ver">Currently running v' . esc_html($local_ver) . '</p>';
                
                // Success indicator relocated
                echo '<div id="podify-updater-success-msg" class="podify-check-success" style="display:none; margin-bottom:15px;"><span class="dashicons dashicons-yes"></span> Updated!</div>';

                echo '<div class="podify-updater-actions">';
                    echo '<button type="button" id="podify-check-updates-btn" class="podify-btn-modern podify-btn-outline" style="width:100%">';
                        echo '<span class="dashicons dashicons-update"></span>';
                        echo '<span>Check Now</span>';
                    echo '</button>';
                echo '</div>';
            echo '</div>';

            echo '<script>
            (function(){
                const btn = document.getElementById("podify-check-updates-btn");
                const successMsg = document.getElementById("podify-updater-success-msg");
                const statusLabel = document.getElementById("podify-updater-status-label");
                if(!btn) return;

                btn.addEventListener("click", function(){
                    if(btn.classList.contains("is-loading")) return;
                    
                    btn.classList.add("is-loading");
                    successMsg.style.display = "none";
                    
                    // Call the REST API to refresh updater
                    fetch(window.location.pathname + "?page=podify-podcast-importer&podify_action=refresh_updater_ajax", {
                        method: "POST",
                        headers: {
                            "X-WP-Nonce": "' . wp_create_nonce('wp_rest') . '",
                            "Content-Type": "application/json"
                        }
                    }).then(r => r.json()).then(data => {
                        btn.classList.remove("is-loading");
                        if(data.success) {
                            successMsg.style.display = "inline-flex";
                            if(data.status_label) {
                                statusLabel.textContent = data.status_label.toUpperCase();
                                statusLabel.style.color = data.status_color;
                                statusLabel.style.backgroundColor = data.status_color + "15";
                                statusLabel.style.borderColor = data.status_color + "30";
                            }
                            setTimeout(() => { successMsg.style.display = "none"; }, 5000);
                        }
                    }).catch(() => {
                        btn.classList.remove("is-loading");
                    });
                });
            })();
            </script>';

            echo '</div>'; // End card
            

            
            echo '<div class="podify-dashboard-card">';
            echo '<h3><span class="dashicons dashicons-yes-alt"></span> Key Features</h3>';
            echo '<ul class="podify-feature-list">';
            echo '<li>Automated Background Imports</li>';
            echo '<li>Modern Sticky Audio Player</li>';
            echo '<li>SEO-Friendly Episode Pages</li>';
            echo '<li>Advanced Category Mapping</li>';
            echo '<li>Bulk Episode Management</li>';
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="podify-dashboard-card">';
            echo '<h3><span class="dashicons dashicons-admin-settings"></span> Quick Actions</h3>';
            echo '<p style="margin-bottom:15px; color:#64748b;">Get started quickly with these common tasks:</p>';
            echo '<div class="podify-action-grid">';
            echo '<a href="'.$base.'&tab=import" class="podify-action-card"><span class="dashicons dashicons-plus-alt2"></span> <span>Import Feed</span></a>';
            echo '<a href="'.$base.'&tab=scheduled" class="podify-action-card"><span class="dashicons dashicons-calendar-alt"></span> <span>Podcast Feed</span></a>';
            echo '<a href="'.$base.'&tab=episodes" class="podify-action-card"><span class="dashicons dashicons-playlist-audio"></span> <span>Episodes</span></a>';
            echo '<a href="'.$base.'&tab=settings" class="podify-action-card"><span class="dashicons dashicons-admin-settings"></span> <span>Settings</span></a>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="podify-dashboard-card podify-latest-episodes-card">';
            echo '<h3><span class="dashicons dashicons-playlist-audio"></span> Latest Episodes (By Feed)</h3>';
            if (!$feeds) {
                echo '<p class="podify-latest-episodes-empty">No feeds found yet.</p>';
            } else {
                $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
                $now = new \DateTimeImmutable('now', $tz);
                $week_start = $now->modify('monday this week')->setTime(0, 0, 0);
                $weekly_items = [];

                foreach ($feeds as $df) {
                    $fid = intval($df['id'] ?? 0);
                    if (!$fid) continue;
                    $feed_url_raw = (string)($df['feed_url'] ?? '');
                    $host = $feed_url_raw ? parse_url($feed_url_raw, PHP_URL_HOST) : '';
                    $feed_label = $host ? ($host . ' (#' . $fid . ')') : ('Feed #' . $fid);

                    $latest = \PodifyPodcast\Core\Database::get_episodes($fid, 1, 0);
                    $ep = ($latest && is_array($latest)) ? ($latest[0] ?? null) : null;

                    $ep_title = is_array($ep) ? (string)($ep['title'] ?? '') : '';
                    $ep_post_id = is_array($ep) ? intval($ep['post_id'] ?? 0) : 0;
                    $ep_image = is_array($ep) ? (string)($ep['image_url'] ?? '') : '';
                    $ep_published = is_array($ep) ? (string)($ep['published'] ?? '') : '';
                    if (!$ep_title || !$ep_published) {
                        continue;
                    }

                    $ep_dt = null;
                    try {
                        $ep_dt = new \DateTimeImmutable($ep_published);
                    } catch (\Exception $e) {
                        $ep_dt = null;
                    }
                    if (!$ep_dt) {
                        continue;
                    }
                    $ep_dt = $ep_dt->setTimezone($tz);
                    if ($ep_dt < $week_start) {
                        continue;
                    }

                    $weekly_items[] = [
                        'feed_label' => $feed_label,
                        'ep_title' => $ep_title,
                        'ep_post_id' => $ep_post_id,
                        'ep_image' => $ep_image,
                        'ep_date' => function_exists('wp_date')
                            ? wp_date(get_option('date_format'), $ep_dt->getTimestamp(), $tz)
                            : date_i18n(get_option('date_format'), $ep_dt->getTimestamp()),
                    ];
                }

                if (!$weekly_items) {
                    echo '<p class="podify-latest-episodes-empty">No new episodes this week.</p>';
                } else {
                    echo '<div class="podify-latest-episodes-list">';
                    foreach ($weekly_items as $it) {
                        $feed_label = (string)$it['feed_label'];
                        $ep_title = (string)$it['ep_title'];
                        $ep_post_id = intval($it['ep_post_id']);
                        $ep_image = (string)$it['ep_image'];
                        $ep_date = (string)$it['ep_date'];

                        echo '<div class="podify-latest-feed-item">';
                        echo '<div class="podify-latest-feed-row">';
                        echo '<div class="podify-latest-episode-media">';
                        if ($ep_image && wp_http_validate_url($ep_image)) {
                            echo '<img src="'.esc_url($ep_image).'" alt="" loading="lazy" decoding="async" />';
                        } else {
                            echo '<span class="dashicons dashicons-format-audio"></span>';
                        }
                        echo '</div>';
                        echo '<div class="podify-latest-episode-body">';
                        echo '<div class="podify-latest-feed-head">';
                        echo '<div class="podify-latest-feed-name">'.esc_html($feed_label).'</div>';
                        if ($ep_date) {
                            echo '<div class="podify-latest-feed-date">'.esc_html($ep_date).'</div>';
                        }
                        echo '</div>';

                        $edit_link = $ep_post_id ? get_edit_post_link($ep_post_id, '') : '';
                        if ($edit_link) {
                            echo '<a class="podify-latest-episode-title" href="'.esc_url($edit_link).'">'.esc_html($ep_title).'</a>';
                        } else {
                            echo '<div class="podify-latest-episode-title">'.esc_html($ep_title).'</div>';
                        }
                        echo '</div>';
                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                }
            }
            echo '</div>';

            echo '<div class="podify-dashboard-card podify-plugin-details-card">';
            echo '<h3><span class="dashicons dashicons-info"></span> Plugin Details</h3>';
            echo '<p><strong>Version:</strong> '.\PODIFY_PODCAST_VERSION.'</p>';
            echo '<p><strong>Author:</strong> Podify LLC</p>';
            echo '<p><strong>License:</strong> Pro</p>';
            echo '<p>Need help? Check the <a href="'.$base.'&tab=changelog">Changelog</a> or contact support.</p>';
            echo '</div>';
            
            echo '</div>'; // End grid
            
        } elseif ($tab === 'import') {
            echo '<div class="podify-card"><h3><span class="dashicons dashicons-plus-alt2" style="margin-right:8px;"></span> Add New Podcast Import</h3>';
            echo '<form method="post" class="podify-feed-options-form"><input type="hidden" name="podify_action" value="add_feed">';
            wp_nonce_field('podify_add_feed');
            echo '<div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:40px;">';
            
            // Column 1: Source
            echo '<div>';
            echo '<h4 class="podify-admin-section-title">Source & Publishing</h4>';
            echo '<div class="podify-field"><label>Podcast Feed URL</label><input type="url" name="feed_url" placeholder="https://example.com/feed/podcast" required></div>';
            echo '<div class="podify-field"><label>Post Type</label><select name="post_type">';
            foreach ($post_types as $pt) { echo '<option value="'.esc_attr($pt->name).'">'.esc_html($pt->labels->singular_name).'</option>'; }
            echo '</select></div>';
            echo '<div class="podify-field"><label>Post Status</label><select name="post_status">';
            foreach ($post_statuses as $k=>$v) { echo '<option value="'.esc_attr($k).'">'.esc_html($v).'</option>'; }
            echo '</select></div>';
            echo '</div>';

            // Column 2: Content
            echo '<div>';
            echo '<h4 class="podify-admin-section-title">Content & Metadata</h4>';
            echo '<div class="podify-field"><label>Import Interval</label><select name="interval">';
            foreach ($intervals as $key=>$label) { echo '<option value="'.esc_attr($key).'">'.esc_html($label).'</option>'; }
            echo '</select></div>';
            echo '<div class="podify-field" style="margin-top:15px;"><label><input type="checkbox" name="import_categories" value="1" checked> Import categories from feed</label></div>';
            echo '<div class="podify-field"><label><input type="checkbox" name="import_tags" value="1" checked> Import tags from feed</label></div>';
            echo '<div class="podify-field"><label><input type="checkbox" name="append_episode_number" value="1"> Append episode number to title</label></div>';
            echo '</div>';

            // Column 3: Display
            echo '<div>';
            echo '<h4 class="podify-admin-section-title">Customisation</h4>';
            echo '<div class="podify-field"><label>Read More Text</label><input type="text" name="read_more_text" placeholder="Default: Read more"></div>';
            echo '<div class="podify-field"><label>Load More Text</label><input type="text" name="load_more_text" placeholder="Default: Load more"></div>';
            echo '</div>';

            echo '</div>'; // End grid
            echo '<div class="podify-actions"><button type="submit" class="podify-btn-modern podify-btn-primary"><span class="dashicons dashicons-plus-alt2"></span> Add Podcast Import</button></div></form>';
            echo '</div>';
        } elseif ($tab === 'scheduled') {
            echo '<div class="podify-table-card">';
            echo '<table class="podify-modern-table podify-scheduled-table">';
            echo '<colgroup>';
            echo '<col class="podify-col-id">';
            echo '<col class="podify-col-source">';
            echo '<col class="podify-col-next">';
            echo '<col class="podify-col-actions">';
            echo '</colgroup>';
            echo '<thead><tr><th class="podify-th-id">ID</th><th class="podify-th-source">Feed Source</th><th class="podify-th-next">Next Sync</th><th class="podify-th-actions">Actions</th></tr></thead>';
            echo '<tbody>';
            if ($feeds) {
                foreach ($feeds as $f) {
                    $id = intval($f['id']);
                    $url = esc_html($f['feed_url']);
                    $full = \PodifyPodcast\Core\Database::get_feed($id);
                    $opts = [];
                    if (!empty($full['options'])) { $opts = json_decode($full['options'], true) ?: []; }
                    $interval_cur = isset($opts['interval']) ? $opts['interval'] : 'hourly';
                    $read_more = $opts['read_more_text'] ?? '';
                    $load_more = $opts['load_more_text'] ?? '';
                    $featured_image = $opts['featured_image'] ?? '';
                    $append_episode_number_checked = !empty($opts['append_episode_number']);
                    $import_categories_checked = !empty($opts['import_categories']);
                    $import_tags_checked = !empty($opts['import_tags']);
                    
                    // Next Sync Logic
                    $next_sync = wp_next_scheduled(\PodifyPodcast\Core\Cron\CronInit::HOOK_FEED, [$id]);
                    $next_sync_html = '<span class="podify-badge podify-badge-outline">Manual Only</span>';
                    
                    if ($interval_cur !== 'manual') {
                        if (!$next_sync) {
                            \PodifyPodcast\Core\Cron\CronInit::schedule_feed($id, $interval_cur);
                            $next_sync = wp_next_scheduled(\PodifyPodcast\Core\Cron\CronInit::HOOK_FEED, [$id]);
                            if (!$next_sync) $next_sync = time() + 60;
                        }
                        $diff = (int)($next_sync - time());
                        if ($diff > 0) {
                            $hours = (int)floor($diff / 3600);
                            $mins = (int)floor(($diff % 3600) / 60);
                            $secs = (int)($diff % 60);
                            if ($hours > 0) {
                                $label = $hours . 'h ' . $mins . 'm';
                            } elseif ($mins > 0) {
                                $label = $mins . 'm ' . $secs . 's';
                            } else {
                                $label = $secs . 's';
                            }
                            $next_sync_html = '<span class="podify-badge podify-badge-primary podify-next-sync-timer" data-time="'.$next_sync.'" style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; min-width:60px; text-align:center;">' . esc_html($label) . '</span>';
                        } else {
                            $next_sync_html = '<span class="podify-badge podify-badge-success" style="min-width:60px; text-align:center;">Syncing...</span>';
                        }
                    }
                    
                    echo '<tr>';
                    echo '<td><span class="podify-badge">#'.$id.'</span></td>';
                    echo '<td><div class="podify-url-text" title="'.$url.'">'.$url.'</div></td>';
                    echo '<td>'.$next_sync_html.'</td>';
                    
                    echo '<td style="text-align:right;">';
                    echo '<div class="podify-actions-cell podify-actions-inline">';
                    echo '<a class="podify-btn-modern podify-btn-outline" aria-label="Episodes" href="'.$base.'&tab=episodes&feed_id='.$id.'"><span class="dashicons dashicons-playlist-audio"></span><span class="podify-btn-label">Episodes</span></a> ';
                    echo '<div class="podify-action-menu" data-id="'.$id.'">';
                    echo '<button type="button" class="podify-btn-modern podify-btn-outline podify-action-menu-toggle" data-id="'.$id.'" aria-haspopup="true" aria-expanded="false" title="Customize, Sync, Force, Backfill">';
                    echo '<span class="dashicons dashicons-ellipsis"></span><span class="podify-btn-label">Actions</span>';
                    echo '</button>';
                    echo '<div class="podify-action-menu-pop" data-id="'.$id.'" role="menu" aria-label="Feed actions" style="display:none;">';
                    echo '<button type="button" class="podify-btn-modern podify-btn-outline podify-toggle-customization" role="menuitem" data-id="'.$id.'"><span class="dashicons dashicons-admin-appearance"></span> Customize</button>';
                    echo '<button type="button" class="podify-btn-modern podify-btn-primary podify-sync" role="menuitem" data-id="'.$id.'"><span class="dashicons dashicons-update"></span> Sync</button>';
                    echo '<button type="button" class="podify-btn-modern podify-btn-outline podify-resync" role="menuitem" data-id="'.$id.'"><span class="dashicons dashicons-image-rotate"></span> Force</button>';
                    echo '<button type="button" class="podify-btn-modern podify-btn-outline podify-backfill" role="menuitem" data-id="'.$id.'"><span class="dashicons dashicons-admin-links"></span> Backfill</button>';
                    echo '</div>';
                    echo '</div> ';
                    echo '<form method="post" style="display:inline; margin:0"><input type="hidden" name="podify_action" value="remove_feed"><input type="hidden" name="feed_id" value="'.$id.'">';
                    wp_nonce_field('podify_remove_feed');
                    echo '<button class="podify-btn-modern podify-btn-danger-outline podify-feed-delete" title="Delete Feed"><span class="dashicons dashicons-trash"></span></button></form>';
                    echo '</div>';
                    echo '<div class="podify-progress-wrap" data-id="'.$id.'" style="width:100%; margin-top:15px; display:none; position:relative; height:24px; border-radius:6px; background:#e2e8f0; overflow:hidden; border:1px solid #cbd5e1;">';
                    echo '<div class="podify-progress-bar" style="height:100%; background:#3b82f6; width:0; transition:width 0.3s ease;"></div>';
                    echo '<div class="podify-progress-text" style="position:absolute; top:0; left:0; width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:#1e293b; pointer-events:none; z-index:2;">Starting...</div>';
                    echo '</div>';
                    echo '</td></tr>';

                    // Customization Row
                    echo '<tr id="podify-customization-'.$id.'" class="podify-customization-row" style="display:none; background:#f8fafc;">';
                    echo '<td colspan="4" style="padding:25px; border-bottom:2px solid #e2e8f0;">';
                    echo '<form method="post" class="podify-feed-options-form">';
                    echo '<input type="hidden" name="podify_action" value="update_feed_options"><input type="hidden" name="feed_id" value="'.$id.'">';
                    wp_nonce_field('podify_update_feed');
                    
                    echo '<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:25px;">';
                    
                    // Column 1: General Settings
                    echo '<div>';
                    echo '<h4 class="podify-admin-section-title">General Sync Settings</h4>';
                    echo '<div class="podify-field"><label>Import Interval</label>';
                    echo '<select name="interval">';
                    foreach ($intervals as $key=>$label) {
                        $sel = $interval_cur===$key ? ' selected' : '';
                        echo '<option value="'.esc_attr($key).'"'.$sel.'>'.esc_html($label).'</option>';
                    }
                    echo '</select></div>';
                    echo '<div class="podify-field"><label>Featured Image URL (Fallback)</label>';
                    echo '<input type="url" name="featured_image" value="'.esc_attr($featured_image).'" placeholder="Optional fallback image URL"></div>';
                    echo '<div class="podify-field" style="margin-top:10px">';
                    echo '<input type="hidden" name="import_categories" value="0">';
                    echo '<label style="display:flex; align-items:center; gap:8px">';
                    echo '<input type="checkbox" name="import_categories" value="1"'.($import_categories_checked ? ' checked' : '').'> Import categories from feed';
                    echo '</label></div>';
                    echo '<div class="podify-field">';
                    echo '<input type="hidden" name="import_tags" value="0">';
                    echo '<label style="display:flex; align-items:center; gap:8px">';
                    echo '<input type="checkbox" name="import_tags" value="1"'.($import_tags_checked ? ' checked' : '').'> Import tags from feed';
                    echo '</label></div>';
                    echo '<div class="podify-field">';
                    echo '<input type="hidden" name="append_episode_number" value="0">';
                    echo '<label style="display:flex; align-items:center; gap:8px">';
                    echo '<input type="checkbox" name="append_episode_number" value="1"'.($append_episode_number_checked ? ' checked' : '').'> Append episode number to title';
                    echo '</label></div>';
                    echo '</div>';

                    // Column 2: Button Customization
                    echo '<div>';
                    echo '<h4 class="podify-admin-section-title">Button Customization</h4>';
                    echo '<div class="podify-field"><label>Read More Text</label>';
                    echo '<input type="text" name="read_more_text" value="'.esc_attr($read_more).'" placeholder="Default: Read More"></div>';
                    echo '<div class="podify-field"><label>Load More Text</label>';
                    echo '<input type="text" name="load_more_text" value="'.esc_attr($load_more).'" placeholder="Default: Load More"></div>';
                    echo '</div>';

                    // Column 3: Display Styling
                    echo '<div>';
                    echo '<h4 class="podify-admin-section-title">Display Styling</h4>';
                    
                    $default_layout_cur = !empty($opts['default_layout']) ? sanitize_key($opts['default_layout']) : '';
                    $default_layout_cur = in_array($default_layout_cur, ['classic','modern','list'], true) ? $default_layout_cur : '';
                    echo '<div class="podify-field">';
                    echo '<label>Default Layout</label>';
                    echo '<select name="default_layout" class="podify-default-layout-select" data-feed="'.$id.'">';
                    echo '<option value=""'.($default_layout_cur===''?' selected':'').'>Choose layout</option>';
                    echo '<option value="classic"'.($default_layout_cur==='classic'?' selected':'').'>Classic</option>';
                    echo '<option value="modern"'.($default_layout_cur==='modern'?' selected':'').'>Modern</option>';
                    echo '<option value="list"'.($default_layout_cur==='list'?' selected':'').'>List</option>';
                    echo '</select>';
                    echo '<p class="description">Select layout first to edit the default styling for this feed.</p>';
                    echo '</div>';

                    $default_card_bg_color = $opts['default_card_bg_color'] ?? '';
                    $default_card_border_color = $opts['default_card_border_color'] ?? '';
                    $default_title_color = $opts['default_title_color'] ?? '';
                    $default_desc_color = $opts['default_desc_color'] ?? '';
                    $default_meta_color = $opts['default_meta_color'] ?? '';
                    $default_button_bg_color = $opts['default_button_bg_color'] ?? '';
                    $default_button_text_color = $opts['default_button_text_color'] ?? '';
                    $default_button_bg_hover_color = $opts['default_button_bg_hover_color'] ?? '';
                    $default_button_text_hover_color = $opts['default_button_text_hover_color'] ?? '';
                    $default_pill_bg_color = $opts['default_pill_bg_color'] ?? '';
                    $default_pill_text_color = $opts['default_pill_text_color'] ?? '';
                    $default_pill_bg_hover_color = $opts['default_pill_bg_hover_color'] ?? '';
                    $default_pill_text_hover_color = $opts['default_pill_text_hover_color'] ?? '';

                    echo '<div class="podify-feed-default-style podify-feed-default-style-'.$id.'" data-feed="'.$id.'" style="'.($default_layout_cur===''?'display:none;':'').'">';
                    echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">';
                    echo '<div class="podify-field"><label>Card Background</label><input type="text" class="podify-color-picker" name="default_card_bg_color" value="'.esc_attr($default_card_bg_color).'"></div>';
                    echo '<div class="podify-field"><label>Card Border</label><input type="text" class="podify-color-picker" name="default_card_border_color" value="'.esc_attr($default_card_border_color).'"></div>';
                    echo '<div class="podify-field"><label>Title Color</label><input type="text" class="podify-color-picker" name="default_title_color" value="'.esc_attr($default_title_color).'"></div>';
                    echo '<div class="podify-field"><label>Description Color</label><input type="text" class="podify-color-picker" name="default_desc_color" value="'.esc_attr($default_desc_color).'"></div>';
                    echo '<div class="podify-field"><label>Meta Color</label><input type="text" class="podify-color-picker" name="default_meta_color" value="'.esc_attr($default_meta_color).'"></div>';
                    echo '<div class="podify-field"><label></label><span></span></div>';
                    echo '<div class="podify-field"><label>Button BG</label><input type="text" class="podify-color-picker" name="default_button_bg_color" value="'.esc_attr($default_button_bg_color).'"></div>';
                    echo '<div class="podify-field"><label>Button Text</label><input type="text" class="podify-color-picker" name="default_button_text_color" value="'.esc_attr($default_button_text_color).'"></div>';
                    echo '<div class="podify-field"><label>Button Hover BG</label><input type="text" class="podify-color-picker" name="default_button_bg_hover_color" value="'.esc_attr($default_button_bg_hover_color).'"></div>';
                    echo '<div class="podify-field"><label>Button Hover Text</label><input type="text" class="podify-color-picker" name="default_button_text_hover_color" value="'.esc_attr($default_button_text_hover_color).'"></div>';
                    echo '</div>';
                    echo '<div class="podify-feed-default-style-list" data-feed="'.$id.'" style="margin-top:15px; '.($default_layout_cur==='list'?'':'display:none;').'">';
                    echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">';
                    echo '<div class="podify-field"><label>Category Pill BG</label><input type="text" class="podify-color-picker" name="default_pill_bg_color" value="'.esc_attr($default_pill_bg_color).'"></div>';
                    echo '<div class="podify-field"><label>Category Pill Text</label><input type="text" class="podify-color-picker" name="default_pill_text_color" value="'.esc_attr($default_pill_text_color).'"></div>';
                    echo '<div class="podify-field"><label>Pill Hover BG</label><input type="text" class="podify-color-picker" name="default_pill_bg_hover_color" value="'.esc_attr($default_pill_bg_hover_color).'"></div>';
                    echo '<div class="podify-field"><label>Pill Hover Text</label><input type="text" class="podify-color-picker" name="default_pill_text_hover_color" value="'.esc_attr($default_pill_text_hover_color).'"></div>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';

                    $show_cat_badge_checked = !array_key_exists('show_category_badge', $opts) || !empty($opts['show_category_badge']);
                    echo '<div class="podify-field" style="margin-top:10px">';
                    echo '<input type="hidden" name="show_category_badge" value="0">';
                    echo '<label style="display:flex; align-items:center; gap:8px">';
                    echo '<input type="checkbox" name="show_category_badge" value="1"'.($show_cat_badge_checked ? ' checked' : '').'> Show Category Badge';
                    echo '</label>';
                    echo '</div>';

                    $pill_cat_id_cur = !empty($opts['pill_color_category_id']) ? intval($opts['pill_color_category_id']) : 0;
                    $pill_cats = \PodifyPodcast\Core\Database::get_categories($id);
                    echo '<div class="podify-field">';
                    echo '<label>Uncategorized Card Style</label>';
                    echo '<select name="pill_color_category_id">';
                    echo '<option value="0"'.($pill_cat_id_cur===0 ? ' selected' : '').'>Default</option>';
                    if ($pill_cats) {
                        foreach ($pill_cats as $pc) {
                            $pcid = intval($pc['id']);
                            $pname = esc_html($pc['name']);
                            $sel = ($pill_cat_id_cur === $pcid) ? ' selected' : '';
                            echo '<option value="'.$pcid.'"'.$sel.'>#'.$pcid.' - '.$pname.'</option>';
                        }
                    }
                    echo '</select>';
                    echo '<p class="description">Applies the selected category style to uncategorized cards for this feed.</p>';
                    echo '</div>';
                    echo '</div>';

                    echo '</div>'; // End main grid (Column 1, 2, 3)

                    echo '<div class="podify-actions">';
                    echo '<button class="podify-btn-modern podify-btn-primary">Save Feed Settings</button>';
                    echo '</div>';
                    
                    echo '</form></td></tr>';
                }
            } else {
                echo '<tr><td colspan="4" style="text-align:center; padding:40px; color:#64748b;">No scheduled imports found. <a href="'.$base.'&tab=import">Add one now</a>.</td></tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
            echo '<script>(function(){';
            echo 'const SYNC_URL = '.wp_json_encode($sync_url).';';
            echo 'const RESYNC_URL = '.wp_json_encode($resync_url).';';
            echo 'const PROGRESS_URL = '.wp_json_encode($progress_url).';';
            echo 'const BACKFILL_URL = '.wp_json_encode(esc_url_raw(rest_url('podify/v1/backfill'))).';';
            echo 'const BACKFILL_PROGRESS_URL = '.wp_json_encode(esc_url_raw(rest_url('podify/v1/backfill-progress'))).';';
            echo 'const NONCE = '.wp_json_encode($nonce).';';
            echo 'const BACKFILL_AUTO = {};';
            
            // Real-time Next Sync Timer
            echo 'setInterval(function(){';
            echo '  document.querySelectorAll(".podify-next-sync-timer").forEach(function(el){';
            echo '    var next = parseInt(el.getAttribute("data-time"));';
            echo '    var now = Math.floor(Date.now() / 1000);';
            echo '    var diff = next - now;';
            echo '    if(diff <= 0) { el.textContent = "Syncing..."; el.className = "podify-badge podify-badge-success"; el.style.minWidth = "60px"; return; }';
            echo '    var h = Math.floor(diff / 3600);';
            echo '    var m = Math.floor((diff % 3600) / 60);';
            echo '    var s = diff % 60;';
            echo '    if(h > 0){ el.textContent = h + "h " + m + "m"; return; }';
            echo '    if(m > 0){ el.textContent = m + "m " + s + "s"; return; }';
            echo '    el.textContent = s + "s";';
            echo '  });';
            echo '}, 1000);'; // Run every 1 second for accurate countdown

            echo 'function poll(id, btn, origText, isBackfill=false) {';
            echo '  document.querySelectorAll(".podify-progress-wrap").forEach(function(w){ w.style.display="none"; });';
            echo '  var wrap = document.querySelector(".podify-progress-wrap[data-id=\'"+id+"\']");';
            echo '  var bar = wrap ? wrap.querySelector(".podify-progress-bar") : null;';
            echo '  var txt = wrap ? wrap.querySelector(".podify-progress-text") : null;';
            echo '  if(wrap) wrap.style.display="inline-block";';
            echo '  if(bar) bar.style.width="0%";';
            echo '  if(txt) txt.textContent="Starting...";';
            echo '  var progressUrl = isBackfill ? BACKFILL_PROGRESS_URL : PROGRESS_URL;';
            echo '  var t = setInterval(function(){';
            echo '    fetch(progressUrl+"?feed_id="+id, {headers:{"X-WP-Nonce":NONCE}}).then(function(r){';
            echo '      if(!r.ok) throw new Error("Status "+r.status);';
            echo '      return r.json();';
            echo '    }).then(function(d){';
            echo '      if(d.ok && d.status!=="idle"){';
            echo '        if(bar) {';
            echo '          bar.style.width=d.percentage+"%";';
            echo '          bar.style.backgroundColor=isBackfill ? "#8b5cf6" : (d.status.indexOf("Phase 2")!==-1 ? "#22c55e" : "#3b82f6");';
            echo '        }';
            echo '        if(txt) {';
            echo '          var statusTxt = d.status || "";';
            echo '          txt.textContent = d.percentage + "% - " + statusTxt + " (" + d.current + "/" + d.total + ")";';
            echo '          if(d.percentage > 50) { txt.style.color = "#ffffff"; } else { txt.style.color = "#1e293b"; }';
            echo '        }';
            echo '        if(isBackfill && typeof d.status === "string" && (d.status.indexOf("Backfill paused") !== -1 || d.status.indexOf("Backfill continuing") !== -1)) {';
            echo '          if(!BACKFILL_AUTO[id]) {';
            echo '            BACKFILL_AUTO[id] = true;';
            echo '            fetch(BACKFILL_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({feed_id:parseInt(id), force:false})})';
            echo '              .then(function(r){return r.json()})';
            echo '              .catch(function(err){})';
            echo '              .finally(function(){ BACKFILL_AUTO[id] = false; });';
            echo '          }';
            echo '        }';
            echo '        if(d.percentage >= 100 && !(isBackfill && typeof d.status === "string" && (d.status.indexOf("Backfill paused") !== -1 || d.status.indexOf("Backfill continuing") !== -1))) { stopPoll(t, id, btn, origText, isBackfill ? "Backfill completed!" : "Sync completed!"); }';
            echo '      } else if (d.ok && d.status === "idle") {';
            echo '        stopPoll(t, id, btn, origText, isBackfill ? "Backfill finished (Idle)" : "Sync finished (Idle)");';
            echo '      }';
            echo '    }).catch(function(e){});';
            echo '  }, 3000);';
            echo '  return t;';
            echo '}';
            echo 'function stopPoll(t, id, btn, origText, msg) {';
            echo '  clearInterval(t);';
            echo '  if(btn) { btn.disabled=false; btn.textContent=origText; }';
            echo '  var wrap = document.querySelector(".podify-progress-wrap[data-id=\'"+id+"\']");';
            echo '  if(wrap) setTimeout(function(){ wrap.style.display="none"; }, 5000);';
            echo '}';
            echo 'function closeAllMenus(){';
            echo '  document.querySelectorAll(".podify-action-menu-pop").forEach(function(m){ m.style.display="none"; });';
            echo '  document.querySelectorAll(".podify-action-menu-toggle").forEach(function(b){ b.setAttribute("aria-expanded","false"); });';
            echo '}';
            echo 'document.addEventListener("click", function(e){';
            echo '  var t = e.target.closest(".podify-action-menu-toggle");';
            echo '  if(t){';
            echo '    var id = t.getAttribute("data-id");';
            echo '    var pop = document.querySelector(".podify-action-menu-pop[data-id=\'"+id+"\']");';
            echo '    var isOpen = pop && pop.style.display !== "none";';
            echo '    closeAllMenus();';
            echo '    if(pop && !isOpen){';
            echo '      pop.style.display="block";';
            echo '      pop.style.position="fixed";';
            echo '      pop.style.zIndex="999999";';
            echo '      var rect = t.getBoundingClientRect();';
            echo '      var popW = pop.offsetWidth || 240;';
            echo '      var popH = pop.offsetHeight || 160;';
            echo '      var top = rect.bottom + 8;';
            echo '      var left = rect.right - popW;';
            echo '      var pad = 8;';
            echo '      if(left < pad) left = pad;';
            echo '      if(left + popW > window.innerWidth - pad) left = Math.max(pad, window.innerWidth - pad - popW);';
            echo '      if(top + popH > window.innerHeight - pad) {';
            echo '        top = rect.top - popH - 8;';
            echo '      }';
            echo '      if(top < pad) top = pad;';
            echo '      pop.style.left = left + "px";';
            echo '      pop.style.top = top + "px";';
            echo '      t.setAttribute("aria-expanded","true");';
            echo '    }';
            echo '    return;';
            echo '  }';
            echo '  if(!e.target.closest(".podify-action-menu")){ closeAllMenus(); }';
            echo '});';
            echo 'document.addEventListener("click",function(e){';
            echo 'var b=e.target.closest(".podify-sync");';
            echo 'if(b){var id=b.getAttribute("data-id");b.disabled=true;var ot=b.textContent;var t=poll(id,b,ot,false);fetch(SYNC_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({feed_id:parseInt(id)})}).then(function(r){return r.json()}).catch(function(err){/* Poll will continue anyway */});return}';
            echo 'var r=e.target.closest(".podify-resync");';
            echo 'if(r){var id=r.getAttribute("data-id");r.disabled=true;var ot=r.textContent;var t=poll(id,r,ot,false);fetch(RESYNC_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({feed_id:parseInt(id)})}).then(function(resp){return resp.json()}).catch(function(err){/* Poll will continue anyway */})}';
            echo 'var bf=e.target.closest(".podify-backfill");';
            echo 'if(bf){var id=bf.getAttribute("data-id");bf.disabled=true;var ot=bf.textContent;var t=poll(id,bf,ot,true);fetch(BACKFILL_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({feed_id:parseInt(id)})}).then(function(r){return r.json()}).catch(function(err){/* Poll will continue anyway */})}';
            echo '});';
            echo '})();</script>';
        } elseif ($tab === 'episodes') {
            $feed_filter = isset($_GET['feed_id']) ? intval($_GET['feed_id']) : 0;
            $limit_ep = isset($_GET['limit']) ? max(25, min(500, intval($_GET['limit']))) : ($feed_filter ? 50 : 25);
            $category_filter_raw = isset($_GET['category_id']) ? sanitize_text_field($_GET['category_id']) : '';
            $category_filter_id = is_numeric($category_filter_raw) ? intval($category_filter_raw) : 0;
            $uncategorized_filter = ($category_filter_raw === 'uncategorized');

            $search_q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
            $orderby_q = isset($_GET['orderby']) && in_array($_GET['orderby'], ['published','title'], true) ? $_GET['orderby'] : 'published';
            $order_q = isset($_GET['order']) && in_array(strtolower($_GET['order']), ['asc','desc'], true) ? strtolower($_GET['order']) : 'desc';
            $has_audio_q = !empty($_GET['has_audio']) ? 1 : 0;

            $query_opts = [
                'feed_id' => $feed_filter ?: null,
                'limit' => $limit_ep,
                'offset' => 0, // Admin always starts at 0 (page 1) for PHP render? 
                // Wait, the table has data-offset logic but the initial render is always page 1?
                // The JS handles pagination via AJAX. But the initial render needs to match params.
                // The previous code had offset => 0.
                'category_id' => $category_filter_id ?: null,
                'uncategorized' => $uncategorized_filter,
                'q' => $search_q,
                'has_audio' => $has_audio_q,
                'orderby' => $orderby_q,
                'order' => $order_q
            ];
            $episodes = \PodifyPodcast\Core\Database::get_episodes_advanced($query_opts);
            $total_episodes = \PodifyPodcast\Core\Database::count_episodes_advanced($query_opts);
            $total_pages = $limit_ep > 0 ? max(1, (int)ceil($total_episodes / $limit_ep)) : 1;

            if ($feed_filter) {
                echo '<div class="podify-field" style="margin-top:0"><strong>Feed '.$feed_filter.':</strong> showing episodes for this feed ('.intval($total_episodes).' found). <a href="'.$base.'&tab=episodes&feed_id='.$feed_filter.'&limit=500">Show all for this feed</a></div>';
            }
            $has_limit_param = isset($_GET['limit']);
            
            echo '<div class="podify-episodes-toolbar">';

            // Bulk Actions
            echo '<div class="podify-bulk-group">';
            echo '<select id="podify-bulk-action-top"><option value="">Bulk Actions</option><option value="assign_category">Assign Category</option></select>';
            echo '<select id="podify-bulk-category-top" style="display:none; max-width:200px;"><option value="">Select Category</option>';
            $bulk_cats = \PodifyPodcast\Core\Database::get_categories($feed_filter ?: null);
            if ($bulk_cats) {
                foreach ($bulk_cats as $bc) {
                    $lbl = (!$feed_filter && !empty($bc['feed_id'])) ? ('Feed '.$bc['feed_id'].': ') : '';
                    echo '<option value="'.intval($bc['id']).'">'.esc_html($lbl . $bc['name']).'</option>';
                }
            }
            echo '<option value="0">Uncategorized</option>';
            echo '</select>';
            echo '<button class="button" id="podify-bulk-apply-top">Apply</button>';
            echo '</div>'; // End bulk group

            // Filters
            echo '<div class="podify-filters-grid">';

            echo '<div class="podify-filter-item podify-field"><label>Search Episodes</label><input type="text" id="podify-ep-search" value="'.esc_attr($search_q).'" placeholder="Title or description..."></div>';

            echo '<div class="podify-filter-item podify-field"><label>Category</label><select id="podify-ep-category">';
            echo '<option value=""'.(($category_filter_id===0 && !$uncategorized_filter)?' selected':'').'>All Categories</option>';
            if ($bulk_cats) {
                foreach ($bulk_cats as $bc) {
                    $lbl = (!$feed_filter && !empty($bc['feed_id'])) ? ('Feed '.$bc['feed_id'].': ') : '';
                    $cid = intval($bc['id']);
                    $sel = ($cid === $category_filter_id && !$uncategorized_filter) ? ' selected' : '';
                    echo '<option value="'.$cid.'"'.$sel.'>'.esc_html($lbl . $bc['name']).'</option>';
                }
            }
            echo '<option value="uncategorized"'.($uncategorized_filter?' selected':'').'>Uncategorized</option>';
            echo '</select></div>';

            echo '<div class="podify-filter-item podify-field"><label>Sort By</label><select id="podify-ep-orderby"><option value="published"'.($orderby_q==='published'?' selected':'').'>Published Date</option><option value="title"'.($orderby_q==='title'?' selected':'').'>Title</option></select></div>';

            echo '<div class="podify-filter-item podify-field"><label>Order</label><select id="podify-ep-order"><option value="desc"'.($order_q==='desc'?' selected':'').'>Desc</option><option value="asc"'.($order_q==='asc'?' selected':'').'>Asc</option></select></div>';

            echo '<div class="podify-filter-item podify-field"><div class="podify-checkbox-field"><label><input type="checkbox" id="podify-ep-audio" value="1"'.($has_audio_q? ' checked':'').'> Only episodes with audio</label></div></div>';

            echo '<div class="podify-filter-item podify-field align-self-end"><button type="button" class="podify-button-modern" id="podify-ep-apply"><span class="dashicons dashicons-filter"></span> Apply Filters</button></div>';

            echo '</div>'; // End filter grid

            echo '</div>'; // End toolbar

            $offset_cur = 0;
            echo '<div class="podify-table-card">';
            echo '<div class="podify-table-wrap">';
            echo '<div id="podify-table-loader" class="podify-loader-overlay"><div class="podify-loader-spinner"></div></div>';
            echo '<table id="podify-admin-episodes" class="podify-modern-table" data-feed="'.$feed_filter.'" data-offset="'.$offset_cur.'" data-limit="'.$limit_ep.'" data-page="1" data-total-episodes="'.intval($total_episodes).'" data-total-pages="'.intval($total_pages).'"><thead><tr><th class="check-column"><input type="checkbox" id="podify-ep-select-all"></th><th>Title</th><th>Feed</th><th>Published</th><th>Links</th><th>Audio URL</th><th>Image URL</th><th>Category</th></tr></thead><tbody>';
            if ($episodes) {
                foreach ($episodes as $e) {
                    $title = esc_html($e['title']);
                    $feed_id = intval($e['feed_id']);
                    $pub = esc_html($e['published']);
                    $audio = !empty($e['audio_url']) && wp_http_validate_url($e['audio_url']) ? esc_url($e['audio_url']) : '';
                    $image = !empty($e['image_url']) && wp_http_validate_url($e['image_url']) ? esc_url($e['image_url']) : '';
                    $audioCell = $audio ? ('<a href="'.$audio.'" target="_blank" rel="noopener">Open</a>') : '—';
                    $imageCell = $image ? ('<a href="'.$image.'" target="_blank" rel="noopener">Open</a>') : '—';
                    $cats = \PodifyPodcast\Core\Database::get_categories($feed_id);
                    $assigned = \PodifyPodcast\Core\Database::get_episode_categories(intval($e['id']));
                    $assigned_names = $assigned ? implode(', ', array_map(function($c){ return esc_html($c['name']); }, $assigned)) : '—';
                    $select = '<select class="podify-assign-cat" data-episode="'.intval($e['id']).'"><option value="">Select category</option>';
                    if ($cats) {
                        foreach ($cats as $c) {
                            $select .= '<option value="'.intval($c['id']).'">'.esc_html($c['name']).'</option>';
                        }
                    }
                    $select .= '<option value="0">Uncategorized</option>';
                    $select .= '</select><div class="podify-assigned">Assigned: '.$assigned_names.'</div>';
                    
                    $desc_content = '';
                    $pid = !empty($e['post_id']) ? intval($e['post_id']) : 0;
                    if ($pid > 0) {
                        $post = get_post($pid);
                        if ($post) {
                            $desc_content = $post->post_content;
                        }
                    }
                    if (empty($desc_content) && !empty($e['description'])) {
                        $desc_content = $e['description'];
                    }
                    $link_count = \PodifyPodcast\Core\Helpers::count_links_in_content($desc_content);
                    $link_color = $link_count > 0 ? '#2e7d32' : '#d32f2f';
                    
                    echo '<tr class="podify-episode-row"><td><input type="checkbox" class="podify-ep-select" value="'.intval($e['id']).'"></td><td>'.$title.'</td><td>'.$feed_id.'</td><td>'.$pub.'</td><td><span style="color:'.$link_color.';font-weight:500;">'.$link_count.'</span></td><td>'.$audioCell.'</td><td>'.$imageCell.'</td><td>'.$select.'</td></tr>';
                }
            } else {
                echo '<tr><td colspan="8">No episodes</td></tr>';
            }
            echo '</tbody></table>';
            echo '</div>'; // End table wrap
            echo '</div>'; // End table card

            // 3. Pagination
            echo '<div class="podify-pagination">';
            echo '<div class="podify-pagination-left">';
            echo '<div class="podify-limit-group">';
            echo '<div class="podify-limit-field">';
            echo '<label for="podify-ep-limit">Items per page</label>';
            echo '<select id="podify-ep-limit"><option value="25"'.($limit_ep===25?' selected':'').'>25</option><option value="50"'.($limit_ep===50?' selected':'').'>50</option><option value="100"'.($limit_ep===100?' selected':'').'>100</option><option value="200"'.($limit_ep===200?' selected':'').'>200</option><option value="500"'.($limit_ep===500?' selected':'').'>500</option></select>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '<div class="podify-pagination-right">';
            echo '<span id="podify-admin-page">Page 1 of '.intval($total_pages).' ('.intval($total_episodes).' episodes)</span>';
            echo '<button class="button" id="podify-admin-prev" disabled><span class="dashicons dashicons-arrow-left-alt2" style="line-height:28px"></span></button>';
            echo '<button class="button" id="podify-admin-next"'.(($limit_ep >= $total_episodes)?' disabled':'').'><span class="dashicons dashicons-arrow-right-alt2" style="line-height:28px"></span></button>';
            echo '</div>';
            echo '</div>';
            
            $assign_url = esc_url_raw( rest_url('podify/v1/assign-category') );
            $bulk_assign_url = esc_url_raw( rest_url('podify/v1/bulk-assign-category') );
            echo '<script>(function(){';
            echo 'const ASSIGN_URL = '.wp_json_encode($assign_url).';';
            echo 'const BULK_ASSIGN_URL = '.wp_json_encode($bulk_assign_url).';';
            echo 'const NONCE = '.wp_json_encode($nonce).';';
            echo 'document.addEventListener("change", function(e){ var sel = e.target.closest(".podify-assign-cat"); if(!sel) return; var eid = parseInt(sel.getAttribute("data-episode")); if(!eid) return; if(sel.value==="") return; var cid = parseInt(sel.value); if(isNaN(cid) || cid < 0) return; sel.disabled = true; fetch(ASSIGN_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({episode_id:eid,category_id:cid})}).then(function(r){return r.json()}).then(function(d){ sel.disabled=false; if(d && d.ok){ var assignedTxt = (cid===0) ? "Assigned: —" : ("Assigned: " + sel.options[sel.selectedIndex].text); sel.nextElementSibling && (sel.nextElementSibling.textContent = assignedTxt); } else { alert("Failed to assign"); } }).catch(function(){ sel.disabled=false; alert("Failed"); }); });';
            echo 'document.addEventListener("change", function(e){ var master = e.target.closest("#podify-ep-select-all"); if(!master) return; var table = document.getElementById("podify-admin-episodes"); if(!table) return; var boxes = table.querySelectorAll(".podify-ep-select"); boxes.forEach(function(b){ b.checked = master.checked; }); });';
            // Pagination JS
            $episodes_url = esc_url_raw( rest_url('podify/v1/episodes') );
                $cats_url = esc_url_raw( rest_url('podify/v1/categories') );
                echo 'const EP_URL = '.wp_json_encode($episodes_url).';';
                echo 'const CATS_URL = '.wp_json_encode($cats_url).';';
                echo 'let PODIFY_CATS = [];';
                echo 'function fetchCats(){ return fetch(CATS_URL + "?feed_id='.intval($feed_filter).'", {headers:{"X-WP-Nonce":NONCE}}).then(function(r){return r.json()}).then(function(d){ PODIFY_CATS = (d && d.items) ? d.items : []; }).catch(function(){ PODIFY_CATS = []; }); }';
                echo 'function makeCatSelect(epId, assignedCats){ var html = \'<select class="podify-assign-cat" data-episode="\'+epId+\'"><option value="">Select category</option>\'; PODIFY_CATS.forEach(function(c){ var label = (c.feed_id ? "Feed "+c.feed_id+": " : "") + (c.name||""); html += \'<option value="\'+(c.id||0)+\'">\'+label+\'</option>\'; }); html += \'<option value="0">Uncategorized</option>\'; var assignedNames = (assignedCats && assignedCats.length) ? assignedCats.map(function(c){ return c.name; }).join(", ") : "—"; html += \'</select><div class="podify-assigned">Assigned: \'+assignedNames+\'</div>\'; return html; }';
                echo 'function ensureEpisodeCheckboxes(){ var table=document.getElementById("podify-admin-episodes"); if(!table) return; var rows=table.querySelectorAll("tbody tr"); rows.forEach(function(tr){ if(tr.querySelector(".podify-ep-select")) return; var sel=tr.querySelector(".podify-assign-cat"); var eid=sel?parseInt(sel.getAttribute("data-episode"))||0:0; var firstCell=tr.querySelector("td"); if(!firstCell) return; var td=document.createElement("td"); var val=eid?String(eid):""; td.innerHTML = \'<input type="checkbox" class="podify-ep-select" value="\'+val+\'">\'; tr.insertBefore(td, firstCell); }); }';
                echo 'function initEpisodeCheckboxObserver(){ var table=document.getElementById("podify-admin-episodes"); if(!table || !window.MutationObserver) return; var tbody=table.querySelector("tbody"); if(!tbody) return; var obs=new MutationObserver(function(){ ensureEpisodeCheckboxes(); }); obs.observe(tbody,{childList:true}); }';
                echo 'ensureEpisodeCheckboxes(); initEpisodeCheckboxObserver();';
                echo 'function setPageLabel(p){ var el=document.getElementById("podify-admin-page"); if(!el) return; var table=document.getElementById("podify-admin-episodes"); var totalPages=1; var totalEpisodes=0; if(table){ var tp=parseInt(table.getAttribute("data-total-pages"))||0; var te=parseInt(table.getAttribute("data-total-episodes"))||0; if(tp>0) totalPages=tp; if(te>0) totalEpisodes=te; } var label="Page "+String(p)+" of "+String(totalPages); if(totalEpisodes>0){ label += " ("+String(totalEpisodes)+" episodes)"; } el.textContent=label; }';
                echo 'function setPrevNextDisabled(prevDis,nextDis){ var p=document.getElementById("podify-admin-prev"); var n=document.getElementById("podify-admin-next"); if(p) p.disabled=!!prevDis; if(n) n.disabled=!!nextDis; }';
                echo 'function showLoader(show){ var l=document.getElementById("podify-table-loader"); if(l) l.style.display = show ? "flex" : "none"; }';
                echo 'function buildParams(limit, offset){ var q=(document.getElementById("podify-ep-search")||{value:""}).value||""; var cat=(document.getElementById("podify-ep-category")||{value:""}).value||""; var ob=(document.getElementById("podify-ep-orderby")||{value:"published"}).value||"published"; var or=(document.getElementById("podify-ep-order")||{value:"desc"}).value||"desc"; var ha=(document.getElementById("podify-ep-audio")||{checked:false}).checked?1:0; var s="&limit="+encodeURIComponent(limit)+"&offset="+encodeURIComponent(offset); if(q.length){ s += "&q="+encodeURIComponent(q); } if(cat){ s += "&category_id="+encodeURIComponent(cat); } if(ob){ s += "&orderby="+encodeURIComponent(ob); } if(or){ s += "&order="+encodeURIComponent(or); } if(ha){ s += "&has_audio=1"; } return s; }';
                echo 'document.addEventListener("click", function(e){ var btnPrev = e.target.closest("#podify-admin-prev"); var btnNext = e.target.closest("#podify-admin-next"); var btnApply = e.target.closest("#podify-ep-apply"); if(!btnPrev && !btnNext && !btnApply) return; var table = document.getElementById("podify-admin-episodes"); if(!table) return; var feed = parseInt(table.getAttribute("data-feed"))||0; var limit = parseInt(table.getAttribute("data-limit"))||50; var page = parseInt(table.getAttribute("data-page"))||1; var nextPage = btnApply ? 1 : (page + (btnNext?1:-1)); if(nextPage<1) nextPage=1; var offset = (nextPage-1)*limit; var url = EP_URL + "?feed_id=" + encodeURIComponent(feed) + buildParams(limit, offset); btnPrev && (btnPrev.disabled = true); btnNext && (btnNext.disabled = true); showLoader(true); fetchCats().then(function(){ return fetch(url).then(function(r){ return r.json(); }); }).then(function(d){ var tbody = table.querySelector("tbody"); var rowsHtml = ""; if(d && d.items){ d.items.forEach(function(it){ var title = it.title || ""; var pub = it.published || ""; var audio = it.audio_url || ""; var image = it.image_url || ""; var feedId = it.feed_id || 0; var linkCount = (typeof it.link_count !== "undefined") ? parseInt(it.link_count) : 0; var linkColor = linkCount > 0 ? "#2e7d32" : "#d32f2f"; var audioCell = audio ? (\'<a href="\'+audio+\'" target="_blank" rel="noopener">Open</a>\') : "—"; var imageCell = image ? (\'<a href="\'+image+\'" target="_blank" rel="noopener">Open</a>\') : "—"; rowsHtml += \'<tr class="podify-episode-row"><td><input type="checkbox" class="podify-ep-select" value="\'+it.id+\'"></td><td>\'+title+\'</td><td>\'+feedId+\'</td><td>\'+pub+\'</td><td><span style="color:\'+linkColor+\';font-weight:500;">\'+linkCount+\'</span></td><td>\'+audioCell+\'</td><td>\'+imageCell+\'</td><td>\'+makeCatSelect(it.id, it.categories)+\'</td></tr>\'; }); } tbody.innerHTML = rowsHtml; var totalCount = d && typeof d.total_count !== "undefined" ? parseInt(d.total_count) || 0 : 0; var totalPages = limit > 0 ? Math.max(1, Math.ceil(totalCount/limit)) : 1; if(totalCount>0){ table.setAttribute("data-total-episodes", String(totalCount)); table.setAttribute("data-total-pages", String(totalPages)); } table.setAttribute("data-page", String(nextPage)); table.setAttribute("data-offset", String(offset)); setPageLabel(nextPage); setPrevNextDisabled(nextPage<=1, nextPage>=totalPages); ensureEpisodeCheckboxes(); showLoader(false); }).catch(function(){ alert("Failed to load episodes"); btnPrev && (btnPrev.disabled = false); btnNext && (btnNext.disabled = false); showLoader(false); }); });';
                echo 'var PODIFY_SEARCH_TIMER; document.addEventListener("input", function(e){ var s = e.target.closest("#podify-ep-search"); if(!s) return; clearTimeout(PODIFY_SEARCH_TIMER); PODIFY_SEARCH_TIMER = setTimeout(function(){ var btn = document.getElementById("podify-ep-apply"); if(btn){ btn.click(); } }, 300); }); document.addEventListener("change", function(e){ var sel = e.target.closest("#podify-ep-limit"); if(!sel) return; var v = parseInt(sel.value)||50; var url = new URL(window.location.href); url.searchParams.set("feed_id", "'.intval($feed_filter).'"); url.searchParams.set("limit", String(v)); window.location.href = url.toString(); });';
        
        echo 'document.addEventListener("change", function(e){ var sel=e.target.closest("#podify-bulk-action-top"); if(!sel) return; var cat=document.getElementById("podify-bulk-category-top"); if(cat) cat.style.display=(sel.value==="assign_category"?"inline-block":"none"); });';
            echo 'document.addEventListener("click", function(e){ var btn=e.target.closest("#podify-bulk-apply-top"); if(!btn) return; var sel=document.getElementById("podify-bulk-action-top"); var act=sel?sel.value:""; if(!act){alert("Select an action");return;} var cbs=document.querySelectorAll(".podify-ep-select:checked"); if(!cbs.length){alert("No episodes selected");return;} var ids=[]; cbs.forEach(function(c){ids.push(parseInt(c.value))}); if(act==="assign_category"){ var catSel=document.getElementById("podify-bulk-category-top"); if(!catSel || catSel.value===""){alert("Select a category");return;} var catId=parseInt(catSel.value); if(isNaN(catId) || catId < 0){alert("Select a category");return;} btn.disabled=true; btn.textContent="Applying..."; fetch(BULK_ASSIGN_URL,{method:"POST",headers:{"Content-Type":"application/json","X-WP-Nonce":NONCE},body:JSON.stringify({episode_ids:ids,category_id:catId})}).then(function(r){return r.json()}).then(function(d){ btn.disabled=false; btn.textContent="Apply"; if(d&&d.ok){ alert("Updated "+(d.count||0)+" episodes"); window.location.reload(); }else{ alert("Failed: "+(d.message||"Error")); } }).catch(function(e){ btn.disabled=false; btn.textContent="Apply"; alert("Error: "+e); }); } });';
            echo '})();</script>';
        } elseif ($tab === 'categories') {
            echo '<div class="podify-categories-container" style="display:flex; flex-direction:column; gap:30px; width:100%; max-width:100%;">';
            
            // Card 1: Add Category
            echo '<div class="podify-card"><h3><span class="dashicons dashicons-plus-alt" style="margin-right:8px;"></span> Add New Category Mapping</h3>';
            echo '<form method="post"><input type="hidden" name="podify_action" value="add_category">';
            wp_nonce_field('podify_add_category');
            echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:30px;">';
            echo '<div class="podify-field"><label>Target Feed</label><select name="feed_id">';
            if ($feeds) {
                foreach ($feeds as $f) {
                    echo '<option value="'.intval($f['id']).'">Feed '.intval($f['id']).' ('.esc_html(parse_url($f['feed_url'], PHP_URL_HOST)).')</option>';
                }
            }
            echo '</select></div>';
            echo '<div class="podify-field"><label>Category Name</label><input type="text" name="category_name" placeholder="e.g. Technology" required></div>';
            echo '</div>';
            echo '<div class="podify-actions"><button type="submit" class="podify-btn-modern podify-btn-primary"><span class="dashicons dashicons-plus"></span> Add Category Mapping</button></div></form>';
            echo '</div>';
            
            // Card 2: Existing Categories
            echo '<div class="podify-card"><h3><span class="dashicons dashicons-list-view" style="margin-right:8px;"></span> Existing Category Mappings</h3>';
            $allCats = \PodifyPodcast\Core\Database::get_categories(null);
            if ($allCats) {
                echo '<div class="podify-table-card" style="margin-top:20px; border:none; box-shadow:none;">';
                echo '<table class="podify-modern-table"><thead><tr><th style="width:180px;">Feed</th><th style="width:60px; text-align:center;">ID</th><th>Name</th><th style="width:180px;">Slug</th><th style="width:320px; text-align:right;">Actions</th></tr></thead><tbody>';
                foreach ($allCats as $c) {
                    $rowId = intval($c['id']);
                    $currentFeedId = intval($c['feed_id']);
                    $name = esc_attr($c['name']);
                    $slug = esc_html($c['slug']);
                    
                    // Color values
                    $c1 = $c['card_bg_color'] ?: '';
                    $ctitle = $c['title_color'] ?: '';
                    $cdesc = $c['desc_color'] ?: '';
                    $bbg = $c['button_bg_color'] ?: '';
                    $btxt = $c['button_text_color'] ?: '';
                    $lmbg = $c['load_more_bg_color'] ?: '';
                    $lmtxt = $c['load_more_text_color'] ?: '';
                    $lmbgh = $c['load_more_bg_hover_color'] ?: '';
                    $lmtxth = $c['load_more_text_hover_color'] ?: '';
                    $tfont = $c['title_font'] ?: '';
                    $tlspace = $c['title_letter_spacing'] ?: '';
                    $tlheight = $c['title_line_height'] ?: '';

                    $feedSelect = '<select class="podify-cat-feed" data-id="'.$rowId.'"><option value="0"'.($currentFeedId===0?' selected':'').'>— Unassigned —</option>';
                    if ($feeds) {
                        foreach ($feeds as $f) {
                            $fid = intval($f['id']);
                            $sel = ($fid === $currentFeedId) ? ' selected' : '';
                            $feedSelect .= '<option value="'.$fid.'"'.$sel.'>Feed '.$fid.'</option>';
                        }
                    }
                    $feedSelect .= '</select>';

                    echo '<tr>';
                    echo '<td>'.$feedSelect.'</td>';
                    echo '<td style="text-align:center; font-weight:700; color:#64748b;">#'.$rowId.'</td>';
                    echo '<td><input type="text" value="'.$name.'" class="podify-cat-name" data-id="'.$rowId.'"></td>';
                    echo '<td class="podify-cat-slug" style="font-size:12px; color:#64748b; font-style:italic; font-family:monospace;">'.$slug.'</td>';
                    echo '<td style="text-align:right;"><div class="podify-actions-cell" style="justify-content:flex-end;">';
                    echo '<button class="podify-btn-modern podify-btn-outline podify-cat-customize-toggle" data-id="'.$rowId.'"><span class="dashicons dashicons-admin-appearance"></span> Colors</button>';
                    echo '<button class="podify-btn-modern podify-btn-primary podify-cat-save" data-id="'.$rowId.'"><span class="dashicons dashicons-saved"></span> Save</button>';
                    echo '<button class="podify-btn-modern podify-btn-danger-outline podify-cat-delete" data-id="'.$rowId.'"><span class="dashicons dashicons-trash"></span></button>';
                    echo '</div></td></tr>';
                    
                    // Customization Row
                    echo '<tr id="podify-cat-customization-'.$rowId.'" style="display:none; background:#f8fafc;"><td colspan="5" style="padding:30px; border-bottom:2px solid #e2e8f0;">';
                    echo '<div style="display:grid; grid-template-columns:1fr 1.5fr 2fr; gap:40px;">';
                    
                    // Group 1: Card Appearance
                    echo '<div>';
                    echo '<h4 class="podify-admin-section-title">Card Appearance</h4>';
                    echo '<div class="podify-field"><label>BACKGROUND COLOR</label><input type="text" class="podify-color-picker podify-cat-c1" data-id="'.$rowId.'" value="'.$c1.'"></div>';
                    echo '<div class="podify-field"><label>TITLE COLOR</label><input type="text" class="podify-color-picker podify-cat-ctitle" data-id="'.$rowId.'" value="'.$ctitle.'"></div>';
                    echo '<div class="podify-field"><label>DESC COLOR</label><input type="text" class="podify-color-picker podify-cat-cdesc" data-id="'.$rowId.'" value="'.$cdesc.'"></div>';
                    echo '<div class="podify-field"><label>TITLE FONT</label><input type="text" class="podify-cat-tfont" data-id="'.$rowId.'" value="'.esc_attr($tfont).'" placeholder="e.g. \'Very Vogue\', serif"></div>';
                    echo '<div class="podify-field"><label>LETTER SPACING</label><input type="text" class="podify-cat-tlspace" data-id="'.$rowId.'" value="'.esc_attr($tlspace).'" placeholder="e.g. 1px"></div>';
                    echo '<div class="podify-field"><label>LINE HEIGHT</label><input type="text" class="podify-cat-tlheight" data-id="'.$rowId.'" value="'.esc_attr($tlheight).'" placeholder="e.g. 1.2"></div>';
                    echo '</div>';
                    
                    // Group 2: Action Buttons
                    echo '<div>';
                    echo '<h4 class="podify-admin-section-title">Read More Buttons</h4>';
                    echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">';
                    echo '<div class="podify-field"><label>BUTTON BG</label><input type="text" class="podify-color-picker podify-cat-bbg" data-id="'.$rowId.'" value="'.$bbg.'"></div>';
                    echo '<div class="podify-field"><label>BUTTON TEXT</label><input type="text" class="podify-color-picker podify-cat-btxt" data-id="'.$rowId.'" value="'.$btxt.'"></div>';
                    echo '</div>';
                    echo '</div>';
                    
                    // Group 3: Load More Button
                    echo '<div>';
                    echo '<h4 class="podify-admin-section-title">Load More Button Styling</h4>';
                    echo '<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">';
                    echo '<div class="podify-field"><label>NORMAL BG</label><input type="text" class="podify-color-picker podify-cat-lmbg" data-id="'.$rowId.'" value="'.$lmbg.'"></div>';
                    echo '<div class="podify-field"><label>NORMAL TEXT</label><input type="text" class="podify-color-picker podify-cat-lmtxt" data-id="'.$rowId.'" value="'.$lmtxt.'"></div>';
                    echo '<div class="podify-field"><label>HOVER BG</label><input type="text" class="podify-color-picker podify-cat-lmbgh" data-id="'.$rowId.'" value="'.$lmbgh.'"></div>';
                    echo '<div class="podify-field"><label>HOVER TEXT</label><input type="text" class="podify-color-picker podify-cat-lmtxth" data-id="'.$rowId.'" value="'.$lmtxth.'"></div>';
                    echo '</div>';
                    echo '</div>';
                    
                    echo '</div></td></tr>';
                }
                echo '</tbody></table></div>';
            } else {
                echo '<p style="padding:40px; text-align:center; color:#64748b; background:#f8fafc; border-radius:8px; margin:20px;">No categories found. Use the form above to add your first category mapping.</p>';
            }
            echo '</div>';
            echo '</div>'; // End container
            
            $upd_url = esc_url_raw( rest_url('podify/v1/update-category') );
            $del_url = esc_url_raw( rest_url('podify/v1/delete-category') );
            echo '<script>window.PODIFY_CAT_CONFIG = {';
            echo ' NONCE: '.wp_json_encode($nonce).',';
            echo ' UPD_URL: '.wp_json_encode($upd_url).',';
            echo ' DEL_URL: '.wp_json_encode($del_url);
            echo '};</script>';
        } elseif ($tab === 'changelog') {
            echo '<div class="podify-changelog">';
            echo '<h1>Changelog</h1>';
            $file = \PODIFY_PODCAST_PATH . 'changelog.md';
            if (file_exists($file)) {
                $raw = file_get_contents($file);
                $lines = explode("\n", $raw);
                $in_list = false;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        if ($in_list) { echo '</ul>'; $in_list = false; }
                        continue;
                    }
                    if (strpos($line, '## ') === 0) {
                        if ($in_list) { echo '</ul>'; $in_list = false; }
                        echo '<h2>'.esc_html(substr($line, 3)).'</h2>';
                    } elseif (strpos($line, '### ') === 0) {
                        if ($in_list) { echo '</ul>'; $in_list = false; }
                        echo '<h3>'.esc_html(substr($line, 4)).'</h3>';
                    } elseif (strpos($line, '- ') === 0) {
                        if (!$in_list) { echo '<ul>'; $in_list = true; }
                        $content = substr($line, 2);
                        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
                        $content = preg_replace('/`(.+?)`/', '<code>$1</code>', $content);
                        echo '<li>'.$content.'</li>';
                    } else {
                        if ($in_list) { echo '</ul>'; $in_list = false; }
                        $content = $line;
                        $content = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $content);
                        echo '<p>'.$content.'</p>';
                    }
                }
                if ($in_list) { echo '</ul>'; }
            } else {
                echo '<p>Changelog not found.</p>';
            }
            echo '</div>';
        } else {
            $settings = \PodifyPodcast\Core\Settings::get();
            echo '<div class="podify-grid">';
            echo '<div class="podify-card"><h3>Shortcodes</h3>';
            echo '<div class="podify-field"><label>Episodes List</label><input type="text" readonly value="[podify_podcast_list]" /></div>';
            echo '<div class="podify-field"><label>By Feed</label><input type="text" readonly value="[podify_podcast_list feed_id=&quot;1&quot;]" /></div>';
            echo '<div class="podify-field"><label>By Feed + Category (optional)</label><input type="text" readonly value="[podify_podcast_list feed_id=&quot;1&quot; category_id=&quot;10&quot;]" /></div>';
            echo '<div class="podify-field"><label>By Feed + Category Slug/Name (optional)</label><input type="text" readonly value="[podify_podcast_list feed_id=&quot;1&quot; category=&quot;technology&quot;]" /></div>';
            echo '<div class="podify-field"><label>By Feed + Uncategorized (optional)</label><input type="text" readonly value="[podify_podcast_list feed_id=&quot;1&quot; category=&quot;uncategorized&quot;]" /></div>';
            echo '<div class="podify-field"><label>Custom Limit & Columns</label><input type="text" readonly value="[podify_podcast_list limit=&quot;12&quot; cols=&quot;4&quot;]" /></div>';
            echo '<div class="podify-field"><label>Layout (via shortcode)</label><input type="text" readonly value="[podify_podcast_list layout=&quot;modern&quot;]" /><p class="description">Accepted values: <code>classic</code>, <code>modern</code>, or <code>list</code>. Example: [podify_podcast_list feed_id=&quot;1&quot; layout=&quot;list&quot;]</p></div>';
            echo '<div class="podify-field"><label>Single Episode Player</label><input type="text" readonly value="[podify_single_player]" /><p class="description">Optional: [podify_single_player post_id=&quot;123&quot;]</p></div>';
            echo '<p style="margin-top:8px">Tip: Omit category to display all episodes for the chosen feed (including uncategorized).</p>';
            echo '</div>';
            echo '<div class="podify-card"><h3>Default Styling</h3>';
            echo '<p style="color:#64748b; margin:0;">Frontend styling now uses the built-in defaults. Category styles (and uncategorized styling via feed options) take precedence.</p>';
            echo '</div>';

            echo '<div class="podify-card"><h3>Sticky Player & Custom CSS</h3>';
            echo '<form method="post" class="podify-global-settings-form"><input type="hidden" name="podify_action" value="save_settings">';
            wp_nonce_field('podify_save_settings');
            $enabled = !empty($settings['sticky_player_enabled']);
            $position = !empty($settings['sticky_player_position']) ? $settings['sticky_player_position'] : 'bottom';

            echo '<input type="hidden" name="sticky_player_enabled" value="0">';
            echo '<div class="podify-field"><label><input type="checkbox" name="sticky_player_enabled" value="1"'.($enabled?' checked':'').'> Enable sticky player</label></div>';
            echo '<div class="podify-field"><label>Position</label><select name="sticky_player_position"><option value="bottom"'.($position==='bottom'?' selected':'').'>Bottom</option><option value="top"'.($position==='top'?' selected':'').'>Top</option></select></div>';
            
            $custom_css = !empty($settings['custom_css']) ? $settings['custom_css'] : '';
            echo '<div class="podify-field"><label>Custom CSS for Episode Cards</label><textarea name="custom_css" rows="12" placeholder="/* Add CSS to style the episode cards and category pills */" style="height: 125px;">'.esc_textarea($custom_css).'</textarea><p class="description">Control layout per shortcode: [podify_podcast_list layout="classic|modern"]. Target elements like .podify-episode-card, .podify-episode-title, .podify-category-pill. Your CSS is injected sitewide.</p></div>';
            echo '<div class="podify-actions" style="margin-top:20px; text-align:right;"><button type="submit" class="podify-btn-modern podify-btn-primary"><span class="dashicons dashicons-saved" style="font-size:18px; width:18px; height:18px;"></span> Save Settings</button></div></form>';
            echo '</div>'; // End Card

            echo '<div class="podify-card"><h3>Tools</h3>';
            echo '<form method="post"><input type="hidden" name="podify_action" value="clear_cache">';
            wp_nonce_field('podify_clear_cache');
            echo '<p style="margin-bottom:15px; color:#64748b;">Clear plugin caches and force update checks.</p>';
            echo '<div class="podify-actions"><button class="podify-btn-modern podify-btn-outline"><span class="dashicons dashicons-trash" style="margin-right:4px;"></span> Clear Cache</button></div></form>';
            echo '</div>';
        }
        echo '</div>'; // End podify-content
        echo '</div>'; // End podify-admin-layout
        echo '</div>'; // End wrap
        
        echo '<script>(function($){';
        echo '  $(function(){ if($.fn.wpColorPicker) $(".podify-color-picker").wpColorPicker(); });';
        echo '  function toggleFeedDefaultStyles(feedId){';
        echo '    var row = $("#podify-customization-" + feedId); if(!row.length) return;';
        echo '    var sel = row.find(".podify-default-layout-select"); if(!sel.length) return;';
        echo '    var val = (sel.val()||"").toString();';
        echo '    var wrap = row.find(".podify-feed-default-style-" + feedId);';
        echo '    var listWrap = row.find(".podify-feed-default-style-list[data-feed=\'"+feedId+"\']");';
        echo '    if(!val){ wrap.hide(); if(listWrap.length) listWrap.hide(); return; }';
        echo '    wrap.show();';
        echo '    if(listWrap.length){ if(val==="list"){ listWrap.show(); } else { listWrap.hide(); } }';
        echo '  }';
        echo '  $(document).on("click", ".podify-toggle-customization, .podify-cat-customize-toggle", function(e){';
        echo '    e.preventDefault();';
        echo '    var t = $(this);';
        echo '    var id = t.attr("data-id");';
        echo '    var rowId = t.hasClass("podify-cat-customize-toggle") ? "podify-cat-customization-" + id : "podify-customization-" + id;';
        echo '    var row = $("#" + rowId); if(!row.length) return;';
        echo '    if(row.css("display") === "none") {';
        echo '      row.css("display", "table-row");';
        echo '      row.find(".podify-color-picker").each(function(){ if(!$(this).next().hasClass("wp-picker-container")) $(this).wpColorPicker(); });';
        echo '      if(!t.hasClass("podify-cat-customize-toggle")) { toggleFeedDefaultStyles(id); }';
        echo '    } else { row.css("display", "none"); }';
        echo '  });';
        echo '  $(document).on("change", ".podify-default-layout-select", function(){';
        echo '    var feedId = $(this).attr("data-feed");';
        echo '    if(feedId) toggleFeedDefaultStyles(feedId);';
        echo '  });';
        echo '  $(document).on("click", ".podify-cat-save", function(e){';
        echo '    e.preventDefault(); if(!window.PODIFY_CAT_CONFIG) return;';
        echo '    var s = $(this); var id = parseInt(s.attr("data-id"));';
        echo '    var row = $("#podify-cat-customization-" + id);';
        echo '    var nameInput = $(".podify-cat-name[data-id=\'"+id+"\']");';
        echo '    var name = nameInput.val() ? nameInput.val().trim() : "";';
        echo '    var feedSelect = $(".podify-cat-feed[data-id=\'"+id+"\']");';
        echo '    var feedId = parseInt(feedSelect.val()) || 0;';
        echo '    if(!name) { alert("Please enter a category name"); return; }';
        echo '    var colors = {';
        echo '      card_bg_color: row.find(".podify-cat-c1").val(),';
        echo '      title_color: row.find(".podify-cat-ctitle").val(),';
        echo '      desc_color: row.find(".podify-cat-cdesc").val(),';
        echo '      button_bg_color: row.find(".podify-cat-bbg").val(),';
        echo '      button_text_color: row.find(".podify-cat-btxt").val(),';
        echo '      load_more_bg_color: row.find(".podify-cat-lmbg").val(),';
        echo '      load_more_text_color: row.find(".podify-cat-lmtxt").val(),';
        echo '      load_more_bg_hover_color: row.find(".podify-cat-lmbgh").val(),';
        echo '      load_more_text_hover_color: row.find(".podify-cat-lmtxth").val(),';
        echo '      title_font: row.find(".podify-cat-tfont").val(),';
        echo '      title_letter_spacing: row.find(".podify-cat-tlspace").val(),';
        echo '      title_line_height: row.find(".podify-cat-tlheight").val()';
        echo '    };';
        echo '    row.find(".podify-color-picker").each(function(){ var el=$(this); var key="";';
        echo '      if(el.hasClass("podify-cat-c1")) key="card_bg_color"; else if(el.hasClass("podify-cat-ctitle")) key="title_color";';
        echo '      else if(el.hasClass("podify-cat-cdesc")) key="desc_color"; else if(el.hasClass("podify-cat-bbg")) key="button_bg_color";';
        echo '      else if(el.hasClass("podify-cat-btxt")) key="button_text_color"; else if(el.hasClass("podify-cat-lmbg")) key="load_more_bg_color";';
        echo '      else if(el.hasClass("podify-cat-lmtxt")) key="load_more_text_color"; else if(el.hasClass("podify-cat-lmbgh")) key="load_more_bg_hover_color";';
        echo '      else if(el.hasClass("podify-cat-lmtxth")) key="load_more_text_hover_color";';
        echo '      if(key && el.wpColorPicker){ var c=el.wpColorPicker("color"); if(c) colors[key]=c; }';
        echo '    });';
        echo '    s.prop("disabled", true); var originalHtml = s.html(); s.html("<span class=\'dashicons dashicons-update\' style=\'animation:podify-spin 1s linear infinite; font-size:16px; width:16px; height:16px;\'></span> Saving...");';
        echo '    $.ajax({ url: window.PODIFY_CAT_CONFIG.UPD_URL, method: "POST", headers: {"X-WP-Nonce": window.PODIFY_CAT_CONFIG.NONCE}, contentType: "application/json", data: JSON.stringify({id:id, name:name, feed_id:feedId, colors:colors}) }).done(function(d){';
        echo '      if(d && d.ok){';
        echo '        s.html("<span class=\'dashicons dashicons-yes\' style=\'font-size:16px; width:16px; height:16px;\'></span> Saved"); s.css({"background-color":"#10b981", "border-color":"#10b981"});';
        echo '        if(d.slug && row.closest("tr").prev().find(".podify-cat-slug").length) { row.closest("tr").prev().find(".podify-cat-slug").text(d.slug); }';
        echo '        setTimeout(function(){ s.prop("disabled", false).html(originalHtml).css({"background-color":"", "border-color":""}); }, 2000);';
        echo '      } else { s.prop("disabled", false).html(originalHtml); alert("Failed: " + (d.message || "Unknown error")); }';
        echo '    }).fail(function(){ s.prop("disabled", false).html(originalHtml); alert("Failed"); });';
        echo '  });';
        echo '  $(document).on("click", ".podify-cat-delete", function(e){';
        echo '    e.preventDefault(); if(!window.PODIFY_CAT_CONFIG || !confirm("Delete category mapping?")) return;';
        echo '    var s = $(this); var id = parseInt(s.attr("data-id")); s.prop("disabled", true);';
        echo '    $.ajax({ url: window.PODIFY_CAT_CONFIG.DEL_URL, method: "POST", headers: {"X-WP-Nonce": window.PODIFY_CAT_CONFIG.NONCE}, contentType: "application/json", data: JSON.stringify({id:id}) }).done(function(d){';
        echo '      if(d && d.ok){ location.reload(); } else { s.prop("disabled", false); alert("Failed: " + (d.message || "")); }';
        echo '    }).fail(function(){ s.prop("disabled", false); alert("Error"); });';
        echo '  });';
        echo '  $(document).on("submit", ".podify-global-settings-form, .podify-feed-options-form", function(e){';
        echo '    var form = $(this);';
        echo '    e.preventDefault(); var btn = form.find(".podify-btn-primary"); if(!btn.length || btn.prop("disabled")) return;';
        echo '    var originalHtml = btn.html(); btn.prop("disabled", true); btn.html("<span class=\'dashicons dashicons-update\' style=\'animation:podify-spin 1s linear infinite; font-size:18px; width:18px; height:18px;\'></span> Saving...");';
        echo '    $.ajax({ url: window.location.href, method: "POST", data: new FormData(this), processData: false, contentType: false }).done(function(){';
        echo '      btn.html("<span class=\'dashicons dashicons-yes\' style=\'font-size:18px; width:18px; height:18px;\'></span> Saved"); btn.css({"background-color":"#10b981", "border-color":"#10b981"});';
        echo '      setTimeout(function(){ btn.prop("disabled", false).html(originalHtml).css({"background-color":"", "border-color":""}); }, 2000);';
        echo '    }).fail(function(){ btn.prop("disabled", false).html(originalHtml); alert("Failed to save settings"); });';
        echo '  });';
        echo '})(jQuery);</script>';
    }
}
