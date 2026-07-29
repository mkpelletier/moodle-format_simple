# Changelog

All notable changes to the Simple course format plugin are documented in this file.

## v0.7.5 (2026-07-29) - Beta

### Fixed
- Activity cards showed only a completion control and lost their name. Core's completion block
  is a wide flex sibling and the card is set to shrink, so the label was squeezed to nothing.
  Cards now carry a small squircle indicator inside the card itself.

### Changed
- Completion on activity cards is a compact squircle: a filled state for tracked activities and
  a clickable checkbox where the student marks completion themselves. Core's fuller controls are
  reserved for content rendered in place, which has no card to hang an indicator on.
- The view condition is no longer shown for content rendered in place. The format marks such
  content viewed once the student has seen it and the section progress ring already reflects
  that, so restating it under the content adds nothing. Other conditions, and the manual
  completion button, are unaffected.

## v0.7.4 (2026-07-29) - Beta

### Fixed
- Featured content had no completion control. The template rendered the content only, so a
  student could not mark an activity done and could not see its completion state. Completion
  now appears beneath featured and inline content as well as on activity cards.
- The view event for content rendered in place did not reliably fire. It waited thirty seconds
  before doing anything, it was limited to a hardcoded list of module types, and it worked by
  fetching the activity's view.php, which for URL and SCORM activities redirects away from
  Moodle. Modules now raise their view event through the dedicated web service instead.

### Changed
- Completion rendering is delegated to core's completion component, so every condition type is
  represented rather than only the manual and automatic split the format drew itself. Combined
  conditions such as view plus grade, and states such as passed, now display correctly.
- Content rendered in place raises its view event once it has been visible for three seconds,
  rather than on a fixed thirty second timer, so completion follows what the student has
  actually seen.
- Completion markup now sits outside the activity card link, since a button cannot legally be
  nested inside an anchor.
- The section progress ring counts explicit completion state attributes rather than reading the
  shape of the completion markup.

### Removed
- The bespoke manual completion toggle and its `markcomplete` and `marknotcomplete` strings,
  both superseded by core's completion component.

## v0.7.3 (2026-07-29) - Beta

### Added
- PHPUnit tests for the primary content option covering validation, form choices and section
  duplication.

### Fixed
- The primary content selector no longer derives its permitted values from request parameters.
  Saving a section through anything other than the section settings form silently discarded the
  teacher's choice, because the choice list collapsed to "automatic" when the expected parameters
  were absent. Validation now uses the section id the caller supplies, so every code path agrees.
- Duplicating a section now points the copy at its own duplicated activity. Previously the copy
  inherited the original section's course module id, which belongs to a different section, so the
  duplicate silently fell back to featuring its first activity.

### Changed
- Section edit form choices are now supplied via `editsection_form()`, the documented extension
  point for formats needing section context, rather than being inferred from the URL.

## v0.7.2 (2026-07-29) - Beta

### Added
- Backup and restore plugin classes (`backup/moodle2/`) for the format.
- PHPUnit tests covering backup and restore of the `primarycontent` section option.

### Fixed
- The `primarycontent` section option holds a course module id, which core restores verbatim.
  It is now translated through the course_module mapping, so the teacher's chosen primary
  activity survives a course restore, import or duplicate instead of being silently lost.
  An unresolvable selection falls back to automatic rather than a stale id, and a merging
  restore no longer risks disturbing a selection already made in the destination course.

## v0.7.1 (2026-03-20) - Beta

### Added
- GitHub Actions CI workflow for automated Moodle plugin checks on push and PR.
- Mustache templates for all JS-rendered markup (cog container, mobile header, modal, progress indicators).

### Changed
- External service now validates context and checks `moodle/course:view` capability.
- Language file string values no longer use concatenation (Moodle coding standards compliance).
- JavaScript HTML rendering replaced with `core/templates` Mustache rendering.

## v0.7.0 (2026-03-16) - Beta

### Added
- Inline LTI embedding via `/mod/lti/launch.php` in an iframe, with view completion tracking and dedicated CSS styling.
- `ZONE_HIDDEN` list for administrative module types that should never render as activity cards (e.g. question banks).

### Fixed
- Question bank (`qbank`) modules no longer appear as cards in course sections.
- Graded LTI, H5P, and SCORM activities remain in the learning zone regardless of grade configuration.
- Activity and resource edit dropdown menus no longer render beneath adjacent cards (z-index fix).

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
