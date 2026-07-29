<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Simple format course format class.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/lib.php');

/**
 * Simple format - a minimalist course format with clean, modern UI.
 *
 * Content is organised into units displayed one at a time, with a custom
 * navigation panel showing progress indicators. Activities are automatically
 * categorised into three zones: learning content, related resources, and
 * related activities.
 */
class format_simple extends core_courseformat\base {
    /**
     * Activity types categorised as primary learning content.
     */
    private const ZONE_LEARNING = ['page', 'h5pactivity', 'scorm', 'lti', 'lesson', 'label'];

    /**
     * Activity types categorised as related resources.
     */
    private const ZONE_RESOURCES = ['url', 'resource', 'book', 'folder'];

    /**
     * Module types that should never render as cards in any section.
     * These are administrative or teacher-only modules with no student-facing content.
     */
    private const ZONE_HIDDEN = ['qbank'];

    /**
     * Section currently being edited, so the primary content choices can be built for it.
     *
     * @var int|null Course_sections id, null when no section edit form is in play.
     */
    protected ?int $editingsectionid = null;

    /**
     * Returns whether this course format uses sections.
     *
     * @return bool
     */
    public function uses_sections(): bool {
        return true;
    }

    /**
     * Returns whether this course format uses the course index drawer.
     *
     * We disable the built-in course index and provide a custom navigation panel instead.
     *
     * @return bool
     */
    public function uses_course_index(): bool {
        return false;
    }

    /**
     * Returns whether this course format uses indentation.
     *
     * @return bool
     */
    public function uses_indentation(): bool {
        return false;
    }

    /**
     * Load the cog navigation module on every page within this course.
     *
     * Called by Moodle whenever any page sets its course context, so the
     * cog popover appears on participants, grades, settings, etc. — not
     * just the main course view.
     *
     * @param moodle_page $page The current page object.
     */
    public function page_set_course(\moodle_page $page): void {
        parent::page_set_course($page);
        $course = $this->get_course();
        $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);
        $formatoptions = $this->get_format_options();
        $section0modal = !empty($formatoptions['section0modal']) ? 1 : 0;

        // Count unread forum posts in section 0 for the cog nav badge.
        $unreadcount = $this->count_section0_unread($course);

