# Changelog

All notable changes to the Simple course format plugin are documented in this file.

## v0.8.0 (2026-08-11) - Beta

### Added
- Optional Font Awesome icon per unit, set in the unit settings and shown in the navigation
  panel. Where the unit has activities with completion tracking the icon sits inside the
  progress ring, in place of the percentage, and a green tick still replaces the lot once every
  activity is complete. Course Info takes an icon too, keeping its book by default.
- Optional list of activities under the unit being viewed in the navigation panel, switched on
  per course with the new "Show activities in the unit index" setting. It lists exactly the
  activities the unit's progress ring is counting, each with the same indicator used on the
  cards, and selecting one scrolls to it rather than leaving the page. Only the unit being
  viewed lists its activities, so the panel stays a list of units. Off by default.
- Activity descriptions on the course page, shown when the activity asks for them, so a teacher
  can say something about an activity without adding a label for it. Set inside the card, in the
  same quiet grey as the zone headings but cased normally, and indented past the icon.
- Renaming an activity from the course page. The name becomes an inplace editable while editing,
  with its pencil kept out of sight until the activity is hovered or focused, so editing mode
  stays as quiet as the rest of the format.
- PHPUnit tests for label placement, activity renaming, descriptions and the index list.

### Fixed
- A unit with no completion-tracked activities showed an empty progress ring for ever, which
  reads as work still outstanding when there is nothing to do. Such a unit now shows a filled
  dot: the same family as the ring, but plainly not a measure of anything.
- Labels rendered as activity cards, which for a label means a card linking nowhere, captioned
  with its auto-generated name rather than the text it carries. A label is now shown as the text
  itself, and travels with the activity it introduces so that zone grouping cannot separate the
  two. Labels the teacher leaves at the end stay at the end, and dragging one keeps where it was
  dropped.
- Ticking "display description on course page" moved an activity into the learning zone and
  rendered its description as though it were the activity itself. Modules offer their
  description and any content they render in place through the same hook, and the two were not
  being told apart.
- Activity cards lost their name when a completion control was placed beside them. Cards now
  carry the compact indicator inside the card, where the shrinking layout cannot squeeze the
  name out.
- Completion indicators collapsed to a bare border wherever they were not a flex item, since
  width and height do not apply to inline elements.

### Changed
- Activity cards are containers rather than links, since the rename pencil is itself a link and
  cannot be nested inside one. Students keep the whole card as a click target through a stretched
  link on the name; while editing, the name carries its own link instead.

## v0.7.5 (2026-07-30) - Beta

Completion tracking for content the format renders in place. Supersedes the 0.7.4 entry, which
described an approach that was reworked before release.

### Added
- Completion indicators on every activity, including content rendered in place, which previously
  had none at all.
- PHPUnit tests covering completion export, the view web services, condition labels and the
  indicator's behaviour across tracking types.

### Fixed
- Featured content had no completion control. The template rendered the content and nothing else,
  so a student could neither mark an activity done nor see its completion state.
- The view event for content rendered in place did not reliably fire. It waited thirty seconds
  before doing anything, covered only a hardcoded list of module types so anything supplying
  content through the standard `cm_info` hook was ignored, and worked by fetching the activity's
  `view.php`. For URL and SCORM activities that redirects, in the URL case to an external site,
  so the response was chased cross-origin and the interface never caught up.
- Activity cards lost their name when the completion control was placed beside them, because the
  card is set to shrink and was squeezed to nothing.
- Completion indicators collapsed to a bare border wherever they were not a flex item, since
  `width` and `height` do not apply to inline elements.

### Changed
- Modules raise their view event through the dedicated `mod_<name>_view_<name>` web service, once
  the content has been visible for three seconds, rather than on a fixed timer. Each module names
  that service's instance parameter after itself, so the name is carried through from the server.
- Completion is shown as a compact squircle. Activities the student marks themselves have a solid,
  heavier outline and act as a checkbox; activities the course completes on its own have a dotted
  outline and are read only. Hovering either reveals a quiet label naming what is required, and the
  tooltip adds whether each condition has been met. Both use Moodle's own wording so the phrasing
  matches the rest of the site and stays translated.
- Content rendered in place carries its indicator inside the content itself, at the foot, slightly
  larger than on a card. Embedded content fills its own aspect-ratio box, so its indicator attaches
  as a strip beneath, sharing the surface so the two read as one card.
- The view condition is not shown for content rendered in place. The format marks such content
  viewed as soon as the student has seen it and the section progress ring already reflects that, so
  restating it under the content adds nothing. Where viewing was the only condition, no indicator
  is shown; other conditions and the manual control are unaffected.
- The section progress ring counts explicit completion state attributes rather than reading the
  shape of the completion markup.

### Removed
- The previous manual completion toggle and its `markcomplete` and `marknotcomplete` strings,
  replaced by the indicator and its per-activity accessible labels.

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
