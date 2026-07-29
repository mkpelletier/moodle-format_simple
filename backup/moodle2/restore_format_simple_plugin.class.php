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
 * Specialised restore for the Simple course format.
 *
 * @package   format_simple
 * @category  backup
 * @copyright 2026 South African Theological Seminary <ict@sats.ac.za>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Specialised restore for the Simple course format.
 *
 * Translates the course module id held in the 'primarycontent' section format option through
 * the course_module mapping. Core restores format option values verbatim, so without this the
 * option would still reference the source course's module and the teacher's choice of primary
 * activity would be silently lost.
 *
 * @package   format_simple
 * @category  backup
 * @copyright 2026 South African Theological Seminary <ict@sats.ac.za>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_format_simple_plugin extends restore_format_plugin {
    /** @var int Course module id the source course had selected, 0 when none was recorded. */
    protected $oldprimarycmid = 0;

    /**
     * Declares the section level data this plugin restores.
     *
     * @return restore_path_element[]
     */
    public function define_section_plugin_structure() {
        return [
            new restore_path_element('primarycontent', $this->get_pathfor('/primarycontent')),
        ];
    }

    /**
     * Remembers the source course module id for translation once modules have been restored.
     *
     * The mapping does not exist yet at this point: sections are restored before the activities
     * they contain, so the actual work happens in {@see after_restore_section()}.
     *
     * @param array|stdClass $data The primarycontent element data.
     * @return void
     */
    public function process_primarycontent($data) {
        $data = (array) $data;
        $this->oldprimarycmid = (int) ($data['cmid'] ?? 0);
    }

    /**
     * Translates the stored course module id now that all activities have been restored.
     *
     * @return void
     */
    public function after_restore_section() {
        global $DB;

        if (empty($this->oldprimarycmid)) {
            return;
        }

        $params = [
            'courseid' => $this->task->get_courseid(),
            'sectionid' => $this->task->get_sectionid(),
            'format' => 'simple',
            'name' => 'primarycontent',
        ];
        $record = $DB->get_record('course_format_options', $params, 'id, value');
        if (!$record) {
            // Restored into a course using a different format, so core skipped the option.
            return;
        }

        // Core wrote the source value through untouched. If what is stored is anything else the
        // section already carried its own selection, which a merging restore must not clobber.
        if ((int) $record->value !== $this->oldprimarycmid) {
            return;
        }

        // Fall back to 0 ('auto') when the selected activity was not part of this restore.
        $newcmid = (int) $this->get_mappingid('course_module', $this->oldprimarycmid, 0);

        if ($newcmid !== (int) $record->value) {
            $DB->set_field('course_format_options', 'value', $newcmid, ['id' => $record->id]);
        }
    }
}
