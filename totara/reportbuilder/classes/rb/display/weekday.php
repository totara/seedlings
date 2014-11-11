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
class weekday extends base {
    public static function display($value, $format, \stdClass $row, \rb_column $column, \reportbuilder $report) {
        $daynum = (int)$value;

        if ($daynum < 1 or $daynum > 7) {
            return '';
        }

        $days = array(
            1 => new \lang_string('sunday', 'calendar'),
            2 => new \lang_string('monday', 'calendar'),
            3 => new \lang_string('tuesday', 'calendar'),
            4 => new \lang_string('wednesday', 'calendar'),
            5 => new \lang_string('thursday', 'calendar'),
            6 => new \lang_string('friday', 'calendar'),
            7 => new \lang_string('saturday', 'calendar')
        );

        return (string)$days[$daynum];
    }
}
