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
 * Course module title output class for format_simple.
 *
 * @package    format_simple
 * @copyright  2026 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_simple\output\courseformat\content\cm;

use core_courseformat\output\local\content\cm\title as title_base;

/**
 * Course module title output class.
 *
 * Renders the activity name through a template of this format's own, which differs from core's
 * only in leaving out the stretched link. Core stretches the name's link across the whole
 * activity row to make it clickable; this format already gives the card its own click target,
 * and a stretched link here would cover the rename pencil and the completion indicator sitting
 * beside it, leaving neither reachable.
 */
class title extends title_base {
    /**
     * The template used to render the name itself.
     *
     * @var string
     */
    protected $displaytemplate = 'format_simple/local/content/cm/title';
}
