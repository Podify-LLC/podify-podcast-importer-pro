<?php
namespace PodifyPodcast\Core\Frontend;

class FrontendInit {
    public static function register() {
        add_shortcode('podify_podcast_list',[self::class,'render_list']);
        add_shortcode('podify_single_player', [self::class, 'render_single_player']);
        add_action('wp_footer', [self::class, 'inject_sticky_player'], 20);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets_global']);
        add_filter('the_content', [self::class, 'inject_single_player'], 100);
    }

    public static function render_single_player($atts) {
        $atts = shortcode_atts([
            'post_id' => 0,
        ], $atts, 'podify_single_player');
        
        $post_id = intval($atts['post_id']);
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$post_id) return '';
        
        self::enqueue_assets();
        
        return self::generate_single_player_html($post_id);
    }

    public static function inject_single_player($content) {
        if (!is_single() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $post_id = get_the_ID();
        $player_html = self::generate_single_player_html($post_id);
        
        if ($player_html) {
            return $player_html . '<div class="podify-episode-content">' . make_clickable($content) . '</div>';
        }

        return $content;
    }
    
    private static function generate_single_player_html($post_id) {
        $audio_url = trim(get_post_meta($post_id, '_podify_audio_url', true));

        if (empty($audio_url)) {
            return '';
        }

        // Validate URL
        if (!wp_http_validate_url($audio_url)) {
            return '';
        }

        $title = get_the_title($post_id);
        $featured_img = get_the_post_thumbnail_url($post_id, 'full');
        $meta_img = get_post_meta($post_id, '_podify_episode_image', true);
        $image = $featured_img ? $featured_img : $meta_img;
        $duration = get_post_meta($post_id, '_podify_duration', true);
        
        $dur_fmt = self::format_duration($duration);
        $dur_sec = self::duration_seconds($duration);
        
        $uniqid = 'podify-sp-' . $post_id . '-' . wp_rand(1000, 9999);

        $player_html = '<div id="'.$uniqid.'" class="podify-single-player podify-single-player-card">';
        $player_html .= '<div class="podify-sp-inner">';
        
        // Cover
        $player_html .= '<div class="podify-sp-cover">';
        if ($image) {
            $player_html .= '<img src="'.esc_url($image).'" alt="'.esc_attr($title).'" loading="lazy" decoding="async" style="width: 100%; height: 100%; object-fit: cover;">';
        } else {
            $player_html .= '<div class="podify-sp-placeholder"></div>';
        }
        $player_html .= '</div>';
        
        // Content
        $player_html .= '<div class="podify-sp-content">';
        $player_html .= '<div class="podify-sp-meta">';
        
        // Channel Title Logic
        $channel_title = '';
        global $wpdb;
        $feed_title = $wpdb->get_var($wpdb->prepare(
            "SELECT f.title FROM {$wpdb->prefix}podify_podcast_feeds f 
             JOIN {$wpdb->prefix}podify_podcast_episodes e ON e.feed_id = f.id 
             WHERE e.post_id = %d LIMIT 1", 
            $post_id
        ));
        
        if ($feed_title) {
            $channel_title = $feed_title;
        } else {
            // Fallback: Check if feed_id is stored in meta
            $feed_id = get_post_meta($post_id, 'podify_feed_id', true);
            if ($feed_id) {
                 $feed_title = $wpdb->get_var($wpdb->prepare(
                    "SELECT title FROM {$wpdb->prefix}podify_podcast_feeds WHERE id = %d LIMIT 1", 
                    $feed_id
                ));
                if ($feed_title) $channel_title = $feed_title;
            }
        }

        if (!$channel_title) {
            $cats = get_the_category($post_id);
            if ($cats && !is_wp_error($cats)) {
                foreach ($cats as $c) {
                     if (strcasecmp($c->name, 'Uncategorized') !== 0) {
                         $channel_title = $c->name;
                         break;
                     }
                }
            }
        }
        
        // Specific fix for "Love Podcast" -> "The Language of Love by Dr. Laura Berman"
        if ($channel_title && (stripos($channel_title, 'love-podcast') !== false || stripos($channel_title, 'Love Podcast') !== false)) {
            $channel_title = 'The Language of Love by Dr. Laura Berman';
        }
        // Formatting: If title looks like a slug (e.g. "love-podcast"), prettify it
        elseif ($channel_title && preg_match('/^[a-z0-9-]+$/i', $channel_title)) {
             $channel_title = ucwords(str_replace('-', ' ', $channel_title));
        }
        
        if (!$channel_title) $channel_title = 'Podcast';
        
        $player_html .= '<span class="podify-sp-label">'.esc_html($channel_title).'</span>';

        $player_html .= '<h3 class="podify-sp-title">'.esc_html($title).'</h3>';
        $player_html .= '</div>';
        
        // Controls
        $player_html .= '<div class="podify-sp-controls" style="display:flex; flex-direction:column; gap:8px; width:100%;">';
        
        // Top Row: Play + Time
        $player_html .= '<div class="podify-sp-controls-top" style="display:flex; justify-content:space-between; align-items:center; width:100%;">';
        
        $player_html .= '<button class="podify-sp-play-btn" aria-label="Play" style="border:none; background:none; padding:0; cursor:pointer; color:#0b5bd3; display:flex; align-items:center; justify-content:center;">';
        $player_html .= '<svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><circle cx="12" cy="12" r="12" fill="currentColor"/><path d="M9.5 8l6 4-6 4V8z" fill="white"/></svg>';
        $player_html .= '</button>';

        $player_html .= '<div style="display:flex; align-items:center; gap:8px;">';
        $player_html .= '<button class="podify-sp-volume-btn" aria-label="Mute" style="border:none; background:none; padding:0; cursor:pointer; color:#64748b; display:flex; align-items:center;">';
        $player_html .= '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M3 9v6h4l5 4V5L7 9H3z"/><path d="M14 8.5a4.5 4.5 0 010 7" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
        $player_html .= '</button>';
        $player_html .= '<input type="range" class="podify-sp-volume-slider" min="0" max="1" step="0.05" value="1" style="width:60px; cursor:pointer;">';
        $player_html .= '<div class="podify-sp-time" style="font-variant-numeric: tabular-nums; font-size: 13px; color: #64748b; font-weight:500;">';
        $player_html .= '<span class="podify-sp-current">0:00</span>';
        $player_html .= '<span class="podify-sp-separator"> / </span>';
        $player_html .= '<span class="podify-sp-duration">'.esc_html($dur_fmt ?: '0:00').'</span>';
        $player_html .= '</div>';
        $player_html .= '</div>'; // End right group
        $player_html .= '</div>'; // End top row
        
        $player_html .= '<div class="podify-sp-progress-container" style="position:relative; width:100%; height:45px; margin-top:4px;">';
        
        // Click Area (Invisible overlay for better seeking)
        $player_html .= '<div class="podify-sp-click-area" style="position:absolute; top:0; left:0; width:100%; height:100%; z-index:10; cursor:pointer;"></div>';
        
        $grad_id = 'podify-grad-' . $uniqid;
        $player_html .= '<svg class="podify-sp-svg" viewBox="0 0 567 45" preserveAspectRatio="none" style="width:100%; height:100%; display:block;">';
        $player_html .= '<defs><linearGradient id="'.$grad_id.'" x1="0" x2="1" y1="0" y2="0"><stop offset="0%" stop-color="#0b5bd3"/><stop offset="0%" stop-color="#e2e8f0"/></linearGradient></defs>';
        $player_html .= '<path fill="url(#'.$grad_id.')" d="M 0 30 L 0 30 A 2.25 2.25 0 0 0 4.5 30 A 2.25 2.25 0 0 1 9 30 L 9 37.14888991369655 A 2.25 2.25 0 0 0 13.5 37.14888991369655 L 13.5 24.81576520192371 A 2.25 2.25 0 0 1 18 24.81576520192371 L 18 40.138563146452334 A 2.25 2.25 0 0 0 22.5 40.138563146452334 L 22.5 19.60218064431122 A 2.25 2.25 0 0 1 27 19.60218064431122 L 27 37.719431451347255 A 2.25 2.25 0 0 0 31.5 37.719431451347255 L 31.5 20.517359509849136 A 2.25 2.25 0 0 1 36 20.517359509849136 L 36 37.415261216153894 A 2.25 2.25 0 0 0 40.5 37.415261216153894 L 40.5 21.577953093089135 A 2.25 2.25 0 0 1 45 21.577953093089135 L 45 37.80570195665064 A 2.25 2.25 0 0 0 49.5 37.80570195665064 L 49.5 21.28045325779037 A 2.25 2.25 0 0 1 54 21.28045325779037 L 54 38.571463864549706 A 2.25 2.25 0 0 0 58.5 38.571463864549706 L 58.5 20.29323407339087 A 2.25 2.25 0 0 1 63 20.29323407339087 L 63 41.12756110415706 A 2.25 2.25 0 0 0 67.5 41.12756110415706 L 67.5 20.563607615784967 A 2.25 2.25 0 0 1 72 20.563607615784967 L 72 41.40238157981422 A 2.25 2.25 0 0 0 76.5 41.40238157981422 L 76.5 19.282890836023455 A 2.25 2.25 0 0 1 81 19.282890836023455 L 81 39.10731932274854 A 2.25 2.25 0 0 0 85.5 39.10731932274854 L 85.5 21.333371763620793 A 2.25 2.25 0 0 1 90 21.333371763620793 L 90 38.17479741748468 A 2.25 2.25 0 0 0 94.5 38.17479741748468 L 94.5 20.75838329270703 A 2.25 2.25 0 0 1 99 20.75838329270703 L 99 38.16279069767442 A 2.25 2.25 0 0 0 103.5 38.16279069767442 L 103.5 21.401854535871927 A 2.25 2.25 0 0 1 108 21.401854535871927 L 108 39.68008432703077 A 2.25 2.25 0 0 0 112.5 39.68008432703077 L 112.5 18.0866657882601 A 2.25 2.25 0 0 1 117 18.0866657882601 L 117 39.63339152776863 A 2.25 2.25 0 0 0 121.5 39.63339152776863 L 121.5 20.792179985506294 A 2.25 2.25 0 0 1 126 20.792179985506294 L 126 39.769912378944596 A 2.25 2.25 0 0 0 130.5 39.769912378944596 L 130.5 20.827755451610777 A 2.25 2.25 0 0 1 135 20.827755451610777 L 135 39.15267804203175 A 2.25 2.25 0 0 0 139.5 39.15267804203175 L 139.5 20.61786020159431 A 2.25 2.25 0 0 1 144 20.61786020159431 L 144 41.08709401146321 A 2.25 2.25 0 0 0 148.5 41.08709401146321 L 148.5 19.70446010936162 A 2.25 2.25 0 0 1 153 19.70446010936162 L 153 39.563574675538575 A 2.25 2.25 0 0 0 157.5 39.563574675538575 L 157.5 19.670663416562356 A 2.25 2.25 0 0 1 162 19.670663416562356 L 162 37.19780617959022 A 2.25 2.25 0 0 0 166.5 37.19780617959022 L 166.5 21.22842413861256 A 2.25 2.25 0 0 1 171 21.22842413861256 L 171 40.35735226299492 A 2.25 2.25 0 0 0 175.5 40.35735226299492 L 175.5 19.413630673957442 A 2.25 2.25 0 0 1 180 19.413630673957442 L 180 39.156680281968505 A 2.25 2.25 0 0 0 184.5 39.156680281968505 L 184.5 21.18262072600303 A 2.25 2.25 0 0 1 189 21.18262072600303 L 189 41.46108109888662 A 2.25 2.25 0 0 0 193.5 41.46108109888662 L 193.5 20.721029053297322 A 2.25 2.25 0 0 1 198 20.721029053297322 L 198 40.01049146847618 A 2.25 2.25 0 0 0 202.5 40.01049146847618 L 202.5 20.52803214968048 A 2.25 2.25 0 0 1 207 20.52803214968048 L 207 38.40826141379537 A 2.25 2.25 0 0 0 211.5 38.40826141379537 L 211.5 20.367497858883986 A 2.25 2.25 0 0 1 216 20.367497858883986 L 216 38.73955794189341 A 2.25 2.25 0 0 0 220.5 38.73955794189341 L 220.5 21.79540812965281 A 2.25 2.25 0 0 1 225 21.79540812965281 L 225 39.56401936886488 A 2.25 2.25 0 0 0 229.5 39.56401936886488 L 229.5 21.9701726068911 A 2.25 2.25 0 0 1 234 21.9701726068911 L 234 43.5 A 2.25 2.25 0 0 0 238.5 43.5 L 238.5 19.463436326503725 A 2.25 2.25 0 0 1 243 19.463436326503725 L 243 41.46908557876013 A 2.25 2.25 0 0 0 247.5 41.46908557876013 L 247.5 19.62930693721589 A 2.25 2.25 0 0 1 252 19.62930693721589 L 252 37.672293958758814 A 2.25 2.25 0 0 0 256.5 37.672293958758814 L 256.5 20.705464786876608 A 2.25 2.25 0 0 1 261 20.705464786876608 L 261 41.823506159826074 A 2.25 2.25 0 0 0 265.5 41.823506159826074 L 265.5 19.11524145200606 A 2.25 2.25 0 0 1 270 19.11524145200606 L 270 39.017046577508395 A 2.25 2.25 0 0 0 274.5 39.017046577508395 L 274.5 20.665887080835365 A 2.25 2.25 0 0 1 279 20.665887080835365 L 279 40.95857764016075 A 2.25 2.25 0 0 0 283.5 40.95857764016075 L 283.5 18.329913037749524 A 2.25 2.25 0 0 1 288 18.329913037749524 L 288 39.46529745042493 A 2.25 2.25 0 0 0 292.5 39.46529745042493 L 292.5 21.292904670926937 A 2.25 2.25 0 0 1 297 21.292904670926937 L 297 39.91532709664668 A 2.25 2.25 0 0 0 301.5 39.91532709664668 L 301.5 21.984402793332897 A 2.25 2.25 0 0 1 306 21.984402793332897 L 306 37.24805652546281 A 2.25 2.25 0 0 0 310.5 37.24805652546281 L 310.5 21.951050793859938 A 2.25 2.25 0 0 1 315 21.951050793859938 L 315 42.894327689571114 A 2.25 2.25 0 0 0 319.5 42.894327689571114 L 319.5 20.79484814546413 A 2.25 2.25 0 0 1 324 20.79484814546413 L 324 39.28830950655511 A 2.25 2.25 0 0 0 328.5 39.28830950655511 L 328.5 19.499011792608208 A 2.25 2.25 0 0 1 333 19.499011792608208 L 333 39.476859476908885 A 2.25 2.25 0 0 0 337.5 39.476859476908885 L 337.5 21.327590750378814 A 2.25 2.25 0 0 1 342 21.327590750378814 L 342 39.62938928783187 A 2.25 2.25 0 0 0 346.5 39.62938928783187 L 346.5 22.48557217207985 A 2.25 2.25 0 0 1 351 22.48557217207985 L 351 39.38792081164767 A 2.25 2.25 0 0 0 355.5 39.38792081164767 L 355.5 20.38617497858884 A 2.25 2.25 0 0 1 360 20.38617497858884 L 360 41.70744120166019 A 2.25 2.25 0 0 0 364.5 41.70744120166019 L 364.5 21.52903682719547 A 2.25 2.25 0 0 1 369 21.52903682719547 L 369 42.287765992489625 A 2.25 2.25 0 0 0 373.5 42.287765992489625 L 373.5 20.093122076553133 A 2.25 2.25 0 0 1 378 20.093122076553133 L 378 40.52989327360169 A 2.25 2.25 0 0 0 382.5 40.52989327360169 L 382.5 20.88556558403057 A 2.25 2.25 0 0 1 387 20.88556558403057 L 387 38.36468146781738 A 2.25 2.25 0 0 0 391.5 38.36468146781738 L 391.5 21.639320772119376 A 2.25 2.25 0 0 1 396 21.639320772119376 L 396 40.53745306014889 A 2.25 2.25 0 0 0 400.5 40.53745306014889 L 400.5 20.017524211081103 A 2.25 2.25 0 0 1 405 20.017524211081103 L 405 39.86774491073193 A 2.25 2.25 0 0 0 409.5 39.86774491073193 L 409.5 22.12581527109823 A 2.25 2.25 0 0 1 414 22.12581527109823 L 414 41.34101390078398 A 2.25 2.25 0 0 0 418.5 41.34101390078398 L 418.5 20.150932208972925 A 2.25 2.25 0 0 1 423 20.150932208972925 L 423 40.17324922590421 A 2.25 2.25 0 0 0 427.5 40.17324922590421 L 427.5 22.434432439554648 A 2.25 2.25 0 0 1 432 22.434432439554648 L 432 42.78982475788919 A 2.25 2.25 0 0 0 436.5 42.78982475788919 L 436.5 18.113792081164767 A 2.25 2.25 0 0 1 441 18.113792081164767 L 441 39.58269648856974 A 2.25 2.25 0 0 0 445.5 39.58269648856974 L 445.5 20.907355557019567 A 2.25 2.25 0 0 1 450 20.907355557019567 L 450 38.47674418604651 A 2.25 2.25 0 0 0 454.5 38.47674418604651 L 454.5 19.196620330720073 A 2.25 2.25 0 0 1 459 19.196620330720073 L 459 39.723664273008765 A 2.25 2.25 0 0 0 463.5 39.723664273008765 L 463.5 20.20607418143488 A 2.25 2.25 0 0 1 468 20.20607418143488 L 468 41.34412675406812 A 2.25 2.25 0 0 0 472.5 41.34412675406812 L 472.5 20.058880690427564 A 2.25 2.25 0 0 1 477 20.058880690427564 L 477 40.14523354634693 A 2.25 2.25 0 0 0 481.5 40.14523354634693 L 481.5 22.182736016865405 A 2.25 2.25 0 0 1 486 22.182736016865405 L 486 39.776138085512876 A 2.25 2.25 0 0 0 490.5 39.776138085512876 L 490.5 21.966615060280652 A 2.25 2.25 0 0 1 495 21.966615060280652 L 495 40.25907503788128 A 2.25 2.25 0 0 0 499.5 40.25907503788128 L 499.5 23.037436590025692 A 2.25 2.25 0 0 1 504 23.037436590025692 L 504 40.57391791290598 A 2.25 2.25 0 0 0 508.5 40.57391791290598 L 508.5 22.549608011067924 A 2.25 2.25 0 0 1 513 22.549608011067924 L 513 40.810050069174515 A 2.25 2.25 0 0 0 517.5 40.810050069174515 L 517.5 21.02164174188023 A 2.25 2.25 0 0 1 522 21.02164174188023 L 522 40.33600698333223 A 2.25 2.25 0 0 0 526.5 40.33600698333223 L 526.5 19.717356215824495 A 2.25 2.25 0 0 1 531 19.717356215824495 L 531 40.670416364714406 A 2.25 2.25 0 0 0 535.5 40.670416364714406 L 535.5 20.999851768891233 A 2.25 2.25 0 0 1 540 20.999851768891233 L 540 40.158574346136106 A 2.25 2.25 0 0 0 544.5 40.158574346136106 L 544.5 20.859773371104815 A 2.25 2.25 0 0 1 549 20.859773371104815 L 549 38.727106528756835 A 2.25 2.25 0 0 0 553.5 38.727106528756835 L 553.5 21.160386059687724 A 2.25 2.25 0 0 1 558 21.160386059687724 L 558 30 A 2.25 2.25 0 0 0 562.5 30 A 2.25 2.25 0 0 1 567 30" fill="none" stroke="#e2e8f0" stroke-width="2" /><path class="podify-sp-progress-path" d="M 0 30 L 0 30 A 2.25 2.25 0 0 0 4.5 30 A 2.25 2.25 0 0 1 9 30 L 9 37.14888991369655 A 2.25 2.25 0 0 0 13.5 37.14888991369655 L 13.5 24.81576520192371 A 2.25 2.25 0 0 1 18 24.81576520192371 L 18 40.138563146452334 A 2.25 2.25 0 0 0 22.5 40.138563146452334 L 22.5 19.60218064431122 A 2.25 2.25 0 0 1 27 19.60218064431122 L 27 37.719431451347255 A 2.25 2.25 0 0 0 31.5 37.719431451347255 L 31.5 20.517359509849136 A 2.25 2.25 0 0 1 36 20.517359509849136 L 36 37.415261216153894 A 2.25 2.25 0 0 0 40.5 37.415261216153894 L 40.5 21.577953093089135 A 2.25 2.25 0 0 1 45 21.577953093089135 L 45 37.80570195665064 A 2.25 2.25 0 0 0 49.5 37.80570195665064 L 49.5 21.28045325779037 A 2.25 2.25 0 0 1 54 21.28045325779037 L 54 38.571463864549706 A 2.25 2.25 0 0 0 58.5 38.571463864549706 L 58.5 20.29323407339087 A 2.25 2.25 0 0 1 63 20.29323407339087 L 63 41.12756110415706 A 2.25 2.25 0 0 0 67.5 41.12756110415706 L 67.5 20.563607615784967 A 2.25 2.25 0 0 1 72 20.563607615784967 L 72 41.40238157981422 A 2.25 2.25 0 0 0 76.5 41.40238157981422 L 76.5 19.282890836023455 A 2.25 2.25 0 0 1 81 19.282890836023455 L 81 39.10731932274854 A 2.25 2.25 0 0 0 85.5 39.10731932274854 L 85.5 21.333371763620793 A 2.25 2.25 0 0 1 90 21.333371763620793 L 90 38.17479741748468 A 2.25 2.25 0 0 0 94.5 38.17479741748468 L 94.5 20.75838329270703 A 2.25 2.25 0 0 1 99 20.75838329270703 L 99 38.16279069767442 A 2.25 2.25 0 0 0 103.5 38.16279069767442 L 103.5 21.401854535871927 A 2.25 2.25 0 0 1 108 21.401854535871927 L 108 39.68008432703077 A 2.25 2.25 0 0 0 112.5 39.68008432703077 L 112.5 18.0866657882601 A 2.25 2.25 0 0 1 117 18.0866657882601 L 117 39.63339152776863 A 2.25 2.25 0 0 0 121.5 39.63339152776863 L 121.5 20.792179985506294 A 2.25 2.25 0 0 1 126 20.792179985506294 L 126 39.769912378944596 A 2.25 2.25 0 0 0 130.5 39.769912378944596 L 130.5 20.827755451610777 A 2.25 2.25 0 0 1 135 20.827755451610777 L 135 39.15267804203175 A 2.25 2.25 0 0 0 139.5 39.15267804203175 L 139.5 20.61786020159431 A 2.25 2.25 0 0 1 144 20.61786020159431 L 144 41.08709401146321 A 2.25 2.25 0 0 0 148.5 41.08709401146321 L 148.5 19.70446010936162 A 2.25 2.25 0 0 1 153 19.70446010936162 L 153 39.563574675538575 A 2.25 2.25 0 0 0 157.5 39.563574675538575 L 157.5 19.670663416562356 A 2.25 2.25 0 0 1 162 19.670663416562356 L 162 37.19780617959022 A 2.25 2.25 0 0 0 166.5 37.19780617959022 L 166.5 21.22842413861256 A 2.25 2.25 0 0 1 171 21.22842413861256 L 171 40.35735226299492 A 2.25 2.25 0 0 0 175.5 40.35735226299492 L 175.5 19.413630673957442 A 2.25 2.25 0 0 1 180 19.413630673957442 L 180 39.156680281968505 A 2.25 2.25 0 0 0 184.5 39.156680281968505 L 184.5 21.18262072600303 A 2.25 2.25 0 0 1 189 21.18262072600303 L 189 41.46108109888662 A 2.25 2.25 0 0 0 193.5 41.46108109888662 L 193.5 20.721029053297322 A 2.25 2.25 0 0 1 198 20.721029053297322 L 198 40.01049146847618 A 2.25 2.25 0 0 0 202.5 40.01049146847618 L 202.5 20.52803214968048 A 2.25 2.25 0 0 1 207 20.52803214968048 L 207 38.40826141379537 A 2.25 2.25 0 0 0 211.5 38.40826141379537 L 211.5 20.367497858883986 A 2.25 2.25 0 0 1 216 20.367497858883986 L 216 38.73955794189341 A 2.25 2.25 0 0 0 220.5 38.73955794189341 L 220.5 21.79540812965281 A 2.25 2.25 0 0 1 225 21.79540812965281 L 225 39.56401936886488 A 2.25 2.25 0 0 0 229.5 39.56401936886488 L 229.5 21.9701726068911 A 2.25 2.25 0 0 1 234 21.9701726068911 L 234 43.5 A 2.25 2.25 0 0 0 238.5 43.5 L 238.5 19.463436326503725 A 2.25 2.25 0 0 1 243 19.463436326503725 L 243 41.46908557876013 A 2.25 2.25 0 0 0 247.5 41.46908557876013 L 247.5 19.62930693721589 A 2.25 2.25 0 0 1 252 19.62930693721589 L 252 37.672293958758814 A 2.25 2.25 0 0 0 256.5 37.672293958758814 L 256.5 20.705464786876608 A 2.25 2.25 0 0 1 261 20.705464786876608 L 261 41.823506159826074 A 2.25 2.25 0 0 0 265.5 41.823506159826074 L 265.5 19.11524145200606 A 2.25 2.25 0 0 1 270 19.11524145200606 L 270 39.017046577508395 A 2.25 2.25 0 0 0 274.5 39.017046577508395 L 274.5 20.665887080835365 A 2.25 2.25 0 0 1 279 20.665887080835365 L 279 40.95857764016075 A 2.25 2.25 0 0 0 283.5 40.95857764016075 L 283.5 18.329913037749524 A 2.25 2.25 0 0 1 288 18.329913037749524 L 288 39.46529745042493 A 2.25 2.25 0 0 0 292.5 39.46529745042493 L 292.5 21.292904670926937 A 2.25 2.25 0 0 1 297 21.292904670926937 L 297 39.91532709664668 A 2.25 2.25 0 0 0 301.5 39.91532709664668 L 301.5 21.984402793332897 A 2.25 2.25 0 0 1 306 21.984402793332897 L 306 37.24805652546281 A 2.25 2.25 0 0 0 310.5 37.24805652546281 L 310.5 21.951050793859938 A 2.25 2.25 0 0 1 315 21.951050793859938 L 315 42.894327689571114 A 2.25 2.25 0 0 0 319.5 42.894327689571114 L 319.5 20.79484814546413 A 2.25 2.25 0 0 1 324 20.79484814546413 L 324 39.28830950655511 A 2.25 2.25 0 0 0 328.5 39.28830950655511 L 328.5 19.499011792608208 A 2.25 2.25 0 0 1 333 19.499011792608208 L 333 39.476859476908885 A 2.25 2.25 0 0 0 337.5 39.476859476908885 L 337.5 21.327590750378814 A 2.25 2.25 0 0 1 342 21.327590750378814 L 342 39.62938928783187 A 2.25 2.25 0 0 0 346.5 39.62938928783187 L 346.5 22.48557217207985 A 2.25 2.25 0 0 1 351 22.48557217207985 L 351 39.38792081164767 A 2.25 2.25 0 0 0 355.5 39.38792081164767 L 355.5 20.38617497858884 A 2.25 2.25 0 0 1 360 20.38617497858884 L 360 41.70744120166019 A 2.25 2.25 0 0 0 364.5 41.70744120166019 L 364.5 21.52903682719547 A 2.25 2.25 0 0 1 369 21.52903682719547 L 369 42.287765992489625 A 2.25 2.25 0 0 0 373.5 42.287765992489625 L 373.5 20.093122076553133 A 2.25 2.25 0 0 1 378 20.093122076553133 L 378 40.52989327360169 A 2.25 2.25 0 0 0 382.5 40.52989327360169 L 382.5 20.88556558403057 A 2.25 2.25 0 0 1 387 20.88556558403057 L 387 38.36468146781738 A 2.25 2.25 0 0 0 391.5 38.36468146781738 L 391.5 21.639320772119376 A 2.25 2.25 0 0 1 396 21.639320772119376 L 396 40.53745306014889 A 2.25 2.25 0 0 0 400.5 40.53745306014889 L 400.5 20.017524211081103 A 2.25 2.25 0 0 1 405 20.017524211081103 L 405 39.86774491073193 A 2.25 2.25 0 0 0 409.5 39.86774491073193 L 409.5 22.12581527109823 A 2.25 2.25 0 0 1 414 22.12581527109823 L 414 41.34101390078398 A 2.25 2.25 0 0 0 418.5 41.34101390078398 L 418.5 20.150932208972925 A 2.25 2.25 0 0 1 423 20.150932208972925 L 423 40.17324922590421 A 2.25 2.25 0 0 0 427.5 40.17324922590421 L 427.5 22.434432439554648 A 2.25 2.25 0 0 1 432 22.434432439554648 L 432 42.78982475788919 A 2.25 2.25 0 0 0 436.5 42.78982475788919 L 436.5 18.113792081164767 A 2.25 2.25 0 0 1 441 18.113792081164767 L 441 39.58269648856974 A 2.25 2.25 0 0 0 445.5 39.58269648856974 L 445.5 20.907355557019567 A 2.25 2.25 0 0 1 450 20.907355557019567 L 450 38.47674418604651 A 2.25 2.25 0 0 0 454.5 38.47674418604651 L 454.5 19.196620330720073 A 2.25 2.25 0 0 1 459 19.196620330720073 L 459 39.723664273008765 A 2.25 2.25 0 0 0 463.5 39.723664273008765 L 463.5 20.20607418143488 A 2.25 2.25 0 0 1 468 20.20607418143488 L 468 41.34412675406812 A 2.25 2.25 0 0 0 472.5 41.34412675406812 L 472.5 20.058880690427564 A 2.25 2.25 0 0 1 477 20.058880690427564 L 477 40.14523354634693 A 2.25 2.25 0 0 0 481.5 40.14523354634693 L 481.5 22.182736016865405 A 2.25 2.25 0 0 1 486 22.182736016865405 L 486 39.776138085512876 A 2.25 2.25 0 0 0 490.5 39.776138085512876 L 490.5 21.966615060280652 A 2.25 2.25 0 0 1 495 21.966615060280652 L 495 40.25907503788128 A 2.25 2.25 0 0 0 499.5 40.25907503788128 L 499.5 23.037436590025692 A 2.25 2.25 0 0 1 504 23.037436590025692 L 504 40.57391791290598 A 2.25 2.25 0 0 0 508.5 40.57391791290598 L 508.5 22.549608011067924 A 2.25 2.25 0 0 1 513 22.549608011067924 L 513 40.810050069174515 A 2.25 2.25 0 0 0 517.5 40.810050069174515 L 517.5 21.02164174188023 A 2.25 2.25 0 0 1 522 21.02164174188023 L 522 40.33600698333223 A 2.25 2.25 0 0 0 526.5 40.33600698333223 L 526.5 19.717356215824495 A 2.25 2.25 0 0 1 531 19.717356215824495 L 531 40.670416364714406 A 2.25 2.25 0 0 0 535.5 40.670416364714406 L 535.5 20.999851768891233 A 2.25 2.25 0 0 1 540 20.999851768891233 L 540 40.158574346136106 A 2.25 2.25 0 0 0 544.5 40.158574346136106 L 544.5 20.859773371104815 A 2.25 2.25 0 0 1 549 20.859773371104815 L 549 38.727106528756835 A 2.25 2.25 0 0 0 553.5 38.727106528756835 L 553.5 21.160386059687724 A 2.25 2.25 0 0 1 558 21.160386059687724 L 558 30 A 2.25 2.25 0 0 0 562.5 30 A 2.25 2.25 0 0 1 567 30" fill="none" stroke="#0b5bd3" stroke-width="2" style="stroke-dasharray: 10000; stroke-dashoffset: 10000;" /><rect class="podify-sp-click-area" width="100%" height="100%" fill="transparent" style="cursor:pointer;" /></svg></div>';
        $player_html .= '</div>'; // End progress container
        $player_html .= '</div>'; // End controls
        $player_html .= '</div>'; // End content
        
        // Hidden Audio (Moved inside inner for better structure)
        $player_html .= '<audio class="podify-episode-audio no-mejs" src="' . esc_url($audio_url) . '" data-title="'.esc_attr($title).'" data-image="'.esc_attr($image).'" data-duration="'.esc_attr($dur_fmt).'" data-duration-seconds="'.esc_attr($dur_sec).'" style="display:none !important; visibility:hidden !important; height:0; width:0;"></audio>';

        $player_html .= '</div>'; // End inner
        
        $player_html .= '</div>';

        return $player_html;
    }
    private static function duration_seconds($d) {
        $s = trim((string)$d);
        if ($s === '') return 0;
        if (preg_match('/^\d+$/', $s)) {
            return intval($s);
        }
        $parts = array_map('intval', explode(':', $s));
        $sec = 0;
        foreach ($parts as $p) { $sec = $sec*60 + $p; }
        return $sec;
    }
    public static function enqueue_assets_global() {
        $settings = \PodifyPodcast\Core\Settings::get();
        self::enqueue_assets();
        
        $dynamic_css = '';
        // Default global styling is handled by frontend.css.
        
        // Force Border Radius Global
        $dynamic_css .= "html body .podify-load-more-wrap button.podify-load-more { border-radius: 8px !important; }";
        
        // Theme Reset: Neutralize pseudo-elements (:before, :after) from themes like cmsmasters
        $dynamic_css .= "html body .podify-load-more-wrap button.podify-load-more:before, ";
        $dynamic_css .= "html body .podify-load-more-wrap button.podify-load-more:after { display: none !important; content: none !important; background: none !important; background-image: none !important; opacity: 0 !important; }";

        // Per-category dynamic CSS
        $all_cats = \PodifyPodcast\Core\Database::get_categories();
        if ($all_cats) {
            foreach ($all_cats as $cat) {
                $cat_id = intval($cat['id']);
                $cat_css = '';
                
                $c1 = $cat['card_bg_color'];
                if ($c1) {
                    $cat_css .= "html body .podify-episodes-grid .podify-cat-{$cat_id}, ";
                    $cat_css .= "html body .podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-card { background-color: " . esc_attr($c1) . " !important; }";
                    // Support for List Layout cards in global category styles
                    $cat_css .= "html body .podify-episodes-grid.podify-list-layout .podify-cat-{$cat_id}, ";
                    $cat_css .= "html body .podify-episodes-grid.podify-list-layout .podify-list-layout-card.podify-cat-{$cat_id}, ";
                    $cat_css .= "html body .podify-episodes-grid.podify-list-layout .podify-cat-{$cat_id}.podify-list-layout-card, ";
                    $cat_css .= "html body .podify-episodes-grid.podify-list-layout.podify-cat-{$cat_id} .podify-list-layout-card { background: " . esc_attr($c1) . " !important; }";
                }
                
                if ($cat['button_bg_color']) {
                    $cat_css .= "html body div.podify-episodes-grid div.podify-cat-{$cat_id} .podify-read-more, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-read-more { background: " . esc_attr($cat['button_bg_color']) . " !important; border-color: " . esc_attr($cat['button_bg_color']) . " !important; }";
                    $cat_css .= "html body div.podify-episodes-grid div.podify-cat-{$cat_id} .podify-list-play-btn, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-list-play-btn { background: " . esc_attr($cat['button_bg_color']) . " !important; border-color: " . esc_attr($cat['button_bg_color']) . " !important; }";
                    $cat_css .= "html body div.podify-episodes-grid div.podify-cat-{$cat_id} .podify-play-action-btn svg, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-play-action-btn svg { color: " . esc_attr($cat['button_bg_color']) . " !important; }";
                    $cat_css .= "html body div.podify-episodes-grid div.podify-cat-{$cat_id} .podify-category-pill, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-category-pill { background-color: " . esc_attr($cat['button_bg_color']) . " !important; background: " . esc_attr($cat['button_bg_color']) . " !important; }";
                    
                    // Reset pseudo-elements for read-more
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-read-more:before, ";
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-read-more:after { display: none !important; content: none !important; background: none !important; background-image: none !important; opacity: 0 !important; }";
                }
                if ($cat['button_text_color']) {
                    $cat_css .= "html body div.podify-episodes-grid div.podify-cat-{$cat_id} .podify-read-more, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-read-more { color: " . esc_attr($cat['button_text_color']) . " !important; }";
                    $cat_css .= "html body div.podify-episodes-grid div.podify-cat-{$cat_id} .podify-list-play-btn, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-list-play-btn { color: " . esc_attr($cat['button_text_color']) . " !important; }";
                    $cat_css .= "html body div.podify-episodes-grid div.podify-cat-{$cat_id} .podify-list-play-btn svg, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-list-play-btn svg { color: " . esc_attr($cat['button_text_color']) . " !important; }";
                    $cat_css .= "html body div.podify-episodes-grid div.podify-cat-{$cat_id} .podify-category-pill, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-category-pill { color: " . esc_attr($cat['button_text_color']) . " !important; }";
                }
                if ($cat['title_color']) {
                    $tc = esc_attr($cat['title_color']);
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-episode-title, ";
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-episode-title a, ";
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-episode-link, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-title, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-title a { color: {$tc} !important; }";
                }
                if ($cat['title_font']) {
                    $tf = $cat['title_font'];
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-episode-title, ";
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-episode-title a, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-title, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-title a { font-family: {$tf} !important; }";
                }
                if ($cat['title_letter_spacing']) {
                    $tls = $cat['title_letter_spacing'];
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-episode-title, ";
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-episode-title a, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-title, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-title a { letter-spacing: {$tls} !important; }";
                }
                if ($cat['title_line_height']) {
                    $tlh = $cat['title_line_height'];
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-episode-title, ";
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-episode-title a, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-title, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-title a { line-height: {$tlh} !important; }";
                }
                if ($cat['desc_color']) {
                    $dc = esc_attr($cat['desc_color']);
                    $cat_css .= "html body div.podify-episodes-grid .podify-cat-{$cat_id} .podify-episode-desc, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-desc { color: {$dc} !important; }";
                }
                
                // Load More Button Styling for this Category
                if ($cat['load_more_bg_color']) {
                    $lmbg = esc_attr($cat['load_more_bg_color']);
                    $cat_css .= "html body .podify-load-more-wrap.podify-cat-{$cat_id} button.podify-load-more { background-color: {$lmbg} !important; background: {$lmbg} !important; border-color: {$lmbg} !important; }";
                }
                if ($cat['load_more_text_color']) {
                    $lmtxt = esc_attr($cat['load_more_text_color']);
                    $cat_css .= "html body .podify-load-more-wrap.podify-cat-{$cat_id} button.podify-load-more { color: {$lmtxt} !important; }";
                }
                if ($cat['load_more_bg_hover_color']) {
                    $lmbgh = esc_attr($cat['load_more_bg_hover_color']);
                    $cat_css .= "html body .podify-load-more-wrap.podify-cat-{$cat_id} button.podify-load-more:hover { background-color: {$lmbgh} !important; background: {$lmbgh} !important; border-color: {$lmbgh} !important; }";
                    $cat_css .= "html body div.podify-episodes-grid div.podify-cat-{$cat_id}:hover .podify-category-pill, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-card:hover .podify-category-pill { background-color: {$lmbgh} !important; background: {$lmbgh} !important; }";
                }
                if ($cat['load_more_text_hover_color']) {
                    $lmtxth = esc_attr($cat['load_more_text_hover_color']);
                    $cat_css .= "html body .podify-load-more-wrap.podify-cat-{$cat_id} button.podify-load-more:hover { color: {$lmtxth} !important; }";
                    $cat_css .= "html body div.podify-episodes-grid div.podify-cat-{$cat_id}:hover .podify-category-pill, ";
                    $cat_css .= "html body div.podify-episodes-grid.podify-cat-{$cat_id} .podify-episode-card:hover .podify-category-pill { color: {$lmtxth} !important; }";
                }
                
                if ($cat_css) {
                    $dynamic_css .= $cat_css;
                }
            }
        }

        if (!empty($dynamic_css)) {
            wp_add_inline_style('podify_frontend', $dynamic_css);
        }

        $custom_css = isset($settings['custom_css']) ? (string)$settings['custom_css'] : '';
        if (trim($custom_css) !== '') {
            wp_add_inline_style('podify_frontend', $custom_css);
        }
    }

    private static function format_duration($d) {
        $s = trim((string)$d);
        if ($s === '') return '';
        if (preg_match('/^\d+$/', $s)) {
            $sec = intval($s);
        } else {
            $parts = array_map('intval', explode(':', $s));
            $sec = 0;
            foreach ($parts as $p) { $sec = $sec*60 + $p; }
        }
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        $se = $sec % 60;
        if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $se);
        return sprintf('%d:%02d', $m, $se);
    }
    private static function format_duration_label($d) {
        $sec = self::duration_seconds($d);
        if ($sec <= 0) return '';
        $h = intdiv($sec, 3600);
        $m = intdiv($sec % 3600, 60);
        if ($h > 0) {
            return $m > 0 ? ($h . 'h ' . $m . 'm') : ($h . 'h');
        }
        $min = max(1, (int) round($sec / 60));
        return $min . ' min';
    }
    private static function enqueue_assets() {
        // Append time to version to bust cache for CSS and JS
        wp_enqueue_style('podify_frontend', \PODIFY_PODCAST_URL . 'assets/css/frontend.css', [], \PODIFY_PODCAST_VERSION . '.' . time());
        wp_enqueue_script('podify_frontend_js', \PODIFY_PODCAST_URL . 'assets/js/frontend-player.js', [], \PODIFY_PODCAST_VERSION . '.' . time(), true);
    }
    public static function render_list($atts = []) {
        $raw_atts = is_array($atts) ? $atts : [];
        $layout_attr_provided = array_key_exists('layout', $raw_atts);
        $atts = shortcode_atts([
            'cols' => 3,
            'limit' => '', 
            'feed_id' => '',
            'category_id' => '',
            'category' => '', 
            'layout' => 'classic',
            'show_categories' => ''
        ], $raw_atts, 'podify_podcast_list');

        self::enqueue_assets();

        $cols = max(1, min(4, intval($atts['cols'])));
        $limit = $atts['limit'] !== '' ? intval($atts['limit']) : ($cols * 2);
        $feed_id = $atts['feed_id'] !== '' ? intval($atts['feed_id']) : null;
        $category_id = $atts['category_id'] !== '' ? intval($atts['category_id']) : null;
        $settings = \PodifyPodcast\Core\Settings::get();
        $sticky_enabled = !empty($settings['sticky_player_enabled']);
        
        $read_more_text = !empty($settings['read_more_text']) ? $settings['read_more_text'] : 'Read more';
        $load_more_text = !empty($settings['load_more_text']) ? $settings['load_more_text'] : 'Load more';

        $show_category_badge = true;
        if (isset($atts['show_categories']) && $atts['show_categories'] !== '') {
            $show_category_badge = filter_var($atts['show_categories'], FILTER_VALIDATE_BOOLEAN);
        }
        $pill_color_category_id = 0;
        $default_layout_feed = '';
        $default_style = [];

        // Feed-specific overrides
        if ($feed_id) {
            $feed_row = \PodifyPodcast\Core\Database::get_feed($feed_id);
            if ($feed_row && !empty($feed_row['options'])) {
                $fopts = json_decode($feed_row['options'], true);
                if ($fopts) {
                    if (!empty($fopts['read_more_text'])) $read_more_text = $fopts['read_more_text'];
                    if (!empty($fopts['load_more_text'])) $load_more_text = $fopts['load_more_text'];
                    if (array_key_exists('show_category_badge', $fopts) && (!isset($atts['show_categories']) || $atts['show_categories'] === '')) {
                        $show_category_badge = !empty($fopts['show_category_badge']);
                    }
                    if (!empty($fopts['pill_color_category_id'])) $pill_color_category_id = intval($fopts['pill_color_category_id']);
                    if (!empty($fopts['default_layout'])) $default_layout_feed = sanitize_key($fopts['default_layout']);
                    $default_style = is_array($fopts) ? $fopts : [];
                }
            }
        }
        
        // Normalize layout
        $layout_raw = sanitize_key($atts['layout']);
        if (!$layout_attr_provided && $default_layout_feed && in_array($default_layout_feed, ['classic','modern','list'], true)) {
            $layout_raw = $default_layout_feed;
        }
        $layout = in_array($layout_raw, ['modern', 'list'], true) ? $layout_raw : 'classic';
        $is_modern = ($layout === 'modern');
        $is_list = ($layout === 'list');

        // Resolve category slug/name to ID if needed
        $cat_param = isset($atts['category']) ? trim((string)$atts['category']) : '';
        $uncategorized = false;
        if (!$category_id && $cat_param && strtolower($cat_param) === 'uncategorized') {
            $uncategorized = true;
        }
        
        // If category param is numeric, treat it as ID first
        if (!$category_id && is_numeric($cat_param) && intval($cat_param) > 0) {
            $category_id = intval($cat_param);
        }

        if (!$category_id && !$uncategorized && $cat_param) {
            $cat_slug = sanitize_title($cat_param);
            $cats_for_feed = \PodifyPodcast\Core\Database::get_categories($feed_id ?: null);
            if (is_array($cats_for_feed)) {
                foreach ($cats_for_feed as $c) {
                    if (
                        (!empty($c['slug']) && sanitize_title($c['slug']) === $cat_slug) ||
                        (!empty($c['name']) && strcasecmp(trim($c['name']), trim($cat_param)) === 0)
                    ) {
                        $category_id = intval($c['id']);
                        break;
                    }
                }
            }
        }

        $is_default_view = (bool)($feed_id && !$category_id && ($cat_param === '' || $uncategorized));

        if ($feed_id && current_user_can('manage_options')) {
            $feed_row = \PodifyPodcast\Core\Database::get_feed($feed_id);
            $need_resync = false;
            if (!$feed_row || empty($feed_row['last_sync'])) {
                $need_resync = true;
            } else {
                $last_ts = strtotime($feed_row['last_sync']);
                if (!$last_ts || (time() - $last_ts) > 3600) {
                    $need_resync = true;
                }
            }
            if ($need_resync) {
                try {
                    \PodifyPodcast\Core\Importer::resync_feed($feed_id);
                } catch (\Throwable $e) {
                }
            }
        }

        if ($uncategorized) {
            $episodes = \PodifyPodcast\Core\Database::get_episodes_advanced([
                'feed_id' => $feed_id ?: null,
                'limit' => $limit,
                'offset' => 0,
                'uncategorized' => true,
            ]);
        } else {
            $episodes = \PodifyPodcast\Core\Database::get_episodes($feed_id ?: null, $limit, 0, $category_id ?: null);
        }

        // Fallback: If feed_id is not provided, detect it from the first episode to load correct styling/settings
        if (!$feed_id && !empty($episodes) && is_array($episodes)) {
            $first_ep = $episodes[0];
            if (!empty($first_ep['feed_id'])) {
                $feed_id = intval($first_ep['feed_id']);
                $feed_row = \PodifyPodcast\Core\Database::get_feed($feed_id);
                if ($feed_row && !empty($feed_row['options'])) {
                    $fopts = json_decode($feed_row['options'], true);
                    if ($fopts) {
                        if (!empty($fopts['read_more_text'])) $read_more_text = $fopts['read_more_text'];
                        if (!empty($fopts['load_more_text'])) $load_more_text = $fopts['load_more_text'];
                        if (array_key_exists('show_category_badge', $fopts) && (!isset($atts['show_categories']) || $atts['show_categories'] === '')) {
                            $show_category_badge = !empty($fopts['show_category_badge']);
                        }
                        if (!empty($fopts['pill_color_category_id'])) $pill_color_category_id = intval($fopts['pill_color_category_id']);
                        if (!empty($fopts['default_layout']) && !$layout_attr_provided) {
                            $layout_raw = sanitize_key($fopts['default_layout']);
                            $layout = in_array($layout_raw, ['modern', 'list'], true) ? $layout_raw : 'classic';
                            $is_modern = ($layout === 'modern');
                            $is_list = ($layout === 'list');
                        }
                        $default_style = is_array($fopts) ? $fopts : [];
                    }
                }
            }
        }

        $has_default_style = false;
        if (!empty($default_style) && is_array($default_style)) {
            foreach ([
                'default_card_bg_color',
                'default_card_border_color',
                'default_title_color',
                'default_desc_color',
                'default_meta_color',
                'default_button_bg_color',
                'default_button_text_color',
                'default_button_bg_hover_color',
                'default_button_text_hover_color',
                'default_pill_bg_color',
                'default_pill_text_color',
                'default_pill_bg_hover_color',
                'default_pill_text_hover_color',
            ] as $k) {
                if (!empty($default_style[$k])) { $has_default_style = true; break; }
            }
        }

        // Always output invisible debug info for troubleshooting
        $debug_info = "<!-- Podify Debug: FeedID={$feed_id}, CatParam={$cat_param}, ResolvedCatID={$category_id}, Layout={$layout}, FeedDefaultLayout={$default_layout_feed}, DefaultView=" . ($is_default_view ? '1' : '0') . ", HasDefaultStyle=" . ($has_default_style ? '1' : '0') . ", Uncategorized=" . ($uncategorized ? '1' : '0') . ", Count=" . (is_array($episodes) ? count($episodes) : 0) . " -->";

        if (!$episodes) {
            $debug = '';
            if (current_user_can('manage_options')) {
                $debug = '<div style="background:#fff3cd; color:#856404; padding:10px; border:1px solid #ffeeba; margin-bottom:10px; font-size:12px; text-align:left;">';
                $debug .= '<strong>Podify Debug:</strong> No episodes found.<br>';
                $debug .= 'Feed ID: ' . esc_html($feed_id) . '<br>';
                $debug .= 'Category Param: ' . esc_html($cat_param) . '<br>';
                $debug .= 'Resolved Category ID: ' . esc_html($category_id) . '<br>';
                $debug .= 'Uncategorized: ' . ($uncategorized ? 'Yes' : 'No') . '<br>';
                
                $total_feed = \PodifyPodcast\Core\Database::count_episodes($feed_id);
                $debug .= 'Total Episodes in Feed: ' . intval($total_feed) . '<br>';
                
                if ($uncategorized) {
                    $total_uncat = \PodifyPodcast\Core\Database::count_episodes_advanced([
                        'feed_id' => $feed_id ?: null,
                        'uncategorized' => true,
                    ]);
                    $debug .= 'Uncategorized Episodes: ' . intval($total_uncat) . '<br>';
                } elseif ($category_id) {
                    $total_cat = \PodifyPodcast\Core\Database::count_episodes($feed_id, $category_id);
                    $debug .= 'Episodes in Category ' . $category_id . ': ' . intval($total_cat) . '<br>';
                } else if ($cat_param) {
                     $debug .= '<em>Warning: Category parameter provided but could not be resolved to an ID. Defaulting to all episodes (but still found none?).</em><br>';
                }
                $debug .= 'Ensure episodes are assigned to this category in the admin dashboard.';
                $debug .= '</div>';
            }
            return $debug_info . '<div class="podify-episodes-grid">' . $debug . 'No episodes found.</div>';
        }

        $container_id = 'podify-ep-'.wp_generate_uuid4();
        
        $feed_text_css = '';
        if ($feed_id && $default_style) {
            $bg = !empty($default_style['default_card_bg_color']) ? $default_style['default_card_bg_color'] : '';
            $border = !empty($default_style['default_card_border_color']) ? $default_style['default_card_border_color'] : '';
            $title_c = !empty($default_style['default_title_color']) ? $default_style['default_title_color'] : '';
            $desc_c = !empty($default_style['default_desc_color']) ? $default_style['default_desc_color'] : '';
            $meta_c = !empty($default_style['default_meta_color']) ? $default_style['default_meta_color'] : '';
            $btn_bg = !empty($default_style['default_button_bg_color']) ? $default_style['default_button_bg_color'] : '';
            $btn_txt = !empty($default_style['default_button_text_color']) ? $default_style['default_button_text_color'] : '';
            $btn_bg_h = !empty($default_style['default_button_bg_hover_color']) ? $default_style['default_button_bg_hover_color'] : '';
            $btn_txt_h = !empty($default_style['default_button_text_hover_color']) ? $default_style['default_button_text_hover_color'] : '';
            $pill_bg = !empty($default_style['default_pill_bg_color']) ? $default_style['default_pill_bg_color'] : '';
            $pill_txt = !empty($default_style['default_pill_text_color']) ? $default_style['default_pill_text_color'] : '';
            $pill_bg_h = !empty($default_style['default_pill_bg_hover_color']) ? $default_style['default_pill_bg_hover_color'] : '';
            $pill_txt_h = !empty($default_style['default_pill_text_hover_color']) ? $default_style['default_pill_text_hover_color'] : '';

            if ($is_list) {
                if ($bg) {
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid.podify-list-layout .podify-list-layout-card { background: " . esc_attr($bg) . " !important; background-color: " . esc_attr($bg) . " !important; }";
                }
                if ($border) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid.podify-list-layout .podify-list-layout-card { border-color: " . esc_attr($border) . " !important; }";
            } else {
                if ($bg) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card { background-color: " . esc_attr($bg) . " !important; }";
                if ($border) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card { border-color: " . esc_attr($border) . " !important; }";
            }
            if ($title_c) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-title, html body #{$container_id}.podify-episodes-grid .podify-episode-title a, html body #{$container_id}.podify-episodes-grid .podify-episode-link { color: " . esc_attr($title_c) . " !important; }";
            if ($desc_c) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-desc { color: " . esc_attr($desc_c) . " !important; }";
            if ($meta_c) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-meta, html body #{$container_id}.podify-episodes-grid .podify-episode-date, html body #{$container_id}.podify-episodes-grid .podify-list-date, html body #{$container_id}.podify-episodes-grid .podify-list-duration { color: " . esc_attr($meta_c) . " !important; }";

            if ($btn_bg) {
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-read-more { background: " . esc_attr($btn_bg) . " !important; border-color: " . esc_attr($btn_bg) . " !important; }";
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-list-play-btn { background: " . esc_attr($btn_bg) . " !important; border-color: " . esc_attr($btn_bg) . " !important; }";
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-play-action-btn svg { color: " . esc_attr($btn_bg) . " !important; }";
            }
            if ($btn_txt) {
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-read-more { color: " . esc_attr($btn_txt) . " !important; }";
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-list-play-btn { color: " . esc_attr($btn_txt) . " !important; }";
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-list-play-btn svg { color: " . esc_attr($btn_txt) . " !important; }";
            }
            if ($btn_bg_h) {
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-read-more:hover { background: " . esc_attr($btn_bg_h) . " !important; border-color: " . esc_attr($btn_bg_h) . " !important; }";
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-list-play-btn:hover { background: " . esc_attr($btn_bg_h) . " !important; border-color: " . esc_attr($btn_bg_h) . " !important; }";
            }
            if ($btn_txt_h) {
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-read-more:hover { color: " . esc_attr($btn_txt_h) . " !important; }";
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-list-play-btn:hover { color: " . esc_attr($btn_txt_h) . " !important; }";
                $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-list-play-btn:hover svg { color: " . esc_attr($btn_txt_h) . " !important; }";
            }

            if ($is_list) {
                if ($pill_bg) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid.podify-list-layout .podify-category-pill { background: " . esc_attr($pill_bg) . " !important; }";
                if ($pill_txt) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid.podify-list-layout .podify-category-pill { color: " . esc_attr($pill_txt) . " !important; }";
                if ($pill_bg_h) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid.podify-list-layout .podify-list-layout-card:hover .podify-category-pill { background: " . esc_attr($pill_bg_h) . " !important; }";
                if ($pill_txt_h) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid.podify-list-layout .podify-list-layout-card:hover .podify-category-pill { color: " . esc_attr($pill_txt_h) . " !important; }";
            }

            // Load More Button Styling for Default Feed Style
            if ($btn_bg) {
                $feed_text_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"] { background-color: " . esc_attr($btn_bg) . " !important; background: " . esc_attr($btn_bg) . " !important; border-color: " . esc_attr($btn_bg) . " !important; }";
            }
            if ($btn_txt) {
                $feed_text_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"] { color: " . esc_attr($btn_txt) . " !important; }";
            }
            if ($btn_bg_h) {
                $feed_text_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"]:hover { background-color: " . esc_attr($btn_bg_h) . " !important; background: " . esc_attr($btn_bg_h) . " !important; border-color: " . esc_attr($btn_bg_h) . " !important; }";
            }
            if ($btn_txt_h) {
                $feed_text_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"]:hover { color: " . esc_attr($btn_txt_h) . " !important; }";
            }
        }
        
        if ($pill_color_category_id > 0) {
            $pill_cat = \PodifyPodcast\Core\Database::get_category($pill_color_category_id);
            if ($pill_cat && (!$feed_id || intval($pill_cat['feed_id']) === intval($feed_id))) {
                $pill_bg = !empty($pill_cat['button_bg_color']) ? $pill_cat['button_bg_color'] : (!empty($pill_cat['load_more_bg_color']) ? $pill_cat['load_more_bg_color'] : '');
                $pill_txt = !empty($pill_cat['button_text_color']) ? $pill_cat['button_text_color'] : (!empty($pill_cat['load_more_text_color']) ? $pill_cat['load_more_text_color'] : '');
                $pill_hover = !empty($pill_cat['load_more_bg_hover_color']) ? $pill_cat['load_more_bg_hover_color'] : $pill_bg;
                $pill_hover_txt = !empty($pill_cat['load_more_text_hover_color']) ? $pill_cat['load_more_text_hover_color'] : $pill_txt;
                if ($pill_bg) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-category-pill { background-color: " . esc_attr($pill_bg) . " !important; background: " . esc_attr($pill_bg) . " !important; }";
                if ($pill_txt) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-category-pill { color: " . esc_attr($pill_txt) . " !important; }";
                if ($pill_hover) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat:hover .podify-category-pill { background-color: " . esc_attr($pill_hover) . " !important; background: " . esc_attr($pill_hover) . " !important; }";
                if ($pill_hover_txt) $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat:hover .podify-category-pill { color: " . esc_attr($pill_hover_txt) . " !important; }";

                if (!empty($pill_cat['card_bg_color'])) {
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat { background-color: " . esc_attr($pill_cat['card_bg_color']) . " !important; }";
                }
                if (!empty($pill_cat['title_color'])) {
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-episode-title, ";
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-episode-title a, ";
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-episode-link { color: " . esc_attr($pill_cat['title_color']) . " !important; }";
                }
                if (!empty($pill_cat['title_font'])) {
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-episode-title, ";
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-episode-title a { font-family: " . $pill_cat['title_font'] . " !important; }";
                }
                if (!empty($pill_cat['title_letter_spacing'])) {
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-episode-title, ";
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-episode-title a { letter-spacing: " . $pill_cat['title_letter_spacing'] . " !important; }";
                }
                if (!empty($pill_cat['title_line_height'])) {
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-episode-title, ";
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-episode-title a { line-height: " . $pill_cat['title_line_height'] . " !important; }";
                }
                if (!empty($pill_cat['desc_color'])) {
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-episode-desc { color: " . esc_attr($pill_cat['desc_color']) . " !important; }";
                }
                if (!empty($pill_cat['button_bg_color'])) {
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-read-more { background: " . esc_attr($pill_cat['button_bg_color']) . " !important; border-color: " . esc_attr($pill_cat['button_bg_color']) . " !important; }";
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-play-action-btn svg { color: " . esc_attr($pill_cat['button_bg_color']) . " !important; }";
                }
                if (!empty($pill_cat['button_text_color'])) {
                    $feed_text_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card.podify-uncat .podify-read-more { color: " . esc_attr($pill_cat['button_text_color']) . " !important; }";
                }
                if ($uncategorized) {
                    if (!empty($pill_cat['load_more_bg_color'])) {
                        $lmbg_uncat = esc_attr($pill_cat['load_more_bg_color']);
                        $feed_text_css .= "html body .podify-load-more-wrap.podify-uncategorized button.podify-load-more[data-target=\"{$container_id}\"] { background-color: {$lmbg_uncat} !important; background: {$lmbg_uncat} !important; border-color: {$lmbg_uncat} !important; }";
                    }
                    if (!empty($pill_cat['load_more_text_color'])) {
                        $lmt_uncat = esc_attr($pill_cat['load_more_text_color']);
                        $feed_text_css .= "html body .podify-load-more-wrap.podify-uncategorized button.podify-load-more[data-target=\"{$container_id}\"] { color: {$lmt_uncat} !important; }";
                    }
                    if (!empty($pill_cat['load_more_bg_hover_color'])) {
                        $lmbg_uncat_h = esc_attr($pill_cat['load_more_bg_hover_color']);
                        $feed_text_css .= "html body .podify-load-more-wrap.podify-uncategorized button.podify-load-more[data-target=\"{$container_id}\"]:hover { background-color: {$lmbg_uncat_h} !important; background: {$lmbg_uncat_h} !important; border-color: {$lmbg_uncat_h} !important; }";
                    }
                    if (!empty($pill_cat['load_more_text_hover_color'])) {
                        $lmt_uncat_h = esc_attr($pill_cat['load_more_text_hover_color']);
                        $feed_text_css .= "html body .podify-load-more-wrap.podify-uncategorized button.podify-load-more[data-target=\"{$container_id}\"]:hover { color: {$lmt_uncat_h} !important; }";
                    }
                }
            }
        }
        $inline_css = $feed_text_css;

        // Dynamic CSS for the grid customization if this is a category-specific grid
        if ($category_id) {
            $cat_data = \PodifyPodcast\Core\Database::get_category($category_id);
            if ($cat_data) {
                $grid_custom_css = '';
                
                // Category Card/Text Styling for this specific grid ID
                if ($cat_data['card_bg_color']) {
                    $cbg = esc_attr($cat_data['card_bg_color']);
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-card { background-color: {$cbg} !important; }";
                    if ($is_list) {
                        $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid.podify-list-layout .podify-list-layout-card { background: {$cbg} !important; }";
                    }
                }
                if ($cat_data['title_color']) {
                    $tc = esc_attr($cat_data['title_color']);
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-title, ";
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-title a, ";
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-link { color: {$tc} !important; }";
                }
                if ($cat_data['title_font']) {
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-title, ";
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-title a { font-family: " . $cat_data['title_font'] . " !important; }";
                }
                if ($cat_data['title_letter_spacing']) {
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-title, ";
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-title a { letter-spacing: " . $cat_data['title_letter_spacing'] . " !important; }";
                }
                if ($cat_data['title_line_height']) {
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-title, ";
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-title a { line-height: " . $cat_data['title_line_height'] . " !important; }";
                }
                if ($cat_data['desc_color']) {
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-episode-desc { color: " . esc_attr($cat_data['desc_color']) . " !important; }";
                }
                
                // Add Meta Color if available (reusing meta_color from default if needed, or if we had it in cat)
                // For now let's handle Button colors which are available in category
                if (!empty($cat_data['button_bg_color'])) {
                    $bbg = esc_attr($cat_data['button_bg_color']);
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-read-more { background: {$bbg} !important; border-color: {$bbg} !important; }";
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-list-play-btn { background: {$bbg} !important; border-color: {$bbg} !important; }";
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-play-action-btn svg { color: {$bbg} !important; }";
                }
                if (!empty($cat_data['button_text_color'])) {
                    $btxt = esc_attr($cat_data['button_text_color']);
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-read-more { color: {$btxt} !important; }";
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-list-play-btn { color: {$btxt} !important; }";
                    $grid_custom_css .= "html body #{$container_id}.podify-episodes-grid .podify-list-play-btn svg { color: {$btxt} !important; }";
                }

                // Load More Button Styling for this Grid (Category Specific)
                if ($cat_data['load_more_bg_color']) {
                    $lmbg = esc_attr($cat_data['load_more_bg_color']);
                    $grid_custom_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"] { background-color: {$lmbg} !important; background: {$lmbg} !important; border-color: {$lmbg} !important; }";
                }
                if ($cat_data['load_more_text_color']) {
                    $lmtxt = esc_attr($cat_data['load_more_text_color']);
                    $grid_custom_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"] { color: {$lmtxt} !important; }";
                }
                if ($cat_data['load_more_bg_hover_color']) {
                    $lmbgh = esc_attr($cat_data['load_more_bg_hover_color']);
                    $grid_custom_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"]:hover { background-color: {$lmbgh} !important; background: {$lmbgh} !important; border-color: {$lmbgh} !important; }";
                }
                if ($cat_data['load_more_text_hover_color']) {
                    $lmtxth = esc_attr($cat_data['load_more_text_hover_color']);
                    $grid_custom_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"]:hover { color: {$lmtxth} !important; }";
                }
                
                // Force Border Radius for this specific button
                $grid_custom_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"] { border-radius: 8px !important; }";
                
                // Theme Reset for this specific button
                $grid_custom_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"]:before, ";
                $grid_custom_css .= "html body .podify-load-more-wrap button.podify-load-more[data-target=\"{$container_id}\"]:after { display: none !important; content: none !important; background: none !important; background-image: none !important; opacity: 0 !important; }";

                if ($grid_custom_css) {
                    $inline_css .= $grid_custom_css;
                }
            }
        }

        $grid_classes = "podify-episodes-grid podify-cols-{$cols}";
        if ($category_id) {
            $grid_classes .= " podify-cat-{$category_id}";
        }
        if ($uncategorized) {
            $grid_classes .= " podify-uncategorized";
        }
        if ($is_list) {
            $grid_classes .= " podify-list-layout";
        }

        $data_category_attr = '';
        if ($uncategorized) {
            $data_category_attr = ' data-category="uncategorized"';
        } elseif ($category_id) {
            $data_category_attr = ' data-category="'.$category_id.'"';
        }
        $style_tag = $inline_css ? '<style>'.$inline_css.'</style>' : '';
        $html = $debug_info . $style_tag . '<div id="'.$container_id.'" class="'.$grid_classes.'" data-limit="'.$limit.'"'.($feed_id?' data-feed="'.$feed_id.'"':'').$data_category_attr.' data-offset="'.count($episodes).'" data-layout="'.$layout.'">';
        
        foreach ($episodes as $e) {
            $title = !empty($e['title']) ? esc_html($e['title']) : 'Untitled Episode';
            $date = !empty($e['published']) ? esc_html( date_i18n(get_option('date_format'), strtotime($e['published'])) ) : '';
            $duration = !empty($e['duration']) ? esc_html(self::format_duration($e['duration'])) : '';
            $dur_raw = !empty($e['duration']) ? $e['duration'] : '';
            $tags = !empty($e['tags']) ? array_map('trim', explode(',', $e['tags'])) : [];
            $tags_str = $tags ? esc_html(implode(', ', array_slice($tags, 0, 3))) : '';
            $img = !empty($e['image_url']) ? esc_url($e['image_url']) : '';
            $audio = !empty($e['audio_url']) ? esc_url(trim($e['audio_url'])) : '';
            $pid = !empty($e['post_id']) ? intval($e['post_id']) : 0;
            
            if (!empty($e['post_id'])) {
                $pid = intval($e['post_id']);
                if ($pid > 0) {
                    $mimage = get_post_meta($pid, '_podify_episode_image', true);
                    // $maudio = get_post_meta($pid, '_podify_audio_url', true);
                    // if (!empty($maudio) && wp_http_validate_url($maudio)) { $audio = esc_url($maudio); }
                    if (has_post_thumbnail($pid)) {
                        $thumb = get_the_post_thumbnail_url($pid, 'large');
                        if ($thumb) { $img = esc_url($thumb); }
                    } elseif (!empty($mimage) && wp_http_validate_url($mimage)) { 
                        $img = esc_url($mimage); 
                    }
                    $mdur = get_post_meta($pid, '_podify_duration', true);
                    if (!empty($mdur)) { 
                        $duration = esc_html(self::format_duration($mdur)); 
                        $dur_raw = $mdur;
                    }
                }
            }
            
            $permalink = '';
            if ($pid > 0) {
                $status = get_post_status($pid);
                if ($status && !in_array($status, ['trash', 'auto-draft'])) {
                    $permalink = get_permalink($pid);
                }
            }
            
            $check_title = !empty($e['title']) ? $e['title'] : '';
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
            $desc_raw = !empty($e['description']) ? wp_strip_all_tags($e['description']) : '';
            $desc = $desc_raw ? esc_html( wp_trim_words($desc_raw, 18) ) : '';
            $meta_date = $date ? '<span class="podify-episode-date">' . $date . '</span>' : '';
            $meta_parts = array_filter([$meta_date, $tags_str], function($x){ return !empty($x); });
            $meta_line = $meta_parts ? implode(' · ', $meta_parts) : '';
            
            $data_attrs = ' data-title="'.esc_attr($title).'"';
            if ($audio) { $data_attrs .= ' data-audio="'.$audio.'"'; }
            if ($img) { $data_attrs .= ' data-image="'.$img.'"'; }
            $data_attrs .= ' data-duration="'.esc_attr(self::format_duration($dur_raw)).'"';
            $data_attrs .= ' data-duration-seconds="'.esc_attr(self::duration_seconds($dur_raw)).'"';
            
            $card_classes = 'podify-episode-card';
            if ($is_modern) {
                $card_classes .= ' podify-modern';
            } elseif ($is_list) {
                $card_classes .= ' podify-list-layout-card';
            } else {
                $card_classes .= ' podify-row';
            }

            // Category Classes Logic
            $applied_cat_ids = [];
            
            // 1. If shortcode is filtered by category, use that
            if ($category_id) {
                $applied_cat_ids[] = intval($category_id);
            } 
            
            // 2. Add all assigned categories for this episode
            $ep_cats = \PodifyPodcast\Core\Database::get_episode_categories(intval($e['id']));
            if ($ep_cats) {
                foreach ($ep_cats as $ecat) {
                    $cat_id_val = intval($ecat['id']);
                    if (!in_array($cat_id_val, $applied_cat_ids)) {
                        $applied_cat_ids[] = $cat_id_val;
                    }
                }
            } else {
                $card_classes .= ' podify-uncat';
            }
            
            if (!empty($applied_cat_ids)) {
                foreach ($applied_cat_ids as $aid) {
                    $card_classes .= ' podify-cat-' . $aid;
                }
                $data_attrs .= ' data-cat-applied="'.implode(',', $applied_cat_ids).'"';
            }

            $html .= '<div class="'.$card_classes.'"'.$data_attrs.'>';

            if ($is_list) {
                $cats_html = '';
                if ($show_category_badge) {
                    $cats_html .= '<div class="podify-episode-categories">';
                    if ($ep_cats) {
                        foreach ($ep_cats as $ecat) {
                            $cats_html .= '<span class="podify-category-pill">'.esc_html($ecat['name']).'</span>';
                        }
                    } else {
                        $cats_html .= '<span class="podify-category-pill">Uncategorized</span>';
                    }
                    $cats_html .= '</div>';
                }

                $dur_label = self::format_duration_label($dur_raw);

                $html .= '<div class="podify-episode-media podify-list-thumb">';
                if ($img) {
                    $html .= '<img src="'.$img.'" alt="'.$title.'" loading="lazy">';
                } else {
                    $html .= '<div class="podify-episode-placeholder"></div>';
                }
                $html .= '</div>';

                $html .= '<div class="podify-episode-body podify-list-content">';
                $html .= '<div class="podify-list-meta-row">';
                if ($cats_html) {
                    $html .= $cats_html;
                }
                if ($dur_label) {
                    $html .= '<span class="podify-list-duration">'.esc_html($dur_label).'</span>';
                }
                $html .= '</div>';

                $html .= '<h3 class="podify-episode-title"><a href="'.esc_url($permalink).'" class="podify-episode-link">'.$title.'</a></h3>';

                if ($desc) {
                    $html .= '<div class="podify-episode-desc podify-list-desc">'.$desc.'</div>';
                }

                $html .= '<div class="podify-list-bottom-row">';
                if ($audio && $sticky_enabled) {
                    $html .= '<button class="podify-play-action-btn podify-list-play-btn" aria-label="Play Episode">';
                    $html .= '<span class="podify-list-play-icon"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>';
                    $html .= '<span class="podify-list-play-text">Play Episode</span>';
                    $html .= '</button>';
                } else {
                    $html .= '<a class="podify-read-more podify-list-play-btn" href="'.esc_url($permalink).'">'.esc_html($read_more_text).'</a>';
                }
                if ($date) {
                    $html .= '<span class="podify-list-date">'.esc_html($date).'</span>';
                }
                $html .= '</div>';

                $html .= '</div>'; // End Body
            } else {
                // Media Area
                $html .= '<div class="podify-episode-media">';
                if ($img) {
                    $html .= '<img src="'.$img.'" alt="'.$title.'" loading="lazy">';
                } else {
                    $html .= '<div class="podify-episode-placeholder"></div>';
                }
                $html .= '</div>';
                
                // Body Area
                $html .= '<div class="podify-episode-body">';
                
                // Categories Area
                $cats_html = '';
                if ($show_category_badge) {
                    $cats_html .= '<div class="podify-episode-categories">';
                    if ($ep_cats) {
                        foreach ($ep_cats as $ecat) {
                            $cats_html .= '<span class="podify-category-pill">'.esc_html($ecat['name']).'</span>';
                        }
                    } else {
                        $cats_html .= '<span class="podify-category-pill">Uncategorized</span>';
                    }
                    $cats_html .= '</div>';
                }

                // Title (Linked in both layouts)
                $html .= '<div class="podify-episode-top">';
                if ($is_modern) {
                    $html .= $cats_html;
                }
                $html .= '<h3 class="podify-episode-title"><a href="'.esc_url($permalink).'" class="podify-episode-link">'.$title.'</a></h3>';
                $html .= '</div>';
                
                if (!$is_modern) {
                    $html .= $cats_html;
                }
                
                // Description
                if ($desc) {
                    $html .= '<div class="podify-episode-desc podify-clamp-2">'.$desc.'</div>';
                }
                
                if ($is_modern) {
                    // Modern Layout Structure
                    if ($meta_line) {
                        $html .= '<div class="podify-episode-meta">'.$meta_line.'</div>';
                    }
                    
                    $html .= '<div class="podify-episode-actions">';
                    $html .= '<a class="podify-read-more" href="'.esc_url($permalink).'">'.esc_html($read_more_text).' <i class="fa fa-angle-right"></i></a>';
                    
                    if ($audio && $sticky_enabled) {
                        $html .= '<button class="podify-play-action-btn" aria-label="Play"><svg viewBox="0 0 24 24" width="36" height="36" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></button>';
                    }
                    if ($duration && $sticky_enabled) {
                        $html .= '<span class="podify-episode-duration">'.$duration.'</span>';
                    }
                    $html .= '</div>'; // End Actions
                    
                } else {
                    // Classic Layout Structure
                    $html .= '<a class="podify-read-more" href="'.esc_url($permalink).'">'.esc_html($read_more_text).' <i class="fa fa-angle-right"></i></a>';
                    
                    $html .= '<div class="podify-episode-actions">';
                    if ($audio && $sticky_enabled) {
                        $html .= '<button class="podify-play-action-btn" aria-label="Play"><svg viewBox="0 0 24 24" width="36" height="36" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></button>';
                    }
                    if ($duration) {
                        $html .= '<span class="podify-episode-duration">'.$duration.'</span>';
                    }
                    $html .= '</div>';
                    
                    if ($meta_line) {
                        $html .= '<div class="podify-episode-meta">'.$meta_line.'</div>';
                    }
                }
                
                $html .= '</div>'; // End Body
            }

            $html .= '</div>'; // End Card
        }
        
        $html .= '</div>'; // End Grid
        
        $episodes_url = esc_url_raw(rest_url('podify/v1/episodes'));
        if ($uncategorized) {
            $total_count = \PodifyPodcast\Core\Database::count_episodes_advanced([
                'feed_id' => $feed_id ?: null,
                'uncategorized' => true,
            ]);
        } else {
            $total_count = \PodifyPodcast\Core\Database::count_episodes($feed_id ?: null, $category_id ?: null);
        }
        $remaining = max(0, intval($total_count) - count($episodes));
        
        if ($remaining > 0) {
            $wrap_classes = "podify-load-more-wrap";
            if ($category_id) {
                $wrap_classes .= " podify-cat-{$category_id}";
            }
            if ($uncategorized) {
                $wrap_classes .= " podify-uncategorized";
            }
            $html .= '<div class="'.$wrap_classes.'" style="text-align:center;margin-top:24px;"><button class="podify-load-more" data-target="'.$container_id.'">'.esc_html($load_more_text).'</button></div>';
        }
        
        $html .= '<script>(function(){/*Safe*/';
        $html .= 'var EP_URL='.wp_json_encode($episodes_url).';';
        $html .= 'var TOTAL_COUNT='.wp_json_encode(intval($total_count)).';';
        $html .= 'var LAYOUT='.wp_json_encode($layout).';';
        $html .= 'var STICKY_ENABLED='.wp_json_encode($sticky_enabled).';';
        $html .= 'var READ_MORE_TEXT='.wp_json_encode($read_more_text).';';
        $html .= 'var LOAD_MORE_TEXT='.wp_json_encode($load_more_text).';';
        $html .= 'var SHOW_CATS='.wp_json_encode($show_category_badge).';';
        $html .= 'var BASE_URL='.wp_json_encode( trailingslashit(home_url()) ).';';
        $html .= 'function parseJSONSafe(r){return r.text().then(function(t){ if(!t||t.trim().charAt(0)==="<"){console.warn("Podify: Received HTML/Invalid JSON", t.substring(0,100));return null;}try{return JSON.parse(t);}catch(_e){console.error("Podify JSON Parse Error:", _e); return null;}});}';
        
        // Helper: Ensure aspect ratio
        $html .= 'function setCardMediaAspect(root){var imgs=(root?root.querySelectorAll(".podify-episode-media img"):document.querySelectorAll(".podify-episode-media img"));imgs.forEach(function(img){function apply(){var w=img.naturalWidth||0,h=img.naturalHeight||0;if(w>0&&h>0){var p=img.parentElement;if(p){img.style.width="100%";img.style.height="100%";var isListThumb=!!(img.closest&&img.closest(".podify-list-thumb"));img.style.objectFit=isListThumb?"contain":"cover";}}}if(img.complete){apply();}else{img.addEventListener("load",apply,{once:true});}});}setCardMediaAspect();';
        
        // Helper: Ensure Layout Classes and Links (Fixes any JS-rendered inconsistencies)
        $html .= 'function ensureLayoutAndLinks(root){';
        $html .= '  var grids = root ? root.querySelectorAll(".podify-episodes-grid") : document.querySelectorAll(".podify-episodes-grid");';
        $html .= '  grids.forEach(function(g){';
        $html .= '    var lay = g.getAttribute("data-layout") || LAYOUT || "classic";';
        $html .= '    var isModern = (lay === "modern");';
        $html .= '    var isList = (lay === "list");';
        $html .= '    g.querySelectorAll(".podify-episode-card").forEach(function(card){';
        $html .= '      if(isModern && !card.classList.contains("podify-modern")){ card.classList.add("podify-modern"); card.classList.remove("podify-row"); card.classList.remove("podify-list-layout-card"); }';
        $html .= '      else if(isList && !card.classList.contains("podify-list-layout-card")){ card.classList.add("podify-list-layout-card"); card.classList.remove("podify-row"); card.classList.remove("podify-modern"); }';
        $html .= '      else if(!isModern && !isList && !card.classList.contains("podify-row")){ card.classList.add("podify-row"); card.classList.remove("podify-modern"); card.classList.remove("podify-list-layout-card"); }';
        $html .= '      var link = card.querySelector(".podify-read-more");';
        $html .= '      if(!link){';
        $html .= '        if(isList) return;';
        $html .= '        var t = card.getAttribute("data-title") || "";';
        $html .= '        var slug = t.toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,"");';
        $html .= '        var url = BASE_URL + slug + "/";';
        $html .= '        link = document.createElement("a"); link.className = "podify-read-more"; link.innerHTML = READ_MORE_TEXT + " <i class=\'fa fa-angle-right\'></i>"; link.href = url;';
        $html .= '        var actions = card.querySelector(".podify-episode-actions");';
        $html .= '        var body = card.querySelector(".podify-episode-body");';
        $html .= '        if(isModern && actions){ actions.insertBefore(link, actions.firstChild); }';
        $html .= '        else if(!isModern && body && actions){ body.insertBefore(link, actions); }';
        $html .= '        else if(body){ body.appendChild(link); }';
        $html .= '      }';
        $html .= '    });';
        $html .= '  });';
        $html .= '}/* ensureLayoutAndLinks(); */';
        
        // Play Button Click Handler
        $html .= 'document.addEventListener("click",function(e){var btn=e.target.closest(".podify-play-action-btn");if(!btn)return;var card=btn.closest(".podify-episode-card");if(!card)return;var src=card.getAttribute("data-audio");if(!src)return;e.preventDefault();try{var player=document.getElementById("podify-sticky-player");var stickyAudio=document.getElementById("podify-sticky-audio");var titleEl=document.getElementById("podify-sticky-title");var imgEl=document.getElementById("podify-sticky-img");var playBtn=document.getElementById("podify-sticky-play");var volBtn=document.getElementById("podify-sticky-volume");if(stickyAudio&&player){stickyAudio.src=src;stickyAudio.setAttribute("data-duration",card.getAttribute("data-duration")||"");stickyAudio.setAttribute("data-duration-seconds",card.getAttribute("data-duration-seconds")||"");document.body.classList.add("podify-player-active");player.style.setProperty("display","block","important");if(titleEl)titleEl.textContent=card.getAttribute("data-title")||titleEl.textContent;if(imgEl)imgEl.src=card.getAttribute("data-image")||imgEl.src;if(playBtn)playBtn.innerHTML=\'<svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><circle cx="12" cy="12" r="12" fill="white"/><path d="M9 8h2v8H9V8zm4 0h2v8h-2V8z" fill="black"/></svg>\';try{stickyAudio.load()}catch(_e){}document.querySelectorAll(".podify-episode-card.podify-playing").forEach(function(x){x.classList.remove("podify-playing")});card.classList.add("podify-playing");stickyAudio.play().catch(function(err){console.error("Podify: Sticky play failed from overlay click",err)})}}catch(err){console.error("Podify: Overlay click error",err)}});';
        
        // Duration Formatter
        $html .= 'function fmtDur(s){if(!s)return"";var sec=0;if(/^[0-9]+$/.test(s)){sec=parseInt(s,10)}else{var parts=s.split(":").map(function(x){return parseInt(x,10)||0});for(var i=0;i<parts.length;i++){sec=sec*60+parts[i]}}var h=Math.floor(sec/3600),m=Math.floor((sec%3600)/60),se=sec%60;return h>0?(h+":"+(m<10?"0":"")+m+":"+(se<10?"0":"")+se):(m+":"+(se<10?"0":"")+se)}';
        
        // Load More Handler
        $html .= 'document.addEventListener("click",function(e){';
        $html .= '  var btn=e.target.closest(".podify-load-more");if(!btn)return;';
        $html .= '  e.preventDefault(); e.stopImmediatePropagation();';
        $html .= '  var id=btn.getAttribute("data-target"); var grid=document.getElementById(id); if(!grid)return;';
        $html .= '  var limit=parseInt(grid.getAttribute("data-limit"))||9; var offset=parseInt(grid.getAttribute("data-offset"))||0;';
        $html .= '  var feed=grid.getAttribute("data-feed")||""; var cat=grid.getAttribute("data-category")||"";';
        $html .= '  var layout=grid.getAttribute("data-layout")||"classic"; var isModern=(layout==="modern"); var isList=(layout==="list");';
        $html .= '  btn.disabled=true; var oldT=btn.textContent; btn.textContent="Loading...";';
        $html .= '  var url=EP_URL+"?limit="+limit+"&offset="+offset+(feed?("&feed_id="+encodeURIComponent(feed)):"")+(cat?("&category_id="+encodeURIComponent(cat)):"");';
        $html .= '  fetch(url).then(parseJSONSafe).then(function(d){';
        $html .= '    btn.disabled=false; btn.textContent=oldT;';
        $html .= '    if(!d||!d.items||!d.items.length){ btn.textContent="No more"; btn.disabled=true; return; }';
        $html .= '    var h="";';
        $html .= '    d.items.forEach(function(ei){';
        $html .= '      var t=ei.title||"Untitled"; var pm=ei.permalink||(BASE_URL+(t.toLowerCase().replace(/[^a-z0-9]+/g,"-").replace(/^-+|-+$/g,""))+"/");';
        $html .= '      var dt=ei.published?new Date(ei.published):null; var dtS=dt?dt.toLocaleDateString():"";';
        $html .= '      var dur=fmtDur(ei.duration||""); var dSec=(function(){var s=ei.duration||"";var z=0;if(/^[0-9]+$/.test(s)){z=parseInt(s,10)}else{var p=s.split(":");p.forEach(function(x){z=z*60+(parseInt(x,10)||0)})}return z})();';
        $html .= '      var tg=(ei.tags||"").split(",").filter(function(x){return x.trim().length}).slice(0,3).join(", ");';
        $html .= '      var im=ei.image_url||""; var au=ei.audio_url||""; var de=ei.description||"";';
        $html .= '      if(de.length>0){de=de.replace(/<[^>]+>/g,"");if(de.length>180)de=de.slice(0,180)+"…";}';
        $html .= '      var mp=[]; if(dtS)mp.push(dtS); if(tg)mp.push(tg); var ml=mp.join(" · ");';
        $html .= '      var catClasses = "";';
        if ($category_id) {
            $html .= '      catClasses += " podify-cat-' . intval($category_id) . '";';
        }
        $html .= '      if (ei.categories && ei.categories.length) {';
        $html .= '        ei.categories.forEach(function(cat){ if (!catClasses.includes("podify-cat-" + cat.id)) { catClasses += " podify-cat-" + cat.id; } });';
        $html .= '      } else { catClasses += " podify-uncat"; }';
        $html .= '      var cc=((isModern?"podify-episode-card podify-modern":(isList?"podify-episode-card podify-list-layout-card":"podify-episode-card podify-row"))) + catClasses;';
        $html .= '      var da=" data-title=\""+t.replace(/"/g,"&quot;")+"\""; if(au)da+=" data-audio=\""+au+"\""; if(im)da+=" data-image=\""+im+"\""; da+=" data-duration=\""+dur+"\" data-duration-seconds=\""+dSec+"\"";';
        $html .= '      var cth=""; if(SHOW_CATS){';
        $html .= '        cth += "<div class=\"podify-episode-categories\">";';
        $html .= '        if(ei.categories && ei.categories.length){';
        $html .= '          ei.categories.forEach(function(cat){ cth += "<span class=\"podify-category-pill\">"+cat.name+"</span>"; });';
        $html .= '        } else {';
        $html .= '          cth += "<span class=\"podify-category-pill\">Uncategorized</span>";';
        $html .= '        }';
        $html .= '        cth += "</div>";';
        $html .= '      }';
        $html .= '      h+="<div class=\""+cc+"\""+da+">";';
        $html .= '      if(isModern){';
        $html .= '        h+="<div class=\"podify-episode-media\">"+(im?"<img src=\""+im+"\" alt=\""+t.replace(/"/g,"&quot;")+"\" loading=\"lazy\" style=\"width:100%;height:100%;object-fit:cover;\">":"<div class=\"podify-episode-placeholder\"></div>")+"</div>";';
        $html .= '        h+="<div class=\"podify-episode-body\"><div class=\"podify-episode-top\">"+cth+"<h3 class=\"podify-episode-title\"><a href=\""+pm+"\" class=\"podify-episode-link\">"+t+"</a></h3></div>";';
        $html .= '        if(de)h+="<div class=\"podify-episode-desc podify-clamp-2\">"+de+"</div>";';
        $html .= '        if(ml)h+="<div class=\"podify-episode-meta\">"+ml+"</div>";';
        $html .= '        h+="<div class=\"podify-episode-actions\"><a class=\"podify-read-more\" href=\""+pm+"\">"+READ_MORE_TEXT+" <i class=\"fa fa-angle-right\"></i></a>";';
        $html .= '        if(au && STICKY_ENABLED)h+="<button class=\"podify-play-action-btn\" aria-label=\"Play\"><svg viewBox=\"0 0 24 24\" width=\"36\" height=\"36\" fill=\"currentColor\"><path d=\"M8 5v14l11-7z\"/></svg></button>";';
        $html .= '        if(dur && STICKY_ENABLED)h+="<span class=\"podify-episode-duration\">"+dur+"</span>";';
        $html .= '        h+="</div></div>";';
        $html .= '      } else if(isList) {';
        $html .= '        var durLbl=""; if(dSec>0){ if(dSec>=3600){ var hh=Math.floor(dSec/3600); var mm=Math.floor((dSec%3600)/60); durLbl = hh+"h"+(mm>0?(" "+mm+"m"):""); } else { var mn=Math.max(1, Math.round(dSec/60)); durLbl = mn+" min"; } }';
        $html .= '        h+="<div class=\"podify-episode-media podify-list-thumb\">"+(im?"<img src=\""+im+"\" alt=\""+t.replace(/"/g,"&quot;")+"\" loading=\"lazy\" style=\"width:100%;height:100%;object-fit:contain;\">":"<div class=\"podify-episode-placeholder\"></div>")+"</div>";';
        $html .= '        h+="<div class=\"podify-episode-body podify-list-content\">";';
        $html .= '        h+="<div class=\"podify-list-meta-row\">"+cth+(durLbl?"<span class=\"podify-list-duration\">"+durLbl+"</span>":"")+"</div>";';
        $html .= '        h+="<h3 class=\"podify-episode-title\"><a href=\""+pm+"\" class=\"podify-episode-link\">"+t+"</a></h3>";';
        $html .= '        if(de)h+="<div class=\"podify-episode-desc podify-list-desc\">"+de+"</div>";';
        $html .= '        h+="<div class=\"podify-list-bottom-row\">";';
        $html .= '        if(au && STICKY_ENABLED){ h+="<button class=\"podify-play-action-btn podify-list-play-btn\" aria-label=\"Play Episode\"><span class=\"podify-list-play-icon\"><svg viewBox=\"0 0 24 24\" width=\"18\" height=\"18\" fill=\"currentColor\"><path d=\"M8 5v14l11-7z\"/></svg></span><span class=\"podify-list-play-text\">Play Episode</span></button>"; } else { h+="<a class=\"podify-read-more podify-list-play-btn\" href=\""+pm+"\">"+READ_MORE_TEXT+"</a>"; }';
        $html .= '        if(dtS)h+="<span class=\"podify-list-date\">"+dtS+"</span>";';
        $html .= '        h+="</div></div>";';
        $html .= '      } else {';
        $html .= '        h+="<div class=\"podify-episode-media\">"+(im?"<img src=\""+im+"\" alt=\""+t.replace(/"/g,"&quot;")+"\" loading=\"lazy\" style=\"width:100%;height:100%;object-fit:cover;\">":"<div class=\"podify-episode-placeholder\"></div>")+"</div>";';
        $html .= '        h+="<div class=\"podify-episode-body\"><div class=\"podify-episode-top\"><h3 class=\"podify-episode-title\"><a href=\""+pm+"\" class=\"podify-episode-link\">"+t+"</a></h3></div>"+cth;';
        $html .= '        if(de)h+="<div class=\"podify-episode-desc podify-clamp-2\">"+de+"</div>";';
        $html .= '        h+="<a class=\"podify-read-more\" href=\""+pm+"\">"+READ_MORE_TEXT+" <i class=\"fa fa-angle-right\"></i></a>";';
        $html .= '        h+="<div class=\"podify-episode-actions\">";';
        $html .= '        if(au && STICKY_ENABLED)h+="<button class=\"podify-play-action-btn\" aria-label=\"Play\"><svg viewBox=\"0 0 24 24\" width=\"36\" height=\"36\" fill=\"currentColor\"><path d=\"M8 5v14l11-7z\"/></svg></button>";';
        $html .= '        if(dur && STICKY_ENABLED)h+="<span class=\"podify-episode-duration\">"+dur+"</span>";';
        $html .= '        h+="</div>";';
        $html .= '        if(ml)h+="<div class=\"podify-episode-meta\">"+ml+"</div>";';
        $html .= '        h+="</div>";';
        $html .= '      }';
        $html .= '      h+="</div>";';
        $html .= '    });';
        $html .= '    var tmp=document.createElement("div"); tmp.innerHTML=h;';
        $html .= '    Array.from(tmp.children).forEach(function(n){ grid.appendChild(n); });';
        $html .= '    grid.setAttribute("data-offset", offset+d.items.length);';
        $html .= '    setCardMediaAspect(grid); ensureLayoutAndLinks(grid);';
        $html .= '  }).catch(function(e){ console.error(e); btn.disabled=false; btn.textContent="Error"; });';
        $html .= '});';
        $html .= '})();</script>';
        return $html;
    }
    public static function inject_sticky_player() {
        echo self::render_sticky();
    }
    public static function render_sticky($atts = []) {
        $settings = \PodifyPodcast\Core\Settings::get();
        $enabled = !empty($settings['sticky_player_enabled']);
        $feed_id = isset($atts['feed_id']) ? intval($atts['feed_id']) : null;
        
        $html = '';
        if ($enabled) {
            $position = !empty($settings['sticky_player_position']) ? $settings['sticky_player_position'] : 'bottom';
            $posClass = $position === 'top' ? 'podify-pos-top' : 'podify-pos-bottom';
            
            $latest = \PodifyPodcast\Core\Database::get_episodes($feed_id ?: null, 50, 0);
            $ep = null;
            if (is_array($latest) && !empty($latest)) {
                foreach ($latest as $row) {
                    if (!empty($row['audio_url'])) { $ep = $row; break; }
                }
                if (!$ep) { $ep = $latest[0]; }
            }
            $ep_title = $ep && !empty($ep['title']) ? esc_html($ep['title']) : 'Untitled Episode';
            $ep_img = $ep && !empty($ep['image_url']) ? esc_url($ep['image_url']) : '';
            $ep_audio = $ep && !empty($ep['audio_url']) ? esc_url(trim($ep['audio_url'])) : '';
            if ($ep && !empty($ep['post_id'])) {
                $pid = intval($ep['post_id']);
                if ($pid > 0) {
                    $maudio = get_post_meta($pid, '_podify_audio_url', true);
                    $mimage = get_post_meta($pid, '_podify_episode_image', true);
                    // if (!empty($maudio) && wp_http_validate_url($maudio)) { $ep_audio = esc_url(trim($maudio)); }
                    if (has_post_thumbnail($pid)) {
                        $thumb = get_the_post_thumbnail_url($pid, 'large');
                        if ($thumb) { $ep_img = esc_url($thumb); }
                    } elseif (!empty($mimage) && wp_http_validate_url($mimage)) { 
                        $ep_img = esc_url($mimage); 
                    }
                }
            }
            $ep_sub = '';
            if ($ep && !empty($ep['published'])) {
                $ep_sub = esc_html( date_i18n(get_option('date_format'), strtotime($ep['published'])) );
            }
            
            
            $html .= '<div id="podify-sticky-player" class="podify-sticky-player '.$posClass.'">';
            $html .= '<audio id="podify-sticky-audio" class="no-mejs" src="'.($ep_audio ?: '').'" preload="none" style="display:none !important; visibility:hidden !important; height:0; width:0;"></audio>';
            $html .= '<div class="podify-sticky-inner">';

            // Left: Controls
            $html .= '<div class="podify-sticky-controls">';
            $html .= '<div class="podify-sticky-thumb"><img id="podify-sticky-img" src="'.($ep_img ?: '').'" alt=""></div>';
            $html .= '<button id="podify-sticky-play" class="podify-play-btn-large" style="margin-right:12px;"><svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><circle cx="12" cy="12" r="12" fill="white"/><path d="M9.5 8l6 4-6 4V8z" fill="black"/></svg></button>';
            
            // Volume Controls
            $html .= '<div class="podify-sticky-volume-wrap" style="display:flex; align-items:center; margin-right:12px;">';
            $html .= '<button id="podify-sticky-volume-btn" class="podify-sp-volume-btn" aria-label="Mute" style="border:none; background:none; padding:0; cursor:pointer; color:#fff; display:flex; align-items:center; margin-right:4px;">';
            $html .= '<svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor"><path d="M3 9v6h4l5 4V5L7 9H3z"/><path d="M14 8.5a4.5 4.5 0 010 7" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
            $html .= '</button>';
            $html .= '<input type="range" id="podify-sticky-volume-slider" class="podify-sp-volume-slider" min="0" max="1" step="0.05" value="1" style="width:60px; cursor:pointer;">';
            $html .= '</div>';
            
            $html .= '<div class="podify-sticky-meta"><div id="podify-sticky-title">'.($ep_title ?: '').'</div><div id="podify-sticky-subtitle">'.($ep_sub ?: '').'</div></div>';
            $html .= '</div>';

            // Center: Waveform
            $html .= '<div class="podify-sticky-progress-container">';
            $html .= '<svg id="podify-sticky-svg" viewBox="0 0 567 45" preserveAspectRatio="none">';
            $html .= '<defs><linearGradient id="podify-sticky-grad" x1="0" x2="1" y1="0" y2="0"><stop offset="0%" stop-color="#0b5bd3"/><stop offset="0%" stop-color="#e2e8f0"/></linearGradient></defs>';
            $html .= '<path id="podify-sticky-progress-path" d="M 0 30 L 0 30 A 2.25 2.25 0 0 0 4.5 30 A 2.25 2.25 0 0 1 9 30 L 9 37.14888991369655 A 2.25 2.25 0 0 0 13.5 37.14888991369655 L 13.5 24.81576520192371 A 2.25 2.25 0 0 1 18 24.81576520192371 L 18 40.138563146452334 A 2.25 2.25 0 0 0 22.5 40.138563146452334 L 22.5 19.60218064431122 A 2.25 2.25 0 0 1 27 19.60218064431122 L 27 37.719431451347255 A 2.25 2.25 0 0 0 31.5 37.719431451347255 L 31.5 20.517359509849136 A 2.25 2.25 0 0 1 36 20.517359509849136 L 36 37.415261216153894 A 2.25 2.25 0 0 0 40.5 37.415261216153894 L 40.5 21.577953093089135 A 2.25 2.25 0 0 1 45 21.577953093089135 L 45 37.80570195665064 A 2.25 2.25 0 0 0 49.5 37.80570195665064 L 49.5 21.28045325779037 A 2.25 2.25 0 0 1 54 21.28045325779037 L 54 38.571463864549706 A 2.25 2.25 0 0 0 58.5 38.571463864549706 L 58.5 20.29323407339087 A 2.25 2.25 0 0 1 63 20.29323407339087 L 63 41.12756110415706 A 2.25 2.25 0 0 0 67.5 41.12756110415706 L 67.5 20.563607615784967 A 2.25 2.25 0 0 1 72 20.563607615784967 L 72 41.40238157981422 A 2.25 2.25 0 0 0 76.5 41.40238157981422 L 76.5 19.282890836023455 A 2.25 2.25 0 0 1 81 19.282890836023455 L 81 39.10731932274854 A 2.25 2.25 0 0 0 85.5 39.10731932274854 L 85.5 21.333371763620793 A 2.25 2.25 0 0 1 90 21.333371763620793 L 90 38.17479741748468 A 2.25 2.25 0 0 0 94.5 38.17479741748468 L 94.5 20.75838329270703 A 2.25 2.25 0 0 1 99 20.75838329270703 L 99 38.16279069767442 A 2.25 2.25 0 0 0 103.5 38.16279069767442 L 103.5 21.401854535871927 A 2.25 2.25 0 0 1 108 21.401854535871927 L 108 39.68008432703077 A 2.25 2.25 0 0 0 112.5 39.68008432703077 L 112.5 18.0866657882601 A 2.25 2.25 0 0 1 117 18.0866657882601 L 117 39.63339152776863 A 2.25 2.25 0 0 0 121.5 39.63339152776863 L 121.5 20.792179985506294 A 2.25 2.25 0 0 1 126 20.792179985506294 L 126 39.769912378944596 A 2.25 2.25 0 0 0 130.5 39.769912378944596 L 130.5 20.827755451610777 A 2.25 2.25 0 0 1 135 20.827755451610777 L 135 39.15267804203175 A 2.25 2.25 0 0 0 139.5 39.15267804203175 L 139.5 20.61786020159431 A 2.25 2.25 0 0 1 144 20.61786020159431 L 144 41.08709401146321 A 2.25 2.25 0 0 0 148.5 41.08709401146321 L 148.5 19.70446010936162 A 2.25 2.25 0 0 1 153 19.70446010936162 L 153 39.563574675538575 A 2.25 2.25 0 0 0 157.5 39.563574675538575 L 157.5 19.670663416562356 A 2.25 2.25 0 0 1 162 19.670663416562356 L 162 37.19780617959022 A 2.25 2.25 0 0 0 166.5 37.19780617959022 L 166.5 21.22842413861256 A 2.25 2.25 0 0 1 171 21.22842413861256 L 171 40.35735226299492 A 2.25 2.25 0 0 0 175.5 40.35735226299492 L 175.5 19.413630673957442 A 2.25 2.25 0 0 1 180 19.413630673957442 L 180 39.156680281968505 A 2.25 2.25 0 0 0 184.5 39.156680281968505 L 184.5 21.18262072600303 A 2.25 2.25 0 0 1 189 21.18262072600303 L 189 41.46108109888662 A 2.25 2.25 0 0 0 193.5 41.46108109888662 L 193.5 20.721029053297322 A 2.25 2.25 0 0 1 198 20.721029053297322 L 198 40.01049146847618 A 2.25 2.25 0 0 0 202.5 40.01049146847618 L 202.5 20.52803214968048 A 2.25 2.25 0 0 1 207 20.52803214968048 L 207 38.40826141379537 A 2.25 2.25 0 0 0 211.5 38.40826141379537 L 211.5 20.367497858883986 A 2.25 2.25 0 0 1 216 20.367497858883986 L 216 38.73955794189341 A 2.25 2.25 0 0 0 220.5 38.73955794189341 L 220.5 21.79540812965281 A 2.25 2.25 0 0 1 225 21.79540812965281 L 225 39.56401936886488 A 2.25 2.25 0 0 0 229.5 39.56401936886488 L 229.5 21.9701726068911 A 2.25 2.25 0 0 1 234 21.9701726068911 L 234 43.5 A 2.25 2.25 0 0 0 238.5 43.5 L 238.5 19.463436326503725 A 2.25 2.25 0 0 1 243 19.463436326503725 L 243 41.46908557876013 A 2.25 2.25 0 0 0 247.5 41.46908557876013 L 247.5 19.62930693721589 A 2.25 2.25 0 0 1 252 19.62930693721589 L 252 37.672293958758814 A 2.25 2.25 0 0 0 256.5 37.672293958758814 L 256.5 20.705464786876608 A 2.25 2.25 0 0 1 261 20.705464786876608 L 261 41.823506159826074 A 2.25 2.25 0 0 0 265.5 41.823506159826074 L 265.5 19.11524145200606 A 2.25 2.25 0 0 1 270 19.11524145200606 L 270 39.017046577508395 A 2.25 2.25 0 0 0 274.5 39.017046577508395 L 274.5 20.665887080835365 A 2.25 2.25 0 0 1 279 20.665887080835365 L 279 40.95857764016075 A 2.25 2.25 0 0 0 283.5 40.95857764016075 L 283.5 18.329913037749524 A 2.25 2.25 0 0 1 288 18.329913037749524 L 288 39.46529745042493 A 2.25 2.25 0 0 0 292.5 39.46529745042493 L 292.5 21.292904670926937 A 2.25 2.25 0 0 1 297 21.292904670926937 L 297 39.91532709664668 A 2.25 2.25 0 0 0 301.5 39.91532709664668 L 301.5 21.984402793332897 A 2.25 2.25 0 0 1 306 21.984402793332897 L 306 37.24805652546281 A 2.25 2.25 0 0 0 310.5 37.24805652546281 L 310.5 21.951050793859938 A 2.25 2.25 0 0 1 315 21.951050793859938 L 315 42.894327689571114 A 2.25 2.25 0 0 0 319.5 42.894327689571114 L 319.5 20.79484814546413 A 2.25 2.25 0 0 1 324 20.79484814546413 L 324 39.28830950655511 A 2.25 2.25 0 0 0 328.5 39.28830950655511 L 328.5 19.499011792608208 A 2.25 2.25 0 0 1 333 19.499011792608208 L 333 39.476859476908885 A 2.25 2.25 0 0 0 337.5 39.476859476908885 L 337.5 21.327590750378814 A 2.25 2.25 0 0 1 342 21.327590750378814 L 342 39.62938928783187 A 2.25 2.25 0 0 0 346.5 39.62938928783187 L 346.5 22.48557217207985 A 2.25 2.25 0 0 1 351 22.48557217207985 L 351 39.38792081164767 A 2.25 2.25 0 0 0 355.5 39.38792081164767 L 355.5 20.38617497858884 A 2.25 2.25 0 0 1 360 20.38617497858884 L 360 41.70744120166019 A 2.25 2.25 0 0 0 364.5 41.70744120166019 L 364.5 21.52903682719547 A 2.25 2.25 0 0 1 369 21.52903682719547 L 369 42.287765992489625 A 2.25 2.25 0 0 0 373.5 42.287765992489625 L 373.5 20.093122076553133 A 2.25 2.25 0 0 1 378 20.093122076553133 L 378 40.52989327360169 A 2.25 2.25 0 0 0 382.5 40.52989327360169 L 382.5 20.88556558403057 A 2.25 2.25 0 0 1 387 20.88556558403057 L 387 38.36468146781738 A 2.25 2.25 0 0 0 391.5 38.36468146781738 L 391.5 21.639320772119376 A 2.25 2.25 0 0 1 396 21.639320772119376 L 396 40.53745306014889 A 2.25 2.25 0 0 0 400.5 40.53745306014889 L 400.5 20.017524211081103 A 2.25 2.25 0 0 1 405 20.017524211081103 L 405 39.86774491073193 A 2.25 2.25 0 0 0 409.5 39.86774491073193 L 409.5 22.12581527109823 A 2.25 2.25 0 0 1 414 22.12581527109823 L 414 41.34101390078398 A 2.25 2.25 0 0 0 418.5 41.34101390078398 L 418.5 20.150932208972925 A 2.25 2.25 0 0 1 423 20.150932208972925 L 423 40.17324922590421 A 2.25 2.25 0 0 0 427.5 40.17324922590421 L 427.5 22.434432439554648 A 2.25 2.25 0 0 1 432 22.434432439554648 L 432 42.78982475788919 A 2.25 2.25 0 0 0 436.5 42.78982475788919 L 436.5 18.113792081164767 A 2.25 2.25 0 0 1 441 18.113792081164767 L 441 39.58269648856974 A 2.25 2.25 0 0 0 445.5 39.58269648856974 L 445.5 20.907355557019567 A 2.25 2.25 0 0 1 450 20.907355557019567 L 450 38.47674418604651 A 2.25 2.25 0 0 0 454.5 38.47674418604651 L 454.5 19.196620330720073 A 2.25 2.25 0 0 1 459 19.196620330720073 L 459 39.723664273008765 A 2.25 2.25 0 0 0 463.5 39.723664273008765 L 463.5 20.20607418143488 A 2.25 2.25 0 0 1 468 20.20607418143488 L 468 41.34412675406812 A 2.25 2.25 0 0 0 472.5 41.34412675406812 L 472.5 20.058880690427564 A 2.25 2.25 0 0 1 477 20.058880690427564 L 477 40.14523354634693 A 2.25 2.25 0 0 0 481.5 40.14523354634693 L 481.5 22.182736016865405 A 2.25 2.25 0 0 1 486 22.182736016865405 L 486 39.776138085512876 A 2.25 2.25 0 0 0 490.5 39.776138085512876 L 490.5 21.966615060280652 A 2.25 2.25 0 0 1 495 21.966615060280652 L 495 40.25907503788128 A 2.25 2.25 0 0 0 499.5 40.25907503788128 L 499.5 23.037436590025692 A 2.25 2.25 0 0 1 504 23.037436590025692 L 504 40.57391791290598 A 2.25 2.25 0 0 0 508.5 40.57391791290598 L 508.5 22.549608011067924 A 2.25 2.25 0 0 1 513 22.549608011067924 L 513 40.810050069174515 A 2.25 2.25 0 0 0 517.5 40.810050069174515 L 517.5 21.02164174188023 A 2.25 2.25 0 0 1 522 21.02164174188023 L 522 40.33600698333223 A 2.25 2.25 0 0 0 526.5 40.33600698333223 L 526.5 19.717356215824495 A 2.25 2.25 0 0 1 531 19.717356215824495 L 531 40.670416364714406 A 2.25 2.25 0 0 0 535.5 40.670416364714406 L 535.5 20.999851768891233 A 2.25 2.25 0 0 1 540 20.999851768891233 L 540 40.158574346136106 A 2.25 2.25 0 0 0 544.5 40.158574346136106 L 544.5 20.859773371104815 A 2.25 2.25 0 0 1 549 20.859773371104815 L 549 38.727106528756835 A 2.25 2.25 0 0 0 553.5 38.727106528756835 L 553.5 21.160386059687724 A 2.25 2.25 0 0 1 558 21.160386059687724 L 558 30 A 2.25 2.25 0 0 0 562.5 30 A 2.25 2.25 0 0 1 567 30" fill="url(#podify-sticky-grad)"></path>';
            $html .= '</svg>';
            $html .= '</div>';

            // Right: Time, Volume
            $html .= '<div class="podify-sticky-right">';
            $html .= '<span id="podify-sticky-time">0:00 / 0:00</span>';
            $html .= '<div class="podify-volume-wrapper" style="display:flex;align-items:center;">';
            $html .= '<button id="podify-sticky-volume" aria-label="Mute/Unmute" class="podify-volume-btn"><svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M3 9v6h4l5 4V5L7 9H3z"></path></svg></button>';
            $html .= '<input type="range" id="podify-sticky-volume-slider" class="podify-volume-slider" min="0" max="1" step="0.05" value="1" style="width:60px;margin-left:8px;cursor:pointer;">';
            $html .= '</div>';
            $html .= '</div>';

            $html .= '</div></div>'; // End inner, End player

        }

        // Inline JS for sticky player logic - FIXED VERSION
        $html .= '<script>
        (function(){
            try {
                function parseJSONSafe(r){return r.text().then(function(t){ if(!t||t.trim().charAt(0)==="<"){console.warn("Podify: Received HTML/Invalid JSON in sticky", t.substring(0,100));return null;}try{return JSON.parse(t);}catch(_e){console.error("Podify Sticky JSON Error:", _e); return null;}});}
                var EP_URL = '.wp_json_encode( esc_url_raw( rest_url('podify/v1/episodes') ) ).';
                var FEED_ID = '.wp_json_encode( $feed_id ? intval($feed_id) : null ).';
                var FEED_ID_JS = (FEED_ID !== null && FEED_ID !== undefined) ? FEED_ID : (function(){ var el = document.querySelector(".podify-episodes-grid[data-feed]"); if(!el) return null; var v = parseInt(el.getAttribute("data-feed")); return isNaN(v)?null:v; })();
                var player = document.getElementById("podify-sticky-player");
                if(!player) { console.error("Podify: Sticky player element not found in DOM"); return; }
                else {
                    // Move player to body to ensure fixed positioning works relative to viewport
                    // This fixes issues where the player is trapped inside a container with transform/filter
                    if (player.parentNode !== document.body) {
                        document.body.appendChild(player);
                    }
                }

                var imgEl = document.getElementById("podify-sticky-img");
                var titleEl = document.getElementById("podify-sticky-title");
                var subEl = document.getElementById("podify-sticky-subtitle");
                var playBtn = document.getElementById("podify-sticky-play");
                var rewindBtn = document.getElementById("podify-sticky-rewind");
                var volBtn = document.getElementById("podify-sticky-volume");
                var volSlider = document.getElementById("podify-sticky-volume-slider");
                var range = document.getElementById("podify-sticky-range");
                var timeEl = document.getElementById("podify-sticky-time");
                var wave = document.getElementById("podify-wave");
                var waveProg = document.getElementById("podify-wave-progress");
                var stickyAudio = document.getElementById("podify-sticky-audio");
                var currentAudio = null;

                // SVGs
                var SVG_PLAY = \'<svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><circle cx="12" cy="12" r="12" fill="white"/><path d="M9.5 8l6 4-6 4V8z" fill="black"/></svg>\';
                var SVG_PAUSE = \'<svg viewBox="0 0 24 24" width="40" height="40" fill="currentColor"><circle cx="12" cy="12" r="12" fill="white"/><path d="M9 8h2v8H9V8zm4 0h2v8h-2V8z" fill="black"/></svg>\';
                var SVG_VOL_ON = \'<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M3 9v6h4l5 4V5L7 9H3z"></path><path d="M14 8.5a4.5 4.5 0 010 7" fill="none" stroke="currentColor" stroke-width="2"></path></svg>\';
                var SVG_VOL_OFF = \'<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M3 9v6h4l5 4V5L7 9H3z"></path><path d="M16 8l4 4-4 4M12 8l-4 4 4 4" fill="none" stroke="currentColor" stroke-width="2"></path></svg>\';
                var SVG_SP_PLAY = \'<svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><circle cx="12" cy="12" r="12" fill="#0b5bd3"/><path d="M9.5 8l6 4-6 4V8z" fill="white"/></svg>\';
                var SVG_SP_PAUSE = \'<svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor"><circle cx="12" cy="12" r="12" fill="#0b5bd3"/><path d="M9 8h2v8H9V8zm4 0h2v8h-2V8z" fill="white"/></svg>\';
                
                function fmtTime(s) {
                    var m = Math.floor(s / 60);
                    var se = Math.floor(s % 60);
                    var h = Math.floor(m / 60);
                    m = m % 60;
                    if(h > 0) return h + ":" + (m < 10 ? "0" : "") + m + ":" + (se < 10 ? "0" : "") + se;
                    return m + ":" + (se < 10 ? "0" : "") + se;
                }

                // range removed

                function setInitialTimeFromSticky(){
                    if(!timeEl || !stickyAudio) return;
                    var dsAttr = stickyAudio.getAttribute("data-duration");
                    var dsSec = parseFloat(stickyAudio.getAttribute("data-duration-seconds")) || 0;
                    var durStr = dsAttr && dsAttr.length ? dsAttr : (dsSec ? fmtTime(dsSec) : "0:00");
                    timeEl.textContent = "0:00 / " + durStr;
                }
                setInitialTimeFromSticky();

                // Bootstrap metadata if empty (no local episode rendered)
                (function bootstrapMeta(){
                    var hasTitle = titleEl && titleEl.textContent && titleEl.textContent.trim().length;
                    var hasImg = imgEl && imgEl.getAttribute("src") && imgEl.getAttribute("src").trim().length;
                    if (hasTitle || hasImg) return;
                    var url = EP_URL + "?limit=1&offset=0" + (FEED_ID ? ("&feed_id="+encodeURIComponent(FEED_ID)) : "");
                    fetch(url).then(parseJSONSafe).then(function(d){
                        if(d && d.items && d.items.length){
                            var it = d.items[0];
                            if(titleEl) titleEl.textContent = it.title || titleEl.textContent;
                            if(imgEl && it.image_url) imgEl.src = it.image_url;
                            if(subEl && it.published){ try{ var dt = new Date(it.published); subEl.textContent = dt.toLocaleDateString(); }catch(_e){} }
                            if(stickyAudio){
                                if(it.duration){
                                    var s = it.duration;
                                    var dsFmt = /^[0-9]+$/.test(s) ? (function(x){ var m=Math.floor(x/60), se=Math.floor(x%60), h=Math.floor(m/60); m=m%60; return h>0?(h+":"+(m<10?"0":"")+m+":"+(se<10?"0":"")+se):(m+":"+(se<10?"0":"")+se); })(parseInt(s,10)) : s;
                                    stickyAudio.setAttribute("data-duration", dsFmt);
                                }
                                var ds = 0; var s = it.duration || ""; if(/^[0-9]+$/.test(s)){ ds=parseInt(s,10);} else { var parts=s.split(":").map(function(x){ return parseInt(x,10)||0 }); for(var i=0;i<parts.length;i++){ ds=ds*60+parts[i]; } }
                                if(ds>0) stickyAudio.setAttribute("data-duration-seconds", ds);
                            }
                            updateProgress();
                        }
                    }).catch(function(err){ console.error("Podify: Bootstrap metadata failed", err); });
                })();

                document.addEventListener("play", function(e){
                    if(e.target && e.target.tagName === "AUDIO" && e.target.classList.contains("podify-episode-audio")) {
                        // Avoid infinite loops if fallback triggers play
                        if (e.target.dataset.podifyFallback === "true") {
                            e.target.dataset.podifyFallback = "false";
                            currentAudio = e.target;
                            updateProgress();
                            return;
                        }
                        
                        try {
                            // Ensure sticky player and its audio element exist and are valid
                            if(stickyAudio && player){
                                e.target.pause();
                                var cleanSrc = (e.target.src || "").trim();
                                if (cleanSrc !== stickyAudio.src) {
                                    stickyAudio.src = cleanSrc;
                                }
                                stickyAudio.setAttribute("data-duration", e.target.getAttribute("data-duration")||"");
                                stickyAudio.setAttribute("data-duration-seconds", e.target.getAttribute("data-duration-seconds")||"");
                                
                                currentAudio = stickyAudio;
                                document.body.classList.add("podify-player-active");
                                player.style.setProperty("display","block","important");
                                
                                if(titleEl) titleEl.textContent = e.target.getAttribute("data-title")||titleEl.textContent;
                                if(imgEl) imgEl.src = e.target.getAttribute("data-image")||imgEl.src;
                                if(playBtn) playBtn.innerHTML = SVG_PAUSE;
                                if(volBtn) volBtn.innerHTML = currentAudio.muted ? SVG_VOL_OFF : SVG_VOL_ON;
                                
                                var p = stickyAudio.play();
                                if (p !== undefined) {
                                    p.then(function(){
                                        updateProgress();
                                    }).catch(function(err){
                                        console.error("Podify: Sticky play failed, falling back to local", err);
                                        // Fallback: Resume local audio
                                        e.target.dataset.podifyFallback = "true";
                                        e.target.play();
                                    });
                                }
                            } else {
                                currentAudio = e.target;
                                updateProgress();
                            }
                        } catch(err){
                            console.error("Podify: Error in play intercept", err);
                            // Fallback for sync errors
                            if (e.target.paused) {
                                e.target.dataset.podifyFallback = "true";
                                e.target.play();
                            }
                        }
                    }
                }, true);
                document.addEventListener("play", function(e){
                    if(e.target === stickyAudio || e.target === currentAudio) {
                        if(playBtn) playBtn.innerHTML = SVG_PAUSE;
                        updateProgress();
                    }
                }, true);

                document.addEventListener("pause", function(e){
                    if(e.target === currentAudio) {
                        if(playBtn) playBtn.innerHTML = SVG_PLAY;
                        updateProgress();
                    }
                }, true);

                document.addEventListener("ended", function(e){
                    if(e.target === currentAudio) {
                        if(playBtn) playBtn.innerHTML = SVG_PLAY;
                        updateProgress();
                    }
                }, true);

                document.addEventListener("timeupdate", function(e){
                    if(e.target === currentAudio) {
                        updateProgress();
                    }
                }, true);
                document.addEventListener("loadedmetadata", function(e){
                    if (!currentAudio && e.target === stickyAudio) {
                        currentAudio = stickyAudio;
                    }
                    if (e.target === currentAudio) {
                        updateProgress();
                    }
                }, true);

                function updateProgress() {
                    if(!currentAudio) return;
                    var cur = currentAudio.currentTime;
                    // Single Player Sync
                    var spCards = document.querySelectorAll(".podify-single-player-card");
                    spCards.forEach(function(card){
                        var audio = card.querySelector("audio");
                        var btn = card.querySelector(".podify-sp-play-btn");
                        var gradient = card.querySelector("linearGradient");
                        var curTxt = card.querySelector(".podify-sp-current");
                        if(audio && audio.src === currentAudio.src) {
                             if(btn) btn.innerHTML = currentAudio.paused ? SVG_SP_PLAY : SVG_SP_PAUSE;
                             var dur = currentAudio.duration || parseFloat(audio.getAttribute("data-duration-seconds")) || 0;
                             if(dur > 0) {
                                 if(gradient) {
                                     var pct = (cur/dur) * 100;
                                     var stops = gradient.querySelectorAll("stop");
                                     if(stops.length >= 2) {
                                         stops[0].setAttribute("offset", pct + "%");
                                         stops[1].setAttribute("offset", pct + "%");
                                     }
                                 }
                                 if(curTxt) curTxt.textContent = fmtTime(cur);
                             }
                        } else {
                            // Reset others
                            if(btn) btn.innerHTML = SVG_SP_PLAY;
                            if(gradient) {
                                var stops = gradient.querySelectorAll("stop");
                                if(stops.length >= 2) {
                                    stops[0].setAttribute("offset", "0%");
                                    stops[1].setAttribute("offset", "0%");
                                }
                            }
                            if(curTxt) curTxt.textContent = "0:00";
                        }
                    });
                    var dur = currentAudio.duration;
                    if(!dur || isNaN(dur)) {
                        var ds = parseFloat(currentAudio.getAttribute("data-duration-seconds")) || 0;
                        if (ds > 0) dur = ds;
                    }
                    
                    var stickySvg = document.getElementById("podify-sticky-svg");
                    if(stickySvg && dur > 0) {
                        var pct = (cur / dur) * 100;
                        var stops = stickySvg.querySelectorAll("stop");
                        if(stops.length >= 2) {
                             stops[0].setAttribute("offset", pct + "%");
                             stops[1].setAttribute("offset", pct + "%");
                        }
                    }

                    if(timeEl) {
                        var durStr = currentAudio.getAttribute("data-duration") || (dur ? fmtTime(dur) : "0:00");
                        timeEl.textContent = fmtTime(cur) + " / " + durStr;
                    }
                    if(waveProg && dur) {
                        var valp = (cur / dur) * 100;
                        waveProg.style.width = valp + "%";
                    }
                }

                if(playBtn) {
                    playBtn.addEventListener("click", function(){
                        if(!currentAudio) {
                            var playing = Array.from(document.querySelectorAll(".podify-episode-audio")).find(function(x){ return !x.paused; });
                            if (playing) { currentAudio = playing; }
                        }
                        if(!currentAudio) {
                            if(stickyAudio && stickyAudio.src) {
                                currentAudio = stickyAudio;
                                document.body.classList.add("podify-player-active");
                                player.style.setProperty("display", "block", "important");
                                if(playBtn) playBtn.innerHTML = SVG_PAUSE;
                                if(volBtn) volBtn.innerHTML = currentAudio.muted ? SVG_VOL_OFF : SVG_VOL_ON;
                                try{stickyAudio.load()}catch(_e){}
                                setInitialTimeFromSticky();
                            } else {
                                var first = document.querySelector(".podify-episode-audio[src]");
                                if (first) {
                                    if(stickyAudio){
                                        stickyAudio.src = first.src;
                                        stickyAudio.setAttribute("data-duration", first.getAttribute("data-duration") || "");
                                        stickyAudio.setAttribute("data-duration-seconds", first.getAttribute("data-duration-seconds") || "");
                                        currentAudio = stickyAudio;
                                        document.body.classList.add("podify-player-active");
                                        player.style.setProperty("display", "block", "important");
                                        if(titleEl) titleEl.textContent = first.getAttribute("data-title") || titleEl.textContent;
                                        if(imgEl) imgEl.src = first.getAttribute("data-image") || imgEl.src;
                                        if(playBtn) playBtn.innerHTML = SVG_PAUSE;
                                        if(volBtn) volBtn.innerHTML = currentAudio.muted ? SVG_VOL_OFF : SVG_VOL_ON;
                                        try{stickyAudio.load()}catch(_e){}
                                        setInitialTimeFromSticky();
                                    }
                                } else {
                                    var url = EP_URL + "?limit=10&offset=0" + (FEED_ID_JS ? ("&feed_id="+encodeURIComponent(FEED_ID_JS)) : "");
                                    function pickAndPlay(list){
                                        var it = (list||[]).find(function(x){ return x.audio_url; });
                                        if(!it) return false;
                                        stickyAudio.src = it.audio_url;
                                        if(titleEl) titleEl.textContent = it.title || titleEl.textContent;
                                        if(imgEl) imgEl.src = it.image_url || imgEl.src;
                                        (function(){
                                            var s = it.duration || "";
                                            if (s) {
                                                var dsFmt = /^[0-9]+$/.test(s) ? (function(x){ var m=Math.floor(x/60), se=Math.floor(x%60), h=Math.floor(m/60); m=m%60; return h>0?(h+":"+(m<10?"0":"")+m+":"+(se<10?"0":"")+se):(m+":"+(se<10?"0":"")+se); })(parseInt(s,10)) : s;
                                                stickyAudio.setAttribute("data-duration", dsFmt);
                                            } else { stickyAudio.removeAttribute("data-duration"); }
                                        })();
                                        stickyAudio.setAttribute("data-duration-seconds", (function(){
                                            var s = it.duration || "";
                                            var sec = 0;
                                            if(/^[0-9]+$/.test(s)) { sec = parseInt(s,10); }
                                            else { var parts = s.split(":").map(function(x){ return parseInt(x,10)||0; }); for(var i=0;i<parts.length;i++){ sec = sec*60 + parts[i]; } }
                                            return sec;
                                        })());
                                        setInitialTimeFromSticky();
                                        currentAudio = stickyAudio;
                                        document.body.classList.add("podify-player-active");
                                        player.style.setProperty("display", "block", "important");
                                        if(playBtn) playBtn.innerHTML = SVG_PAUSE;
                                        if(volBtn) volBtn.innerHTML = currentAudio.muted ? SVG_VOL_OFF : SVG_VOL_ON;
                                        try{stickyAudio.load()}catch(_e){}
                                        currentAudio.play().then(function(){ if(playBtn) playBtn.innerHTML = SVG_PAUSE; updateProgress(); }).catch(function(err){ console.error("Podify: Play failed after fetch", err); });
                                        return true;
                                    }
                                    fetch(url).then(parseJSONSafe).then(function(d){
                                        if(d && d.items) {
                                            if (pickAndPlay(d.items)) return;
                                            var tries = 0;
                                            function next(offset){
                                                if (tries++ >= 5) return;
                                                var u = EP_URL + "?limit=10&offset="+offset + (FEED_ID_JS ? ("&feed_id="+encodeURIComponent(FEED_ID_JS)) : "");
                                                fetch(u).then(parseJSONSafe).then(function(dd){
                                                    if(dd && dd.items && dd.items.length){
                                                        if (pickAndPlay(dd.items)) return;
                                                        next(dd.next_offset || (offset + dd.items.length));
                                                    }
                                                }).catch(function(err){ console.error("Podify: Fetch episodes page failed", err); });
                                            }
                                            next(d.next_offset || 10);
                                            // If nothing playable found at all, set metadata from the first item of the first page
                                            if (d.items && d.items.length && (!stickyAudio.src || stickyAudio.src.length===0)) {
                                                var it0 = d.items[0];
                                                if(titleEl) titleEl.textContent = it0.title || titleEl.textContent;
                                                if(imgEl && it0.image_url) imgEl.src = it0.image_url;
                                                if(subEl && it0.published){ try{ var dt0 = new Date(it0.published); subEl.textContent = dt0.toLocaleDateString(); }catch(_e){} }
                                                if(stickyAudio){
                                                    if(it0.duration){
                                                        var s0 = it0.duration;
                                                        var dsFmt0 = /^[0-9]+$/.test(s0) ? (function(x){ var m=Math.floor(x/60), se=Math.floor(x%60), h=Math.floor(m/60); m=m%60; return h>0?(h+":"+(m<10?"0":"")+m+":"+(se<10?"0":"")+se):(m+":"+(se<10?"0":"")+se); })(parseInt(s0,10)) : s0;
                                                        stickyAudio.setAttribute("data-duration", dsFmt0);
                                                    }
                                                    var ds0 = 0; var s0 = it0.duration || ""; if(/^[0-9]+$/.test(s0)){ ds0=parseInt(s0,10);} else { var parts0=s0.split(":").map(function(x){ return parseInt(x,10)||0 }); for(var i0=0;i0<parts0.length;i0++){ ds0=ds0*60+parts0[i0]; } }
                                                    if(ds0>0) stickyAudio.setAttribute("data-duration-seconds", ds0);
                                                    setInitialTimeFromSticky();
                                                }
                                                updateProgress();
                                            }
                                        }
                                    }).catch(function(err){ console.error("Podify: Fetch episodes for sticky failed", err); });
                                    return;
                                }
                            }
                        }
                        if(currentAudio.paused) { currentAudio.play().then(function(){ if(playBtn) playBtn.innerHTML = SVG_PAUSE; updateProgress(); }).catch(function(err){ console.error("Podify: Play failed", err); }); }
                        else { currentAudio.pause(); if(playBtn) playBtn.innerHTML = SVG_PLAY; }
                    });
                }

                if(volBtn) {
                    volBtn.addEventListener("click", function(){
                        if(!currentAudio) {
                            if(stickyAudio && stickyAudio.src) { currentAudio = stickyAudio; }
                            else { currentAudio = document.querySelector(".podify-episode-audio[src]"); }
                            if(!currentAudio) return;
                        }
                        currentAudio.muted = !currentAudio.muted;
                        if(volSlider) volSlider.value = currentAudio.muted ? 0 : (currentAudio.volume || 1);
                        volBtn.innerHTML = currentAudio.muted ? SVG_VOL_OFF : SVG_VOL_ON;
                    });
                }

                if(volSlider) {
                    volSlider.addEventListener("input", function(){
                        var val = parseFloat(volSlider.value);
                        if(!currentAudio) {
                            if(stickyAudio && stickyAudio.src) { currentAudio = stickyAudio; }
                            else { currentAudio = document.querySelector(".podify-episode-audio[src]"); }
                        }
                        if(currentAudio) {
                            currentAudio.volume = val;
                            currentAudio.muted = (val === 0);
                        }
                        if(stickyAudio && stickyAudio !== currentAudio) {
                            stickyAudio.volume = val;
                            stickyAudio.muted = (val === 0);
                        }
                        if(volBtn) volBtn.innerHTML = (val === 0) ? SVG_VOL_OFF : SVG_VOL_ON;
                    });
                }

                if(rewindBtn) {
                    rewindBtn.addEventListener("click", function(){
                        if(!currentAudio) return;
                        currentAudio.currentTime = Math.max(0, currentAudio.currentTime - 15);
                    });
                }

                // Sticky Click
                var stickyClick = document.getElementById("podify-sticky-click-area");
                if(stickyClick) {
                    stickyClick.addEventListener("click", function(e){
                        if(!currentAudio) return;
                        var rect = stickyClick.getBoundingClientRect();
                        var x = e.clientX - rect.left;
                        var w = rect.width;
                        if(w > 0) {
                            var pct = x / w;
                            if(currentAudio.duration) {
                                currentAudio.currentTime = pct * currentAudio.duration;
                            }
                        }
                    });
                }
                
                // SP Click
                document.addEventListener("click", function(e){
                    var t = e.target.closest(".podify-sp-click-area");
                    if(!t) return;
                    var svg = t.closest("svg");
                    if(!svg) return;
                    var card = svg.closest(".podify-single-player-card");
                    if(!card) return;
                    var audio = card.querySelector("audio");
                    
                    if(currentAudio && audio && currentAudio.src === audio.src) {
                        var rect = svg.getBoundingClientRect();
                        var x = e.clientX - rect.left;
                        var w = rect.width;
                        if(w > 0 && currentAudio.duration) {
                            currentAudio.currentTime = (x/w) * currentAudio.duration;
                        }
                    }
                });

                // Single Player Click Handler
                document.addEventListener("click", function(e){
                    var btn = e.target.closest(".podify-sp-play-btn");
                    if(!btn) return;
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var card = btn.closest(".podify-single-player-card");
                    if(!card) return;
                    var audio = card.querySelector("audio");
                    if(!audio) return;
                    
                    // If already playing this audio, just toggle play/pause
                    if(currentAudio && currentAudio.src === audio.src) {
                        if(currentAudio.paused) {
                            currentAudio.play().catch(function(err){console.error("Podify SP Resume Error", err)});
                        } else {
                            currentAudio.pause();
                        }
                    } else {
                        // Load into sticky player and play (dont call audio.play() directly!)
                        if(stickyAudio) {
                            stickyAudio.src = audio.src;
                            stickyAudio.setAttribute("data-duration", audio.getAttribute("data-duration") || "");
                            stickyAudio.setAttribute("data-duration-seconds", audio.getAttribute("data-duration-seconds") || "");
                            currentAudio = stickyAudio;
                            
                            // Update UI
                            if(titleEl) titleEl.textContent = audio.getAttribute("data-title") || titleEl.textContent;
                            if(imgEl) imgEl.src = audio.getAttribute("data-image") || imgEl.src;
                            if(playBtn) playBtn.innerHTML = SVG_PAUSE;
                            if(volBtn) volBtn.innerHTML = currentAudio.muted ? SVG_VOL_OFF : SVG_VOL_ON;
                            
                            // Show sticky player
                            document.body.classList.add("podify-player-active");
                            player.style.setProperty("display", "block", "important");
                            
                            // Play
                            try { stickyAudio.load(); } catch(_e) {}
                            
                            stickyAudio.play().then(function(){
                                updateProgress();
                            }).catch(function(err){
                                console.error("Podify SP Play Error", err);
                            });
                        }
                    }
                });

                player.style.setProperty("display", "block", "important");
            } catch(e) {
                console.error("Podify Init Error", e);
            }
        })();
        </script>';
        
        return $html;
    }
}
