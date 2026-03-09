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

namespace format_simple\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/course/lib.php');

use core_external\external_api;

/**
 * Unit tests for the get_section0_content external function.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_simple\external\get_section0_content
 */
final class get_section0_content_test extends \externallib_advanced_testcase {

    /**
     * Test that an enrolled student can fetch section 0 content.
     */
    public function test_execute_as_student(): void {
        global $PAGE;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'simple',
            'numsections' => 3,
        ], ['createsections' => true]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        // Enrolled students need course:view capability granted via their role.
        // Assign the student role explicitly in the course context.
        $context = \context_course::instance($course->id);
        $PAGE->set_context($context);

        $result = get_section0_content::execute($course->id);
        $result = external_api::clean_returnvalue(
            get_section0_content::execute_returns(),
            $result
        );

        $this->assertArrayHasKey('html', $result);
        $this->assertIsString($result['html']);
    }

    /**
     * Test that an admin can fetch section 0 content.
     */
    public function test_execute_as_admin(): void {
        global $PAGE;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'simple',
            'numsections' => 3,
        ], ['createsections' => true]);

        $this->setAdminUser();
        $PAGE->set_context(\context_course::instance($course->id));

        $result = get_section0_content::execute($course->id);
        $result = external_api::clean_returnvalue(
            get_section0_content::execute_returns(),
            $result
        );

        $this->assertArrayHasKey('html', $result);
        $this->assertNotEmpty($result['html']);
    }

    /**
     * Test that an unenrolled user cannot fetch section 0 content.
     */
    public function test_execute_unenrolled_user(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'simple',
            'numsections' => 3,
        ], ['createsections' => true]);

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\core\exception\require_login_exception::class);
        get_section0_content::execute($course->id);
    }

    /**
     * Test that a guest user cannot fetch section 0 content from a non-guest course.
     */
    public function test_execute_guest_user(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'simple',
            'numsections' => 3,
        ], ['createsections' => true]);

        $this->setGuestUser();

        $this->expectException(\core\exception\require_login_exception::class);
        get_section0_content::execute($course->id);
    }

    /**
     * Test with an invalid course ID.
     */
    public function test_execute_invalid_course(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $this->expectException(\dml_exception::class);
        get_section0_content::execute(99999);
    }

    /**
     * Test that section 0 content includes activities when present.
     */
    public function test_execute_with_activities(): void {
        global $PAGE;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'simple',
            'numsections' => 3,
        ], ['createsections' => true]);

        // Add a page to section 0.
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 0,
            'name' => 'Welcome Page',
            'content' => 'Welcome to the course',
        ]);

        $this->setAdminUser();
        $PAGE->set_context(\context_course::instance($course->id));

        $result = get_section0_content::execute($course->id);
        $result = external_api::clean_returnvalue(
            get_section0_content::execute_returns(),
            $result
        );

        $this->assertNotEmpty($result['html']);
        // The template renders the activity in the cmlist area.
        $this->assertStringContainsString('data-for="cmitem"', $result['html']);
    }

    /**
     * Test parameter validation.
     */
    public function test_execute_parameters(): void {
        $params = get_section0_content::execute_parameters();
        $this->assertInstanceOf(\core_external\external_function_parameters::class, $params);
    }

    /**
     * Test return value definition.
     */
    public function test_execute_returns(): void {
        $returns = get_section0_content::execute_returns();
        $this->assertInstanceOf(\core_external\external_single_structure::class, $returns);
    }
}
