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

use core\hook\output\before_standard_head_html_generation;
use format_simple\local\brand_palette;

/**
 * Hook callbacks for format_simple.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Put the site's brand palette in the page head.
     *
     * styles.css is compiled into the theme's cached stylesheet, so a colour chosen in the admin
     * settings cannot live there. It goes out as a small inline rule instead, on every page of a
     * course in this format, which covers the fixed rail and popovers as well as the course page.
     *
     * @param before_standard_head_html_generation $hook The hook.
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE;

        if (during_initial_install() || ($PAGE->course->format ?? '') !== 'simple') {
            return;
        }

        $css = brand_palette::get_css($PAGE->theme);
        if ($css !== '') {
            $hook->add_html(\html_writer::tag('style', $css, ['id' => 'format-simple-brand']));
        }
    }
}
