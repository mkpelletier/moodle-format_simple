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
 * Site-wide settings for format_simple.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use format_simple\local\brand_palette;

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'format_simple/colourheading',
        get_string('colourheading', 'format_simple'),
        get_string('colourheading_desc', 'format_simple')
    ));

    $settings->add(new admin_setting_configselect(
        'format_simple/colourscheme',
        get_string('colourscheme', 'format_simple'),
        get_string('colourscheme_desc', 'format_simple'),
        brand_palette::SCHEME_DEFAULT,
        [
            brand_palette::SCHEME_DEFAULT => get_string('colourscheme_default', 'format_simple'),
            brand_palette::SCHEME_THEME => get_string('colourscheme_theme', 'format_simple'),
            brand_palette::SCHEME_CUSTOM => get_string('colourscheme_custom', 'format_simple'),
        ]
    ));

    $settings->add(new admin_setting_configcolourpicker(
        'format_simple/brandcolour',
        get_string('brandcolour', 'format_simple'),
        get_string('brandcolour_desc', 'format_simple'),
        '#2563eb'
    ));
    $settings->hide_if('format_simple/brandcolour', 'format_simple/colourscheme', 'neq', brand_palette::SCHEME_CUSTOM);
}
