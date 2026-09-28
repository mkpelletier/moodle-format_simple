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
require_once($CFG->dirroot . '/course/format/simple/lib.php');

/**
 * Tests for opening a Q&A forum's only discussion straight from the course page.
 *
 * @package    format_simple
 * @copyright  2026 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_simple::get_single_forum_discussion
 */
final class forum_direct_link_test extends \advanced_testcase {
    /** @var \stdClass The course. */
    protected \stdClass $course;

    /** @var \stdClass A teacher, who can see all groups. */
    protected \stdClass $teacher;

    /**
     * Create a course in this format with a teacher.
     *
     * @param int $skip The skipsingleforumindex course option.
     */
    protected function make_course(int $skip = 1): void {
        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course([
            'format' => 'simple',
            'numsections' => 1,
            'skipsingleforumindex' => $skip,
        ]);
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Create a forum in the course.
     *
     * @param string $type The forum type.
     * @param int $groupmode The activity's group mode.
     * @return \stdClass The forum record, with cmid.
     */
    protected function make_forum(string $type = 'qanda', int $groupmode = NOGROUPS): \stdClass {
        return $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'type' => $type,
            'groupmode' => $groupmode,
        ]);
    }

    /**
     * Post a discussion by the teacher.
     *
     * @param \stdClass $forum The forum.
     * @param array $extra Extra discussion fields, such as groupid or timestart.
     * @return int The discussion id.
     */
    protected function post(\stdClass $forum, array $extra = []): int {
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion([
            'course' => $this->course->id,
            'forum' => $forum->id,
            'userid' => $this->teacher->id,
        ] + $extra);
        return (int) $discussion->id;
    }

    /**
     * What the course page would open for a user.
     *
     * @param \stdClass $forum The forum.
     * @param \stdClass $user The user viewing the course page.
     * @return int|null The discussion id, or null for the forum's own page.
     */
    protected function direct_for(\stdClass $forum, \stdClass $user): ?int {
        $this->setUser($user);
        $cm = get_fast_modinfo($this->course, $user->id)->get_cm($forum->cmid);
        return \format_simple::get_single_forum_discussion($cm);
    }

    public function test_single_discussion_opens_directly(): void {
        $this->resetAfterTest();
        $this->make_course();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $forum = $this->make_forum();
        $discussionid = $this->post($forum);

        $this->assertSame($discussionid, $this->direct_for($forum, $student));
    }

    public function test_no_or_several_discussions_keep_the_list(): void {
        $this->resetAfterTest();
        $this->make_course();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $forum = $this->make_forum();

        $this->assertNull($this->direct_for($forum, $student));

        $this->post($forum);
        $this->post($forum);
        $this->assertNull($this->direct_for($forum, $student));
    }

    public function test_only_qanda_forums(): void {
        $this->resetAfterTest();
        $this->make_course();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $forum = $this->make_forum('general');
        $this->post($forum);

        $this->assertNull($this->direct_for($forum, $student));
    }

    public function test_teacher_keeps_the_list(): void {
        $this->resetAfterTest();
        $this->make_course();
        $forum = $this->make_forum();
        $this->post($forum);

        $this->assertNull($this->direct_for($forum, $this->teacher));
    }

    public function test_course_option_off_keeps_the_list(): void {
        $this->resetAfterTest();
        $this->make_course(0);
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $forum = $this->make_forum();
        $this->post($forum);

        $this->assertNull($this->direct_for($forum, $student));
    }

    public function test_separate_groups_copy_per_group(): void {
        $this->resetAfterTest();
        $this->make_course();
        $generator = $this->getDataGenerator();
        $group1 = $generator->create_group(['courseid' => $this->course->id]);
        $group2 = $generator->create_group(['courseid' => $this->course->id]);

        $ingroup1 = $generator->create_and_enrol($this->course, 'student');
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $ingroup1->id]);
        $inboth = $generator->create_and_enrol($this->course, 'student');
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $inboth->id]);
        $generator->create_group_member(['groupid' => $group2->id, 'userid' => $inboth->id]);
        $innone = $generator->create_and_enrol($this->course, 'student');

        $forum = $this->make_forum('qanda', SEPARATEGROUPS);
        $copy1 = $this->post($forum, ['groupid' => $group1->id]);
        $this->post($forum, ['groupid' => $group2->id]);

        $this->assertSame($copy1, $this->direct_for($forum, $ingroup1));
        $this->assertNull($this->direct_for($forum, $inboth));
        $this->assertNull($this->direct_for($forum, $innone));
    }

    public function test_separate_groups_counts_posts_to_all_participants(): void {
        $this->resetAfterTest();
        $this->make_course();
        $generator = $this->getDataGenerator();
        $group1 = $generator->create_group(['courseid' => $this->course->id]);
        $ingroup1 = $generator->create_and_enrol($this->course, 'student');
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $ingroup1->id]);
        $innone = $generator->create_and_enrol($this->course, 'student');

        $forum = $this->make_forum('qanda', SEPARATEGROUPS);
        $forall = $this->post($forum, ['groupid' => -1]);

        $this->assertSame($forall, $this->direct_for($forum, $innone));
        $this->assertSame($forall, $this->direct_for($forum, $ingroup1));

        $this->post($forum, ['groupid' => $group1->id]);
        $this->assertNull($this->direct_for($forum, $ingroup1));
        $this->assertSame($forall, $this->direct_for($forum, $innone));
    }

    public function test_visible_groups_sees_every_copy(): void {
        $this->resetAfterTest();
        $this->make_course();
        $generator = $this->getDataGenerator();
        $group1 = $generator->create_group(['courseid' => $this->course->id]);
        $group2 = $generator->create_group(['courseid' => $this->course->id]);
        $student = $generator->create_and_enrol($this->course, 'student');
        $generator->create_group_member(['groupid' => $group1->id, 'userid' => $student->id]);

        $forum = $this->make_forum('qanda', VISIBLEGROUPS);
        $this->post($forum, ['groupid' => $group1->id]);
        $this->post($forum, ['groupid' => $group2->id]);

        $this->assertNull($this->direct_for($forum, $student));
    }

    public function test_timed_discussions_count_only_while_showing(): void {
        global $CFG;

        $this->resetAfterTest();
        $CFG->forum_enabletimedposts = 1;
        $this->make_course();
        $student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $forum = $this->make_forum();

        $showing = $this->post($forum);
        $this->post($forum, ['timestart' => time() + DAYSECS]);
        $this->post($forum, ['timestart' => time() - 2 * DAYSECS, 'timeend' => time() - DAYSECS]);

        $this->assertSame($showing, $this->direct_for($forum, $student));
    }
}
