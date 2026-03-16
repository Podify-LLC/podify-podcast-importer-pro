# Changelog

All notable changes to **Podify Podcast Importer Pro** are documented here.

## 1.0.39
### Title
- Sync Reliability & Dashboard Enhancements

### Added
- **Dashboard**: Real-time "Next Sync" timer in the Scheduled Imports table.
- **Sync**: Dynamic Title and Content synchronization between RSS feed and WordPress posts.
- **Typography**: Support for custom Font Family, Letter Spacing, and Line Height for episode titles.

### Changed
- **Sync**: Refactored the Auto-Sync engine for 100% reliability with multiple feeds.
- **Sync**: Improved "Missing Post Recovery" to recreate deleted WordPress posts without duplicating database records.
- **Sync**: Implemented "Artwork Auto-Refresh" to update featured images if the RSS artwork URL changes.
- **UI**: Updated card thumbnails to a 1:1 square aspect ratio with proper containment.
- **UI**: Stabilized card and button layouts to remain stationary on hover.
- **Styling**: Increased CSS specificity and implemented theme resets to resolve conflicts with aggressive themes (e.g., cmsmasters).

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

## 1.0.30
### Title
- AJAX Updater & Hero Banner Refinement

### Added
- **Updater**: Implemented AJAX-based partial refresh for the "Check Now" button to avoid full page reloads.
- **Updater**: Updated WordPress core updater to use `logo.png` for plugin icons.

### Changed
- **UI**: Removed the logo from the Dashboard Hero banner to reduce redundancy.
- **UI**: Disabled the hover rotation animation on the "Check Now" button; animation now only triggers during active loading.
- **UI**: Adjusted the Dashboard Hero banner with a centered layout and optimized padding.
- **UI**: Relocated the update success indicator to a more logical position below the version status.
- **UI**: Improved badge alignment within the hero heading for better responsiveness.

## 1.0.29
### Title
- Logo Integration, Mobile Responsiveness & Pro Branding

### Added
- **UI**: Integrated `logo_cropped.png` into the admin sidebar and `logo.png` into the dashboard hero banner.
- **UI**: Added "PRO" badges beside the version number in the Dashboard and Sidebar for premium branding.
- **UI**: Implemented full mobile responsiveness for the admin dashboard, including a scrollable sidebar menu and stacked layouts for smaller screens.
- **UI**: Added a smooth rotation animation to the "Check Now" button icon in the Dashboard.
- **Updater**: Integrated plugin icons into the WordPress update core for a more professional update experience.

### Changed
- **UX**: Moved the "Updater checked successfully" notice from the top banner directly into the Updater Status widget for a cleaner interface.
- **UX**: Added a "Checked!" confirmation badge next to the "Check Now" button after a manual update check.

### Fixed
- **UI**: Refined sidebar layout and hero banner alignment for better responsiveness across screen sizes.
- **UI**: Fixed filter layout on mobile devices to prevent horizontal overflow.

## 1.0.27
### Title
- UI Layout & Filter Alignment Fixes

### Fixed
- **UI**: Corrected Episodes page filter layout to a single row with improved vertical alignment.
- **Maintenance**: Updated deprecated `WP_User_Query` arguments for WordPress 5.9+ compatibility.
- **Updater**: Fixed repository URL mismatch to ensure correct updates for the Pro version.

## 1.0.26
### Title
- Updater & UI Refinements

### Improved
- **UI**: Enhanced text readability for updater status messages and flash notices.
- **Updater**: Improved robustness of plugin update extraction and folder handling to prevent "No valid plugins found" errors.

## 1.0.25
### Title
- Updater Fixes & Cleanup

### Fixed
- **Updater**: Resolved "Failed to download package" error by implementing proper authentication headers for private repository assets.
- **Cleanup**: Removed residual debug logging for cleaner production performance.

## 1.0.24
### Title
- Modern UI Updates & Enhanced Updater Control

### Added
- **Updater**: Added a "Check Now" button to the Dashboard Updater Status widget for manual update checks.

### Changed
- **UI**: Completely modernized the "Schedules" table layout with card styling, status badges, and better alignment.
- **Admin**: Restored and improved the Updater Status widget in the Dashboard for better visibility of version status.

## 1.0.23
### Title
- UI Streamlining, Cleanup & Standardization

### Changed
- **Maintenance**: Renamed `changelogs.md` to `changelog.md` to follow standard conventions.
- **Admin**: Updated dashboard to read from the new changelog filename.
- **Admin**: Removed the "Podify Updater" settings page. Token configuration is now handled exclusively via `wp-config.php` for better security and cleaner UI. The Updater Status widget remains in the Dashboard.

## 1.0.22

### Title
- Version Bump and Security Improvements

### Changed
- **Security**: Switched to `PODIFY_GITHUB_TOKEN` constant for safer GitHub authentication.
- **Updater**: Added detailed debug logging for token verification.

## 1.0.21

### Title
- Font Updates, Updater Status, and Code Cleanup

### Added
- **Updater Status**: Added visual indicator in Admin Dashboard (General tab) to show GitHub updater connection status and last check time.

### Changed
- **Typography**: Updated the episode title font in the podcast player to "Very Vogue" for a more stylish appearance.
- **Code Cleanup**: Removed residual debug logs and `console.log` statements for cleaner production performance.

## 1.0.20

### Title
- Volume Controls, Modern Admin Dashboard, Menu Positioning, and Player Fixes

### Added
- **Volume Control**: Added volume slider and mute toggle to both Single Player and Sticky Player.
- **Modern Admin Dashboard**: Completely redesigned admin interface with sidebar navigation, Welcome page, and grid-based actions.
- **Auto-Updater Configuration**: Hardcoded GitHub Personal Access Token for seamless automatic updates without manual configuration.

### Fixed
- **Audio Playback**: Resolved "Missing audio or play button" error by improving player initialization and fallback detection.
- **Player Layout**: Fixed issue where audio element was rendered outside the player container.
- **Channel Title**: Forced channel title display to "The Language of Love by Dr. Laura Berman" for specific feed slugs.
- **Duplicate Handlers**: Fixed conflict where duplicate play button listeners prevented audio playback.

### Changed
- **Admin Menu**: Moved "Podcast Importer" menu item up (below Media) for better accessibility.
- **Logs**: Removed excessive non-error `console.log` debugging messages.
- **Sticky Player**: Optimized state management for smoother play/pause transitions.

## 1.0.17

### Title
- Debugging tools, category sync fixes, and layout improvements

### Added
- **Add debug `console.log` messages to List, Sticky, and Single Player play buttons for easier verification.**

### Fixed
- **Fix "Uncategorized" category appearing in Single Player frontend.**
- **Fix Single Player layout issues (SVG progress bar overlap and time display wrapping).**
- **Fix database sync to ensure manually assigned categories propagate to WordPress Post terms.**
- **Fix Importer resync to preserve manual categories and correctly update audio URLs.**
