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
 * External function to persist the user's dark mode preference.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_simple\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use invalid_parameter_exception;

/**
 * Saves the user's dark mode theme preference (light, dark, or auto), along with the
 * start/end times used to compute auto mode.
 */
class set_theme_preference extends external_api {
    /**
     * Parameter definitions.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'theme' => new external_value(PARAM_ALPHA, 'Theme: light, dark, or auto'),
            'start' => new external_value(PARAM_TEXT, 'Auto mode dark start time, HH:MM'),
            'end' => new external_value(PARAM_TEXT, 'Auto mode dark end time, HH:MM'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param string $theme The theme: light, dark, or auto.
     * @param string $start Auto mode start time (HH:MM).
     * @param string $end Auto mode end time (HH:MM).
     * @return array ['success' => bool]
     */
    public static function execute(string $theme, string $start, string $end): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'theme' => $theme,
            'start' => $start,
            'end' => $end,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        require_login(null, false);

        if (!in_array($params['theme'], ['light', 'dark', 'auto'], true)) {
            throw new invalid_parameter_exception('Invalid theme value');
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $params['start'])) {
            throw new invalid_parameter_exception('Invalid start time');
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $params['end'])) {
            throw new invalid_parameter_exception('Invalid end time');
        }

        set_user_preference('format_simple_darktheme', $params['theme']);
        set_user_preference('format_simple_darkstart', $params['start']);
        set_user_preference('format_simple_darkend', $params['end']);

        return ['success' => true];
    }

    /**
     * Return value definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the preference was saved'),
        ]);
    }
}
