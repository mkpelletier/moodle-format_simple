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

namespace format_simple;

use core_external\external_api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Tests for completion tracking of content the format renders in place.
 *
 * Featured and inline content is never opened through the activity's own view.php, so the view
 * event that ordinarily records the visit does not happen and no completion control is reachable
 * from the activity card. The format therefore exports core's completion information for every
 * activity and tells JavaScript which web service raises the view event.
 *
 * @package    format_simple
 * @copyright  2026 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_simple\output\courseformat\content\section
 * @covers     \format_simple\output\courseformat\content\cm\completion
 */
final class completion_test extends \advanced_testcase {
    /**
     * Build a completion-enabled course with a student enrolled.
     *
     * @return array{0: \stdClass, 1: \stdClass} The course and the enrolled student.
     */
    protected function make_course(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            [
                'format' => 'simple',
                'numsections' => 2,
                'enablecompletion' => 1,
                'showcompletionconditions' => 1,
            ],
            ['createsections' => true]
        );
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        return [$course, $student];
    }

    /**
     * Create a page activity with the given completion settings.
     *
     * @param \stdClass $course The course to add it to.
     * @param string $name The activity name.
     * @param array $completion Completion fields for the module.
     * @return \stdClass The created module.
     */
    protected function make_page(\stdClass $course, string $name, array $completion): \stdClass {
        return $this->getDataGenerator()->create_module('page', array_merge([
            'course' => $course->id,
            'section' => 1,
            'name' => $name,
            'content' => "<p>Body of {$name}</p>",
            'contentformat' => FORMAT_HTML,
        ], $completion));
    }

    /**
     * Mark an activity as the section's featured content.
     *
     * @param \stdClass $course The course.
     * @param int $cmid The course module to feature.
     * @return \stdClass The section record.
     */
    protected function feature(\stdClass $course, int $cmid): \stdClass {
        global $DB;

        $section = $DB->get_record(
            'course_sections',
            ['course' => $course->id, 'section' => 1],
            '*',
            MUST_EXIST
        );
        $DB->insert_record('course_format_options', (object) [
            'courseid' => $course->id,
            'format' => 'simple',
            'sectionid' => $section->id,
            'name' => 'primarycontent',
            'value' => $cmid,
        ]);
        rebuild_course_cache($course->id, true);

        return $section;
    }

    /**
     * Export the section template data as the given user.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $section The section record.
     * @param \stdClass $user The user to export as.
     * @return \stdClass The exported section data.
     */
    protected function export_section(\stdClass $course, \stdClass $section, \stdClass $user): \stdClass {
        global $PAGE;

        $this->setUser($user);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);

        $format = course_get_format($course->id);
        $renderer = $format->get_renderer($PAGE);
        $sectioninfo = get_fast_modinfo($course->id, $user->id)->get_section_info_by_id($section->id);
        $outputclass = $format->get_output_classname('content\\section');
        $widget = new $outputclass($format, $sectioninfo);

        return $widget->export_for_template($renderer);
    }

    /**
     * Invoke a view web service the way the browser would.
     *
     * call_external_function() enforces a session key, which only exists during a real request;
     * core/ajax supplies it automatically in the browser.
     *
     * @param string $service The external function name.
     * @param string $param The name of the service's instance id parameter.
     * @param int $instance The activity instance id.
     * @return array The external call result.
     */
    protected function call_view_service(string $service, string $param, int $instance): array {
        $_POST['sesskey'] = sesskey();
        try {
            return external_api::call_external_function($service, [$param => $instance]);
        } finally {
            unset($_POST['sesskey']);
        }
    }

    /**
     * Find an exported activity by name.
     *
     * @param \stdClass $data The exported section data.
     * @param string $name The activity name to find.
     * @return \stdClass|null The activity data.
     */
    protected function find_item(\stdClass $data, string $name): ?\stdClass {
        foreach ($data->learningcontent as $item) {
            if ($item->name === $name) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Featured content offers a manual completion control.
     *
     * Before this worked, the featured branch of the template rendered the content only, so a
     * student had no way to mark the activity done.
     */
    public function test_featured_content_exposes_manual_completion(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $page = $this->make_page($course, 'Featured', ['completion' => COMPLETION_TRACKING_MANUAL]);
        $section = $this->feature($course, (int) $page->cmid);

        $data = $this->export_section($course, $section, $student);
        $item = $this->find_item($data, 'Featured');

        $this->assertNotNull($item);
        $this->assertTrue($item->isprimary, 'The activity should be the featured one.');
        $this->assertTrue($item->hasinlinecontent, 'Featured page content should render in place.');
        $this->assertTrue($item->hascompletion, 'Featured content must carry completion information.');
        $this->assertTrue(
            $item->completion->showmanualcompletion,
            'A manual completion control must be offered.'
        );
        $this->assertTrue(
            $item->completion->istrackeduser,
            'The student must be tracked for completion.'
        );
    }

    /**
     * Featured content reports the web service that raises its view event.
     */
    public function test_featured_content_exposes_view_service(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $page = $this->make_page($course, 'Featured', [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);
        $section = $this->feature($course, (int) $page->cmid);

        $item = $this->find_item($this->export_section($course, $section, $student), 'Featured');

        $this->assertTrue($item->hasviewtracking);
        $this->assertEquals('mod_page_view_page', $item->viewservice);
        $this->assertEquals($page->id, $item->viewinstance);
    }

    /**
     * Every advertised view service exists and accepts the parameter name we send it.
     *
     * Each module names its instance parameter after itself rather than using a common one, so
     * the pairing has to be checked, not assumed. Guards against a typo here or a module
     * changing its signature in a future release.
     */
    public function test_advertised_view_services_match_their_signatures(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        // Mirrors format_simple\output\courseformat\content\section::VIEW_SERVICES.
        $services = [
            'mod_book_view_book' => 'bookid',
            'mod_folder_view_folder' => 'folderid',
            'mod_h5pactivity_view_h5pactivity' => 'h5pactivityid',
            'mod_lesson_view_lesson' => 'lessonid',
            'mod_lti_view_lti' => 'ltiid',
            'mod_page_view_page' => 'pageid',
            'mod_resource_view_resource' => 'resourceid',
            'mod_scorm_view_scorm' => 'scormid',
            'mod_url_view_url' => 'urlid',
        ];

        foreach ($services as $service => $param) {
            $info = external_api::external_function_info($service, IGNORE_MISSING);
            $this->assertNotFalse($info, "External function {$service} does not exist.");
            $this->assertArrayHasKey(
                $param,
                $info->parameters_desc->keys,
                "{$service} does not accept a parameter named {$param}."
            );
        }
    }

    /**
     * The exported parameter name is one the advertised service actually accepts.
     */
    public function test_exported_view_parameter_is_valid_for_the_service(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $page = $this->make_page($course, 'Featured', ['completion' => COMPLETION_TRACKING_MANUAL]);
        $section = $this->feature($course, (int) $page->cmid);

        $item = $this->find_item($this->export_section($course, $section, $student), 'Featured');

        $this->setAdminUser();
        $info = external_api::external_function_info($item->viewservice, IGNORE_MISSING);
        $this->assertNotFalse($info);
        $this->assertArrayHasKey(
            $item->viewparam,
            $info->parameters_desc->keys,
            'The exported parameter name is not accepted by the exported service.'
        );
    }

    /**
     * Calling the advertised service satisfies view based completion.
     *
     * This is the mechanism the JavaScript relies on, so it is worth proving end to end rather
     * than trusting that the service name alone is enough.
     */
    public function test_view_service_completes_the_activity(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $page = $this->make_page($course, 'Featured', [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);
        $section = $this->feature($course, (int) $page->cmid);

        $this->setUser($student);
        $cm = get_fast_modinfo($course->id, $student->id)->get_cm((int) $page->cmid);
        $completion = new \completion_info(get_course($course->id));

        $before = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(COMPLETION_INCOMPLETE, $before->completionstate);

        // Exactly what the JavaScript does once the content has been seen.
        $item = $this->find_item($this->export_section($course, $section, $student), 'Featured');
        $result = $this->call_view_service($item->viewservice, $item->viewparam, (int) $item->viewinstance);
        $this->assertFalse($result['error'], 'The view service call failed: '
            . json_encode($result['exception'] ?? null));

        $after = $completion->get_data($cm, false, $student->id);
        $this->assertEquals(
            COMPLETION_COMPLETE,
            $after->completionstate,
            'Raising the view event must satisfy view based completion.'
        );
    }

    /**
     * The view condition is not restated underneath content the student is already reading.
     *
     * This format marks in-place content viewed as soon as it has been seen, and the section
     * progress ring reflects that, so a "view this activity" condition adds nothing. With
     * viewing as the only condition there is nothing left to show at all.
     */
    public function test_view_condition_is_not_shown_for_inplace_content(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $page = $this->make_page($course, 'Featured', [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);
        $section = $this->feature($course, (int) $page->cmid);

        $item = $this->find_item($this->export_section($course, $section, $student), 'Featured');

        $this->assertTrue($item->hasinlinecontent);
        $this->assertTrue($item->completionenabled, 'Completion is still tracked.');
        $this->assertFalse(
            $item->hascompletion,
            'Viewing was the only condition, so nothing should be rendered.'
        );
    }

    /**
     * A manual button still appears when viewing is also a condition.
     */
    public function test_manual_button_survives_view_condition_removal(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $page = $this->make_page($course, 'Featured', ['completion' => COMPLETION_TRACKING_MANUAL]);
        $section = $this->feature($course, (int) $page->cmid);

        $item = $this->find_item($this->export_section($course, $section, $student), 'Featured');

        $this->assertTrue($item->hascompletion);
        $this->assertTrue($item->completion->showmanualcompletion);
        foreach ($item->completion->completiondetails ?? [] as $detail) {
            $this->assertNotEquals(
                'completionview',
                $detail->key ?? '',
                'The view condition should have been removed.'
            );
        }
    }

    /**
     * Activities without completion tracking export no completion information.
     */
    public function test_untracked_activity_has_no_completion(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $page = $this->make_page($course, 'Featured', ['completion' => COMPLETION_TRACKING_NONE]);
        $section = $this->feature($course, (int) $page->cmid);

        $item = $this->find_item($this->export_section($course, $section, $student), 'Featured');

        $this->assertFalse($item->completionenabled);
        $this->assertFalse($item->hascompletion);
        $this->assertEquals('incomplete', $item->completionstate);
    }

    /**
     * Activity cards carry the compact indicator, not core's completion block.
     *
     * Core's markup contains a button, which cannot sit inside the card's link, and is far too
     * heavy for a card. Cards therefore expose the state the indicator template needs.
     */
    public function test_card_activities_use_the_compact_indicator(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $featured = $this->make_page($course, 'Featured', ['completion' => COMPLETION_TRACKING_MANUAL]);
        $this->make_page($course, 'Sibling', ['completion' => COMPLETION_TRACKING_MANUAL]);
        $section = $this->feature($course, (int) $featured->cmid);

        $item = $this->find_item($this->export_section($course, $section, $student), 'Sibling');

        $this->assertNotNull($item);
        $this->assertFalse($item->isprimary);
        $this->assertFalse(
            $item->hascompletion,
            'Cards must not carry core\'s completion block; a button cannot nest in the link.'
        );

        // What the indicator template needs instead.
        $this->assertTrue($item->completionenabled);
        $this->assertTrue($item->ismanualcompletion);
        $this->assertEquals('incomplete', $item->completionstate);
    }

    /**
     * The completion state attribute follows the activity's real state.
     *
     * The section progress ring counts this attribute, so it must not go stale.
     */
    public function test_completion_state_reflects_progress(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $page = $this->make_page($course, 'Featured', [
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);
        $section = $this->feature($course, (int) $page->cmid);

        $item = $this->find_item($this->export_section($course, $section, $student), 'Featured');
        $this->assertEquals('incomplete', $item->completionstate);

        $this->setUser($student);
        $this->call_view_service('mod_page_view_page', 'pageid', (int) $page->id);

        $item = $this->find_item($this->export_section($course, $section, $student), 'Featured');
        $this->assertEquals('complete', $item->completionstate);
        $this->assertTrue($item->iscomplete);
    }
}
