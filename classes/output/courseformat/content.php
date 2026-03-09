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
 * Course content output class for format_simple.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_simple\output\courseformat;

use core_courseformat\output\local\content as content_base;
use renderer_base;
use stdClass;

/**
 * Content output class.
 *
 * Builds the data for the main course layout including the custom
 * navigation panel and the single-section content area.
 */
class content extends content_base {

    /**
     * Returns the template name for this output class.
     *
     * @param renderer_base $renderer The renderer.
     * @return string The template name.
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_simple/local/content';
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Template data.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $PAGE;

        $format = $this->format;
        $course = $format->get_course();
        $modinfo = $format->get_modinfo();

        $data = new stdClass();
        $data->courseid = $course->id;
        $data->editing = $PAGE->user_is_editing();
        $data->sectionreturn = $format->get_sectionnum() ?? 'null';
        $data->pagesectionid = $format->get_sectionid() ?? 'null';

        // Build navigation items and section content.
        $data->navitems = [];
        $data->sections = [];
        $data->hassections = false;

        $sections = $modinfo->get_section_info_all();
        $activesection = optional_param('section', 0, PARAM_INT);
        $firstvisible = null;

        // Handle section 0 (Course Info) separately.
        $formatoptions = $format->get_format_options();
        $section0modal = !empty($formatoptions['section0modal']);
        $data->hassection0 = false;
        $data->section0nav = null;
        $data->section0modal = $section0modal;
        $data->section0modalhtml = '';
        $section0 = $modinfo->get_section_info(0);
        $section0unread = $this->count_section_unread_posts($section0, $course, $modinfo);
        $data->section0unread = $section0unread;
        $data->hassection0unread = ($section0unread > 0);
        $data->section0unreadlabel = ($section0unread > 0)
            ? get_string('unreadcount', 'format_simple', $section0unread) : '';

        if ($section0 !== null && $format->is_section_visible($section0)) {
            // Build section 0 content.
            $sectionclass = $format->get_output_classname('content\\section');
            $sectionoutput = new $sectionclass($format, $section0);
            $sectiondata = $sectionoutput->export_for_template($output);

            // Banner data for section 0 (controlled by format setting).
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

            if ($section0modal && !$data->editing) {
                // Modal mode (non-editing): render section 0 as a regular
                // (hidden) section so Moodle's JS fully initialises its
                // content. cognav.js will move the live DOM node into the
                // modal on first open, preserving all event listeners.
                $sectiondata->isactive = false;
                $data->sections[] = $sectiondata;
            } else {
                // Inline mode (or editing): show section 0 in nav and inline as usual.
                $data->hassection0 = true;

                $navitem = new stdClass();
                $navitem->num = 0;
                $navitem->id = (int) $section0->id;
                $navitem->name = get_string('courseinfo', 'format_simple');
                $navitem->isactive = false;
                $navitem->issection0 = true;
                $data->section0nav = $navitem;

                $data->sections[] = $sectiondata;
            }
        }

        foreach ($sections as $section) {
            // Skip section 0 — handled above.
            if ((int) $section->section === 0) {
                continue;
            }

            // Skip sections not visible to the user.
            if (!$format->is_section_visible($section)) {
                continue;
            }

            if ($firstvisible === null) {
                $firstvisible = (int) $section->section;
            }

            // Build navigation item.
            $navitem = $this->build_nav_item($section, $course, $format, $output);
            $data->navitems[] = $navitem;

            // Build section content using the section output class.
            $sectionclass = $format->get_output_classname('content\\section');
            $sectionoutput = new $sectionclass($format, $section);
            $sectiondata = $sectionoutput->export_for_template($output);
            $data->sections[] = $sectiondata;
        }

        // Determine active section.
        // When section 0 is in modal mode or not present, fall back to the first visible unit.
        if ($activesection === 0 && !$data->hassection0 && $firstvisible !== null) {
            $activesection = $firstvisible;
        }
        $data->activesection = $activesection;

        // Mark the active nav item and section.
        if ($data->hassection0) {
            $data->section0nav->isactive = ($activesection === 0);
        }
        foreach ($data->navitems as &$navitem) {
            $navitem->isactive = ((int) $navitem->num === $activesection);
        }
        foreach ($data->sections as &$sectiondata) {
            $sectiondata->isactive = ((int) $sectiondata->num === $activesection);
        }

        $data->hassections = !empty($data->navitems) || $data->hassection0;

        // "Add section" button for editing mode.
        $data->addsectionhtml = '';
        $data->hasaddsection = false;
        if ($data->editing) {
            $addsectionclass = $format->get_output_classname('content\\addsection');
            $addsection = new $addsectionclass($format);
            $addsectiondata = $addsection->export_for_template($output);
            $data->addsectionhtml = $output->render_from_template(
                'core_courseformat/local/content/addsection',
                $addsectiondata
            );
            $data->hasaddsection = !empty($data->addsectionhtml);
        }

        // Pass strings for JS.
        $data->str_showcoursemenu = get_string('showcoursemenu', 'format_simple');
        $data->str_selectunit = get_string('selectunit', 'format_simple');

        return $data;
    }

    /**
     * Build a navigation item for a section.
     *
     * @param \section_info $section The section info.
     * @param stdClass $course The course object.
     * @param \core_courseformat\base $format The course format.
     * @return stdClass Navigation item data.
     */
    private function build_nav_item(
        \section_info $section,
        stdClass $course,
        \core_courseformat\base $format,
        renderer_base $output
    ): stdClass {
        $navitem = new stdClass();
        $navitem->num = (int) $section->section;
        $navitem->id = (int) $section->id;
        $navitem->name = $format->get_section_name($section);
        $navitem->isactive = false;

        // Availability / restriction status.
        $navitem->isrestricted = !$section->uservisible && $section->visible;
        $navitem->availabilityinfo = '';
        if ($navitem->isrestricted && !empty($section->availableinfo)) {
            $navitem->availabilityinfo = $this->render_availability_info(
                $section->availableinfo,
                $output,
                $course
            );
        }

        // Completion progress.
        $progress = \format_simple::get_section_progress($section, $course);
        $navitem->progress = $progress;
        $navitem->iscomplete = ($progress->status === 'complete');
        $navitem->isinprogress = ($progress->status === 'inprogress');
        $navitem->isnotstarted = ($progress->status === 'notstarted');
        $navitem->percentage = $progress->percentage;

        // SVG progress circle parameters (for a 36px circle, radius 16, circumference ~100.5).
        $circumference = 100.53;
        $navitem->circumference = $circumference;
        $navitem->dashoffset = $circumference - ($circumference * $progress->percentage / 100);

        return $navitem;
    }

