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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Tests for how a section renders labels and activity names.
 *
 * A label carries text rather than linking to a page of its own, so it is shown as section copy.
 * Activity names are rendered through an inplace editable while editing, so they can be renamed
 * without leaving the course page.
 *
 * @package    format_simple
 * @copyright  2026 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_simple\output\courseformat\content\section
 * @covers     \format_simple\output\courseformat\content\cm\title
 */
final class section_rendering_test extends \advanced_testcase {
    /**
     * Build a course with a section 1 to put things in.
     *
     * @return array{0: \stdClass, 1: \stdClass} The course and its section 1 record.
     */
    protected function make_course(): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'simple', 'numsections' => 2],
            ['createsections' => true]
        );
        $section = $DB->get_record(
            'course_sections',
            ['course' => $course->id, 'section' => 1],
            '*',
            MUST_EXIST
        );

        return [$course, $section];
    }

    /**
     * Add a label to section 1.
     *
     * @param \stdClass $course The course.
     * @param string $text The label's text.
     * @return \stdClass The created module.
     */
    protected function make_label(\stdClass $course, string $text): \stdClass {
        return $this->getDataGenerator()->create_module('label', [
            'course' => $course->id,
            'section' => 1,
            'intro' => $text,
            'introformat' => FORMAT_HTML,
        ]);
    }

    /**
     * Export the section template data.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $section The section record.
     * @return \stdClass The exported data.
     */
    protected function export_section(\stdClass $course, \stdClass $section): \stdClass {
        global $PAGE;

        $PAGE->set_url('/course/view.php', ['id' => $course->id]);

        $format = course_get_format($course->id);
        $renderer = $format->get_renderer($PAGE);
        $sectioninfo = get_fast_modinfo($course->id)->get_section_info_by_id($section->id);
        $outputclass = $format->get_output_classname('content\\section');
        $widget = new $outputclass($format, $sectioninfo);

        return $widget->export_for_template($renderer);
    }

    /**
     * A label is carried by the activity it introduces, not left where zoning would put it.
     */
    public function test_label_travels_with_the_activity_below_it(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->make_label($course, '<p>Read this first.</p>');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'A page',
        ]);

        $data = $this->export_section($course, $section);

        $this->assertCount(1, $data->learningcontent, 'The label must not count as an activity.');
        $page = $data->learningcontent[0];
        $this->assertEquals('A page', $page->name);
        $this->assertTrue($page->hasprecedinglabels);
        $this->assertCount(1, $page->precedinglabels);
        $this->assertStringContainsString(
            'Read this first.',
            $page->precedinglabels[0]->inlinecontent
        );
    }

    /**
     * A label follows whichever activity it was placed above, whatever zone that lands in.
     */
    public function test_label_attaches_across_zones(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'section' => 1, 'name' => 'A page',
        ]);
        $this->make_label($course, '<p>Now discuss it.</p>');
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id, 'section' => 1, 'name' => 'A forum',
        ]);

        $data = $this->export_section($course, $section);

        $this->assertFalse(
            $data->learningcontent[0]->hasprecedinglabels,
            'The page comes before the label, so it should carry nothing.'
        );
        $forum = $data->activities[0];
        $this->assertEquals('A forum', $forum->name);
        $this->assertTrue(
            $forum->hasprecedinglabels,
            'The label belongs to the forum it was placed above, even in another zone.'
        );
        $this->assertStringContainsString('Now discuss it.', $forum->precedinglabels[0]->inlinecontent);
    }

    /**
     * Several labels in a row all travel with the activity that follows them.
     */
    public function test_consecutive_labels_stay_together(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->make_label($course, '<p>First note.</p>');
        $this->make_label($course, '<p>Second note.</p>');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'section' => 1, 'name' => 'A page',
        ]);

        $page = $this->export_section($course, $section)->learningcontent[0];

        $this->assertCount(2, $page->precedinglabels);
        $this->assertStringContainsString('First note.', $page->precedinglabels[0]->inlinecontent);
        $this->assertStringContainsString('Second note.', $page->precedinglabels[1]->inlinecontent);
    }

    /**
     * A label with nothing after it has nothing to introduce, so it stays at the end.
     */
    public function test_trailing_label_stays_at_the_end(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'section' => 1, 'name' => 'A page',
        ]);
        $this->make_label($course, '<p>Closing thought.</p>');

        $data = $this->export_section($course, $section);

        $this->assertTrue($data->hastraillabels);
        $this->assertCount(1, $data->traillabels);
        $this->assertStringContainsString('Closing thought.', $data->traillabels[0]->inlinecontent);
        $this->assertFalse($data->learningcontent[0]->hasprecedinglabels);
    }

    /**
     * The label's own text is what gets rendered, and it links nowhere.
     */
    public function test_label_carries_its_text(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->make_label($course, '<p>Read this first.</p>');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'section' => 1, 'name' => 'A page',
        ]);

        $label = $this->export_section($course, $section)->learningcontent[0]->precedinglabels[0];

        $this->assertStringContainsString('Read this first.', $label->inlinecontent);
        $this->assertEquals('label', $label->zone);
        $this->assertTrue($label->islabel);
        $this->assertSame('', $label->url, 'A label has nowhere to link to.');
    }

    /**
     * A label cannot be chosen as a section's featured content.
     */
    public function test_label_is_not_offered_as_featured_content(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $label = $this->make_label($course, '<p>Read this first.</p>');
        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'A page',
        ]);

        $format = course_get_format($course->id);
        $sectioninfo = get_fast_modinfo($course->id)->get_section_info_by_id($section->id);
        $format->editsection_form(new \moodle_url('/course/editsection.php'), [
            'cs' => $sectioninfo,
            'editoroptions' => ['maxfiles' => 0],
            'defaultsectionname' => 'Section 1',
            'showonly' => '',
        ]);
        $choices = $format->section_format_options(true)['primarycontent']['element_attributes'][0];

        $this->assertArrayHasKey((int) $page->cmid, $choices);
        $this->assertArrayNotHasKey((int) $label->cmid, $choices);
    }

    /**
     * A label with no text still exports without error.
     */
    public function test_empty_label_is_harmless(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->make_label($course, '');

        $data = $this->export_section($course, $section);

        $this->assertTrue($data->hastraillabels);
        $this->assertCount(1, $data->traillabels);
    }

    /**
     * An activity set to show its description exports that description.
     */
    public function test_description_is_exported_when_asked_for(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'A forum',
            'intro' => '<p>Post your reflection by Friday.</p>',
            'introformat' => FORMAT_HTML,
            'showdescription' => 1,
        ]);

        $item = $this->export_section($course, $section)->activities[0];

        $this->assertTrue($item->hasdescription);
        $this->assertStringContainsString('Post your reflection by Friday.', $item->description);
    }

    /**
     * Without the setting there is no description to show.
     */
    public function test_description_is_absent_by_default(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'A forum',
            'intro' => '<p>Post your reflection by Friday.</p>',
            'introformat' => FORMAT_HTML,
        ]);

        $item = $this->export_section($course, $section)->activities[0];

        $this->assertFalse($item->hasdescription);
        $this->assertSame('', $item->description);
    }

    /**
     * A description must not be mistaken for content the activity renders in place.
     *
     * Modules expose both through the same cm_info hook, so without the distinction, ticking
     * "display description" would move an activity into the learning zone and show its
     * description as though it were the activity itself.
     */
    public function test_description_does_not_promote_the_activity(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'A forum',
            'intro' => '<p>Post your reflection by Friday.</p>',
            'introformat' => FORMAT_HTML,
            'showdescription' => 1,
        ]);

        $data = $this->export_section($course, $section);

        $this->assertCount(0, $data->learningcontent, 'A forum is not learning content.');
        $this->assertCount(1, $data->activities);
        $item = $data->activities[0];
        $this->assertEquals('activities', $item->zone);
        $this->assertFalse($item->hasinlinecontent, 'The description is not content to render in place.');
    }

    /**
     * A label is only its own text, so it gains no separate description.
     */
    public function test_label_has_no_separate_description(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->make_label($course, '<p>Read this first.</p>');
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id, 'section' => 1, 'name' => 'A page',
        ]);

        $label = $this->export_section($course, $section)->learningcontent[0]->precedinglabels[0];

        $this->assertFalse($label->hasdescription);
    }

    /**
     * While editing, the activity name is an inplace editable so it can be renamed here.
     */
    public function test_activity_name_is_editable_while_editing(): void {
        global $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'A forum',
        ]);

        $USER->editing = 1;
        $item = $this->export_section($course, $section)->activities[0];

        $this->assertTrue($item->haseditablename);
        $this->assertStringContainsString('data-inplaceeditable="1"', $item->editablename);
        $this->assertStringContainsString('data-itemtype="activityname"', $item->editablename);
        $this->assertStringContainsString('quickeditlink', $item->editablename, 'The pencil should be there.');
    }

    /**
     * The name's link is not stretched, so the controls beside it stay reachable.
     */
    public function test_editable_name_link_is_not_stretched(): void {
        global $USER;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'A forum',
        ]);

        $USER->editing = 1;
        $item = $this->export_section($course, $section)->activities[0];

        // Guard against passing simply because there is no name to inspect.
        $this->assertTrue($item->haseditablename);
        $this->assertStringContainsString('<a href=', $item->editablename);
        $this->assertStringNotContainsString(
            'stretched-link',
            $item->editablename,
            'A stretched link would cover the rename pencil and the completion indicator.'
        );
    }

    /**
     * Students get no editable name, and so no pencil.
     */
    public function test_activity_name_is_not_editable_for_students(): void {
        $this->resetAfterTest(true);

        [$course, $section] = $this->make_course();
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'A forum',
        ]);

        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');
        $this->setUser($student);

        $item = $this->export_section($course, $section)->activities[0];

        $this->assertFalse($item->haseditablename);
        $this->assertSame('', $item->editablename);
    }
}
