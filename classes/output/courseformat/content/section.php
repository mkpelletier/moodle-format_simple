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
 * Section output class for format_simple.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_simple\output\courseformat\content;

use core_courseformat\output\local\content\section as section_base;
use renderer_base;
use stdClass;

/**
 * Section output class.
 *
 * Categorises activities into three zones and prepares data for inline
 * content embedding, resource icons, and learning outcomes display.
 */
class section extends section_base {

    /**
     * Returns the template name for this output class.
     *
     * @param renderer_base $renderer The renderer.
     * @return string The template name.
     */
    public function get_template_name(renderer_base $renderer): string {
        return 'format_simple/local/content/section';
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
        $section = $this->section;
        $modinfo = $format->get_modinfo();

        $data = new stdClass();
        $data->num = (int) $section->section;
        $data->id = (int) $section->id;
        $data->name = $format->get_section_name($section);
        $data->isactive = false;
        $data->editing = $PAGE->user_is_editing();

        // Section summary — rewrite @@PLUGINFILE@@ tokens before formatting.
        $data->summary = '';
        if (!empty($section->summary)) {
            $context = \context_course::instance($course->id);
            $summarytext = file_rewrite_pluginfile_urls(
                $section->summary,
                'pluginfile.php',
                $context->id,
                'course',
                'section',
                $section->id
            );
            $data->summary = format_text(
                $summarytext,
                $section->summaryformat,
                ['noclean' => true, 'overflowdiv' => true, 'context' => $context]
            );
        }
        $data->hassummary = !empty($data->summary);

        // Learning outcomes.
        $data->outcomes = $this->get_outcomes($section, $format);
        $data->hasoutcomes = !empty($data->outcomes);

        // Editing controls.
        $data->sectioncontrolmenu = '';
        $data->hassectioncontrolmenu = false;
        $data->cmcontrols = '';
        $data->hascmcontrols = false;

        if ($data->editing) {
            $coursecontext = \context_course::instance($course->id);

            // Section control menu (edit, hide, delete, duplicate, move).
            if (has_capability('moodle/course:update', $coursecontext)) {
                $controlmenuclass = $format->get_output_classname('content\\section\\controlmenu');
                $controlmenu = new $controlmenuclass($format, $section);
                $controlmenudata = $controlmenu->export_for_template($output);
                if (!empty($controlmenudata->menu)) {
                    $data->sectioncontrolmenu = $output->render_from_template(
                        'core_courseformat/local/content/section/controlmenu',
                        $controlmenudata
                    );
                    $data->hassectioncontrolmenu = true;
                }
            }

            // "Add an activity or resource" button.
            if (has_capability('moodle/course:manageactivities', $coursecontext)) {
                $data->cmcontrols = $output->course_section_add_cm_control(
                    $course,
                    (int) $section->section,
                    null
                );
                $data->hascmcontrols = !empty($data->cmcontrols);
            }
        }

        // Section 0 flag — used by the template for flat list rendering.
        $data->issection0 = ((int) $section->section === 0);

        // Build activity lists.
        $data->learningcontent = [];
        $data->resources = [];
        $data->activities = [];
        $data->allcms = [];

        $cms = $modinfo->get_cms();
        foreach ($cms as $cm) {
            if ($cm->section != $section->id) {
                continue;
            }
            if (!$cm->uservisible && !$cm->is_visible_on_course_page()) {
                continue;
            }

            $zone = \format_simple::get_activity_zone($cm);
            $cmdata = $this->build_cm_data($cm, $course, $zone, $output);

            if ($data->issection0) {
                // Section 0: flat list, no zone categorization.
                $data->allcms[] = $cmdata;
            } else {
                switch ($zone) {
                    case 'learning':
                        $data->learningcontent[] = $cmdata;
                        break;
                    case 'resources':
                        $data->resources[] = $cmdata;
                        break;
                    default:
                        $data->activities[] = $cmdata;
                        break;
                }
            }
        }

        $data->hasallcms = !empty($data->allcms);
        $data->haslearningcontent = !empty($data->learningcontent);
        $data->hasresources = !empty($data->resources);
        $data->hasactivities = !empty($data->activities);

        // Prepare the primary (first) learning content for inline display.
        if ($data->haslearningcontent) {
            $primary = $data->learningcontent[0];
            $primary->isprimary = true;
            $data->learningcontent[0] = $primary;
        }

        // Section completion progress.
        $progress = \format_simple::get_section_progress($section, $course);
        $data->progress = $progress;
        $data->iscomplete = ($progress->status === 'complete');
        $data->isinprogress = ($progress->status === 'inprogress');

        // Availability.
        $data->isrestricted = !$section->uservisible && $section->visible;
        $data->availabilityinfo = '';
        if ($data->isrestricted && !empty($section->availableinfo)) {
            $data->availabilityinfo = $section->availableinfo;
        }

        // Strings for template.
        $data->str_learningcontent = get_string('zone_learningcontent', 'format_simple');
        $data->str_resources = get_string('zone_resources', 'format_simple');
        $data->str_activities = get_string('zone_activities', 'format_simple');
        $data->str_outcomes = get_string('outcomesbtn', 'format_simple');
        $data->str_nooutcomes = get_string('nooutcomes', 'format_simple');

        return $data;
    }

