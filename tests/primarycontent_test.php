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
 * Tests for the primary content section option.
 *
 * The option stores a course module id. Its permitted values depend on which section is being
 * edited, which the base format API does not pass to section_format_options(), so the format
 * validates the value itself and keeps it correct when a section is duplicated.
 *
 * @package    format_simple
 * @copyright  2026 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_simple
 */
final class primarycontent_test extends \advanced_testcase {
    /**
     * Read the primarycontent value stored for a section.
     *
     * @param int $courseid The course to look in.
     * @param int $sectionid The course_sections id.
     * @return int The stored course module id, 0 when unset or absent.
     */
    protected function stored_value(int $courseid, int $sectionid): int {
        global $DB;

        return (int) $DB->get_field('course_format_options', 'value', [
            'courseid' => $courseid,
            'format' => 'simple',
            'sectionid' => $sectionid,
            'name' => 'primarycontent',
        ]);
    }

    /**
     * Build a course with a section 1 containing the requested page activities.
     *
     * @param string[] $pagenames Names of the pages to create in section 1.
     * @return array{0: \stdClass, 1: \stdClass, 2: array<string, int>} Course, section, name => cmid.
     */
    protected function make_course(array $pagenames = ['First', 'Second', 'Third']): array {
        global $DB;

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );

        $cmids = [];
        foreach ($pagenames as $name) {
            $mod = $generator->create_module(
                'page',
                ['course' => $course->id, 'section' => 1, 'name' => $name]
            );
            $cmids[$name] = (int) $mod->cmid;
        }

        $section = $DB->get_record(
            'course_sections',
            ['course' => $course->id, 'section' => 1],
            '*',
            MUST_EXIST
        );

