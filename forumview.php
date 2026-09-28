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
 * Opens a Q&A forum's only discussion, recording the visit as a view of the forum.
 *
 * The course page links here instead of to the forum when the forum holds a single discussion
 * for the user. Core's discussion page does not mark the forum viewed, only its list of
 * discussions does, so this does that part first. The discussion is looked up again rather
 * than taken from the link, so a stale link (a second discussion has since been posted) opens
 * the forum's own page as usual.
 *
 * @package    format_simple
 * @copyright  2026 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot . '/course/format/simple/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

$id = required_param('id', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($id, 'forum');

$PAGE->set_url('/course/format/simple/forumview.php', ['id' => $cm->id]);
require_login($course, false, $cm);

$discussionid = format_simple::get_single_forum_discussion($cm);
if ($discussionid === null) {
    redirect(new moodle_url('/mod/forum/view.php', ['id' => $cm->id]));
}

$forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
forum_view($forum, $course, $cm, $cm->context);

redirect(new moodle_url('/mod/forum/discuss.php', ['d' => $discussionid]));
