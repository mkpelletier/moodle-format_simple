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

use backup;
use backup_controller;
use restore_controller;
use restore_dbops;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Tests that the 'primarycontent' section format option survives backup and restore.
 *
 * Core copies section format option values verbatim, so the course module id this option holds
 * has to be translated through the course_module mapping by the format's own restore plugin.
 *
 * @package    format_simple
 * @copyright  2026 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \restore_format_simple_plugin
 * @covers     \backup_format_simple_plugin
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Store a primarycontent selection for a section.
     *
     * Written straight to the table because validate_format_options() rebuilds the select's
     * permitted values from section_format_options(true), whose choices come from request
     * parameters that do not exist under CLI. The resulting row is identical to the one a
     * successful save on /course/editsection.php produces.
     *
     * @param int $courseid The course the section belongs to.
     * @param int $sectionid The course_sections id.
     * @param int $cmid The course module id to select.
     */
    protected function set_primarycontent(int $courseid, int $sectionid, int $cmid): void {
        global $DB;

        $params = [
            'courseid' => $courseid,
            'format' => 'simple',
            'sectionid' => $sectionid,
            'name' => 'primarycontent',
        ];
        if ($existing = $DB->get_record('course_format_options', $params, 'id')) {
            $DB->set_field('course_format_options', 'value', $cmid, ['id' => $existing->id]);
        } else {
            $DB->insert_record('course_format_options', (object) ($params + ['value' => $cmid]));
        }
    }

    /**
     * Read the primarycontent selection stored for a section number.
     *
     * @param int $courseid The course to look in.
     * @param int $sectionnum The section number.
     * @return int The selected course module id, 0 when unset.
     */
    protected function get_primarycontent(int $courseid, int $sectionnum): int {
        global $DB;

        $sectionid = $DB->get_field(
            'course_sections',
            'id',
            ['course' => $courseid, 'section' => $sectionnum],
            MUST_EXIST
        );
        return (int) $DB->get_field('course_format_options', 'value', [
            'courseid' => $courseid,
            'format' => 'simple',
            'sectionid' => $sectionid,
            'name' => 'primarycontent',
        ]);
    }

    /**
     * Back a course up and restore it, returning the destination course id.
     *
     * @param \stdClass $srccourse The course to back up.
     * @param \stdClass|null $dstcourse Destination course, or null to create a new one.
     * @param int $target One of the backup::TARGET_* constants.
     * @return int The destination course id.
     */
    protected function backup_and_restore(
        $srccourse,
        $dstcourse = null,
        $target = backup::TARGET_NEW_COURSE
    ): int {
        global $USER, $CFG;

        // Turn off file logging, otherwise the file cannot be deleted on Windows.
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $srccourse->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        if ($dstcourse !== null) {
            $newcourseid = $dstcourse->id;
        } else {
            $newcourseid = restore_dbops::create_new_course(
                $srccourse->fullname,
                $srccourse->shortname . '_2',
                $srccourse->category
            );
        }

        $rc = new restore_controller(
            $backupid,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            $target
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }

    /**
     * The selected activity is still the selected activity after restore into a new course.
     */
    public function test_primarycontent_is_remapped_on_restore(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );

        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'First page']);
        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Second page']);
        $third = $generator->create_module(
            'page',
            ['course' => $course->id, 'section' => 1, 'name' => 'Third page']
        );

        // Deliberately not the first activity, so falling back to the default would be visible.
        $sectionid = $DB->get_field(
            'course_sections',
            'id',
            ['course' => $course->id, 'section' => 1],
            MUST_EXIST
        );
        $this->set_primarycontent($course->id, $sectionid, (int) $third->cmid);

        $newcourseid = $this->backup_and_restore($course);

        $restoredvalue = $this->get_primarycontent($newcourseid, 1);
        $this->assertNotEquals(0, $restoredvalue, 'The selection was lost during restore.');
        $this->assertNotEquals(
            (int) $third->cmid,
            $restoredvalue,
            'The selection still points at the source course module.'
        );

        // It must resolve to a module in the new course, and be the same activity as before.
        $modinfo = get_fast_modinfo($newcourseid);
        $cm = $modinfo->get_cm($restoredvalue);
        $this->assertEquals($newcourseid, $cm->course);
        $this->assertEquals('Third page', $cm->name);
    }

    /**
     * An existing selection in the destination course is not clobbered by a merging restore.
     */
    public function test_primarycontent_not_overwritten_when_merging(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $source = $generator->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $sourcepage = $generator->create_module(
            'page',
            ['course' => $source->id, 'section' => 1, 'name' => 'Source page']
        );
        $sourcesectionid = $DB->get_field(
            'course_sections',
            'id',
            ['course' => $source->id, 'section' => 1],
            MUST_EXIST
        );
        $this->set_primarycontent($source->id, $sourcesectionid, (int) $sourcepage->cmid);

        $target = $generator->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $targetpage = $generator->create_module(
            'page',
            ['course' => $target->id, 'section' => 1, 'name' => 'Target page']
        );
        $targetsectionid = $DB->get_field(
            'course_sections',
            'id',
            ['course' => $target->id, 'section' => 1],
            MUST_EXIST
        );
        $this->set_primarycontent($target->id, $targetsectionid, (int) $targetpage->cmid);

        $this->backup_and_restore($source, $target, backup::TARGET_EXISTING_ADDING);

        $this->assertEquals(
            (int) $targetpage->cmid,
            $this->get_primarycontent($target->id, 1),
            'A merging restore must leave the destination selection alone.'
        );
    }

    /**
     * Restoring into a course using a different format does not carry the option or error.
     */
    public function test_restore_into_other_format_is_harmless(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $source = $generator->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $page = $generator->create_module(
            'page',
            ['course' => $source->id, 'section' => 1, 'name' => 'Source page']
        );
        $sourcesectionid = $DB->get_field(
            'course_sections',
            'id',
            ['course' => $source->id, 'section' => 1],
            MUST_EXIST
        );
        $this->set_primarycontent($source->id, $sourcesectionid, (int) $page->cmid);

        $target = $generator->create_course(
            ['format' => 'topics', 'numsections' => 3],
            ['createsections' => true]
        );

        $this->backup_and_restore($source, $target, backup::TARGET_EXISTING_ADDING);

        $this->assertEquals(0, $DB->count_records(
            'course_format_options',
            ['courseid' => $target->id, 'name' => 'primarycontent']
        ));
    }

    /**
     * A selection pointing at an activity excluded from the backup falls back to auto.
     */
    public function test_primarycontent_falls_back_when_module_missing(): void {
        global $DB;

        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $generator->create_module('page', ['course' => $course->id, 'section' => 1, 'name' => 'Kept page']);

        // Point the option at a course module id that is not part of this course at all.
        $sectionid = $DB->get_field(
            'course_sections',
            'id',
            ['course' => $course->id, 'section' => 1],
            MUST_EXIST
        );
        $bogus = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_modules}') + 1000;
        $this->set_primarycontent($course->id, $sectionid, $bogus);

        $newcourseid = $this->backup_and_restore($course);

        $this->assertEquals(
            0,
            $this->get_primarycontent($newcourseid, 1),
            'An unresolvable selection must fall back to auto rather than a stale id.'
        );
    }
}