        return [$course, $section, $cmids];
    }

    // Group A: regression. Behaviour that must not change.

    /**
     * Course level options are still validated by the parent, untouched.
     */
    public function test_course_format_options_still_validated_by_parent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course] = $this->make_course([]);
        $format = course_get_format($course->id);

        // A valid select value is accepted.
        $format->update_course_format_options(['id' => $course->id, 'hiddensections' => 0]);
        $this->assertEquals(0, $format->get_format_options()['hiddensections']);

        // A value outside the select's choices is still rejected by the parent.
        $format->update_course_format_options(['id' => $course->id, 'hiddensections' => 99]);
        $this->assertEquals(
            0,
            $format->get_format_options()['hiddensections'],
            'Parent validation of course level options must be left alone.'
        );
    }

    /**
     * The other section option is unaffected by the primarycontent handling.
     */
    public function test_learningoutcomes_option_unaffected(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $format = course_get_format($course->id);

        $format->update_section_format_options([
            'id' => $section->id,
            'learningoutcomes' => "Outcome one\nOutcome two",
        ]);

        // Note that get_format_options() reads a bare int as a section number, not a section id.
        $this->assertEquals(
            "Outcome one\nOutcome two",
            $format->get_format_options(1)['learningoutcomes']
        );
    }

    /**
     * Saving both section options together leaves each one correct.
     */
    public function test_both_section_options_save_together(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section, $cmids] = $this->make_course();
        $format = course_get_format($course->id);

        $format->update_section_format_options([
            'id' => $section->id,
            'learningoutcomes' => 'An outcome',
            'primarycontent' => $cmids['Third'],
        ]);

        $options = $format->get_format_options(1);
        $this->assertEquals('An outcome', $options['learningoutcomes']);
        $this->assertEquals($cmids['Third'], (int) $options['primarycontent']);
    }

    // Group B: validation correctness.

    /**
     * A valid selection is stored even with no request parameters present.
     */
    public function test_valid_selection_stored_without_request_params(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section, $cmids] = $this->make_course();

        course_get_format($course->id)->update_section_format_options([
            'id' => $section->id,
            'primarycontent' => $cmids['Third'],
        ]);

        $this->assertEquals($cmids['Third'], $this->stored_value($course->id, $section->id));
    }

    /**
     * A module from another section of the same course is rejected.
     */
    public function test_cmid_from_another_section_rejected(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $other = $this->getDataGenerator()->create_module(
            'page',
            ['course' => $course->id, 'section' => 2, 'name' => 'Elsewhere']
        );

        course_get_format($course->id)->update_section_format_options([
            'id' => $section->id,
            'primarycontent' => (int) $other->cmid,
        ]);

        $this->assertEquals(
            0,
            $this->stored_value($course->id, $section->id),
            'A module from a different section must not be accepted.'
        );
    }

    /**
     * A module from a different course is rejected.
     */
    public function test_cmid_from_another_course_rejected(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        [, , $othercmids] = $this->make_course(['Foreign']);

        course_get_format($course->id)->update_section_format_options([
            'id' => $section->id,
            'primarycontent' => $othercmids['Foreign'],
        ]);

        $this->assertEquals(
            0,
            $this->stored_value($course->id, $section->id),
            'A module from another course must not be accepted.'
        );
    }

    /**
     * A module outside the learning zone cannot be the primary content.
     */
    public function test_non_learning_zone_module_rejected(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $forum = $this->getDataGenerator()->create_module(
            'forum',
            ['course' => $course->id, 'section' => 1, 'name' => 'Discussion']
        );

        // Confirm the premise: a forum is not learning zone content.
        $cm = get_fast_modinfo($course->id)->get_cm((int) $forum->cmid);
        $this->assertEquals('activities', \format_simple::get_activity_zone($cm));

        course_get_format($course->id)->update_section_format_options([
            'id' => $section->id,
            'primarycontent' => (int) $forum->cmid,
        ]);

        $this->assertEquals(0, $this->stored_value($course->id, $section->id));
    }

    /**
     * Zero means automatic and is always accepted.
     */
    public function test_zero_is_accepted_as_automatic(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section, $cmids] = $this->make_course();
        $format = course_get_format($course->id);

        $format->update_section_format_options(['id' => $section->id, 'primarycontent' => $cmids['Third']]);
        $this->assertEquals($cmids['Third'], $this->stored_value($course->id, $section->id));

        $format->update_section_format_options(['id' => $section->id, 'primarycontent' => 0]);
        $this->assertEquals(
            0,
            $this->stored_value($course->id, $section->id),
            'Selecting automatic must clear the stored module id.'
        );
    }

    /**
     * A course module id that does not exist is rejected rather than throwing.
     */
    public function test_nonexistent_cmid_rejected(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();
        $bogus = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_modules}') + 1000;

        course_get_format($course->id)->update_section_format_options([
            'id' => $section->id,
            'primarycontent' => $bogus,
        ]);

        $this->assertEquals(0, $this->stored_value($course->id, $section->id));
    }

    // Group C: form choices.

    /**
     * The select offers the section's learning activities once the section is known.
     */
    public function test_form_choices_come_from_the_edited_section(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section, $cmids] = $this->make_course();
        $sectioninfo = get_fast_modinfo($course->id)->get_section_info_by_id($section->id);

        $format = course_get_format($course->id);
        $format->editsection_form(new \moodle_url('/course/editsection.php'), [
            'cs' => $sectioninfo,
            'editoroptions' => ['maxfiles' => 0],
            'defaultsectionname' => 'Section 1',
            'showonly' => '',
        ]);

        $choices = $format->section_format_options(true)['primarycontent']['element_attributes'][0];

        $this->assertArrayHasKey(0, $choices, 'Automatic must always be offered.');
        foreach (['First', 'Second', 'Third'] as $name) {
            $this->assertArrayHasKey($cmids[$name], $choices);
            $this->assertEquals($name, $choices[$cmids[$name]]);
        }
    }

    /**
     * With no section in context only the automatic choice is offered.
     */
    public function test_form_choices_without_section_context(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course] = $this->make_course();

        $choices = course_get_format($course->id)
            ->section_format_options(true)['primarycontent']['element_attributes'][0];

        $this->assertEquals([0], array_keys($choices));
    }

    /**
     * Request parameters no longer influence the choices offered.
     */
    public function test_form_choices_ignore_request_parameters(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section, $cmids] = $this->make_course();

        // Previously this shaped the choice list. It must now be irrelevant.
        $_GET['sectionid'] = $section->id;
        $_GET['id'] = $section->id;

        try {
            $choices = course_get_format($course->id)
                ->section_format_options(true)['primarycontent']['element_attributes'][0];
            $this->assertEquals(
                [0],
                array_keys($choices),
                'Choices must come from the edited section, not the request.'
            );

            // And with the section supplied properly, the request params are still irrelevant.
            $sectioninfo = get_fast_modinfo($course->id)->get_section_info_by_id($section->id);
            $format = course_get_format($course->id);
            $format->editsection_form(new \moodle_url('/course/editsection.php'), [
                'cs' => $sectioninfo,
                'editoroptions' => ['maxfiles' => 0],
                'defaultsectionname' => 'Section 1',
                'showonly' => '',
            ]);
            $choices = $format->section_format_options(true)['primarycontent']['element_attributes'][0];
            $this->assertArrayHasKey($cmids['Third'], $choices);
        } finally {
            unset($_GET['sectionid'], $_GET['id']);
        }
    }

    // Group D: section duplication.

    /**
     * Duplicating a section re-points the selection at the duplicated activity.
     */
    public function test_duplicate_section_remaps_primarycontent(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section, $cmids] = $this->make_course();
        $format = course_get_format($course->id);
        $format->update_section_format_options([
            'id' => $section->id,
            'primarycontent' => $cmids['Third'],
        ]);

        $sectioninfo = get_fast_modinfo($course->id)->get_section_info_by_id($section->id);
        $newsection = $format->duplicate_section($sectioninfo);

        $newvalue = $this->stored_value($course->id, $newsection->id);
        $this->assertNotEquals(0, $newvalue, 'The duplicated section lost its selection.');
        $this->assertNotEquals(
            $cmids['Third'],
            $newvalue,
            'The duplicate still points at the original section\'s activity.'
        );

        // It must be an activity inside the duplicated section, and the same one by name.
        $cm = get_fast_modinfo($course->id)->get_cm($newvalue);
        $this->assertEquals($newsection->id, $cm->section);
        $this->assertEquals('Third', $cm->name);
    }

    /**
     * A section set to automatic duplicates as automatic.
     */
    public function test_duplicate_section_keeps_automatic(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course, $section] = $this->make_course();

        $sectioninfo = get_fast_modinfo($course->id)->get_section_info_by_id($section->id);
        $newsection = course_get_format($course->id)->duplicate_section($sectioninfo);

        $this->assertEquals(0, $this->stored_value($course->id, $newsection->id));
    }

    /**
     * Duplicating an empty section does not error.
     */
    public function test_duplicate_empty_section(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        [$course] = $this->make_course();
        $empty = $DB->get_record(
            'course_sections',
            ['course' => $course->id, 'section' => 2],
            '*',
            MUST_EXIST
        );

        $sectioninfo = get_fast_modinfo($course->id)->get_section_info_by_id($empty->id);
        $newsection = course_get_format($course->id)->duplicate_section($sectioninfo);

        $this->assertEquals(0, $this->stored_value($course->id, $newsection->id));
    }
}
