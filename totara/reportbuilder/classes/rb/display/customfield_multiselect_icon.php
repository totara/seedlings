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
class customfield_multiselect_icon extends base {
    public static function display($value, $format, \stdClass $row, \rb_column $column, \reportbuilder $report) {
        global $CFG;
        require_once($CFG->dirroot . '/totara/customfield/field/multiselect/field.class.php');

        $field = "{$column->type}_{$column->value}";
        $extrafields = self::get_extrafields_row($row, $column);

        if ($format === 'html') {
            $displaytext = \customfield_multiselect::display_item_data($extrafields->{$field . '_json'},
                                array('display' => 'list-icons'));

        } else {
            $displaytext = \customfield_multiselect::display_item_data($extrafields->{$field . '_json'},
                                array('display' => 'list-text'));
            $displaytext = html_to_text($displaytext, 0, false);
            $displaytext = \core_text::entities_to_utf8($displaytext);
        }

        return $displaytext;
    }
}
