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
 * Unit tests for the Simple course format.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_simple
 */
final class format_simple_test extends \advanced_testcase {

    /**
     * Test that the format declares section support.
     */
    public function test_uses_sections(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $format = course_get_format($course);

        $this->assertTrue($format->uses_sections());
    }

    /**
     * Test that the built-in course index is disabled.
     */
    public function test_uses_course_index(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $format = course_get_format($course);

        $this->assertFalse($format->uses_course_index());
    }

    /**
     * Test that indentation is disabled.
     */
    public function test_uses_indentation(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $format = course_get_format($course);

        $this->assertFalse($format->uses_indentation());
    }

    /**
     * Test that the format supports reactive editor components.
     */
    public function test_supports_components(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $format = course_get_format($course);

        $this->assertTrue($format->supports_components());
    }

    /**
     * Test that AJAX support is enabled.
     */
    public function test_supports_ajax(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $format = course_get_format($course);

        $ajaxsupport = $format->supports_ajax();
        $this->assertTrue($ajaxsupport->capable);
    }

    /**
     * Test that sections can be deleted.
     */
    public function test_can_delete_section(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);

        $this->assertTrue($format->can_delete_section(1));
    }

    /**
     * Test default section name for section 0.
     */
    public function test_get_default_section_name_section0(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);

        $section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
        $this->assertEquals(
            get_string('section0name', 'format_simple'),
            $format->get_default_section_name($section0)
        );
    }

    /**
     * Test default section name for numbered sections.
     */
    public function test_get_default_section_name_numbered(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);

        $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $expected = get_string('sectionname', 'format_simple') . ' 1';
        $this->assertEquals($expected, $format->get_default_section_name($section1));
    }

    /**
     * Test get_section_name with default (no custom name set).
     */
    public function test_get_section_name_default(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);

        $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
        foreach ($sections as $section) {
            $this->assertEquals(
                $format->get_default_section_name($section),
                $format->get_section_name($section)
            );
        }
    }

    /**
     * Test get_section_name with a custom name.
     */
    public function test_get_section_name_custom(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);

        $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $DB->set_field('course_sections', 'name', 'My Custom Unit', ['id' => $section1->id]);

        // Re-fetch format to clear caches.
        $format = course_get_format($course);
        $this->assertEquals('My Custom Unit', $format->get_section_name($section1->section));
    }

    /**
     * Test get_view_url returns URL with section anchor.
     */
    public function test_get_view_url_with_section(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);

        $url = $format->get_view_url(2);
        $this->assertStringContainsString('/course/view.php', $url->out(false));
        $this->assertStringContainsString('id=' . $course->id, $url->out(false));
        $this->assertStringContainsString('#section-2', $url->out(false));
    }

    /**
     * Test get_view_url with section object.
     */
    public function test_get_view_url_with_section_object(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $url = $format->get_view_url($section);
        $this->assertStringContainsString('#section-1', $url->out(false));
    }

    /**
     * Test course format options are declared correctly.
     */
    public function test_course_format_options(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $format = course_get_format($course);

        $options = $format->course_format_options();

        $this->assertArrayHasKey('hiddensections', $options);
        $this->assertArrayHasKey('showsection0banner', $options);
        $this->assertArrayHasKey('section0modal', $options);

        $this->assertEquals(1, $options['hiddensections']['default']);
        $this->assertEquals(1, $options['showsection0banner']['default']);
        $this->assertEquals(0, $options['section0modal']['default']);

        $this->assertEquals(PARAM_INT, $options['hiddensections']['type']);
        $this->assertEquals(PARAM_INT, $options['showsection0banner']['type']);
        $this->assertEquals(PARAM_INT, $options['section0modal']['type']);
    }

    /**
     * Test course format options for edit form include labels.
     */
    public function test_course_format_options_for_edit_form(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $format = course_get_format($course);

        $options = $format->course_format_options(true);

        $this->assertArrayHasKey('label', $options['hiddensections']);
        $this->assertArrayHasKey('element_type', $options['hiddensections']);
        $this->assertEquals('select', $options['hiddensections']['element_type']);

        $this->assertArrayHasKey('label', $options['showsection0banner']);
        $this->assertEquals('select', $options['showsection0banner']['element_type']);

        $this->assertArrayHasKey('label', $options['section0modal']);
        $this->assertEquals('select', $options['section0modal']['element_type']);
    }

    /**
     * Test section format options include learning outcomes.
     */
    public function test_section_format_options(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $format = course_get_format($course);

        $options = $format->section_format_options();
        $this->assertArrayHasKey('learningoutcomes', $options);
        $this->assertEquals('', $options['learningoutcomes']['default']);
        $this->assertEquals(PARAM_TEXT, $options['learningoutcomes']['type']);
    }

    /**
     * Test section format options for edit form include textarea element.
     */
    public function test_section_format_options_for_edit_form(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $format = course_get_format($course);

        $options = $format->section_format_options(true);
        $this->assertArrayHasKey('label', $options['learningoutcomes']);
        $this->assertEquals('textarea', $options['learningoutcomes']['element_type']);
    }

    /**
     * Test get_config_for_external returns an empty object.
     */
    public function test_get_config_for_external(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $format = course_get_format($course);

        $config = $format->get_config_for_external();
        $this->assertIsObject($config);
    }

    /**
     * Test activity zone categorisation for learning modules.
     */
    public function test_get_activity_zone_learning(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);
        $learningmods = ['page', 'h5pactivity', 'scorm', 'lti', 'lesson', 'label'];

        foreach ($learningmods as $modname) {
            // Skip modules that aren't installed.
            if (!\core_component::get_component_directory('mod_' . $modname)) {
                continue;
            }
            $activity = $this->getDataGenerator()->create_module($modname, ['course' => $course->id]);
            $modinfo = get_fast_modinfo($course);
            $cm = $modinfo->get_cm($activity->cmid);
            $this->assertEquals(
                'learning',
                \format_simple::get_activity_zone($cm),
                "Expected 'learning' zone for mod_{$modname}"
            );
            // Re-fetch modinfo after each to avoid cache issues.
            rebuild_course_cache($course->id);
        }
    }

    /**
     * Test activity zone categorisation for resource modules.
     */
    public function test_get_activity_zone_resources(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);

        // Test URL (non-video).
        $url = $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'externalurl' => 'https://example.com/article',
        ]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($url->cmid);
        $this->assertEquals('resources', \format_simple::get_activity_zone($cm));

        // Test book.
        rebuild_course_cache($course->id);
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($book->cmid);
        $this->assertEquals('resources', \format_simple::get_activity_zone($cm));

        // Test folder.
        rebuild_course_cache($course->id);
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($folder->cmid);
        $this->assertEquals('resources', \format_simple::get_activity_zone($cm));
    }

    /**
     * Test activity zone categorisation for activity modules.
     */
    public function test_get_activity_zone_activities(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);

        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($assign->cmid);
        $this->assertEquals('activities', \format_simple::get_activity_zone($cm));

        rebuild_course_cache($course->id);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($quiz->cmid);
        $this->assertEquals('activities', \format_simple::get_activity_zone($cm));

        rebuild_course_cache($course->id);
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($forum->cmid);
        $this->assertEquals('activities', \format_simple::get_activity_zone($cm));
    }

    /**
     * Test that a YouTube URL module is categorised as learning, not resources.
     */
    public function test_get_activity_zone_video_url(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);

        $youtube = $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'externalurl' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($youtube->cmid);
        $this->assertEquals('learning', \format_simple::get_activity_zone($cm));

        // Test youtu.be short URL.
        rebuild_course_cache($course->id);
        $youtubeshort = $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'externalurl' => 'https://youtu.be/dQw4w9WgXcQ',
        ]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($youtubeshort->cmid);
        $this->assertEquals('learning', \format_simple::get_activity_zone($cm));

        // Test Vimeo.
        rebuild_course_cache($course->id);
        $vimeo = $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'externalurl' => 'https://vimeo.com/123456',
        ]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($vimeo->cmid);
        $this->assertEquals('learning', \format_simple::get_activity_zone($cm));
    }

    /**
     * Test resource icon mapping for known module types.
     */
    public function test_get_resource_icon_known_types(): void {
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course(['format' => 'simple']);

        // URL module.
        $url = $this->getDataGenerator()->create_module('url', [
            'course' => $course->id,
            'externalurl' => 'https://example.com',
        ]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($url->cmid);
        $this->assertEquals('fa-link', \format_simple::get_resource_icon($cm));

        // Book module.
        rebuild_course_cache($course->id);
        $book = $this->getDataGenerator()->create_module('book', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($book->cmid);
        $this->assertEquals('fa-book', \format_simple::get_resource_icon($cm));

        // Folder module.
        rebuild_course_cache($course->id);
        $folder = $this->getDataGenerator()->create_module('folder', ['course' => $course->id]);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($folder->cmid);
        $this->assertEquals('fa-folder', \format_simple::get_resource_icon($cm));
    }

    /**
     * Test stealth module visibility rules.
     */
    public function test_allow_stealth_module_visibility(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course(
            ['format' => 'simple', 'numsections' => 3],
            ['createsections' => true]
        );
        $format = course_get_format($course);

        // Section 0 always allows stealth (regardless of visibility).
        $section0 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0]);
        $this->assertTrue($format->allow_stealth_module_visibility(null, $section0));

        // Visible section allows stealth.
        $section1 = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 1]);
        $this->assertTrue($format->allow_stealth_module_visibility(null, $section1));

        // Hidden section does not allow stealth.
        $DB->set_field('course_sections', 'visible', 0, ['id' => $section1->id]);
        $section1 = $DB->get_record('course_sections', ['id' => $section1->id]);
        $this->assertFalse($format->allow_stealth_module_visibility(null, $section1));
    }

    /**
     * Test section progress calculation with no completion tracking.
     */
    public function test_get_section_progress_no_completion(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'simple',
            'numsections' => 1,
            'enablecompletion' => 0,
        ], ['createsections' => true]);

        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(1);

        $progress = \format_simple::get_section_progress($section, $course);

        $this->assertEquals('notstarted', $progress->status);
        $this->assertEquals(0, $progress->percentage);
        $this->assertEquals(0, $progress->completed);
        $this->assertEquals(0, $progress->total);
    }

    /**
     * Test section progress calculation with completion enabled but no tracked activities.
     */
    public function test_get_section_progress_no_tracked_activities(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'simple',
            'numsections' => 1,
            'enablecompletion' => 1,
        ], ['createsections' => true]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        // Add an activity without completion tracking.
        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_NONE,
        ]);

        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(1);

        $progress = \format_simple::get_section_progress($section, $course);
        $this->assertEquals(0, $progress->total);
        $this->assertEquals('notstarted', $progress->status);
    }

    /**
     * Test section progress calculation with tracked activities.
     */
    public function test_get_section_progress_with_completion(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'simple',
            'numsections' => 1,
            'enablecompletion' => 1,
        ], ['createsections' => true]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        // Add two activities with manual completion.
        $page1 = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        $page2 = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        // Complete one activity.
        $completion = new \completion_info($course);
        $modinfo = get_fast_modinfo($course);
        $cm1 = $modinfo->get_cm($page1->cmid);
        $completion->update_state($cm1, COMPLETION_COMPLETE);

        // Re-fetch modinfo after completion state change.
        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(1);
        $progress = \format_simple::get_section_progress($section, $course);

        $this->assertEquals(2, $progress->total);
        $this->assertEquals(1, $progress->completed);
        $this->assertEquals(50, $progress->percentage);
        $this->assertEquals('inprogress', $progress->status);
    }

    /**
     * Test section progress at 100% shows complete status.
     */
    public function test_get_section_progress_complete(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course([
            'format' => 'simple',
            'numsections' => 1,
            'enablecompletion' => 1,
        ], ['createsections' => true]);

        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $page = $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);

        $completion = new \completion_info($course);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($page->cmid);
        $completion->update_state($cm, COMPLETION_COMPLETE);

        $modinfo = get_fast_modinfo($course);
        $section = $modinfo->get_section_info(1);
        $progress = \format_simple::get_section_progress($section, $course);

        $this->assertEquals(1, $progress->total);
        $this->assertEquals(1, $progress->completed);
        $this->assertEquals(100, $progress->percentage);
        $this->assertEquals('complete', $progress->status);
    }

    /**
     * Test that all required language strings are defined.
     */
    public function test_language_strings_exist(): void {
        $requiredstrings = [
            'pluginname',
            'sectionname',
            'section0name',
            'zone_learningcontent',
            'zone_resources',
            'zone_activities',
            'learningoutcomes',
            'showsection0banner',
            'section0modal',
            'privacy:metadata',
        ];

        foreach ($requiredstrings as $stringkey) {
            $value = get_string($stringkey, 'format_simple');
            $this->assertNotEmpty($value, "Language string '{$stringkey}' should be defined");
        }
    }
}
