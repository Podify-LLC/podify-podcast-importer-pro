# Changelog

All notable changes to **Podify Podcast Importer Pro** are documented here.

## 1.0.42
### Title
- Hyperlink Preservation & Bulk Backfill for Show Notes

### Added
- **Importer**: HTML sanitization function that preserves hyperlinks (<a> tags with href, target, rel, title attributes)
- **Importer**: Strict priority order for selecting episode descriptions:
  1. Use `content:encoded` if it exists and contains ANY <a> tags
  2. Otherwise use `description` if it contains ANY <a> tags
  3. Only use `itunes:summary` as a fallback when no richer HTML description exists
- **Importer**: Simplified link detection to look for ANY `<a` tag (matches ALL links, regardless of type or attributes)
- **Importer**: Automatically adds rel="noopener noreferrer" to any links with target="_blank" for security
- **Importer**: Bulk backfill functionality to reprocess all existing episodes and restore links from the original RSS feed
- **Backfill**: Loads ALL episodes from the database first, then matches to feed items
- **Backfill**: Uses the EXACT SAME priority order and link detection as the importer
- **REST API**: New endpoints /backfill and /backfill-progress to trigger and monitor the bulk backfill process
- **Cron**: New hook and handler for background backfill processing
- **Logging**: Detailed logging for backfill showing updated, skipped, failed, and unmatched episodes

### Fixed
- **Show Notes**: Hyperlinks from the podcast feed are no longer stripped during import, and ALL links are preserved (guest websites, books, courses, resources, ad choices, etc.)

## 1.0.41
### Title
- List Layout & Per-Feed Enhancements

### Added
- **Frontend**: New "List Layout" option for [podify_podcast_list] (layout="list").
- **Frontend**: Updated List Layout styling to match the horizontal image | content structure.
- **Podcast Feed**: Choose default layout (Classic/Modern/List) before editing per-feed styling.
- **Frontend**: Apply per-feed default styling when rendering the default feed list.
- **Dashboard**: "Latest Episodes (By Feed)" card that shows the latest episode for each feed.

### Changed
- **Podcast Feed**: Replaced "Pending..." with a next sync countdown timer (auto-schedules if missing).

## 1.0.40
### Title
- Per-Feed Category Badge Toggle

### Added
- **Per-Feed**: Toggle to show/hide category badges (pills) on episode cards.
- **Episodes**: Added an "Uncategorized" option to the category dropdown for easier management.
- **Shortcodes**: If no category is specified, the feed shortcode shows all episodes (including uncategorized).
- **Per-Feed**: Added an "Uncategorized Card Style" dropdown to reuse a category style for uncategorized cards.

## 1.0.39
### Title
- Sync Reliability & Dashboard Enhancements

### Added
- **Dashboard**: Real-time "Next Sync" timer in the Scheduled Imports table.
- **Dashboard**: Explicit "Manual" option in the synchronization interval list.
- **Sync**: Dynamic Title and Content synchronization between RSS feed and WordPress posts.
- **Sync**: "Pending..." status for automatic feeds waiting for their first background cycle.
- **Typography**: Support for custom Font Family, Letter Spacing, and Line Height for episode titles.

### Changed
- **Sync**: Refactored the Auto-Sync engine for 100% reliability with multiple feeds.
- **Sync**: Optimized synchronization performance by implementing "Smart Change Detection"—the plugin now skips database and WordPress updates if the RSS data matches existing content perfectly.
- **Sync**: Implemented "Site Performance Protection"—added CPU throttling, automatic memory clearing, and synchronization locking to ensure the import process never slows down your website.
- **Sync**: Improved "Missing Post Recovery" to recreate deleted WordPress posts without duplicating database records.
- **Sync**: Implemented "Artwork Auto-Refresh" to update featured images if the RSS artwork URL changes.
- **UI**: Updated card thumbnails to a 1:1 square aspect ratio with proper containment.
- **UI**: Stabilized card and button layouts to remain stationary on hover.
- **Styling**: Increased CSS specificity and implemented theme resets to resolve conflicts with aggressive themes (e.g., cmsmasters).

### Fixed
- **Sync**: Implemented background processing for manual triggers—syncing now starts **immediately** upon clicking but runs via WP-Cron to completely eliminate 504 Gateway Timeout errors.
- **Sync**: Re-engineered the "Force" sync logic to perform a full background re-import, ensuring all episodes and images are refreshed even on slow servers.
- **Sync**: Improved the progress bar to be "Self-Healing"—it now tracks background tasks until completion, even if the initial browser request is interrupted.
- **Dashboard**: Fixed alignment issues in the "Global Styling" settings card by standardizing field layouts.
- **Sync**: Resolved an issue where some episodes were missing their connected WordPress posts by implementing a self-healing recreation logic.
- **Sync**: Fixed real-time sync timer layout and responsiveness in the admin table.
- **Dashboard**: Redesigned the progress bar into a single unit with centered status text, high-contrast dynamic coloring, and per-item real-time updates for a smoother sync experience.
- **Dashboard**: Added real-time status text to the progress bar (Percentage, Phase, and Item count).
- **Maintenance**: Fixed a PHP syntax error in the Importer class.
- **Maintenance**: Added detailed debug logging for image sideloading, database operations, and post creation failures.
- **Maintenance**: Improved finalization logic for the synchronization progress bar to ensure it correctly reaches 100%.

## 1.0.38
### Title
- Admin Dashboard Redesign & Per-Feed Text Styling

### Added
- **UI**: Added font family and text color settings for Title, Description, and Meta info in the per-feed customization options.
- **Styling**: Support for custom fonts and colors applied to each podcast feed individually.

### Changed
- **UI**: Completely redesigned the Categories tab with a clean 2-column grid layout.
- **UI**: Modernized all dashboard tables with better spacing, typography, and hover effects.
- **UI**: Replaced standard WordPress buttons with custom `podify-button-modern` styles for a more professional look.
- **UI**: Improved the "Add Import" and "Schedules" tabs with better alignment, organized customization panels, and added hover color settings for the "Load More" button.
- **UI**: Fixed misalignments in the category customization rows and color picker containers.

## 1.0.37
### Title
- Simplified Category Styling

### Changed
- **UI**: Removed gradient background options from categories to simplify the styling interface.
- **Styling**: Category backgrounds are now limited to solid colors for a cleaner look.

## 1.0.36
### Title
- Per-Category Customization & Modern Layout Fixes

### Added
- **UI**: Moved color and gradient customization to the Categories tab.
- **Styling**: Support for per-category card background and button colors.
- **Styling**: Support for per-category gradients and hover effects.

### Changed
- **UI**: Reverted per-feed color customization for a cleaner interface.
- **Frontend**: Updated modern layout to ensure better alignment and proper category styling.
- **Frontend**: Preserved per-feed custom button text (Read More / Load More).

## 1.0.35
### Title
- Per-Feed Customization & Enhanced Styling Control

### Added
- **UI**: Added a "Customize" toggle to each feed in the Schedules tab for individual feed styling.
- **UI**: Integrated WordPress Color Pickers into the feed customization settings for card background, button background, and button text colors.
- **UI**: Added per-feed overrides for "Read More" and "Load More" button text.
- **Styling**: Implemented unique container-based dynamic CSS injection for shortcodes to prevent style bleeding between different feeds on the same page.
- **Settings**: Added global default styling options in the main Settings tab.

### Changed
- **Frontend**: Updated shortcode rendering to prioritize feed-specific customization with a fallback to global settings.
- **Admin**: Modernized the feed settings layout with a toggleable customization form for better organization.