        $page->requires->js_call_amd('format_simple/cognav', 'init', [
            $courseurl->out(false),
            (int) $course->id,
            $section0modal,
            $unreadcount,
        ]);
    }

    /**
     * Count unread forum posts in section 0 for the current user.
     *
     * @param stdClass $course The course object.
     * @return int Total unread post count.
     */
    private function count_section0_unread(\stdClass $course): int {
        global $CFG, $USER;

        if (empty($CFG->forum_trackreadposts) || isguestuser() || empty($USER->id)) {
            return 0;
        }

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $modinfo = get_fast_modinfo($course);
        $section0 = $modinfo->get_section_info(0);
        if ($section0 === null) {
            return 0;
        }

        $unread = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->section != $section0->id || $cm->modname !== 'forum' || !$cm->uservisible) {
                continue;
            }
            $unread += forum_tp_count_forum_unread_posts($cm, $course);
        }

        return $unread;
    }

    /**
     * Returns the URL for viewing a section.
     *
     * Always returns the main course view URL with a #section-N anchor.
     * This format displays all sections on one page, so we never redirect
     * to section.php.
     *
     * @param int|stdClass|\section_info $section The section number or object.
     * @param array $options Options for the URL.
     * @return \moodle_url
     */
    public function get_view_url($section, $options = []): \moodle_url {
        $course = $this->get_course();
        $url = new \moodle_url('/course/view.php', ['id' => $course->id]);

        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }

        if ($sectionno !== null) {
            $url->set_anchor('section-' . $sectionno);
        }

        return $url;
    }

    /**
     * Returns whether this course format supports the reactive course editor components.
     *
     * @return bool
     */
    public function supports_components(): bool {
        return true;
    }

    /**
     * Returns the information about the ajax support in the given source format.
     *
     * @return stdClass
     */
    public function supports_ajax(): stdClass {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Whether this format allows to delete sections.
     *
     * @param int|stdClass|section_info $section The section to check.
     * @return bool
     */
    public function can_delete_section($section): bool {
        return true;
    }

    /**
     * Allow the "Available but not shown on course page" (stealth) visibility state.
     *
     * This is permitted inside visible sections and section 0, matching the
     * behaviour of the topics and weeks formats.
     *
     * @param \stdClass|\cm_info $cm Course module (may be null for new modules).
     * @param \stdClass|\section_info $section Section where the module is located.
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section): bool {
        return !$section->section || $section->visible;
    }

    /**
     * Returns the display name for a section.
     *
     * Uses the custom section name if set, otherwise falls back to the default.
     *
     * @param int|stdClass|section_info $section The section.
     * @return string The section name.
     */
    public function get_section_name($section): string {
        $section = $this->get_section($section);
        if (!empty($section->name)) {
            return format_string(
                $section->name,
                true,
                ['context' => \context_course::instance($this->courseid)]
            );
        }
        return $this->get_default_section_name($section);
    }

    /**
     * Returns the default section name for the format.
     *
     * @param stdClass $section Section object from database or could be null.
     * @return string The default section name.
     */
    public function get_default_section_name($section): string {
        if ((int) $section->section === 0) {
            return get_string('section0name', 'format_simple');
        }
        return get_string('sectionname', 'format_simple') . ' ' . $section->section;
    }

    /**
     * Definitions of the additional options that this course format uses for courses.
     *
     * @param bool $foreditform Whether the options are for the edit form.
     * @return array Array of options.
     */
    public function course_format_options($foreditform = false): array {
        static $courseformatoptions = false;

        if ($courseformatoptions === false) {
            $courseformatoptions = [
                'hiddensections' => [
                    'default' => 1,
                    'type' => PARAM_INT,
                ],
                'showsection0banner' => [
                    'default' => 1,
                    'type' => PARAM_INT,
                ],
                'section0modal' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
            ];
        }

        if ($foreditform && !isset($courseformatoptions['hiddensections']['label'])) {
            $courseformatoptions['hiddensections'] = array_merge(
                $courseformatoptions['hiddensections'],
                [
                    'label' => new \lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new \lang_string('hiddensectionscollapsed'),
                            1 => new \lang_string('hiddensectionsinvisible'),
                        ],
                    ],
                ]
            );
            $courseformatoptions['showsection0banner'] = array_merge(
                $courseformatoptions['showsection0banner'],
                [
                    'label' => new \lang_string('showsection0banner', 'format_simple'),
                    'help' => 'showsection0banner',
                    'help_component' => 'format_simple',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new \lang_string('no'),
                            1 => new \lang_string('yes'),
                        ],
                    ],
                ]
            );
            $courseformatoptions['section0modal'] = array_merge(
                $courseformatoptions['section0modal'],
                [
                    'label' => new \lang_string('section0modal', 'format_simple'),
                    'help' => 'section0modal',
                    'help_component' => 'format_simple',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new \lang_string('no'),
                            1 => new \lang_string('yes'),
                        ],
                    ],
                ]
            );
        }

        return $courseformatoptions;
    }

    /**
     * Definitions of the additional options that this course format uses for sections.
     *
     * Provides a textarea for learning outcomes (one per line) and a selector for the
     * section's primary content. The selector's choices need to know which section is being
     * edited, which the base API does not supply; {@see editsection_form()} records it, and
     * {@see validate_format_options()} does the authoritative check when values are saved.
     *
     * @param bool $foreditform Whether the options are for the edit form.
     * @return array Array of options.
     */
    public function section_format_options($foreditform = false): array {
        $options = [
            'learningoutcomes' => [
                'default' => '',
                'type' => PARAM_TEXT,
            ],
            'primarycontent' => [
                'default' => 0,
                'type' => PARAM_INT,
            ],
        ];

        if ($foreditform) {
            $options['learningoutcomes'] = array_merge(
                $options['learningoutcomes'],
                [
                    'label' => new \lang_string('learningoutcomes', 'format_simple'),
                    'help' => 'learningoutcomes',
                    'help_component' => 'format_simple',
                    'element_type' => 'textarea',
                    'element_attributes' => [
                        ['rows' => 5, 'cols' => 60],
                    ],
                ]
            );

            $options['primarycontent'] = array_merge(
                $options['primarycontent'],
                [
                    'label' => new \lang_string('primarycontent', 'format_simple'),
                    'help' => 'primarycontent',
                    'help_component' => 'format_simple',
                    'element_type' => 'select',
                    'element_attributes' => [$this->get_primarycontent_choices($this->editingsectionid)],
                ]
            );
        }

        return $options;
    }

    /**
     * Course modules in a section that may serve as its primary content.
     *
     * @param int|null $sectionid The course_sections id, or null when no section is in context.
     * @return \cm_info[] Candidate modules keyed by course module id, empty if there are none.
     */
    protected function get_primarycontent_candidates(?int $sectionid): array {
        if (empty($sectionid)) {
            return [];
        }

        $modinfo = get_fast_modinfo($this->get_course());
        $sectioninfo = $modinfo->get_section_info_by_id($sectionid);
        if ($sectioninfo === null) {
            // The section belongs to a different course, or no longer exists.
            return [];
        }

        $candidates = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->section != $sectioninfo->id) {
                continue;
            }
            if (!$cm->is_visible_on_course_page()) {
                continue;
            }
            if (self::get_activity_zone($cm) !== 'learning') {
                continue;
            }
            $candidates[$cm->id] = $cm;
        }

        return $candidates;
    }

    /**
     * Select choices for the primary content option.
     *
     * @param int|null $sectionid The course_sections id, or null when no section is in context.
     * @return array Course module id => activity name, always including 0 for automatic.
     */
    protected function get_primarycontent_choices(?int $sectionid): array {
        $choices = [0 => get_string('primarycontent_auto', 'format_simple')];
        foreach ($this->get_primarycontent_candidates($sectionid) as $cm) {
            $choices[$cm->id] = format_string($cm->name);
        }
        return $choices;
    }

    /**
     * Return an instance of moodleform to edit a specified section.
     *
     * The base API gives section_format_options() no way to know which section is being
     * edited, so record it here. This is the documented extension point for formats that
     * need section context while the edit form is built.
     *
     * @param mixed $action The action attribute for the form.
     * @param array $customdata Custom data, with the section_info in the 'cs' field.
     * @return \moodleform
     */
    public function editsection_form($action, $customdata = []): \moodleform {
        if (!empty($customdata['cs']->id)) {
            $this->editingsectionid = (int) $customdata['cs']->id;
        }
        return parent::editsection_form($action, $customdata);
    }

    /**
     * Prepares values of course or section format options before storing them in DB.
     *
     * The parent checks 'primarycontent' against the select's choices, which
     * section_format_options() can only build when a section happens to be in context. That
     * makes the stored value depend on how the save was triggered. Validate it here instead,
     * against the section id the caller actually supplied, so every code path agrees.
     *
     * @param array $rawdata Associative array of the proposed format options.
     * @param int|null $sectionid Null for course format options.
     * @return array Options that have valid values.
     */
    protected function validate_format_options(array $rawdata, ?int $sectionid = null): array {
        $data = parent::validate_format_options($rawdata, $sectionid);

        if ($sectionid !== null && array_key_exists('primarycontent', $rawdata)) {
            $cmid = (int) $rawdata['primarycontent'];
            $candidates = $this->get_primarycontent_candidates($sectionid);
            // Anything that is not a current candidate falls back to automatic selection.
            $data['primarycontent'] = array_key_exists($cmid, $candidates) ? $cmid : 0;
        }

        return $data;
    }

    /**
     * Duplicate a section, carrying the primary content selection to the copied activity.
     *
     * The parent copies section format options before duplicating the modules, so at that
     * point the new section is still empty and the selection cannot be resolved. Re-point it
     * once the copies exist, matching each duplicate to its original by position.
     *
     * @param \section_info $originalsection The section to duplicate.
     * @return \section_info The new section.
     */
    public function duplicate_section(\section_info $originalsection): \section_info {
        $originalprimary = (int) ($this->get_format_options($originalsection)['primarycontent'] ?? 0);
        $originalcmids = $originalprimary ? $this->get_duplicable_cmids($originalsection) : [];

        $newsection = parent::duplicate_section($originalsection);

        $position = $originalprimary ? array_search($originalprimary, $originalcmids, true) : false;
        if ($position !== false) {
            $newcmids = $this->get_duplicable_cmids($newsection);
            // Only trust the pairing when the copy came out the same shape as the original.
            if (count($newcmids) === count($originalcmids) && isset($newcmids[$position])) {
                $this->update_section_format_options([
                    'id' => $newsection->id,
                    'primarycontent' => $newcmids[$position],
                ]);
            }
        }

        return get_fast_modinfo($this->get_course())->get_section_info_by_id($newsection->id);
    }

    /**
     * Course module ids of a section, in the order the parent duplicates them.
     *
     * Mirrors the filtering in core_courseformat\base::duplicate_section() so that originals
     * and copies line up index for index.
     *
     * @param \section_info $section The section to inspect.
     * @return int[] Ordered course module ids.
     */
    protected function get_duplicable_cmids(\section_info $section): array {
        $modinfo = get_fast_modinfo($this->get_course());
        $cmids = [];

        foreach ($modinfo->sections[$section->section] ?? [] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->is_of_type_that_can_display() || $cm->deletioninprogress) {
                continue;
            }
            $cmids[] = (int) $cmid;
        }

        return $cmids;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return stdClass
     */
    public function get_config_for_external(): stdClass {
        return (object) [];
    }

    /**
     * Determine which content zone an activity belongs to.
     *
     * @param \cm_info $mod The course module info.
     * @return string One of 'learning', 'resources', 'activities', or 'hidden'.
     */
    public static function get_activity_zone(\cm_info $mod): string {
        $modname = $mod->modname;

        // Administrative modules that should never render as cards.
        if (in_array($modname, self::ZONE_HIDDEN, true)) {
            return 'hidden';
        }

        // URL modules with video links are learning content, not resources.
        if ($modname === 'url' && self::is_video_url($mod)) {
            return 'learning';
        }

        if (in_array($modname, self::ZONE_LEARNING, true)) {
            return 'learning';
        }
        if (in_array($modname, self::ZONE_RESOURCES, true)) {
            return 'resources';
        }

        // Modules that provide inline content via the standard Moodle cm_info hook
        // (e.g. courseschedule with showinline) belong in the learning zone.
        if (!empty($mod->content)) {
            return 'learning';
        }

        return 'activities';
    }

    /**
     * Check whether a URL module points to a video hosting service.
     *
     * @param \cm_info $mod The course module info for a URL activity.
     * @return bool True if the URL is a YouTube or Vimeo link.
     */
    private static function is_video_url(\cm_info $mod): bool {
        global $DB;

        $urlrecord = $DB->get_record('url', ['id' => $mod->instance], 'externalurl');
        if (!$urlrecord || empty($urlrecord->externalurl)) {
            return false;
        }

        $url = $urlrecord->externalurl;

        if (preg_match('/(?:youtube\.com|youtu\.be)/', $url)) {
            return true;
        }
        if (preg_match('/vimeo\.com/', $url)) {
            return true;
        }

        return false;
    }

    /**
     * Calculate section completion progress.
     *
     * @param \section_info $section The section info object.
     * @param \stdClass $course The course object.
     * @return stdClass Object with properties: status, percentage, completed, total.
     */
    public static function get_section_progress(\section_info $section, \stdClass $course): \stdClass {
        global $USER;

        $progress = new \stdClass();
        $progress->status = 'notstarted';
        $progress->percentage = 0;
        $progress->completed = 0;
        $progress->total = 0;

        $completion = new \completion_info($course);
        if (!$completion->is_enabled()) {
            return $progress;
        }

        $modinfo = get_fast_modinfo($course);

        // Get all course modules in this section that have completion tracking.
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->section != $section->id) {
                continue;
            }
            if (!$cm->uservisible) {
                continue;
            }
            if ($cm->completion == COMPLETION_TRACKING_NONE) {
                continue;
            }

            $progress->total++;
            $completiondata = $completion->get_data($cm, true, $USER->id);
            if (
                $completiondata->completionstate == COMPLETION_COMPLETE
                || $completiondata->completionstate == COMPLETION_COMPLETE_PASS
            ) {
                $progress->completed++;
            }
        }

        if ($progress->total === 0) {
            return $progress;
        }

        $progress->percentage = (int) round(($progress->completed / $progress->total) * 100);

        if ($progress->completed === $progress->total) {
            $progress->status = 'complete';
        } else if ($progress->completed > 0) {
            $progress->status = 'inprogress';
        }

        return $progress;
    }

    /**
     * Get the Font Awesome icon class for a resource module.
     *
     * @param \cm_info $mod The course module info.
     * @return string Font Awesome icon class.
     */
    public static function get_resource_icon(\cm_info $mod): string {
        $modname = $mod->modname;

        switch ($modname) {
            case 'url':
                return 'fa-link';
            case 'book':
                return 'fa-book';
            case 'folder':
                return 'fa-folder';
            case 'resource':
                return self::get_file_icon($mod);
            default:
                return 'fa-file';
        }
    }

    /**
     * Get the Font Awesome icon class based on a file resource's mimetype.
     *
     * @param \cm_info $mod The course module info for a resource.
     * @return string Font Awesome icon class.
     */
    private static function get_file_icon(\cm_info $mod): string {
        global $DB;

        $context = \context_module::instance($mod->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder', false);
        $file = reset($files);

        if (!$file) {
            return 'fa-file';
        }

        $mimetype = $file->get_mimetype();

        if (str_contains($mimetype, 'pdf')) {
            return 'fa-file-pdf';
        }
        if (str_contains($mimetype, 'word') || str_contains($mimetype, 'document')) {
            return 'fa-file-word';
        }
        if (str_contains($mimetype, 'spreadsheet') || str_contains($mimetype, 'excel')) {
            return 'fa-file-excel';
        }
        if (str_contains($mimetype, 'presentation') || str_contains($mimetype, 'powerpoint')) {
            return 'fa-file-powerpoint';
        }
        if (str_contains($mimetype, 'image')) {
            return 'fa-file-image';
        }
        if (str_contains($mimetype, 'video')) {
            return 'fa-file-video';
        }
        if (str_contains($mimetype, 'audio')) {
            return 'fa-file-audio';
        }
        if (str_contains($mimetype, 'zip') || str_contains($mimetype, 'archive')) {
            return 'fa-file-archive';
        }
        if (str_contains($mimetype, 'text')) {
            return 'fa-file-alt';
        }

        return 'fa-file';
    }
}
