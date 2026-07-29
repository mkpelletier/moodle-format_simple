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
 * Specialised backup for the Simple course format.
 *
 * @package   format_simple
 * @category  backup
 * @copyright 2026 South African Theological Seminary <ict@sats.ac.za>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Specialised backup for the Simple course format.
 *
 * The 'primarycontent' section format option holds a course module id. Core backs section
 * format options up verbatim, and restores them verbatim, so on restore the value would still
 * point at the source course's module. We record the id separately here so that
 * {@see restore_format_simple_plugin} can translate it through the course_module mapping.
 *
 * @package   format_simple
 * @category  backup
 * @copyright 2026 South African Theological Seminary <ict@sats.ac.za>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_format_simple_plugin extends backup_format_plugin {
    /**
     * Adds the primary content course module id to the section backup structure.
     *
     * Nothing is added for courses using a different format, or for sections left on the
     * default 'auto' setting, in which case there is no module id to translate.
     *
     * @return void
     */
    public function define_section_plugin_structure() {
        global $DB;

        // Every installed format plugin gets asked to contribute here, so only act on our own courses.
        if ($DB->get_field('course', 'format', ['id' => $this->task->get_courseid()]) !== 'simple') {
            return;
        }

        $plugin = $this->get_plugin_element();

        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $primarycontent = new backup_nested_element('primarycontent', null, ['cmid']);
        $pluginwrapper->add_child($primarycontent);

        // Only sections with an explicit selection are worth recording; '0' means auto-select.
        $primarycontent->set_source_sql(
            "SELECT cfo.value AS cmid
               FROM {course_format_options} cfo
              WHERE cfo.courseid = ?
                    AND cfo.sectionid = ?
                    AND cfo.format = 'simple'
                    AND cfo.name = 'primarycontent'
                    AND cfo.value <> '0'",
            [backup::VAR_COURSEID, backup::VAR_SECTIONID]
        );
    }
}
