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
 * External function to return rendered section 0 HTML.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_simple\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns rendered section 0 HTML for the course info overlay.
 */
class get_section0_content extends external_api {

    /**
     * Parameter definitions.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'The course ID'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $courseid The course ID.
     * @return array ['html' => string]
     */
    public static function execute(int $courseid): array {
        global $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), ['courseid' => $courseid]);
        $courseid = $params['courseid'];

        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('moodle/course:view', $context);

        $course = get_course($courseid);
        $format = course_get_format($course);
        $modinfo = $format->get_modinfo();

        $section0 = $modinfo->get_section_info(0);
        if ($section0 === null || !$format->is_section_visible($section0)) {
            return ['html' => ''];
        }

        // Use the page renderer for Mustache template rendering.
        $output = $PAGE->get_renderer('core');

        // Build section 0 data using the format's section output class.
        $sectionclass = $format->get_output_classname('content\\section');
        $sectionoutput = new $sectionclass($format, $section0);
        $sectiondata = $sectionoutput->export_for_template($output);

        // Banner data (controlled by format setting).
        $formatoptions = $format->get_format_options();
        $sectiondata->showbanner = !empty($formatoptions['showsection0banner']);

        if ($sectiondata->showbanner) {
            $coursecontext = \context_course::instance($course->id);
            $sectiondata->courseshortname = format_string($course->shortname);
            $sectiondata->coursefullname = format_string(
                $course->fullname,
                true,
                ['context' => $coursecontext]
            );

            $courseimage = course_get_courseimage($course);
            if ($courseimage) {
                $sectiondata->courseimageurl = \moodle_url::make_pluginfile_url(
                    $courseimage->get_contextid(),
                    $courseimage->get_component(),
                    $courseimage->get_filearea(),
                    null,
                    $courseimage->get_filepath(),
                    $courseimage->get_filename()
                )->out();
                $sectiondata->hascourseimage = true;
            } else {
                $sectiondata->courseimageurl = '';
                $sectiondata->hascourseimage = false;
            }
        }

        // Force section to appear active so it renders content.
        $sectiondata->isactive = true;

        $html = $output->render_from_template('format_simple/local/content/section', $sectiondata);

        return ['html' => $html];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'html' => new external_value(PARAM_RAW, 'Rendered section 0 HTML'),
        ]);
    }
}