    /**
     * Build template data for a single course module.
     *
     * @param \cm_info $cm The course module info.
     * @param stdClass $course The course object.
     * @param string $zone The zone this module belongs to.
     * @param renderer_base $output The renderer.
     * @return stdClass Course module template data.
     */
    private function build_cm_data(\cm_info $cm, stdClass $course, string $zone, renderer_base $output): stdClass {
        global $PAGE, $USER;

        $cmdata = new stdClass();
        $cmdata->id = $cm->id;
        $cmdata->zone = $zone;
        $cmdata->name = $cm->get_formatted_name();
        $cmdata->modname = $cm->modname;
        $cmdata->url = $cm->url ? $cm->url->out(false) : '';
        $cmdata->iconurl = $cm->get_icon_url()->out(false);
        $cmdata->uservisible = $cm->uservisible;
        $cmdata->isprimary = false;
        $cmdata->editing = $PAGE->user_is_editing();

        // Completion state.
        $cmdata->completionenabled = ($cm->completion != COMPLETION_TRACKING_NONE);
        $cmdata->ismanualcompletion = ($cm->completion == COMPLETION_TRACKING_MANUAL);
        $cmdata->iscomplete = false;
        if ($cmdata->completionenabled) {
            $completion = new \completion_info($course);
            $completiondata = $completion->get_data($cm, true, $USER->id);
            $cmdata->iscomplete = (
                $completiondata->completionstate == COMPLETION_COMPLETE
                || $completiondata->completionstate == COMPLETION_COMPLETE_PASS
            );
        }

        // Availability restrictions.
        $cmdata->isrestricted = !$cm->uservisible && $cm->visible;
        $cmdata->availabilityinfo = '';
        if ($cmdata->isrestricted && !empty($cm->availableinfo)) {
            $cmdata->availabilityinfo = $cm->availableinfo;
        }

        // Zone-specific data.
        if ($zone === 'resources') {
            $cmdata->faicon = \format_simple::get_resource_icon($cm);
        }

        if ($zone === 'learning') {
            $cmdata->inlinecontent = $this->get_inline_content($cm);
            $cmdata->hasinlinecontent = !empty($cmdata->inlinecontent);
            $cmdata->embedurl = $this->get_embed_url($cm);
            $cmdata->hasembedurl = !empty($cmdata->embedurl);
            $cmdata->isembedh5p = ($cm->modname === 'h5pactivity' && $cmdata->hasembedurl);

            // View completion tracking for inline/embedded content.
            // These modules are displayed without visiting view.php, so JS
            // will fetch the view URL in the background to trigger completion.
            $viewmods = ['page', 'book', 'h5pactivity'];
            if (($cmdata->hasinlinecontent || $cmdata->hasembedurl) && in_array($cm->modname, $viewmods, true)) {
                $cmdata->viewurl = $cm->url ? $cm->url->out(false) : '';
                $cmdata->hasviewtracking = !empty($cmdata->viewurl);
            }
        }

        // Activity editing controls.
        $cmdata->cmcontrolmenu = '';
        $cmdata->hascmcontrolmenu = false;
        if ($cmdata->editing) {
            $format = $this->format;
            $sectioninfo = $format->get_modinfo()->get_section_info_by_id($cm->section);
            $controlmenuclass = $format->get_output_classname('content\\cm\\controlmenu');
            $controlmenu = new $controlmenuclass($format, $sectioninfo, $cm);
            $controlmenudata = $controlmenu->export_for_template($output);
            if (!empty($controlmenudata->menu)) {
                $cmdata->cmcontrolmenu = $output->render_from_template(
                    'core_courseformat/local/content/cm/controlmenu',
                    $controlmenudata
                );
                $cmdata->hascmcontrolmenu = true;
            }
        }

        return $cmdata;
    }

