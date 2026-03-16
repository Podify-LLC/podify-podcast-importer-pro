<?php
namespace PodifyPodcast\Core\API;

class RestInit {
    public static function register() {
        add_action('rest_api_init',[self::class,'routes']);
    }
    public static function routes() {
        register_rest_route('podify/v1','/progress',[
            'methods' => 'GET',
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                if (!$feed_id) return ['ok'=>false];
                $progress = get_transient('podify_import_progress_' . $feed_id);
                if (!$progress) {
                    return ['ok' => true, 'percentage' => 0, 'status' => 'idle'];
                }
                return ['ok' => true, 'percentage' => $progress['percentage'], 'status' => $progress['status'], 'current' => $progress['current'], 'total' => $progress['total']];
            }
        ]);
        register_rest_route('podify/v1','/sync',[
            'methods' => 'POST',
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                if (!$feed_id) {
                    return ['ok' => false, 'message' => 'Missing feed_id'];
                }
                
                // Set initial status immediately so polling sees it
                set_transient('podify_import_progress_'.$feed_id, [
                    'total' => 0, 'current' => 0, 'percentage' => 0, 'status' => 'Syncing...'
                ], 3600);

                // Schedule immediate background sync
                wp_schedule_single_event(time(), \PodifyPodcast\Core\Cron\CronInit::HOOK_FEED, [$feed_id, false]);
                spawn_cron(); // Try to trigger cron execution immediately
                
                return ['ok' => true, 'message' => 'Sync started'];
            }
        ]);
        register_rest_route('podify/v1','/resync',[
            'methods' => 'POST',
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                if (!$feed_id) {
                    return ['ok' => false, 'message' => 'Missing feed_id'];
                }

                // Set initial status immediately
                set_transient('podify_import_progress_'.$feed_id, [
                    'total' => 0, 'current' => 0, 'percentage' => 0, 'status' => 'Force Syncing...'
                ], 3600);

                // Schedule background force sync
                wp_schedule_single_event(time(), \PodifyPodcast\Core\Cron\CronInit::HOOK_FEED, [$feed_id, true]);
                spawn_cron();
                
                return ['ok' => true, 'message' => 'Force sync started'];
            }
        ]);
        register_rest_route('podify/v1','/episodes',[
            'methods' => 'GET',
            'permission_callback' => '__return_true',
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                $limit = intval($req->get_param('limit'));
                $offset = intval($req->get_param('offset'));
                $cat_raw = $req->get_param('category_id');
                $category_id = is_numeric($cat_raw) ? intval($cat_raw) : 0;
                $uncategorized = is_string($cat_raw) && $cat_raw === 'uncategorized';
                $q = (string)$req->get_param('q');
                $has_audio = (bool)$req->get_param('has_audio');
                $orderby = (string)$req->get_param('orderby');
                $order = (string)$req->get_param('order');

                // DEBUG LOGGING
                // error_log(sprintf('Podify API /episodes: feed_id=%d limit=%d offset=%d cat=%s q=%s', $feed_id, $limit, $offset, is_null($cat_raw)?'null':$cat_raw, $q));

                if ($limit <= 0 || $limit > 500) $limit = 9;
                if ($offset < 0) $offset = 0;
                $use_adv = ($q !== '') || $has_audio || ($category_id > 0) || $uncategorized || ($orderby !== '') || ($order !== '');
                if ($use_adv) {
                    $opts = [
                        'feed_id' => $feed_id ?: null,
                        'limit' => $limit,
                        'offset' => $offset,
                        'category_id' => $category_id ?: null,
                        'uncategorized' => $uncategorized,
                        'q' => $q,
                        'has_audio' => $has_audio,
                        'orderby' => $orderby,
                        'order' => $order
                    ];
                    $rows = \PodifyPodcast\Core\Database::get_episodes_advanced($opts);
                    $total = \PodifyPodcast\Core\Database::count_episodes_advanced($opts);
                } else {
                    $rows = \PodifyPodcast\Core\Database::get_episodes($feed_id ?: null, $limit, $offset, null);
                    $total = \PodifyPodcast\Core\Database::count_episodes($feed_id ?: null, $category_id ?: null);
                }
                $items = [];
                if (is_array($rows)) {
                    foreach ($rows as $r) {
                        $pid = !empty($r['post_id']) ? intval($r['post_id']) : 0;
                        $audio = !empty($r['audio_url']) && wp_http_validate_url($r['audio_url']) ? esc_url($r['audio_url']) : '';
                        $image = !empty($r['image_url']) && wp_http_validate_url($r['image_url']) ? esc_url($r['image_url']) : '';
                        if ($pid > 0) {
                            $maudio = get_post_meta($pid, '_podify_audio_url', true);
                            $mimage = get_post_meta($pid, '_podify_episode_image', true);
                            // if (!empty($maudio) && wp_http_validate_url($maudio)) { $audio = esc_url($maudio); }
                            if (has_post_thumbnail($pid)) {
                                $thumb = get_the_post_thumbnail_url($pid, 'large');
                                if ($thumb) { $image = esc_url($thumb); }
                            } elseif (!empty($mimage) && wp_http_validate_url($mimage)) { 
                                $image = esc_url($mimage); 
                            }
                        }
                        $permalink = '';
                        if ($pid > 0) {
                            $status = get_post_status($pid);
                            if ($status && !in_array($status, ['trash', 'auto-draft'])) {
                                $permalink = get_permalink($pid);
                            }
                        }
                        
                        $check_title = !empty($r['title']) ? (string)$r['title'] : '';
                        if (!$permalink && $check_title) {
                            $pts = get_post_types(['public' => true], 'names');
                            
                            // 1. Try exact title
                            $found = get_page_by_title($check_title, OBJECT, $pts);
                            
                            // 2. Try decoded title
                            if (!$found) {
                                $decoded = html_entity_decode($check_title, ENT_QUOTES | ENT_HTML5);
                                if ($decoded !== $check_title) {
                                    $found = get_page_by_title($decoded, OBJECT, $pts);
                                }
                            }

                            // 3. Try slug search
                            if (!$found) {
                                $slug = sanitize_title($check_title);
                                $q = new \WP_Query([
                                    'name' => $slug,
                                    'post_type' => $pts,
                                    'post_status' => 'publish',
                                    'posts_per_page' => 1,
                                    'fields' => 'ids'
                                ]);
                                if ($q->have_posts()) {
                                    $permalink = get_permalink($q->posts[0]);
                                }
                            } elseif ($found) {
                                $permalink = get_permalink($found->ID);
                            }
                        }
                        if (!$permalink) {
                            $permalink = home_url('/'.sanitize_title($check_title).'/');
                        }
                        $items[] = [
                            'id' => intval($r['id']),
                            'post_id' => $pid,
                            'feed_id' => intval($r['feed_id']),
                            'title' => is_string($r['title']) ? $r['title'] : '',
                            'description' => is_string($r['description']) ? $r['description'] : '',
                            'audio_url' => $audio,
                            'image_url' => $image,
                            'duration' => is_string($r['duration']) ? $r['duration'] : '',
                            'tags' => is_string($r['tags']) ? $r['tags'] : '',
                            'published' => is_string($r['published']) ? $r['published'] : '',
                            'permalink' => $permalink,
                            'categories' => (function($episode_id, $feed_id) {
                                $cats = \PodifyPodcast\Core\Database::get_episode_categories(intval($episode_id));
                                if (empty($cats) && $feed_id) {
                                    $feed_cats = \PodifyPodcast\Core\Database::get_categories(intval($feed_id));
                                    if ($feed_cats) {
                                        foreach ($feed_cats as $fcat) {
                                            if ($fcat['card_bg_color'] || $fcat['button_bg_color'] || $fcat['title_color'] || $fcat['desc_color']) {
                                                $cats[] = $fcat;
                                                break;
                                            }
                                        }
                                    }
                                }
                                return array_map(function($c) {
                                    $full = \PodifyPodcast\Core\Database::get_category(intval($c['id']));
                                    return [
                                        'id'=>intval($c['id']),
                                        'name'=>is_string($c['name'])?$c['name']:'',
                                        'slug'=>is_string($c['slug'])?$c['slug']:'',
                                        'colors' => $full ? [
                                            'card_bg_color' => $full['card_bg_color'],
                                            'title_color' => $full['title_color'],
                                            'desc_color' => $full['desc_color'],
                                            'button_bg_color' => $full['button_bg_color'],
                                            'button_text_color' => $full['button_text_color'],
                                            'load_more_bg_color' => $full['load_more_bg_color'],
                                            'load_more_text_color' => $full['load_more_text_color'],
                                            'load_more_bg_hover_color' => $full['load_more_bg_hover_color'],
                                        'load_more_text_hover_color' => $full['load_more_text_hover_color'],
                                        'title_font' => $full['title_font'],
                                        'title_letter_spacing' => $full['title_letter_spacing'],
                                        'title_line_height' => $full['title_line_height'],
                                    ] : null
                                    ]; 
                                }, $cats);
                            })(intval($r['id']), intval($r['feed_id'] ?? 0)),
                        ];
                    }
                }
                $resp = new \WP_REST_Response([
                    'ok' => true,
                    'items' => $items,
                    'next_offset' => $offset + (is_array($rows) ? count($rows) : 0),
                    'total_count' => intval($total)
                ]);
                $resp->header('Content-Type', 'application/json; charset=utf-8');
                return $resp;
            }
        ]);
        register_rest_route('podify/v1','/categories',[
            'methods' => 'GET',
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                $rows = \PodifyPodcast\Core\Database::get_categories($feed_id ?: null);
                return ['ok' => true, 'items' => is_array($rows)?$rows:[]];
            }
        ]);
        register_rest_route('podify/v1','/update-category',[
            'methods' => 'POST',
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'callback' => function(\WP_REST_Request $req) {
                $id = intval($req->get_param('id'));
                $name = (string)$req->get_param('name');
                $feed_id = $req->get_param('feed_id');
                $colors = $req->get_param('colors');
                
                if (!$id || trim($name) === '') return ['ok'=>false,'message'=>'Invalid payload'];
                
                $fid = is_numeric($feed_id) ? intval($feed_id) : null;
                $color_data = is_array($colors) ? $colors : [];
                
                $ok = \PodifyPodcast\Core\Database::update_category($id, $name, $fid, $color_data);
                if (!$ok) {
                    $err = \PodifyPodcast\Core\Database::last_error();
                    return ['ok' => false, 'message' => $err ?: 'Update failed'];
                }
                
                $updated_cat = \PodifyPodcast\Core\Database::get_category($id);
                return [
                    'ok' => true,
                    'slug' => $updated_cat ? $updated_cat['slug'] : '',
                    'colors' => $color_data
                ];
            }
        ]);
        register_rest_route('podify/v1','/delete-category',[
            'methods' => 'POST',
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'callback' => function(\WP_REST_Request $req) {
                $id = intval($req->get_param('id'));
                if (!$id) return ['ok'=>false,'message'=>'Invalid payload'];
                $ok = \PodifyPodcast\Core\Database::delete_category($id);
                return ['ok' => (bool)$ok];
            }
        ]);
        register_rest_route('podify/v1','/assign-category',[
            'methods' => 'POST',
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'callback' => function(\WP_REST_Request $req) {
                $episode_id = intval($req->get_param('episode_id'));
                $category_id = intval($req->get_param('category_id'));
                if (!$episode_id || !$category_id) return ['ok'=>false,'message'=>'Invalid payload'];
                $ok = \PodifyPodcast\Core\Database::assign_episode_category($episode_id, $category_id);
                return ['ok' => (bool)$ok];
            }
        ]);
        register_rest_route('podify/v1','/bulk-assign-category',[
            'methods' => 'POST',
            'permission_callback' => function() { return current_user_can('manage_options'); },
            'callback' => function(\WP_REST_Request $req) {
                $episode_ids = $req->get_param('episode_ids');
                $category_id = intval($req->get_param('category_id'));
                if (!is_array($episode_ids) || empty($episode_ids) || !$category_id) {
                    return ['ok'=>false,'message'=>'Invalid payload'];
                }
                $count = 0;
                foreach ($episode_ids as $eid) {
                    if (\PodifyPodcast\Core\Database::assign_episode_category(intval($eid), $category_id)) {
                        $count++;
                    }
                }
                return ['ok' => true, 'count' => $count];
            }
        ]);
        register_rest_route('podify/v1','/feed-options',[
            'methods' => 'POST',
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
            'callback' => function(\WP_REST_Request $req) {
                $feed_id = intval($req->get_param('feed_id'));
                $options = $req->get_param('options');
                if (!$feed_id || !is_array($options)) {
                    return ['ok' => false, 'message' => 'Invalid payload'];
                }
                \PodifyPodcast\Core\Database::update_feed_options($feed_id, $options);
                $interval = isset($options['interval']) ? $options['interval'] : 'hourly';
                \PodifyPodcast\Core\Cron\CronInit::clear_feed($feed_id);
                \PodifyPodcast\Core\Cron\CronInit::schedule_feed($feed_id, $interval);
                return ['ok' => true];
            }
        ]);
    }
}