    /**
     * Count unread forum posts in a given section for the current user.
     *
     * @param \section_info|null $section The section info.
     * @param stdClass $course The course object.
     * @param \course_modinfo $modinfo The course module info.
     * @return int Total unread post count across all forums in the section.
     */
    private function count_section_unread_posts(?\section_info $section, stdClass $course, \course_modinfo $modinfo): int {
        global $CFG;

        if ($section === null) {
            return 0;
        }

        // Forum tracking must be enabled at the site level.
        if (empty($CFG->forum_trackreadposts)) {
            return 0;
        }

        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $unread = 0;
        $cms = $modinfo->get_cms();
        foreach ($cms as $cm) {
            if ($cm->section != $section->id) {
                continue;
            }
            if ($cm->modname !== 'forum') {
                continue;
            }
            if (!$cm->uservisible) {
                continue;
            }
            $unread += forum_tp_count_forum_unread_posts($cm, $course);
        }

        return $unread;
    }

    /**
     * Render availability info that may be a string or a renderable object.
     *
     * In Moodle 5.0+ the availableinfo property can be a
     * core_availability_multiple_messages renderable rather than a plain string.
     *
     * @param string|object $info The availability info (string or renderable).
     * @param renderer_base $output The renderer.
     * @param stdClass $course The course object.
     * @return string Rendered HTML.
     */
    private function render_availability_info($info, renderer_base $output, stdClass $course): string {
        if (is_string($info)) {
            return \core_availability\info::format_info($info, $course);
        }

        $renderable = new \core_availability\output\availability_info($info);
        $templatedata = $renderable->export_for_template($output);
        $text = $output->render_from_template('core_availability/availability_info', $templatedata);
        return \core_availability\info::format_info($text, $course);
    }
}