    /**
     * Get inline content for a learning content module (page or book).
     *
     * @param \cm_info $cm The course module info.
     * @return string HTML content to render inline, or empty string.
     */
    private function get_inline_content(\cm_info $cm): string {
        global $DB;

        if (!$cm->uservisible) {
            return '';
        }

        if ($cm->modname === 'page') {
            return $this->get_page_inline_content($cm);
        }

        if ($cm->modname === 'book') {
            return $this->get_book_inline_content($cm);
        }

        return '';
    }

    /**
     * Get inline content for a mod_page activity.
     *
     * @param \cm_info $cm The course module info.
     * @return string HTML content or empty string.
     */
    private function get_page_inline_content(\cm_info $cm): string {
        global $DB;

        $page = $DB->get_record('page', ['id' => $cm->instance], 'content, contentformat');
        if (!$page) {
            return '';
        }

        $context = \context_module::instance($cm->id);
        $content = file_rewrite_pluginfile_urls(
            $page->content,
            'pluginfile.php',
            $context->id,
            'mod_page',
            'content',
            0
        );

        return format_text($content, $page->contentformat, [
            'noclean' => true,
            'context' => $context,
        ]);
    }

    /**
     * Get inline content for a mod_book activity.
     *
     * Fetches all visible chapters and renders them sequentially.
     *
     * @param \cm_info $cm The course module info.
     * @return string HTML content or empty string.
     */
    private function get_book_inline_content(\cm_info $cm): string {
        global $DB;

        $chapters = $DB->get_records('book_chapters',
            ['bookid' => $cm->instance, 'hidden' => 0],
            'pagenum ASC'
        );
        if (!$chapters) {
            return '';
        }

        $context = \context_module::instance($cm->id);
        $content = '';
        foreach ($chapters as $chapter) {
            $chaptercontent = file_rewrite_pluginfile_urls(
                $chapter->content,
                'pluginfile.php',
                $context->id,
                'mod_book',
                'chapter',
                $chapter->id
            );
            $tag = $chapter->subchapter ? 'h4' : 'h3';
            $content .= '<' . $tag . '>' . format_string($chapter->title) . '</' . $tag . '>';
            $content .= format_text($chaptercontent, $chapter->contentformat, [
                'noclean' => true,
                'context' => $context,
            ]);
        }
        return $content;
    }

    /**
     * Get an embed URL for inline iframe display.
     *
     * Supports video URLs (YouTube/Vimeo) and H5P activities.
     *
     * @param \cm_info $cm The course module info.
     * @return string Embed URL or empty string.
     */
    private function get_embed_url(\cm_info $cm): string {
        global $DB;

        if (!$cm->uservisible) {
            return '';
        }

        // H5P activity — embed via the core H5P player (clean, no Moodle chrome).
        if ($cm->modname === 'h5pactivity') {
            return $this->get_h5p_embed_url($cm);
        }

        // URL module — check for YouTube/Vimeo video embeds.
        if ($cm->modname !== 'url') {
            return '';
        }

        $urlrecord = $DB->get_record('url', ['id' => $cm->instance], 'externalurl');
        if (!$urlrecord || empty($urlrecord->externalurl)) {
            return '';
        }

        $url = $urlrecord->externalurl;

        // YouTube detection.
        if (preg_match(
            '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
            $url,
            $matches
        )) {
            return 'https://www.youtube-nocookie.com/embed/' . $matches[1] . '?rel=0';
        }

        // Vimeo detection.
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }

        return '';
    }

    /**
     * Get the embed URL for an H5P activity via the core H5P player.
     *
     * @param \cm_info $cm The course module info.
     * @return string Embed URL or empty string.
     */
    private function get_h5p_embed_url(\cm_info $cm): string {
        $context = \context_module::instance($cm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_h5pactivity', 'package', 0, 'id', false);
        $file = reset($files);
        if (!$file) {
            return '';
        }

        $fileurl = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );
        $embedurl = new \moodle_url('/h5p/embed.php', ['url' => $fileurl->out(false)]);
        return $embedurl->out(false);
    }

    /**
     * Get learning outcomes for a section.
     *
     * @param \section_info $section The section info.
     * @param \core_courseformat\base $format The course format.
     * @return array Array of outcome objects with 'text' property.
     */
    private function get_outcomes(\section_info $section, \core_courseformat\base $format): array {
        $options = $format->get_format_options($section);
        $raw = $options['learningoutcomes'] ?? '';
        if (empty($raw)) {
            return [];
        }

        $lines = explode("\n", $raw);
        $outcomes = [];
        foreach ($lines as $line) {
            $text = trim($line);
            if ($text !== '') {
                $outcome = new stdClass();
                $outcome->text = format_string($text);
                $outcomes[] = $outcome;
            }
        }

        return $outcomes;
    }
}
