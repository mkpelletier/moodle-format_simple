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
        $page->requires->js_call_amd('format_simple/cognav', 'init');
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
        }

        return $courseformatoptions;
    }

    /**
     * Definitions of the additional options that this course format uses for sections.
     *
     * Provides a textarea for learning outcomes (one per line).
     *
     * @param bool $foreditform Whether the options are for the edit form.
     * @return array Array of options.
     */
    public function section_format_options($foreditform = false): array {
        $options = [
            'learningoutcomes' => [
                'default' => '',
                'type' => PARAM_RAW,
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
        }

        return $options;
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
     * @return string One of 'learning', 'resources', or 'activities'.
     */
    public static function get_activity_zone(\cm_info $mod): string {
        $modname = $mod->modname;

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
