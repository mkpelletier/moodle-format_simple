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
 * Course module completion output class for format_simple.
 *
 * @package    format_simple
 * @copyright  2026 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_simple\output\courseformat\content\cm;

use core_courseformat\output\local\content\cm\completion as completion_base;
use renderer_base;
use stdClass;

/**
 * Course module completion output class.
 *
 * Adds the ability to leave out the view condition. Content shown in place is marked as viewed
 * by this format as soon as the student has actually seen it, and the section progress ring
 * already reflects that, so repeating "view this activity" underneath the content the student
 * is reading tells them nothing.
 */
class completion extends completion_base {
    /**
     * Whether the view condition should be left out of the rendered conditions.
     *
     * @var bool
     */
    protected bool $hideviewcondition = false;

    /**
     * Leave the view condition out of the rendered completion conditions.
     *
     * Other conditions, and the manual completion button, are unaffected.
     *
     * @param bool $hide Whether to hide the view condition.
     */
    public function set_hideviewcondition(bool $hide): void {
        $this->hideviewcondition = $hide;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass|null Completion data, or null when there is nothing left to show.
     */
    public function export_for_template(renderer_base $output): ?stdClass {
        $data = parent::export_for_template($output);

        if ($data === null || !$this->hideviewcondition || empty($data->completiondetails)) {
            return $data;
        }

        $remaining = array_values(array_filter(
            $data->completiondetails,
            fn($detail) => ($detail->key ?? '') !== 'completionview'
        ));

        if (count($remaining) === count($data->completiondetails)) {
            // The activity does not use the view condition, so there is nothing to change.
            return $data;
        }

        $data->completiondetails = $remaining;

        if (empty($remaining)) {
            // Viewing was the only condition. Drop the conditions dialog, and with it the whole
            // block unless a manual button still needs somewhere to live.
            unset($data->completiondialog);
            if (empty($data->showmanualcompletion) || empty($data->istrackeduser)) {
                return null;
            }
            return $data;
        }

        // Other conditions remain, so rebuild the dialog without the view one.
        $data->completiondialog = $this->get_completion_dialog($output, $data);

        return $data;
    }
}
