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
require_once($CFG->libdir . '/completionlib.php');

/**
 * Tests for the optional list of activities under the active unit in the index.
 *
 * The list must show exactly the activities the unit's progress ring is counting, so that the
 * checklist and the percentage above it can never disagree.
 *
 * @package    format_simple
 * @copyright  2026 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_simple\output\courseformat\content
 */
final class index_activities_test extends \advanced_testcase {
    /**
     * Build a completion-enabled course with a student enrolled.
     *
     * @param int $showindex Whether the index should list activities.
     * @return array{0: \stdClass, 1: \stdClass} The course and the student.
     */
    protected function make_course(int $showindex = 1): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            ['format' => 'simple', 'numsections' => 2, 'enablecompletion' => 1],
            ['createsections' => true]
        );
        $this->set_showindexactivities($course, $showindex);

        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        return [$course, $student];
    }

    /**
     * Turn the index activity list on or off for a course.
     *
     * Written straight to the table: creating the course already stored the option's default,
     * so this updates that row rather than adding another.
     *
     * @param \stdClass $course The course.
     * @param int $value 1 to list activities, 0 to leave the index as a list of units.
     */
    protected function set_showindexactivities(\stdClass $course, int $value): void {
        global $DB;

        $params = [
            'courseid' => $course->id,
            'format' => 'simple',
            'sectionid' => 0,
            'name' => 'showindexactivities',
        ];
        if ($existing = $DB->get_record('course_format_options', $params, 'id')) {
            $DB->set_field('course_format_options', 'value', $value, ['id' => $existing->id]);
        } else {
            $DB->insert_record('course_format_options', (object) ($params + ['value' => $value]));
        }
        rebuild_course_cache($course->id, true);
    }

    /**
     * Add a forum with the given completion tracking.
     *
     * @param \stdClass $course The course.
     * @param string $name The activity name.
     * @param int $completion One of the COMPLETION_TRACKING_* constants.
     * @param int $sectionnum The section to put it in.
     * @return \stdClass The created module.
     */
    protected function make_forum(
        \stdClass $course,
        string $name,
        int $completion,
        int $sectionnum = 1
    ): \stdClass {
        return $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'section' => $sectionnum,
            'name' => $name,
            'completion' => $completion,
        ]);
    }

    /**
     * Export the course content as the given user.
     *
     * @param \stdClass $course The course.
     * @param \stdClass $user The user to export as.
     * @return \stdClass The exported data.
     */
    protected function export_content(\stdClass $course, \stdClass $user): \stdClass {
        global $PAGE;

        $this->setUser($user);
        $PAGE->set_url('/course/view.php', ['id' => $course->id]);

        $format = course_get_format($course->id);
        $renderer = $format->get_renderer($PAGE);
        $outputclass = $format->get_output_classname('content');
        $widget = new $outputclass($format);

        return $widget->export_for_template($renderer);
    }

    /**
     * Find a unit's nav item by section number.
     *
     * @param \stdClass $data The exported content data.
     * @param int $num The section number.
     * @return \stdClass|null The nav item.
     */
    protected function nav_item(\stdClass $data, int $num): ?\stdClass {
        foreach ($data->navitems as $item) {
            if ((int) $item->num === $num) {
                return $item;
            }
        }
        return null;
    }

    /**
     * With the setting off, the index stays a list of units.
     */
    public function test_no_activities_listed_when_setting_is_off(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course(0);
        $this->make_forum($course, 'Tracked', COMPLETION_TRACKING_MANUAL);

        $item = $this->nav_item($this->export_content($course, $student), 1);

        $this->assertFalse($item->hascompletionitems);
        $this->assertSame([], $item->completionitems);
    }

    /**
     * The setting defaults to off, so existing courses are unchanged.
     */
    public function test_setting_defaults_to_off(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'simple', 'numsections' => 2, 'enablecompletion' => 1],
            ['createsections' => true]
        );

        $options = course_get_format($course->id)->get_format_options();

        $this->assertArrayHasKey('showindexactivities', $options);
        $this->assertEquals(0, $options['showindexactivities']);
    }

    /**
     * With the setting on, tracked activities are listed and untracked ones are not.
     */
    public function test_only_tracked_activities_are_listed(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $this->make_forum($course, 'Tracked', COMPLETION_TRACKING_MANUAL);
        $this->make_forum($course, 'Untracked', COMPLETION_TRACKING_NONE);

        $item = $this->nav_item($this->export_content($course, $student), 1);

        $this->assertTrue($item->hascompletionitems);
        $this->assertCount(1, $item->completionitems);
        $this->assertEquals('Tracked', $item->completionitems[0]->name);
    }

    /**
     * The list matches the progress ring, so the two can never disagree.
     */
    public function test_list_matches_the_progress_ring(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $this->make_forum($course, 'One', COMPLETION_TRACKING_MANUAL);
        $this->make_forum($course, 'Two', COMPLETION_TRACKING_MANUAL);
        $this->make_forum($course, 'Untracked', COMPLETION_TRACKING_NONE);

        $item = $this->nav_item($this->export_content($course, $student), 1);

        $this->assertEquals(
            $item->progress->total,
            count($item->completionitems),
            'The list and the percentage must count the same activities.'
        );
    }

    /**
     * Completing an activity is reflected in its index entry.
     */
    public function test_completion_state_is_reported(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $forum = $this->make_forum($course, 'Tracked', COMPLETION_TRACKING_MANUAL);

        $before = $this->nav_item($this->export_content($course, $student), 1);
        $this->assertFalse($before->completionitems[0]->iscomplete);
        $this->assertEquals('incomplete', $before->completionitems[0]->completionstate);

        $cm = get_fast_modinfo($course->id, $student->id)->get_cm((int) $forum->cmid);
        (new \completion_info(get_course($course->id)))
            ->update_state($cm, COMPLETION_COMPLETE, $student->id);

        $after = $this->nav_item($this->export_content($course, $student), 1);
        $this->assertTrue($after->completionitems[0]->iscomplete);
        $this->assertEquals('complete', $after->completionitems[0]->completionstate);
    }

    /**
     * A state stored as a string still counts as complete.
     *
     * Read back from the database the state arrives as a string rather than an integer, so a
     * strict comparison reported every activity incomplete on every page load. Manual completion
     * appeared to work only because the interface updates itself the moment it is clicked; the
     * state was wrong again as soon as the page was reloaded, and condition-based activities,
     * which have no such shortcut, were wrong throughout.
     */
    public function test_completion_state_stored_as_a_string_counts_as_complete(): void {
        global $DB;

        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $forum = $this->make_forum($course, 'Tracked', COMPLETION_TRACKING_MANUAL);

        $cm = get_fast_modinfo($course->id, $student->id)->get_cm((int) $forum->cmid);
        (new \completion_info(get_course($course->id)))
            ->update_state($cm, COMPLETION_COMPLETE, $student->id);

        // Reproduce what a page load sees: the value as the database hands it back.
        $stored = $DB->get_field('course_modules_completion', 'completionstate', [
            'coursemoduleid' => (int) $forum->cmid,
            'userid' => $student->id,
        ]);
        $this->assertEquals(COMPLETION_COMPLETE, $stored);

        $item = $this->nav_item($this->export_content($course, $student), 1);
        $this->assertTrue(
            $item->completionitems[0]->iscomplete,
            'A completion state must be read by value, not by type.'
        );
    }

    /**
     * A unit with nothing to complete says so, rather than reporting no progress for ever.
     *
     * The progress ring has no answer when there is nothing tracked, and an empty ring reads as
     * work still outstanding.
     */
    public function test_unit_without_tracking_is_distinguished(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $this->make_forum($course, 'Tracked', COMPLETION_TRACKING_MANUAL, 1);
        $this->make_forum($course, 'Untracked', COMPLETION_TRACKING_NONE, 2);

        $data = $this->export_content($course, $student);

        $this->assertTrue($this->nav_item($data, 1)->hastracking);
        $this->assertFalse($this->nav_item($data, 2)->hastracking);
    }

    /**
     * A teacher's chosen icon is carried to the index.
     */
    public function test_unit_icon_is_exported(): void {
        global $DB;

        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $this->make_forum($course, 'Tracked', COMPLETION_TRACKING_MANUAL, 1);
        $sectionid = $DB->get_field(
            'course_sections',
            'id',
            ['course' => $course->id, 'section' => 1],
            MUST_EXIST
        );
        $DB->insert_record('course_format_options', (object) [
            'courseid' => $course->id,
            'format' => 'simple',
            'sectionid' => $sectionid,
            'name' => 'indexicon',
            'value' => 'fa-scroll',
        ]);
        rebuild_course_cache($course->id, true);

        $item = $this->nav_item($this->export_content($course, $student), 1);

        $this->assertTrue($item->hasindexicon);
        $this->assertEquals('fa-scroll', $item->indexicon);
    }

    /**
     * Units without a chosen icon report none, and Course Info keeps its book.
     */
    public function test_icon_defaults(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $this->make_forum($course, 'Tracked', COMPLETION_TRACKING_MANUAL, 1);

        $data = $this->export_content($course, $student);

        $this->assertFalse($this->nav_item($data, 1)->hasindexicon);
        $this->assertSame('', $this->nav_item($data, 1)->indexicon);
        $this->assertEquals('fa-book', $data->section0nav->indexicon);
    }

    /**
     * An icon setting is reduced to something safe to put in a class attribute.
     *
     * @dataProvider icon_provider
     * @param string $raw What the teacher typed.
     * @param string $expected What should reach the page.
     */
    public function test_icon_is_cleaned(string $raw, string $expected): void {
        $this->assertEquals($expected, \format_simple::clean_index_icon($raw));
    }

    /**
     * Icon settings and what they should become.
     *
     * @return array[]
     */
    public static function icon_provider(): array {
        return [
            'already a full name' => ['fa-book', 'fa-book'],
            'bare name gains the prefix' => ['book', 'fa-book'],
            'case is levelled' => ['FA-Book', 'fa-book'],
            'each word is prefixed' => ['solid book', 'fa-solid fa-book'],
            'markup is stripped' => ['fa-book"><script>alert(1)</script>', 'fa-bookscriptalert1script'],
            'nothing usable gives nothing' => ['<>!', ''],
            'empty stays empty' => ['', ''],
            'spacing is tidied' => ['  fa-book   fa-pen ', 'fa-book fa-pen'],
        ];
    }

    /**
     * Each unit reports its own activities, so switching unit switches the list.
     */
    public function test_each_unit_reports_its_own_activities(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $this->make_forum($course, 'In unit one', COMPLETION_TRACKING_MANUAL, 1);
        $this->make_forum($course, 'In unit two', COMPLETION_TRACKING_MANUAL, 2);

        $data = $this->export_content($course, $student);

        $this->assertEquals('In unit one', $this->nav_item($data, 1)->completionitems[0]->name);
        $this->assertEquals('In unit two', $this->nav_item($data, 2)->completionitems[0]->name);
    }

    /**
     * How an activity is completed decides which indicator it gets.
     */
    public function test_manual_and_automatic_are_distinguished(): void {
        $this->resetAfterTest(true);

        [$course, $student] = $this->make_course();
        $this->make_forum($course, 'Manual', COMPLETION_TRACKING_MANUAL);
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'name' => 'Automatic',
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionview' => 1,
        ]);

        $items = [];
        foreach ($this->nav_item($this->export_content($course, $student), 1)->completionitems as $i) {
            $items[$i->name] = $i;
        }

        $this->assertTrue($items['Manual']->ismanualcompletion);
        $this->assertFalse($items['Automatic']->ismanualcompletion);
    }

    /**
     * Nothing is listed when the course does not track completion at all.
     */
    public function test_nothing_listed_without_course_completion(): void {
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            ['format' => 'simple', 'numsections' => 2, 'enablecompletion' => 0],
            ['createsections' => true]
        );
        $this->set_showindexactivities($course, 1);
        $student = $generator->create_user();
        $generator->enrol_user($student->id, $course->id, 'student');

        $item = $this->nav_item($this->export_content($course, $student), 1);

        $this->assertFalse($item->hascompletionitems);
    }
}
