<?php
namespace PodifyPodcast\Core;

class Importer {
    private static function clean_url($u) {
        $s = is_string($u) ? trim($u) : '';
        if ($s === '') return '';
        $s = trim($s, " \t\n\r\0\x0B`'\"");
        $s = esc_url_raw($s);
        return $s ?: '';
    }
    private static function resolve_url($u, $base) {
        $u = is_string($u) ? trim($u) : '';
        if ($u === '') return '';
        if (preg_match('/^\/\//', $u)) {
            return 'https:' . $u;
        }
        if (preg_match('/^[a-z]+:\/\//i', $u)) {
            return $u;
        }
        if ($base) {
            $bp = parse_url($base);
            $scheme = isset($bp['scheme']) ? $bp['scheme'] : 'https';
            $host = isset($bp['host']) ? $bp['host'] : '';
            $path = isset($bp['path']) ? $bp['path'] : '';
            if ($u[0] === '/') {
                return $scheme . '://' . $host . $u;
            }
            $dir = rtrim(dirname($path), '/');
            return $scheme . '://' . $host . ($dir ? ('/'.$dir) : '') . '/' . $u;
        }
        return $u;
    }

    private static function set_featured_image($post_id, $image_url) {
        if (!$post_id || !$image_url) return;
        
        // Check if post already has a featured image with the same source URL
        $current_thumb_id = get_post_thumbnail_id($post_id);
        if ($current_thumb_id) {
            $source_url = get_post_meta($current_thumb_id, '_podify_source_url', true);
            if ($source_url === $image_url) {
                return; // Same image already set
            }
        }
        
        // Check if we have an attachment for this URL to avoid re-uploading
        global $wpdb;
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_podify_source_url' AND meta_value = %s LIMIT 1",
            $image_url
        ));
        
        if ($attachment_id) {
            set_post_thumbnail($post_id, $attachment_id);
            return;
        }

        // Need to sideload
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Sideload
        $desc = "Imported from " . basename($image_url);
        try {
            $id = media_sideload_image($image_url, $post_id, $desc, 'id');

            if (!is_wp_error($id)) {
                set_post_thumbnail($post_id, $id);
                update_post_meta($id, '_podify_source_url', $image_url);
            } else {
                Logger::error('Importer: Failed to sideload image for post '.$post_id.': ' . $id->get_error_message());
            }
        } catch (\Throwable $e) {
            Logger::error('Importer: Exception during image sideload for post '.$post_id.': ' . $e->getMessage());
        } catch (\Exception $e) {
            Logger::error('Importer: Exception during image sideload for post '.$post_id.': ' . $e->getMessage());
        }
    }

    public static function import_feed($feed_id, $force = false) {
        $lock_key = 'podify_sync_lock_' . $feed_id;
        if (get_transient($lock_key)) {
            Logger::log("Sync already in progress for feed $feed_id. Skipping.");
            return ['ok' => false, 'message' => 'Sync already in progress'];
        }
        set_transient($lock_key, true, 1800); // 30 min lock

        Logger::log("Starting import for feed $feed_id (Force: " . ($force?'Yes':'No') . ")");
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        $feed_id = intval($feed_id);
        if (!$feed_id) {
            return ['ok' => false, 'message' => 'Invalid feed_id'];
        }
        $feed = Database::get_feed($feed_id);
        if (!$feed || empty($feed['feed_url'])) {
            Logger::log("Feed $feed_id not found or has no URL");
            delete_transient($lock_key);
            return ['ok' => false, 'message' => 'Feed not found'];
        }
        $options = [];
        if (!empty($feed['options'])) {
            $dec = json_decode($feed['options'], true);
            if (is_array($dec)) $options = $dec;
        }
        $url = esc_url_raw($feed['feed_url']);
        Logger::log("Fetching feed URL: $url");
        $resp = wp_remote_get($url, ['timeout' => 30, 'headers' => ['Accept' => 'application/rss+xml, application/xml;q=0.9, */*;q=0.8']]);
        if (is_wp_error($resp)) {
            $error_msg = $resp->get_error_message();
            Logger::error("Import error for feed $feed_id ($url): " . $error_msg);
            delete_transient($lock_key);
            return ['ok' => false, 'message' => 'Failed to fetch feed: ' . $error_msg];
        }
        $response_code = wp_remote_retrieve_response_code($resp);
        if ($response_code !== 200) {
            $error_msg = "HTTP $response_code";
            Logger::error("Feed fetch failed for $feed_id ($url) with status code: $response_code");
            delete_transient($lock_key);
            return ['ok' => false, 'message' => "Failed to fetch feed ($error_msg)"];
        }
        $body = wp_remote_retrieve_body($resp);
        if (!$body) {
            Logger::error("Feed response body is empty");
            delete_transient($lock_key);
            return ['ok' => false, 'message' => 'Empty feed response'];
        }
        Logger::log("Feed fetched successfully. Body length: " . strlen($body));
        
        // OPTIMIZATION: Suspend cache and defer counting to save memory/CPU
        if (function_exists('wp_suspend_cache_addition')) wp_suspend_cache_addition(true);
        if (function_exists('wp_defer_term_counting')) wp_defer_term_counting(true);
        if (function_exists('wp_defer_comment_counting')) wp_defer_comment_counting(true);

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                Logger::error("XML Parse Error for feed $feed_id: " . $error->message);
            }
            libxml_clear_errors();
            // Restore settings
            if (function_exists('wp_suspend_cache_addition')) wp_suspend_cache_addition(false);
            if (function_exists('wp_defer_term_counting')) wp_defer_term_counting(false);
            if (function_exists('wp_defer_comment_counting')) wp_defer_comment_counting(false);
            delete_transient($lock_key);
            return ['ok' => false, 'message' => 'Invalid RSS XML'];
        }

        $channel = isset($xml->channel) ? $xml->channel : $xml;
        
        // Extract and update feed title
        $feed_title = isset($channel->title) ? trim((string)$channel->title) : '';
        if ($feed_title) {
            global $wpdb;
            $wpdb->update(
                "{$wpdb->prefix}podify_podcast_feeds", 
                ['title' => $feed_title], 
                ['id' => $feed_id]
            );
        }

        $defaultImage = '';
        if (isset($channel->image) && isset($channel->image->url)) {
            $defaultImage = (string)$channel->image->url;
        }
        $cItunes = $channel->children('itunes', true);
        if (!$defaultImage && $cItunes && isset($cItunes->image) && isset($cItunes->image['href'])) {
            $defaultImage = (string)$cItunes->image['href'];
        }
        $atom = $channel->children('http://www.w3.org/2005/Atom');

        $count = 0;
        $items_to_process = [];
        global $wpdb;
        $table = "{$wpdb->prefix}podify_podcast_episodes";

        $total_items = isset($channel->item) ? count($channel->item) : 0;
        set_transient('podify_import_progress_'.$feed_id, [
            'total' => $total_items,
            'current' => 0,
            'percentage' => 0,
            'status' => 'Starting import...'
        ], 3600);

        foreach ($channel->item as $item) {
            // Throttle CPU usage slightly to avoid 503 Service Unavailable on shared hosting
            usleep(10000); // 10ms pause per item
            
            $title = trim((string)$item->title);
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
            $pubRaw = (string)$item->pubDate;
            $published = $pubRaw ? date('Y-m-d H:i:s', strtotime($pubRaw)) : current_time('mysql');
            $audio = '';
            if (!empty($item->enclosure)) {
                $encAttrs = $item->enclosure->attributes();
                if ($encAttrs && isset($encAttrs['url'])) {
                    $audio = (string)$encAttrs['url'];
                } elseif (isset($item->enclosure['url'])) {
                    $audio = (string)$item->enclosure['url'];
                } else {
                    $audioStr = trim((string)$item->enclosure);
                    if ($audioStr && preg_match('/https?:\/\/\S+\.(mp3|m4a|ogg|wav)/i', $audioStr, $m)) {
                        $audio = $m[0];
                    }
                }
            }
            if (!$audio && !empty($options['audio_field'])) {
                $key = trim((string)$options['audio_field']);
                if ($key !== '') {
                    $node = isset($item->{$key}) ? $item->{$key} : null;
                    if ($node) {
                        if (isset($node['url'])) { $audio = (string)$node['url']; }
                        elseif (isset($node['href'])) { $audio = (string)$node['href']; }
                        else {
                            $cand = trim((string)$node);
                            if ($cand && preg_match('/(https?:)?\/\/\S+\.(mp3|m4a|ogg|wav)/i', $cand, $mm)) {
                                $audio = $mm[0];
                            }
                        }
                    } else {
                        $raw = trim((string)$item->asXML());
                        if ($raw) {
                            if (preg_match('/<'.$key.'[^>]*?(?:url|href)=["\']([^"\']+\.(?:mp3|m4a|ogg|wav)[^"\']*)["\']/i', $raw, $ma)) {
                                $audio = $ma[1];
                            } elseif (preg_match('/<'.$key.'[^>]*?>([^<]+\.(?:mp3|m4a|ogg|wav)[^<]*)<\/'.$key.'>/i', $raw, $mb)) {
                                $audio = $mb[1];
                            }
                        }
                    }
                }
            }
            if (!$audio) {
                $media = $item->children('media', true);
                if (!$media || (!isset($media->thumbnail) && !isset($media->content) && !isset($media->group))) {
                    $media = $item->children('http://search.yahoo.com/mrss/');
                }
                if ($media && isset($media->group)) {
                    $group = $media->group;
                    if ($group) {
                        foreach ($group->content as $mc) {
                            $href = isset($mc['url']) ? (string)$mc['url'] : '';
                            $type = isset($mc['type']) ? strtolower((string)$mc['type']) : '';
                            if ($href && (strpos($type, 'audio') !== false || preg_match('/\.(mp3|m4a|ogg|wav)(\?.*)?$/i', $href))) {
                                $audio = $href;
                                break;
                            }
                        }
                    }
                }
                if (!$audio && $media && isset($media->content)) {
                    foreach ($media->content as $mc) {
                        $href = isset($mc['url']) ? (string)$mc['url'] : '';
                        $type = isset($mc['type']) ? strtolower((string)$mc['type']) : '';
                        if ($href && (strpos($type, 'audio') !== false || preg_match('/\.(mp3|m4a|ogg|wav)(\?.*)?$/i', $href))) {
                            $audio = $href;
                            break;
                        }
                    }
                }
            }
            $image = '';
            if ($itunes && isset($itunes->image)) {
                $imgAttrs = $itunes->image->attributes();
                if ($imgAttrs && isset($imgAttrs['href'])) {
                    $image = (string)$imgAttrs['href'];
                } else {
                    $imageStr = trim((string)$itunes->image);
                    if ($imageStr) $image = $imageStr;
                }
            }
            if (!$image) {
                $media = $item->children('media', true);
                if (!$media || (!isset($media->thumbnail) && !isset($media->content) && !isset($media->group))) {
                    $media = $item->children('http://search.yahoo.com/mrss/');
                }
                if ($media && isset($media->thumbnail)) {
                    $thumbAttrs = $media->thumbnail->attributes();
                    if ($thumbAttrs && isset($thumbAttrs['url'])) {
                        $image = (string)$thumbAttrs['url'];
                    } elseif (isset($media->thumbnail['url'])) {
                        $image = (string)$media->thumbnail['url'];
                    }
                }
                if (!$image && $media && isset($media->group)) {
                    $group = $media->group;
                    if ($group && isset($group->thumbnail)) {
                        $gThumbAttrs = $group->thumbnail->attributes();
                        if ($gThumbAttrs && isset($gThumbAttrs['url'])) {
                            $image = (string)$gThumbAttrs['url'];
                        } elseif (isset($group->thumbnail['url'])) {
                            $image = (string)$group->thumbnail['url'];
                        }
                    }
                }
                if (!$image && $media && isset($media->content)) {
                    foreach ($media->content as $mc) {
                        $href = isset($mc['url']) ? (string)$mc['url'] : '';
                        $type = isset($mc['type']) ? strtolower((string)$mc['type']) : '';
                        if ($href && (strpos($type, 'image') !== false || preg_match('/\.(jpg|jpeg|png|gif|webp)(\?.*)?$/i', $href))) {
                            $image = $href;
                            break;
                        }
                    }
                }
            }
            if (!$image) {
                $content = $item->children('content', true);
                $html = '';
                if ($content && isset($content->encoded)) {
                    $html = (string)$content->encoded;
                } elseif (!empty($desc)) {
                    $html = $desc;
                }
                if ($html && preg_match('/<img[^>]+src=[\'"]([^\'"]+)/i', $html, $mimg)) {
                    $image = $mimg[1];
                }
            }
            $duration = '';
            if ($itunes && isset($itunes->duration)) {
                $duration = trim((string)$itunes->duration);
            }
            if (!$duration) {
                $durNode = isset($item->duration) ? trim((string)$item->duration) : '';
                if ($durNode) $duration = $durNode;
            }
            if (!$audio) {
                $aAtom = $item->children('http://www.w3.org/2005/Atom');
                if ($aAtom) {
                    foreach ($aAtom->link as $lnk) {
                        $lnkAttrs = $lnk->attributes();
                        $rel = isset($lnkAttrs['rel']) ? strtolower((string)$lnkAttrs['rel']) : (isset($lnk['rel']) ? strtolower((string)$lnk['rel']) : '');
                        $href = isset($lnkAttrs['href']) ? (string)$lnkAttrs['href'] : (isset($lnk['href']) ? (string)$lnk['href'] : '');
                        $type = isset($lnkAttrs['type']) ? strtolower((string)$lnkAttrs['type']) : (isset($lnk['type']) ? strtolower((string)$lnk['type']) : '');
                        if ($href && ($rel === 'enclosure' || strpos($type, 'audio') !== false) && preg_match('/\.(mp3|m4a|ogg|wav)(\?.*)?$/i', $href)) {
                            $audio = $href;
                            break;
                        }
                    }
                }
            }
            if (!$audio) {
                $raw = trim((string)$item->asXML());
                if ($raw) {
                    if (preg_match('/(?:url|href|src)\\s*=\\s*["\']([^"\']+\\.(?:mp3|m4a|ogg|wav)[^"\']*)["\']/i', $raw, $ma)) {
                        $audio = $ma[1];
                    } elseif (preg_match('/https?:\\/\\/[^\\s"\'<]+\\.(?:mp3|m4a|ogg|wav)[^\\s"\'<]*/i', $raw, $mb)) {
                        $audio = $mb[0];
                    }
                }
            }
            if (!$audio && !empty($item->link)) {
                $link = (string)$item->link;
                if ($link && preg_match('/\.(mp3|m4a|ogg|wav)(\?.*)?$/i', $link)) {
                    $audio = $link;
                }
            }
            if (!$audio && !empty($item->guid)) {
                $guid = (string)$item->guid;
                if ($guid && preg_match('/\.(mp3|m4a|ogg|wav)(\?.*)?$/i', $guid)) {
                    $audio = $guid;
                }
            }
            if (!$audio && $desc) {
                if (preg_match('/https?:\/\/\S+\.(mp3|m4a|ogg|wav)/i', $desc, $m)) {
                    $audio = $m[0];
                }
            }
            if ($audio) {
                $audio = self::resolve_url($audio, (string)$item->link);
            }
            if ($audio) {
                Logger::log('Audio detected: '.$audio);
            }
            if (!$audio && $desc) {
                $raw = strip_tags($desc);
                if ($raw && preg_match('/https?:\\/\\/[^\\s"\'<]+\\.(?:mp3|m4a|ogg|wav)[^\\s"\'<]*/i', $raw, $md)) {
                    $audio = $md[0];
                }
            }
            $tagsArr = [];
            $categoriesArr = [];
            foreach ($item->category as $cat) {
                $name = trim((string)$cat);
                if ($name) {
                    $tagsArr[] = $name;
                    $categoriesArr[] = $name;
                }
            }
            if ($itunes && isset($itunes->category)) {
                foreach ($itunes->category as $ic) {
                    $atts = $ic->attributes();
                    if ($atts && isset($atts['text'])) {
                        $name = trim((string)$atts['text']);
                        if ($name) $categoriesArr[] = $name;
                    }
                }
            }
            $tags = $tagsArr ? implode(',', array_slice($tagsArr, 0, 6)) : '';
            $categoriesArr = array_unique($categoriesArr);

            if (!$image && $defaultImage) {
                $image = $defaultImage;
            }
            $image = self::clean_url($image);
            $audio = self::clean_url($audio);
            if ($audio && !wp_http_validate_url($audio)) {
                Logger::log('Importer: Invalid audio URL filtered: '.$audio);
                $audio = '';
            }
            if ($image && !wp_http_validate_url($image)) {
                Logger::log('Importer: Invalid image URL filtered: '.$image);
                $image = '';
            }
            if ($image) {
                Logger::log('Episode image: '.$image);
            }
            $need_image = (!$image || $image === self::clean_url($defaultImage));
            if ( ($need_image || !$audio) && !empty($item->link) ) {
                $pageUrl = (string)$item->link;
                if ($pageUrl) {
                    $hresp = wp_remote_get($pageUrl, ['timeout' => 6]);
                    if (!is_wp_error($hresp)) {
                        $html = wp_remote_retrieve_body($hresp);
                        if ($html) {
                            if ($need_image) {
                                if (preg_match('/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $mog)) {
                                    $ogimg = trim($mog[1]);
                                    if ($ogimg) $image = $ogimg;
                                } elseif (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $mimg2)) {
                                    $image = trim($mimg2[1]);
                                }
                            }
                            if (!$audio) {
                                if (preg_match('/<audio[^>]+src=["\']([^"\']+\.(mp3|m4a|ogg|wav))["\']/i', $html, $ma)) {
                                    $audio = trim($ma[1]);
                                } elseif (preg_match('/<source[^>]+src=["\']([^"\']+\.(mp3|m4a|ogg|wav))["\']/i', $html, $ms)) {
                                    $audio = trim($ms[1]);
                                } elseif (preg_match('/<a[^>]+href=["\']([^"\']+\.(mp3|m4a|ogg|wav))["\']/i', $html, $mh)) {
                                    $audio = trim($mh[1]);
                                }
                                if ($audio) { $audio = self::resolve_url($audio, $pageUrl); }
                            }
                        }
                    }
                }
            }

            $rowId = 0;
            $post_id = 0;

            // Always check for existing to avoid duplicates
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE feed_id=%d AND title=%s AND published=%s LIMIT 1",
                $feed_id, $title, $published
            ), ARRAY_A);
            
            if ($existing && !empty($existing['id'])) {
                $rowId = intval($existing['id']);
                $post_id = intval($existing['post_id']);
                
                // Verify post still exists and is a valid post (not an attachment)
                if ($post_id > 0) {
                    $post = get_post($post_id);
                    $expected_pt = !empty($options['post_type']) ? $options['post_type'] : 'post';
                    if (!$post || $post->post_type !== $expected_pt || $post->post_status === 'trash') {
                        $post_id = 0; // Post missing or invalid type, needs recreation
                    }
                }

                // Change Detection for Internal DB
                $changed = false;
                $update = [];
                if ($audio && $audio !== ($existing['audio_url']??'')) { $update['audio_url'] = $audio; $changed = true; }
                if ($image && $image !== ($existing['image_url']??'')) { $update['image_url'] = $image; $changed = true; }
                if ($duration && $duration !== ($existing['duration']??'')) { $update['duration'] = $duration; $changed = true; }
                if ($tags && $tags !== ($existing['tags']??'')) { $update['tags'] = $tags; $changed = true; }
                if ($desc && $desc !== ($existing['description']??'')) { $update['description'] = $desc; $changed = true; }
                
                // If we found the post was missing, update the DB record too
                if ($post_id === 0 && intval($existing['post_id']) > 0) {
                    $update['post_id'] = 0;
                    $changed = true;
                }

                if (!empty($update)) {
                    $wpdb->update($table, $update, ['id' => intval($rowId)]);
                }

                // Only process Phase 2 if something changed OR post is missing OR we are forcing
                $needs_processing = ($changed || $post_id === 0 || $force);
                
                if (!$needs_processing) {
                    $count++;
                    continue; // Already exists and data matches, skip to next
                }
            }

            if (!$rowId) {
                    if (!$audio) { Logger::warning('Importer: No audio detected for episode "'.$title.'" (feed '.$feed_id.')'); }
                    $inserted = $wpdb->insert($table, [
                        'feed_id' => $feed_id,
                        'title' => $title,
                        'description' => $desc,
                        'audio_url' => $audio,
                        'image_url' => $image ?: (!empty($options['featured_image']) ? self::clean_url($options['featured_image']) : ''),
                        'duration' => $duration,
                        'tags' => $tags,
                        'categories' => $categoriesArr,
                        'published' => $published,
                    ]);
                    if ($inserted === false) {
                        Logger::error("Importer: Failed to insert episode into database: $title. SQL Error: " . $wpdb->last_error);
                        continue; // Skip processing if database insert failed
                    }
                    $rowId = intval($wpdb->insert_id);
                }

            if ($rowId) {
                $items_to_process[] = [
                    'row_id' => $rowId,
                    'post_id' => $post_id,
                    'title' => $title,
                    'description' => $desc,
                    'audio' => $audio,
                    'image' => $image,
                    'duration' => $duration,
                    'tags' => $tags,
                    'published' => $published,
                    'options' => $options,
                    'feed_id' => $feed_id
                ];
            }

            $count++;
            // Update progress (Phase 1: 0-50%)
            if ($count % 10 === 0 || $count === $total_items) {
                $pct = ($total_items > 0) ? round(($count / $total_items) * 50) : 0;
                set_transient('podify_import_progress_'.$feed_id, [
                    'total' => $total_items,
                    'current' => $count,
                    'percentage' => $pct,
                    'status' => 'Phase 1: Syncing Data...'
                ], 3600);
            }
        }

        // Phase 2: Post Creation
        $count_posts = 0;
        $total_posts = count($items_to_process);
        
        foreach ($items_to_process as $item_data) {
            // Site Performance Guard: Throttle CPU and clear memory every 5 items
            if ($count_posts % 5 === 0) {
                usleep(100000); // 100ms pause to let other site processes run
                if (function_exists('wp_cache_flush_runtime')) {
                    wp_cache_flush_runtime(); // Clear runtime cache to prevent memory bloat
                }
            }

            $count_posts++;
            $rowId = $item_data['row_id'];
            $post_id = $item_data['post_id'];
            $title = $item_data['title'];
            $desc = $item_data['description'];
            $audio = $item_data['audio'];
            $image = $item_data['image'];
            $duration = $item_data['duration'];
            $tags = $item_data['tags'];
            $categories = isset($item_data['categories']) ? $item_data['categories'] : [];
            
            // Check for manually assigned categories in internal DB and prioritize them
            $internal_cats = Database::get_episode_categories($rowId);
            if (!empty($internal_cats)) {
                $categories = array_map(function($c) { return $c['name']; }, $internal_cats);
            }

            $published = $item_data['published'];
            $options = $item_data['options'];
            $feed_id = $item_data['feed_id'];

            if ($post_id > 0) {
                 $post = get_post($post_id);
                 if ($post) {
                     // Only update post content if it actually changed
                     if ($post->post_title !== $title || $post->post_content !== $desc) {
                         wp_update_post([
                             'ID' => $post_id,
                             'post_title' => $title,
                             'post_content' => $desc,
                         ]);
                         Logger::log("Updated existing post content for $post_id: $title");
                     }
                 }
                 
                 if ($audio) {
                     $old_audio = get_post_meta($post_id, '_podify_audio_url', true);
                     if ($old_audio !== $audio) {
                         update_post_meta($post_id, '_podify_audio_url', esc_url_raw($audio)); 
                         Logger::log("Updated audio for post $post_id");
                     }
                 }
                 if ($image) { 
                     $old_img = get_post_meta($post_id, '_podify_episode_image', true);
                     if ($old_img !== $image) {
                         update_post_meta($post_id, '_podify_episode_image', esc_url_raw($image)); 
                         self::set_featured_image($post_id, $image);
                     }
                 }
                 if ($duration) { 
                     $old_dur = get_post_meta($post_id, '_podify_duration', true);
                     if ($old_dur !== $duration) {
                         update_post_meta($post_id, '_podify_duration', sanitize_text_field($duration)); 
                     }
                 }
                 if ($tags) { 
                     $old_tags = get_post_meta($post_id, '_podify_tags', true);
                     if ($old_tags !== $tags) {
                         update_post_meta($post_id, '_podify_tags', sanitize_text_field($tags)); 
                     }
                 }
                 if ($feed_id) { 
                     $old_feed = get_post_meta($post_id, 'podify_feed_id', true);
                     if (intval($old_feed) !== intval($feed_id)) {
                         update_post_meta($post_id, 'podify_feed_id', intval($feed_id)); 
                     }
                 }
                 
                 // Sync Categories
                 if (!empty($categories)) {
                     $current_terms = wp_get_object_terms($post_id, 'category', ['fields' => 'names']);
                     $diff1 = array_diff($categories, $current_terms);
                     $diff2 = array_diff($current_terms, $categories);
                     
                     if (!empty($diff1) || !empty($diff2)) {
                         $cat_ids = [];
                         foreach ($categories as $cname) {
                             $term = term_exists($cname, 'category');
                             if (!$term) { $term = wp_insert_term($cname, 'category'); }
                             if (!is_wp_error($term) && isset($term['term_id'])) { $cat_ids[] = intval($term['term_id']); }
                         }
                         if (!empty($cat_ids)) { wp_set_object_terms($post_id, $cat_ids, 'category', false); }
                     }
                 }
            } else {
                 $pt = !empty($options['post_type']) ? sanitize_key($options['post_type']) : 'post';
                 $ps = !empty($options['post_status']) ? sanitize_key($options['post_status']) : 'publish';
                 $pa = !empty($options['post_author']) ? intval($options['post_author']) : 0;
                 $postarr = [
                     'post_title' => $title,
                     'post_content' => $desc,
                     'post_type' => $pt,
                     'post_status' => $ps,
                     'post_author' => $pa,
                     'post_date' => $published ?: current_time('mysql'),
                 ];
                 $new_post_id = wp_insert_post($postarr, true);
                 if (!is_wp_error($new_post_id) && $new_post_id) {
                     Logger::log("Created new WP post $new_post_id for episode: $title");
                     $wpdb->update($table, ['post_id' => intval($new_post_id)], ['id' => intval($rowId)]);
                     if ($audio) { update_post_meta($new_post_id, '_podify_audio_url', esc_url_raw($audio)); }
                     if ($image) { 
                         update_post_meta($new_post_id, '_podify_episode_image', esc_url_raw($image)); 
                         self::set_featured_image($new_post_id, $image);
                     }
                     if ($duration) { update_post_meta($new_post_id, '_podify_duration', sanitize_text_field($duration)); }
                     if ($tags) { update_post_meta($new_post_id, '_podify_tags', sanitize_text_field($tags)); }
                     if ($feed_id) { update_post_meta($new_post_id, 'podify_feed_id', intval($feed_id)); }
                     
                     // Sync Categories
                     if (!empty($categories)) {
                         $cat_ids = [];
                         foreach ($categories as $cname) {
                             $term = term_exists($cname, 'category');
                             if (!$term) {
                                 $term = wp_insert_term($cname, 'category');
                             }
                             if (!is_wp_error($term) && isset($term['term_id'])) {
                                 $cat_ids[] = intval($term['term_id']);
                             }
                         }
                         if (!empty($cat_ids)) {
                             // Use false to REPLACE existing categories (removing Uncategorized)
                             wp_set_object_terms($new_post_id, $cat_ids, 'category', false);
                         }
                     }
                 } else {
                     $error_msg = is_wp_error($new_post_id) ? $new_post_id->get_error_message() : 'Unknown error';
                     Logger::error('Importer: Failed to create WP post for episode "'.$title.'". Error: ' . $error_msg);
                 }
            }

            if ($count_posts % 5 === 0 || $count_posts === $total_posts) {
                $pct = 50 + (($total_posts > 0) ? round(($count_posts / $total_posts) * 50) : 0);
                set_transient('podify_import_progress_'.$feed_id, [
                    'total' => $total_items,
                    'current' => $count_posts,
                    'percentage' => $pct,
                    'status' => 'Phase 2: Creating Posts...'
                ], 3600);
                 if ($pct % 20 === 0) {
                    Logger::log("Feed $feed_id Phase 2 progress: $pct% ($count_posts/$total_posts)");
                }
            }
        }
        delete_transient('podify_import_progress_'.$feed_id);
        
        // Restore optimization settings
        if (function_exists('wp_suspend_cache_addition')) wp_suspend_cache_addition(false);
        if (function_exists('wp_defer_term_counting')) wp_defer_term_counting(false);
        if (function_exists('wp_defer_comment_counting')) wp_defer_comment_counting(false);
        
        Database::set_feed_last_sync($feed_id);
        Logger::log("Import for feed $feed_id completed successfully. Total episodes processed: $count");
        delete_transient($lock_key);
        return ['ok' => true, 'message' => 'Import completed', 'imported' => $count];
    }
    public static function resync_feed($feed_id) {
        global $wpdb;
        $feed_id = intval($feed_id);
        if (!$feed_id) {
            return ['ok' => false, 'message' => 'Invalid feed_id'];
        }
        // Force sync to update all episodes and images
        return self::import_feed($feed_id, true);
    }
}
