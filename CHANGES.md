# Changelog

All notable changes to the Simple course format plugin are documented in this file.

## v0.7.0 (2026-03-16) - Beta

### Added
- Inline LTI embedding via `/mod/lti/launch.php` in an iframe, with view completion tracking and dedicated CSS styling.
- `ZONE_HIDDEN` list for administrative module types that should never render as activity cards (e.g. question banks).

### Fixed
- Question bank (`qbank`) modules no longer appear as cards in course sections.
- Graded LTI, H5P, and SCORM activities remain in the learning zone regardless of grade configuration.

## v0.6.0 (2026-03-10) - Beta

### Added
- PHPUnit tests (28 tests) and Behat acceptance test.
- External API class for section 0 content with PHPUnit tests (8 tests).
- Unread forum posts badge on section 0 nav button and cog navigation.
- Course Info overlay setting — display section 0 in a floating modal accessible from any course page.
- Persistent home button for navigating back to the course from activity pages.
- Inline content rendering for modules using standard Moodle `cm_info` content (e.g. courseschedule, syllabus).
- MutationObserver detection for JS-injected inline content with automatic card hiding.
- `aalink` class on all card links for standard Moodle click interception (modal display, etc.).
- Internationalized all hardcoded strings in JS and templates.

### Changed
- Section 0 modal now moves live DOM nodes instead of copying HTML, preserving JS bindings, menus, and interactive widget state.
- Inline content check runs for all zones so section 0 flat list correctly hides cards for modules providing `cm->content`.
- Home button decoupled from cog popover — shows on all course pages even when secondary navigation is absent.
- Cog navigation hidden on embedded page layouts (grading interfaces).
- Centered inline content with minimum width for better readability.

### Fixed
- Modules with `cm->content` now correctly promoted to learning zone for inline rendering.
- Inline content fallback to `cm->content` for non-page/book modules.
- Section 0 template now renders inline content.

## v0.5.0 (2026-03-06)

### Added
- Stealth module visibility support (`allow_stealth_module_visibility`).
- Visual dimming with Font Awesome icons for hidden and stealth activities.
- Privacy null provider for Moodle coding standards compliance.
- Expanded cog nav icons: recycle bin, usage stats, reminders, LTI, grader, submissions, overrides, freeze.
- PNG icon support in cog tiles via `img:` prefix.

### Changed
- Activity and section visibility now honours Moodle core patterns (`is_visible_on_course_page`, `is_section_visible`).
- Card-based items in learning zone switched to flex layout to fix edit button overlapping completion indicators.
- Dropdown menus stay visible when open and escape overflow constraints.

### Fixed
- Availability rendering for Moodle 5.0 (renderable objects instead of strings).
- Broken HTML in title attributes from raw availability info — uses plain-text `availabilitytext` instead.
- Subpanel flyout menus closing prematurely on diagonal cursor movement (400ms grace period).

## v0.4.0 (2026-03-05)

### Added
- Inline SCORM embedding via `player.php` in popup mode.
- YouTube and Vimeo URL modules dynamically routed to learning zone.
- Fullscreen toggle button on all embed containers (Fullscreen API).
- Plugin description lang string for course settings.
- SCORM and URL added to view completion tracking.

### Fixed
- Zone categorization: `h5p` corrected to `h5pactivity` (correct Moodle modname).

## v0.3.0 (2026-03-04)

### Added
- Inline content rendering for Page and Book modules.
- View completion tracking with animated progress refresh.
- Cog popover navigation — fixed button replacing secondary navigation with tile-grid popover.
- `cognav.js` AMD module loaded on all course pages via `page_set_course()`.
- Font Awesome icon mapping for nav items by data-key or text matching.

### Changed
- Page context header hidden; secondary navigation hidden after cog is built.

## v0.1.0 (2026-03-03)

### Added
- Initial release of format_simple.
- Custom navigation panel (built-in course index disabled).
- Single-section display with crossfade transitions.
- Three-zone layout: Learning Content, Related Resources, Related Activities.
- Auto-categorization of modules by type.
- Responsive mobile support with viewport-aware burger menu.
- Course banner with hero image for section 0.
- Font Awesome mimetype icons for file resources.
- Section-level learning outcomes (textarea, one per line).
- SVG-based completion progress indicators in navigation.
