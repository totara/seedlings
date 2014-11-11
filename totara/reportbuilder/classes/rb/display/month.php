<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2014 onwards Totara Learning Solutions LTD
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara_reportbuilder
 */

namespace totara_reportbuilder\rb\display;

/**
 * Class describing column display formatting.
 *
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara_reportbuilder
 */
class month extends base {
    public static function display($value, $format, \stdClass $row, \rb_column $column, \reportbuilder $report) {
        $monthnum = (int)$value;

        if ($monthnum < 1 or $monthnum > 12) {
            return '';
        }

        // Note: following code comes from lib/formslib.php, it uses PHP locale info.
        $months = array(
            1 => date_format_string(strtotime("January 1"), '%B', 99),
            2 => date_format_string(strtotime("February 1"), '%B', 99),
            3 => date_format_string(strtotime("March 1"), '%B', 99),
            4 => date_format_string(strtotime("April 1"), '%B', 99),
            5 => date_format_string(strtotime("May 1"), '%B', 99),
            6 => date_format_string(strtotime("June 1"), '%B', 99),
            7 => date_format_string(strtotime("July 1"), '%B', 99),
            8 => date_format_string(strtotime("August 1"), '%B', 99),
            9 => date_format_string(strtotime("September 1"), '%B', 99),
            10 => date_format_string(strtotime("October 1"), '%B', 99),
            11 => date_format_string(strtotime("November 1"), '%B', 99),
            12 => date_format_string(strtotime("December 1"), '%B', 99)
        );

        return $months[$monthnum];
    }
}
