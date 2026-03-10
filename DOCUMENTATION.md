# Simple Format — Documentation

Comprehensive technical and user documentation for the `format_simple` Moodle course format plugin.

## Table of contents

- [Architecture overview](#architecture-overview)
- [File structure](#file-structure)
- [PHP implementation](#php-implementation)
  - [lib.php — Core format class](#libphp--core-format-class)
  - [content.php — Main layout renderer](#contentphp--main-layout-renderer)
  - [section.php — Section renderer](#sectionphp--section-renderer)
  - [get_section0_content.php — Web service](#get_section0_contentphp--web-service)
  - [format.php — Entry point](#formatphp--entry-point)
  - [Privacy and services](#privacy-and-services)
- [JavaScript implementation](#javascript-implementation)
  - [simple.js — Main format interactions](#simplejs--main-format-interactions)
  - [cognav.js — Cog navigation](#cognavjs--cog-navigation)
- [Templates](#templates)
  - [content.mustache — Main layout](#contentmustache--main-layout)
  - [section.mustache — Section layout](#sectionmustache--section-layout)
- [CSS architecture](#css-architecture)
  - [Design tokens](#design-tokens)
  - [Layout system](#layout-system)
  - [Component styles](#component-styles)
  - [Responsive breakpoints](#responsive-breakpoints)
- [Language strings](#language-strings)
- [Data flow](#data-flow)
  - [Page load flow](#page-load-flow)
  - [Section switch flow](#section-switch-flow)
  - [Completion toggle flow](#completion-toggle-flow)
  - [Inline content rendering flow](#inline-content-rendering-flow)
  - [Embed URL generation flow](#embed-url-generation-flow)
- [Zone categorization](#zone-categorization)
- [Configuration reference](#configuration-reference)
- [Accessibility](#accessibility)
- [Customization and extension points](#customization-and-extension-points)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Privacy and security](#privacy-and-security)

---

## Architecture overview

The plugin follows a three-tier architecture:

1. **PHP backend** — Core format class, module categorization, content rendering via output classes, web service API for the Course Info overlay, and privacy compliance.
2. **JavaScript frontend** — Two AMD modules handle section navigation with transitions, cog popover construction, home button, Course Info modal, keyboard navigation, mobile responsiveness, completion tracking, and inline content detection.
3. **Mustache templates + CSS** — Semantic HTML structure with all styles scoped under the `.format-simple` body class to prevent leakage into the host theme.

---

## File structure

```
format_simple/
├── format.php                              Course view entry point
├── lib.php                                 Core format class (format_simple)
├── version.php                             Plugin metadata
├── styles.css                              All CSS (~1500 lines, scoped)
├── lang/en/format_simple.php               Language strings
├── classes/
│   ├── output/
│   │   ├── renderer.php                    Minimal renderer (delegates to output classes)
│   │   └── courseformat/
│   │       ├── content.php                 Main layout renderer
│   │       └── content/
│   │           └── section.php             Section renderer
│   ├── external/
│   │   └── get_section0_content.php        Web service for Course Info overlay
│   └── privacy/
│       └── provider.php                    Null privacy provider
├── templates/local/
│   ├── content.mustache                    Main layout template
│   └── content/
│       └── section.mustache                Section template
├── amd/
│   ├── src/
│   │   ├── simple.js                       Section navigation, completion, transitions
│   │   └── cognav.js                       Cog popover, home button, modal
│   └── build/
│       ├── simple.min.js                   Minified (loaded by Moodle)
│       └── cognav.min.js                   Minified (loaded by Moodle)
├── db/
│   └── services.php                        Web service declaration
├── tests/
│   ├── format_simple_test.php              PHPUnit tests (28 tests)
│   ├── behat/
│   │   └── format_simple.feature           Behat acceptance test
│   └── external/
│       └── get_section0_content_test.php   Web service tests (8 tests)
├── README.md
├── CHANGES.md
└── DOCUMENTATION.md                        This file
```

---

## PHP implementation

### lib.php — Core format class

**Class:** `format_simple extends core_courseformat\base`

#### Capability methods

| Method | Returns | Purpose |
|--------|---------|---------|
| `uses_sections()` | `true` | Declares section support |
| `uses_course_index()` | `false` | Disables built-in course index (custom nav panel used instead) |
| `uses_indentation()` | `false` | Disables activity indentation (flat list structure) |
| `supports_components()` | `true` | Enables Moodle 5.0 reactive course editor |
| `supports_ajax()` | `stdClass` | Enables AJAX drag-and-drop reordering |
| `can_delete_section($section)` | `true` | Allows section deletion |
| `allow_stealth_module_visibility($cm, $section)` | `bool` | Permits "available but not shown" visibility state |

#### Navigation and display

| Method | Purpose |
|--------|---------|
| `get_view_url($section, $options)` | Returns course view URL with `#section-N` anchor (single-page format) |
| `get_section_name($section)` | Gets section display name (custom or default) |
| `get_default_section_name($section)` | Returns "General" for section 0, "Unit N" for others |

#### Format options

**Course-level options** (defined in `course_format_options()`):

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `hiddensections` | `int` | `1` | Whether hidden sections are collapsed (`0`) or invisible (`1`) |
| `showsection0banner` | `int` | `1` | Display course banner with hero image on section 0 |
| `section0modal` | `int` | `0` | Show Course Info as floating overlay modal |

**Section-level options** (defined in `section_format_options()`):

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `learningoutcomes` | `PARAM_TEXT` | `''` | One outcome per line, displayed in popout panel |

#### Module zone categorization

The `get_activity_zone(\cm_info $mod)` method assigns each activity to one of three zones:

| Zone constant | Modules | Description |
|---------------|---------|-------------|
| `ZONE_LEARNING` | `page`, `h5pactivity`, `scorm`, `lti`, `lesson`, `label`, video URLs (YouTube/Vimeo), modules with `cm->content` | Primary learning content |
| `ZONE_RESOURCES` | `url` (non-video), `resource`, `book`, `folder` | Supplementary materials |
| `ZONE_ACTIVITIES` | Everything else (`quiz`, `assign`, `forum`, etc.) | Interactive/submission activities |

Video URL detection: URLs containing `youtube.com`, `youtu.be`, or `vimeo.com` are reclassified from resources to learning content.

#### Resource icon mapping

The `get_resource_icon(\cm_info $mod)` method returns Font Awesome classes based on file mimetype:

| File type | Icon class |
|-----------|-----------|
| URL module | `fa-link` |
| Book module | `fa-book` |
| Folder module | `fa-folder` |
| PDF | `fa-file-pdf` |
| Word document | `fa-file-word` |
| Excel spreadsheet | `fa-file-excel` |
| PowerPoint | `fa-file-powerpoint` |
| Image | `fa-file-image` |
| Video | `fa-file-video` |
| Audio | `fa-file-audio` |
| ZIP/archive | `fa-file-archive` |
| Text | `fa-file-alt` |
| Other | `fa-file` |

#### Progress calculation

The `get_section_progress(\section_info $section, \stdClass $course)` method returns a `stdClass` with:

```
status:     'complete' | 'inprogress' | 'notstarted'
percentage: 0–100
completed:  count of finished modules
total:      count of trackable modules
```

Only counts visible modules with `completion != COMPLETION_TRACKING_NONE`. Checks for `COMPLETION_COMPLETE` or `COMPLETION_COMPLETE_PASS` states.

#### Unread forum post counting

The `count_section0_unread(\stdClass $course)` method counts unread forum posts in section 0. Called from `page_set_course()` to pass the count into the cog nav JS module.

Conditions for the count to be non-zero:
- `$CFG->forum_trackreadposts` must be enabled (site-level)
- User must be logged in (not guest)
- Forum must be in section 0 and visible to the user
- Forum's read tracking must be set to Optional (not Off)
- User's profile preference `trackforums` must be enabled
- Posts must be newer than `$CFG->forum_oldpostdays` (default 14 days)

#### page_set_course()

This method is called by Moodle on **every course page** (not just the course view). It initializes the `format_simple/cognav` AMD module with four parameters:

```php
$page->requires->js_call_amd('format_simple/cognav', 'init', [
    $courseurl->out(false),  // Course view URL
    (int) $course->id,       // Course ID
    $section0modal,          // 1 if modal enabled, 0 otherwise
    $unreadcount,            // Unread forum post count
]);
```

---

### content.php — Main layout renderer

**Class:** `content extends core_courseformat\output\local\content\base`
**Template:** `format_simple/local/content`

The `export_for_template()` method prepares the complete course layout context.

#### Template data produced

**Core:**
- `courseid`, `editing`, `sectionreturn`, `pagesectionid`

**Navigation items (`navitems` array):**
Each entry contains:
- `num` — section number
- `id` — section database ID
- `name` — display name
- `isactive` — whether this is the currently viewed section
- `isrestricted` — whether user lacks access
- `progress` — status string (`complete`, `inprogress`, `notstarted`)
- `percentage` — 0–100
- `circumference` — SVG circle circumference constant (2 × π × 16)
- `dashoffset` — SVG stroke-dashoffset for progress arc
- `availabilityinfo` — HTML restriction message (if restricted)

**Section 0 handling:**
- `hassection0` — whether section 0 exists and is visible
- `section0nav` — nav item data for section 0
- `section0modal` — whether modal mode is enabled
- `section0modalhtml` — pre-rendered section 0 HTML (for modal DOM transfer)
- `section0unread`, `hassection0unread`, `section0unreadlabel` — unread badge data

**Banner data:**
- `showbanner`, `courseshortname`, `coursefullname`, `courseimageurl`, `hascourseimage`

**Sections:**
- Array of rendered section data (each produced by the section output class)
- First visible section marked `isactive`

**Strings:**
- `str_showcoursemenu`, `str_selectunit`

#### Supporting methods

| Method | Purpose |
|--------|---------|
| `build_nav_item()` | Creates a single nav item with progress SVG data |
| `count_section_unread_posts()` | Counts unread forum posts in any given section |
| `render_availability_info()` | Handles both string and `core_availability_multiple_messages` renderable (Moodle 5.0 compatibility) |

---

### section.php — Section renderer

**Class:** `section extends core_courseformat\output\local\content\section\base`
**Template:** `format_simple/local/content/section`

The `export_for_template()` method prepares a single section's content.

#### Template data produced

**Section metadata:**
- `num`, `id`, `name`, `isactive`, `editing`, `issection0`
- `summary` (HTML with `@@PLUGINFILE@@` URLs rewritten), `hassummary`

**Learning outcomes:**
- `outcomes` — array of `{text: string}` objects (parsed from format option, one per line)
- `hasoutcomes`

**Activity lists:**

For section 0: a flat list in `allcms` (no zone separation).

For sections 1+: three zone arrays:
- `learningcontent` — with the first item marked `isprimary`
- `resources`
- `activities`
- Each zone has a `has*` boolean flag

**Completion progress:**
- `progress` object (`status`, `percentage`, `completed`, `total`)
- `iscomplete`, `isinprogress` flags

**Editing controls:**
- Section control menu (move, hide, duplicate, delete)
- "Add activity" button
- Activity control menus

**Restrictions:**
- `isrestricted`, `availabilityinfo`

#### build_cm_data() — Activity card data

Builds template data for each activity module:

| Property | Description |
|----------|-------------|
| `id`, `name`, `modname`, `url`, `iconurl` | Basic module info |
| `ishidden`, `isstealth`, `isrestricted` | Visibility states |
| `availabilityinfo`, `availabilitytext` | Restriction messages |
| `completionenabled`, `ismanualcompletion`, `iscomplete` | Completion state |
| `embedurl`, `hasembedurl`, `isembedh5p`, `isembedscorm` | Embed data (learning zone) |
| `viewurl`, `hasviewtracking` | View completion URL |
| `faicon` | Font Awesome icon class (resource zone) |
| `inlinecontent`, `hasinlinecontent` | Inline HTML content |
| `cmcontrolmenu`, `hascmcontrolmenu` | Edit controls |

#### Inline content extraction

The `get_inline_content(\cm_info $cm)` method extracts content to display directly on the page:

| Module type | Content source |
|-------------|---------------|
| `page` | `mdl_page.content` with file URL rewriting |
| `book` | All visible chapters concatenated with `<h4>` headers |
| Other | `cm_info->content` fallback (for plugins like `mod_syllabus` that set content via `get_coursemodule_info()`) |

#### Embed URL generation

The `get_embed_url(\cm_info $cm)` method returns iframe-ready URLs:

| Module | Embed URL pattern |
|--------|------------------|
| H5P | `/h5p/embed.php?url=[pluginfile_url]` |
| SCORM | `/mod/scorm/player.php?cm=[id]&scoid=[first_sco_id]&display=popup` |
| YouTube URL | `https://www.youtube-nocookie.com/embed/[VIDEO_ID]?rel=0` |
| Vimeo URL | `https://player.vimeo.com/video/[VIDEO_ID]` |

---

### get_section0_content.php — Web service

**Function name:** `format_simple_get_section0_content`

Returns rendered section 0 HTML for the Course Info overlay when accessed from non-course-view pages (where section 0 isn't already in the DOM).

**Parameters:** `courseid` (int, required)
**Returns:** `{html: string}` — rendered section 0 HTML
**Security:** Validates course context and user access before returning content.
**Declared in:** `db/services.php` as AJAX-enabled, login-required.

---

### format.php — Entry point

Minimal entry point that wires format class → output class → template:

```php
$format = course_get_format($course);
$renderer = $format->get_renderer($PAGE);
$outputclass = $format->get_output_classname('content');
$widget = new $outputclass($format);
echo $renderer->render($widget);
```

---

### Privacy and services

**Privacy:** Implements `core_privacy\local\metadata\null_provider`. The plugin stores no personal data.

**Web services (db/services.php):** One AJAX-enabled read service:
- `format_simple_get_section0_content` — requires login, callable via AJAX

**Capabilities:** No custom capabilities. Uses standard Moodle capabilities (`moodle/course:update`, `moodle/course:manageactivities`, `core/completion:view`).

---

## JavaScript implementation

### simple.js — Main format interactions

**AMD module:** `format_simple/simple`
**Dependencies:** `core/ajax`, `core/str`
**Loaded by:** `content.mustache` template via `{{#js}}` block
**Entry point:** `init(rootId)` where `rootId` is `#format-simple-root`

#### Global state

```javascript
let root = null;                  // Root element (#format-simple-root)
var langStrings = {};             // Preloaded language strings
let activeSection = 0;            // Currently displayed section number
let isEditing = false;            // Editing mode flag
let navOpen = false;              // Mobile nav drawer state
let collapseTimer = null;         // Nav auto-collapse timer
const COLLAPSE_DELAY = 3000;      // Collapse nav after 3s inactivity
const viewedUrls = new Set();     // Tracked inline content views (prevents duplicates)
const VIEW_DELAY = 30000;         // 30s before view completion fetch
const CIRCUMFERENCE = 2*PI*16;    // SVG progress ring circumference
let drawerHideTimer = null;       // Right drawer auto-close timer
const DRAWER_HIDE_DELAY = 3000;   // Close drawer after 3s inactivity
```

#### Key functions

| Function | Purpose |
|----------|---------|
| `setupNavigation()` | View mode: click handlers for single-section switching |
| `setupEditingNavigation()` | Editing mode: IntersectionObserver scroll-spy highlights topmost visible section |
| `switchSection(sectionNum)` | Crossfade transition to target section (fade out → swap → fade in) |
| `setupNavCollapse()` | Auto-collapse nav panel after 3s of inactivity |
| `setupOutcomesPopovers()` | Toggle outcomes panels with outside-click dismissal |
| `setupTopNavHover()` | Breadcrumb hover-reveal with delay |
| `setupKeyboardNav()` | Arrow Up/Down, Home/End navigation within nav panel |
| `setupMobileNav()` | Hamburger toggle below 1024px breakpoint |
| `setupDrawerAutoHide()` | Auto-close Moodle's right blocks drawer after 3s |
| `setupZoneGrouping()` | MutationObserver rebuilds zone structure after drag-and-drop |
| `setupManualCompletion()` | Toggle completion via `core_completion_update_activity_completion_status_manually` |
| `setupEmbedFullscreen()` | Fullscreen API toggle for iframes and inline content |
| `setupSubpanelTolerance()` | 400ms grace period for Moodle's dropdown subpanel menus |
| `setupInlineContentDetection()` | MutationObserver watches for JS-injected inline content |
| `triggerViewCompletion(sectionNum)` | Fetches view.php after 30s to trigger completion for inline/embedded content |
| `refreshSectionProgress(sectionNum)` | Recounts completed items and animates SVG progress indicator |
| `animateProgress(indicator, fromPct, toPct)` | Smooth 1s arc animation using `requestAnimationFrame` with ease-out cubic easing |
| `restoreFromHash()` | Navigate to section from URL hash on page load |

#### Section switching animation

1. Current section fades out: opacity 0, translateX -8px over 400ms
2. After transition ends, current section hidden
3. Target section set to display block, opacity 0, translateX 8px
4. Force reflow, then: opacity 1, translateX 0 over 500ms
5. Nav state updated, URL hash updated to `#section-N`
6. Mobile nav closes automatically
7. Content scrolls to top
8. View completion timer starts for inline/embedded items

#### Zone grouping (drag-and-drop support)

Moodle's reactive editor `_fixOrder()` flattens the DOM structure after drag-and-drop, which breaks zone grouping. The solution:

1. A `MutationObserver` watches each `.simple-cmlist[data-for="cmlist"]`
2. On child list changes, disconnects observer temporarily
3. After a 50ms delay (for Moodle's updates to finish), `regroupZones()` runs:
   - Collects all `[data-for="cmitem"]` elements
   - Groups by `data-zone` attribute
   - Reorders children: learning → resources → activities
   - Updates zone header visibility
   - Skips DOM work if already in correct order
4. Reconnects observer

#### View completion triggering

Inline/embedded content is displayed without visiting `view.php`, so completion events don't fire automatically. The solution:

1. On section switch, `triggerViewCompletion()` finds elements with `[data-viewurl]`
2. For each viewable module, waits 30 seconds to count as "viewed"
3. Fetches the view URL in the background via `fetch()`
4. Prevents duplicate fetches using the `viewedUrls` Set
5. On success, adds `is-complete` class and calls `refreshSectionProgress()`

---

### cognav.js — Cog navigation

**AMD module:** `format_simple/cognav`
**Dependencies:** `core/ajax`, `core/str`
**Loaded by:** `page_set_course()` in `lib.php` (runs on **every** course page)
**Entry point:** `init(courseUrl, courseId, section0Modal, unreadCount)`

#### Purpose

Replaces Moodle's secondary navigation bar with a fixed cog button that opens a tile-grid popover. Available on all course pages (course view, participants, grades, settings, etc.).

#### Safety checks

`init()` only runs when:
- Not already initialised (`initialised` flag)
- `.format-simple` body class is present
- Not on embedded/fullscreen page layouts

#### Icon mapping

The `COG_ICON_MAP` object contains 60+ entries matching navigation keys and labels to Font Awesome icons. Examples:

| Key | Icon |
|-----|------|
| `coursehome` | `fa-book` |
| `participants` | `fa-users` |
| `grades` | `fa-chart-bar` |
| `settings` | `fa-sliders` |
| `badges` | `fa-certificate` |
| `completion` | `fa-check-circle` |
| `calendar` | `fa-calendar` |
| `contentbank` | `fa-shapes` |
| `recyclebin` | `fa-recycle` |

Default fallback: `fa-ellipsis-h`. Also supports custom image icons via `img:/path/to/icon.png` prefix.

#### Navigation extraction

Parses Moodle's secondary navigation DOM in order:
1. Top-level visible links (`.nav > li > a.nav-link`, excluding dropdown toggles)
2. Dropdown sub-items (`.dropdown-menu .dropdown-item`)
3. More menu items (`[data-region="moredropdown"] .dropdown-item`)

Deduplicates by URL, extracts text, and detects active state.

#### Cog popover HTML structure

```
.simple-cog-container (fixed, top: 70px, left: 0)
  ├── .simple-cog-btn (gear icon, 44×44px)
  ├── .simple-cog-popover (slides out, 340px min-width)
  │   ├── .simple-cog-heading ("COURSE TOOLS")
  │   └── .simple-cog-grid (3-column CSS grid)
  │       └── .simple-cog-tile (per nav item)
  │           ├── .simple-cog-tile-icon (32×32px FA icon or image)
  │           └── label text
  ├── .simple-home-btn (if not on course view page)
  └── .simple-s0-modal-btn (if section0modal enabled)
      └── .simple-unread-badge (if unread count > 0)
```

#### Home button

- Shown on every course page **except** the main course view
- Fixed position below the cog button
- Links to the course view URL
- Font Awesome house icon with "Back to course" tooltip

#### Section 0 modal

When `section0modal` is enabled:

**Content loading strategy:**
- On the course view page: section 0 is pre-rendered as a hidden element. On first modal open, the live DOM nodes are **moved** (not copied) into the modal body, preserving JS bindings, menus, and interactive widget state.
- On other course pages: AJAX fetches `format_simple_get_section0_content` web service. A loading spinner is shown during the request.

**Modal DOM structure:**
```
.simple-s0-modal-backdrop (blur effect, click-to-close)
.simple-s0-modal (scrollable dialog, centered)
  ├── .simple-s0-modal-close (X button, top-right)
  └── .simple-s0-modal-body (scrollable content area)
```

**Closing triggers:** backdrop click, close button click, Escape key. Focus returns to the trigger button.

#### Unread forum badge

- Red badge positioned top-right of the Course Info modal button
- Only shown when count > 0
- Tooltip: "[count] Unread posts"
- Count passed from PHP via `page_set_course()`

---

## Templates

### content.mustache — Main layout

**Path:** `templates/local/content.mustache`
**Root element:** `#format-simple-root` (`.format-simple-wrapper`)

**Data attributes on root:**
- `data-courseid` — course ID
- `data-activesection` — currently active section number
- `data-editing` — `'true'` if editing mode

**Structure:**

```
#format-simple-root
  ├── nav.simple-nav (sticky, 280px wide)
  │   ├── Section 0 nav item (book icon, info circle, unread badge)
  │   ├── Separator
  │   ├── Section 1..N nav items (progress SVG + label + callout arrow)
  │   └── "Add section" button (editing mode only)
  └── .simple-content (flex: 1, scrollable)
      ├── Section 0 (rendered via section.mustache)
      ├── Section 1..N (rendered via section.mustache)
      └── Add section button (editing mode, bottom)
```

**JS initialization block:**
```
{{#js}}
require(['core_courseformat/local/content'], function(component) { ... });
require(['format_simple/simple'], function(mod) { mod.init('#format-simple-root'); });
{{/js}}
```

### section.mustache — Section layout

**Path:** `templates/local/content/section.mustache`
**Root element:** `#simple-section-[NUM]` (`.simple-section`)

**Data attributes:**
- `data-section` — section number
- `data-sectionid` — section database ID
- `data-for="section"` — Moodle reactive marker
- `hidden` — set if not active (view mode)
- `aria-labelledby` — points to section title

**Section 0 layout (flat):**

```
.simple-section
  ├── .simple-banner (if showbanner: hero image + course name)
  ├── Section header (title + edit controls)
  ├── Summary
  └── .simple-cmlist
      └── .simple-cm-wrapper (per activity, flat — no zones)
          ├── .simple-inline-content (if has inline content)
          ├── .simple-cm-card (link with icon + name + completion)
          └── .simple-cm-edit (edit controls)
```

**Sections 1+ layout (zoned):**

```
.simple-section
  ├── Section header (title + outcomes button + edit controls)
  ├── Outcomes popout panel (if defined)
  ├── Summary
  ├── Restriction notice (if restricted)
  └── .simple-cmlist[data-for="cmlist"]
      ├── .simple-zone-header[data-zone-header="learning"]
      ├── .simple-cm[data-zone="learning"] (learning content items)
      │   ├── .simple-inline-content (primary item: inline HTML)
      │   ├── .simple-embed-container (primary item: H5P/SCORM/video iframe)
      │   ├── .simple-cm-card (secondary items: card link)
      │   └── .simple-cm-edit (editing controls)
      ├── .simple-zone-header[data-zone-header="resources"]
      ├── .simple-cm-wrapper[data-zone="resources"]
      │   ├── .simple-cm-card (FA icon + name + completion)
      │   └── .simple-cm-edit
      ├── .simple-zone-header[data-zone-header="activities"]
      └── .simple-cm-wrapper[data-zone="activities"]
          ├── .simple-cm-card (module icon + name + completion + lock)
          └── .simple-cm-edit
```

**Key HTML attributes for JS:**

| Attribute | Purpose |
|-----------|---------|
| `data-zone="learning\|resources\|activities"` | Zone membership (on cmitems) |
| `data-zone-header="..."` | Zone divider element |
| `data-orphan="true"` | Tells Moodle to preserve zone headers during drag-and-drop |
| `data-viewurl="..."` | View URL for completion tracking (on inline/embed containers) |
| `data-has-completion="1"` | Module has completion tracking |
| `data-action="toggle-completion"` | Manual completion checkbox |
| `is-primary` class | First learning content item (rendered inline/embedded) |

---

## CSS architecture

**File:** `styles.css` (~1500 lines)
**Scoping:** All selectors nested under `.format-simple` body class.

### Design tokens

```css
/* Colors */
--simple-bg: #eef2ff;                      /* Page background (subtle indigo) */
--simple-surface: #ffffff;                  /* Cards, panels */
--simple-text: #1a1a1a;                     /* Primary text */
--simple-text-secondary: #6b7280;           /* Secondary text */
--simple-text-muted: #9ca3af;              /* Tertiary text */
--simple-border: #e5e7eb;                   /* Standard border */
--simple-border-light: #f3f4f6;            /* Light hover background */
--simple-accent: #2563eb;                   /* Blue primary CTA */
--simple-accent-light: #dbeafe;            /* Light blue background */
--simple-success: #10b981;                  /* Green (completion) */
--simple-success-light: #d1fae5;           /* Light green */
--simple-warning: #f59e0b;                 /* Amber */
--simple-danger: #ef4444;                   /* Red */

/* Border radius */
--simple-radius: 12px;                      /* Standard */
--simple-radius-sm: 8px;                    /* Small (buttons, inputs) */
--simple-radius-lg: 16px;                   /* Large (sections, panels) */

/* Shadows */
--simple-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
--simple-shadow-md: 0 4px 6px rgba(0,0,0,0.05), 0 2px 4px rgba(0,0,0,0.03);
--simple-shadow-lg: 0 10px 25px rgba(0,0,0,0.08), 0 4px 10px rgba(0,0,0,0.04);

/* Transitions */
--simple-transition: 500ms cubic-bezier(0.4, 0, 0.2, 1);
--simple-transition-fast: 400ms cubic-bezier(0.4, 0, 0.2, 1);
--simple-transition-slow: 600ms cubic-bezier(0.4, 0, 0.2, 1);

/* Layout */
--simple-nav-width: 280px;
--simple-nav-collapsed-width: 60px;
--simple-nav-collapse-speed: 1.2s cubic-bezier(0.25, 0.1, 0.25, 1);

/* Typography */
--simple-font-sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, ...;
```

### Layout system

- **Main wrapper:** Flexbox container — nav panel (280px fixed) + content area (flex: 1)
- **Nav panel:** Sticky positioned, scrollable, border-right separator
- **Content area:** 32px padding, scrollable, max-width constrained
- **Nav collapse:** Width transitions from 280px → 60px, labels fade out

### Component styles

| Component | Key styles |
|-----------|-----------|
| **Cog container** | Fixed position (top: 70px, left: 0), z-index layering |
| **Cog button** | 44×44px, white background, rounded corners |
| **Cog popover** | 340px min-width, 3-column grid, slide-in animation |
| **Cog tiles** | 8px border-radius, hover lift with shadow |
| **Breadcrumb** | Hidden by default (opacity 0, translateY -8px), revealed on hover |
| **Nav items** | Buttons with icon + label + progress indicator, active state with blue highlight and inset border-left |
| **Speech-bubble curves** | Pseudo-elements on active nav item sides |
| **Progress SVGs** | 36×36px circles — green check (complete), gray ring + green arc (in progress), gray outline (not started), gray + lock (restricted) |
| **Course banner** | 240px min-height, background image, dark overlay, white text, course code badge |
| **Section headers** | 1.75rem bold, dark text |
| **Zone headers** | Divider lines, 0.875rem uppercase gray text, hidden when zone empty |
| **Activity cards** | Flex column, icon + name + completion indicator, hover lift |
| **Primary learning card** | Larger, full width |
| **Inline content** | Centered, max 80vw width |
| **Embed containers** | Iframes with fullscreen button overlay |
| **Outcomes panel** | White background, border, shadow, slide-down animation, bullet list |
| **Unread badge** | Red circle, white text, positioned top-right |

### Responsive breakpoints

**Tablet/mobile (max-width: 1024px):**
- Mobile header bar appears with hamburger toggle + course title
- Nav becomes a toggleable drawer (slides from left)
- Closes after section selection
- Stacked layout (nav above content)

**Small mobile (max-width: 768px):**
- Cog modal goes full-screen (0 insets)
- Nav width reduces
- Content padding reduces

---

## Language strings

All strings defined in `lang/en/format_simple.php`:

| Key | Value | Usage |
|-----|-------|-------|
| `pluginname` | Simple format | Plugin name in admin |
| `plugin_description` | A clean, minimalist... | Admin plugin description |
| `sectionname` | Unit | Default section name |
| `section0name` | General | Section 0 default name |
| `newsectionname` | New unit | New section default |
| `hidefromothers` | Hide unit | Section visibility toggle |
| `showfromothers` | Show unit | Section visibility toggle |
| `currentsection` | This unit | Current section label |
| `deletesection` | Delete unit | Delete confirmation |
| `editsection` | Edit unit | Edit action |
| `addsection` | Add unit | Add action |
| `zone_learningcontent` | Learning Content | Zone heading |
| `zone_resources` | Related Resources | Zone heading |
| `zone_activities` | Related Activities | Zone heading |
| `learningoutcomes` | Learning outcomes | Section option label |
| `learningoutcomes_help` | Enter one outcome per line... | Help text |
| `outcomesbtn` | Outcomes | Button label |
| `nooutcomes` | No learning outcomes defined... | Empty state |
| `progress_complete` | Complete | Progress state |
| `progress_inprogress` | In progress ({$a}%) | Progress with percentage |
| `progress_notstarted` | Not started | Progress state |
| `progress_restricted` | Restricted | Progress state |
| `navpanel` | Unit navigation | Nav aria-label |
| `selectunit` | Select unit | Nav label |
| `showcoursemenu` | Show course menu | Hidden UI label |
| `hiddensections` | Hidden sections | Course option |
| `showsection0banner` | Show course banner | Course option |
| `section0modal` | Show Course Info as overlay | Course option |
| `courseinfo` | Course Info | Section 0 modal title |
| `restricted_prereqs` | Prerequisites | Restriction label |
| `restricted_info` | This unit is not yet available... | Restriction message |
| `nocoursesections` | No units have been added... | Empty course |
| `markcomplete` | Mark as complete | Completion tooltip |
| `marknotcomplete` | Mark as not complete | Completion tooltip |
| `togglefullscreen` | Toggle fullscreen | Embed button |
| `exitfullscreen` | Exit fullscreen | Embed button |
| `unreadposts` | Unread posts | Forum badge label |
| `unreadcount` | {$a} unread | Badge with count |
| `backtocourse` | Back to course | Home button tooltip |
| `coursetools` | Course tools | Cog button label |
| `close` | Close | Modal close button |
| `loading` | Loading... | Modal loading state |
| `failedtoload` | Failed to load course info. | AJAX error |
| `privacy:metadata` | The Simple format plugin does not store any personal data. | Privacy |

---

## Data flow

### Page load flow

```
1. User navigates to /course/view.php?id=123
2. Moodle loads format_simple via format.php
3. PHP backend:
   a. content.export_for_template():
      - Builds navitems[] (all sections with progress SVG data)
      - For each section, section.export_for_template():
        - Categorizes activities into zones
        - Extracts inline content (page/book/cm_info)
        - Generates embed URLs (H5P/SCORM/video)
        - Calculates completion progress
        - Checks availability restrictions
   b. Renders static HTML via content.mustache → section.mustache
4. lib.php page_set_course():
   - Counts section 0 unread forum posts
   - Loads cognav.js AMD module with (courseUrl, courseId, section0Modal, unreadCount)
5. JavaScript initialization:
   a. simple.js init():
      - Preloads language strings
      - Sets up all event handlers and observers
      - Restores section from URL hash
      - Triggers view completion for visible inline content
   b. cognav.js init():
      - Extracts secondary nav items from DOM
      - Builds cog popover with tile grid
      - Hides secondary nav bar
      - Creates home button (if not on course view)
      - Creates modal (if section0modal enabled)
6. User sees course with nav panel + active section
```

### Section switch flow

```
1. User clicks nav item (or presses Enter/Space on focused item)
2. switchSection(sectionNum) called
3. Animation sequence:
   a. Current section: opacity → 0, translateX → -8px (400ms)
   b. transitionend: current section hidden
   c. Target section: display → block, opacity 0, translateX 8px
   d. Force reflow (offsetHeight read)
   e. Target section: opacity → 1, translateX → 0 (500ms)
4. Nav state updated (aria-current, active class, callout arrow)
5. URL hash updated to #section-N
6. Mobile nav closes (if open)
7. Content area scrolls to top
8. triggerViewCompletion() starts for inline/embedded items
```

### Completion toggle flow

```
1. User clicks manual completion checkbox
2. JavaScript:
   a. Optimistic UI: toggle .is-complete class, update icon
   b. AJAX call: core_completion_update_activity_completion_status_manually
3. Moodle backend:
   a. Validates context and capability
   b. Updates mdl_course_modules_completion
   c. Triggers completion_updated event
4. JavaScript callback:
   a. Success: keep UI state
   b. Failure: revert UI, show error
5. refreshSectionProgress(sectionNum):
   a. Count completed items in section DOM
   b. Calculate new percentage
   c. animateProgress(): smooth 1s SVG arc animation
   d. At 100%: swap to green checkmark SVG
   e. At 0%: swap to empty circle SVG
```

### Inline content rendering flow

```
1. PHP (section.php):
   a. For each learning content module, check isprimary (first = true)
   b. get_inline_content() → page content, book chapters, or cm->content
   c. If content found: render inline; else: render card link
2. Template renders:
   a. .simple-inline-content with {{{inlinecontent}}} (unescaped HTML)
   b. [data-viewurl] for completion tracking
3. JavaScript (after page load):
   a. setupInlineContentDetection() watches cmitems via MutationObserver
   b. If a module injects content via JS: hideCardIfInlineContent()
   c. triggerViewCompletion() fetches view.php after 30s to fire completion
4. Moodle completion event fires → nav progress animates
```

### Embed URL generation flow

```
1. PHP (section.php): get_embed_url() called for primary learning content
2. H5P:
   a. Query mdl_files for H5P package in module context
   b. Build pluginfile URL via moodle_url
   c. Return /h5p/embed.php?url=[encoded_file_url]
3. SCORM:
   a. Query mdl_scorm_scoes for first launchable SCO
   b. Return /mod/scorm/player.php?cm=[id]&scoid=[sco_id]&display=popup
4. YouTube/Vimeo:
   a. Regex match video ID from URL
   b. Return youtube-nocookie.com/embed/[ID] or player.vimeo.com/video/[ID]
5. Template renders:
   a. .simple-embed-container with <iframe src="[embedurl]">
   b. Fullscreen toggle button overlay
6. JavaScript:
   a. setupEmbedFullscreen() handles Fullscreen API
   b. triggerViewCompletion() fetches view.php after 30s
```

---

## Zone categorization

Activities are automatically sorted into three zones based on module type. The categorization logic lives in `lib.php` → `get_activity_zone()`.

### Learning Content zone

Primary instructional content that students consume directly.

**Static assignments:** `page`, `h5pactivity`, `scorm`, `lti`, `lesson`, `label`

**Dynamic assignments:**
- URL modules pointing to YouTube (`youtube.com`, `youtu.be`) or Vimeo (`vimeo.com`) are reclassified from resources to learning content
- Any module that provides inline content via `cm_info->content` (set in `get_coursemodule_info()` hook) is promoted to learning content

**Special behavior:** The first item in this zone is marked `isprimary` and rendered inline (embedded HTML, iframe, or video player) rather than as a card link.

### Related Resources zone

Supplementary reference materials.

**Modules:** `url` (non-video), `resource` (file), `book`, `folder`

**Special behavior:** Each item displays a Font Awesome mimetype icon instead of the standard Moodle module icon.

### Related Activities zone

Interactive, submission-based, or assessment activities.

**Modules:** Everything not in the other two zones — `assign`, `quiz`, `forum`, `choice`, `feedback`, `workshop`, `glossary`, `wiki`, `data`, `chat`, `survey`, etc.

### Section 0 exception

Section 0 uses a flat list (`allcms`) with no zone separation. All activities render in their natural order with standard module icons.

---

## Configuration reference

### Plugin metadata (version.php)

```php
$plugin->version   = 2026031002;        // Build: YYYYMMDDXX
$plugin->requires  = 2025041400;        // Moodle 5.0+
$plugin->component = 'format_simple';
$plugin->maturity  = MATURITY_BETA;
$plugin->release   = '0.6.0';
```

### Course-level settings

| Setting | Admin label | Type | Default | Description |
|---------|-----------|------|---------|-------------|
| `hiddensections` | Hidden sections | Select | `1` (Invisible) | `0` = collapsed (visible but closed), `1` = invisible (completely hidden) |
| `showsection0banner` | Show course banner | Checkbox | `1` (Yes) | Display hero banner with course image and name on section 0 |
| `section0modal` | Show Course Info as overlay | Checkbox | `0` (No) | Display section 0 in a floating modal instead of inline in navigation |

### Section-level settings

| Setting | Admin label | Type | Default | Description |
|---------|-----------|------|---------|-------------|
| `learningoutcomes` | Learning outcomes | Textarea | `''` | One outcome per line. Displayed in a collapsible popout panel. |

### Site-level dependencies

These Moodle settings affect plugin behavior:

| Setting | Path | Effect |
|---------|------|--------|
| `forum_trackreadposts` | Plugins → Forum → Read tracking | Required for unread badge to appear |
| `forum_allowforcedreadtracking` | Plugins → Forum | Affects which forum tracking types count |
| `forum_oldpostdays` | Plugins → Forum | Posts older than this (days) don't count as unread |
| User `trackforums` preference | User profile → Forum preferences | Must be enabled per-user for unread counting |

---

## Accessibility

### ARIA attributes

| Element | Attribute | Value |
|---------|-----------|-------|
| Navigation panel | `role="navigation"`, `aria-label` | "Select unit" |
| Nav items | `aria-current` | `"true"` for active section |
| Outcomes button | `aria-expanded`, `aria-controls` | Toggle state, panel ID |
| Completion checkbox | `role="checkbox"`, `aria-checked` | Completion state |
| Modal | `role="dialog"`, `aria-label` | "Course Info" |
| Modal backdrop | `aria-hidden` | `"true"` when visible |
| Cog button | `aria-expanded`, `aria-label` | Toggle state, "Course tools" |
| Hidden decorative icons | `aria-hidden="true"` | Prevents screen reader noise |

### Keyboard navigation

| Key | Context | Action |
|-----|---------|--------|
| Tab | Global | Navigate through interactive elements |
| Enter / Space | Nav item | Switch to section |
| Enter / Space | Completion checkbox | Toggle completion |
| Arrow Down | Nav panel (focused) | Move to next section |
| Arrow Up | Nav panel (focused) | Move to previous section |
| Home | Nav panel (focused) | Jump to first section |
| End | Nav panel (focused) | Jump to last section |
| Escape | Modal open | Close modal |
| Escape | Cog popover open | Close popover |

### Other accessibility features

- Focus management in modals (close button focused on open, trigger button focused on close)
- Minimum 44×44px touch targets
- No color-only indicators (icons + text always paired)
- System font stack for maximum readability
- Flexible layouts without horizontal scroll

---

## Customization and extension points

### Adding a custom activity zone

1. Define the zone in `lib.php` → `get_activity_zone()`:
   ```php
   if ($cm->modname === 'mymodule') {
       return 'customzone';
   }
   ```
2. Add zone header and item rendering in `section.mustache`
3. Add CSS styling for the new zone
4. Add a language string for the zone name

### Adding custom resource icons

In `lib.php` → `get_resource_icon()`:
```php
if ($cm->modname === 'myresource') {
    return 'fa-custom-icon';
}
```

### Adding custom inline content

In `section.php` → `get_inline_content()`:
```php
if ($cm->modname === 'mymodule') {
    // Return HTML string to display inline
    return $this->render_mymodule_content($cm);
}
```

### Adding custom embed URLs

In `section.php` → `get_embed_url()`:
```php
if ($cm->modname === 'myembeddable') {
    return new \moodle_url('/mod/myembeddable/embed.php', ['id' => $cm->instance]);
}
```

### Overriding templates via theme

Place custom templates in your theme directory:
```
theme/mytheme/templates/format_simple/local/content.mustache
theme/mytheme/templates/format_simple/local/content/section.mustache
```

### Adding cog nav icons

In `cognav.js`, add entries to the `COG_ICON_MAP` object:
```javascript
'mynavkey': 'fa-my-icon',
'my tool name': 'fa-my-other-icon',
```

For custom image icons, use: `'mykey': 'img:/path/to/icon.png'`

---

## Testing

### PHPUnit

```bash
php vendor/bin/phpunit --testsuite format_simple_testsuite
```

**Format tests (28 tests)** in `tests/format_simple_test.php`:
- `test_uses_sections()` — section support enabled
- `test_uses_course_index()` — course index disabled
- `test_uses_indentation()` — indentation disabled
- `test_supports_components()` — reactive editor supported
- `test_supports_ajax()` — AJAX drag-and-drop supported
- `test_can_delete_section()` — section deletion allowed
- Additional tests for format options, zone categorization, progress calculation, etc.

**Web service tests (8 tests)** in `tests/external/get_section0_content_test.php`:
- Tests for the `format_simple_get_section0_content` external function

### Behat

```bash
php admin/tool/behat/cli/run.php --tags=@format_simple
```

Acceptance test in `tests/behat/format_simple.feature`.

---

## Troubleshooting

### JavaScript not loading

**Symptom:** Navigation not interactive, no transitions.

**Checks:**
1. Verify `amd/build/*.min.js` files exist
2. Rebuild if needed: `terser amd/src/simple.js --compress --mangle -o amd/build/simple.min.js`
3. Purge Moodle caches: `php admin/cli/purge_caches.php`

### CSS not applied

**Symptom:** Layout broken, no styling.

**Checks:**
1. Purge Moodle caches
2. Verify `.format-simple` class on `<body>` element
3. Verify `styles.css` exists in the plugin directory

### Section 0 modal not opening

**Symptom:** Button visible but nothing happens on click.

**Checks:**
1. Verify `section0modal` course format option is enabled
2. Verify `format_simple_get_section0_content` web service is registered (check `db/services.php`)
3. Check browser console for AJAX errors
4. Verify section 0 exists and is visible to the user

### Progress indicators not updating

**Symptom:** Completing activities doesn't update nav progress rings.

**Checks:**
1. Verify completion tracking is enabled on the course
2. Verify activities have completion conditions configured
3. Check browser console for errors in `refreshSectionProgress()`

### Unread forum badge not showing

**Symptom:** Badge never appears despite unread posts.

**Checks (all must be true):**
1. Site admin: Plugins → Forum → "Read tracking" enabled (`forum_trackreadposts`)
2. Forum activity: Read tracking set to "Optional" (not "Off")
3. Student user: Profile → Preferences → Forum preferences → "Forum tracking" set to "Yes"
4. Posts are newer than `forum_oldpostdays` (default 14 days)
5. Forum is in section 0

### Drag-and-drop breaks zone layout

**Symptom:** Activities in wrong zones after reordering.

**Resolution:** The zone grouping MutationObserver should automatically fix the order within 50ms. If it doesn't:
1. Check browser console for errors
2. Verify `data-zone` attributes on cmitems
3. Verify `data-zone-header` attributes on zone dividers

---

## Privacy and security

### Data storage

The plugin stores **no personal data**. Format options (section names, learning outcomes) are stored in Moodle's standard `course_format_options` table and are course-level, not user-level.

### Privacy compliance

- **Privacy provider:** `null_provider` — declares no personal data storage
- **GDPR data export:** Not applicable
- **GDPR data deletion:** Not applicable

### Security measures

- All user inputs sanitized via Moodle's `format_string()` and `format_text()`
- Web service validates course context and user access before returning content
- AJAX calls use Moodle's session validation and sesskey tokens
- HTML output escaped via Mustache template engine (`{{escaped}}` by default, `{{{unescaped}}}` only for trusted pre-rendered HTML)
- File URLs routed through Moodle's `pluginfile.php` with context and component checks
- No custom database tables or direct SQL queries against user data
