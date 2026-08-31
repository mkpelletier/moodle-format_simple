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
 * Privacy Subsystem implementation for format_simple.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_simple\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for format_simple.
 *
 * The only personal data this plugin stores is the user's own dark mode
 * preference, kept as standard Moodle user preferences.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\user_preference_provider {
    /**
     * Describe the user preferences stored by this plugin.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_user_preference(
            'format_simple_darktheme',
            'privacy:metadata:preference:format_simple_darktheme'
        );
        $collection->add_user_preference(
            'format_simple_darkstart',
            'privacy:metadata:preference:format_simple_darkstart'
        );
        $collection->add_user_preference(
            'format_simple_darkend',
            'privacy:metadata:preference:format_simple_darkend'
        );

        return $collection;
    }

    /**
     * Export all user preferences for the given user.
     *
     * @param int $userid The user whose preferences should be exported.
     */
    public static function export_user_preferences(int $userid): void {
        $preferences = [
            'format_simple_darktheme' => 'privacy:metadata:preference:format_simple_darktheme',
            'format_simple_darkstart' => 'privacy:metadata:preference:format_simple_darkstart',
            'format_simple_darkend' => 'privacy:metadata:preference:format_simple_darkend',
        ];

        foreach ($preferences as $name => $reason) {
            $value = get_user_preferences($name, null, $userid);
            if ($value === null) {
                continue;
            }
            writer::export_user_preference(
                'format_simple',
                $name,
                $value,
                get_string($reason, 'format_simple')
            );
        }
    }
}
