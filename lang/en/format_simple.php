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
 * Language strings for format_simple.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'Simple format';
$string['plugin_description'] = 'A clean, minimalist course format that presents content one unit at a time. Each unit displays learning outcomes, related resources, and related activities in distinct zones for a focused learning experience.';

// Section defaults.
$string['sectionname'] = 'Unit';
$string['section0name'] = 'General';
$string['newsectionname'] = 'New unit';
$string['hidefromothers'] = 'Hide unit';
$string['showfromothers'] = 'Show unit';
$string['currentsection'] = 'This unit';
$string['deletesection'] = 'Delete unit';
$string['editsection'] = 'Edit unit';
$string['addsection'] = 'Add unit';

// Content zones.
$string['zone_learningcontent'] = 'Learning Content';
$string['zone_resources'] = 'Related Resources';
$string['zone_activities'] = 'Related Activities';

// Learning outcomes.
$string['learningoutcomes'] = 'Learning outcomes';
$string['learningoutcomes_help'] = 'Enter one learning outcome per line. These will be displayed in a popout panel for students to see the objectives for this unit.';
$string['outcomesbtn'] = 'Outcomes';
$string['nooutcomes'] = 'No learning outcomes defined for this unit.';

// Progress states.
$string['progress_complete'] = 'Complete';
$string['progress_inprogress'] = 'In progress ({$a}%)';
$string['progress_notstarted'] = 'Not started';
$string['progress_restricted'] = 'Restricted';

// Navigation.
$string['navpanel'] = 'Unit navigation';
$string['selectunit'] = 'Select unit';

// Hidden UI.
$string['showcoursemenu'] = 'Show course menu';

// Course format options.
$string['hiddensections'] = 'Hidden sections';
$string['hiddensections_help'] = 'Whether hidden sections are shown as not available or are completely invisible.';
$string['showsection0banner'] = 'Show course banner';
$string['showsection0banner_help'] = 'Display a hero banner at the top of the Course Info section showing the course name and image.';

// Primary content selector.
$string['primarycontent'] = 'Featured learning content';
$string['primarycontent_help'] = 'Select which learning content item to display inline as the featured content for this unit. Other learning items will appear as secondary cards. If set to automatic, the first learning content item is used.';
$string['primarycontent_auto'] = 'Automatic (first item)';

// Availability.
$string['restricted_prereqs'] = 'Prerequisites';
$string['restricted_info'] = 'This unit is not yet available. Complete the following requirements:';

// Empty state.
$string['nocoursesections'] = 'No units have been added to this course yet.';

// Section 0.
$string['courseinfo'] = 'Course Info';
$string['section0modal'] = 'Show Course Info as overlay';
$string['section0modal_help'] = 'When enabled, the Course Info section is displayed in a floating overlay accessible from a tab on every course page, instead of being shown inline in the navigation panel.';

// Completion.
$string['markcomplete'] = 'Mark as complete';
$string['marknotcomplete'] = 'Mark as not complete';

// Fullscreen.
$string['togglefullscreen'] = 'Toggle fullscreen';
$string['exitfullscreen'] = 'Exit fullscreen';

// Unread posts.
$string['unreadposts'] = 'Unread posts';
$string['unreadcount'] = '{$a} unread';

// Mobile navigation.
$string['togglenav'] = 'Toggle navigation';

// Cog navigation (JS).
$string['backtocourse'] = 'Back to course';
$string['coursetools'] = 'Course tools';
$string['close'] = 'Close';
$string['loading'] = 'Loading...';
$string['failedtoload'] = 'Failed to load course info.';

// Privacy.
$string['privacy:metadata'] = 'The Simple format plugin does not store any personal data.';
