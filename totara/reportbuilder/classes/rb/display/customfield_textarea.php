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
class customfield_textarea extends base {
    public static function display($value, $format, \stdClass $row, \rb_column $column, \reportbuilder $report) {
        global $CFG;
        require_once($CFG->dirroot.'/totara/customfield/field/textarea/field.class.php');

        if (is_null($value) or $value === '') {
            return '';
        }

        $field = "{$column->type}_{$column->value}";
        $extrafields = self::get_extrafields_row($row, $column);

        // Hierarchy custom fields are stored in the FileAPI fileareas using the longform of the prefix
        // extract prefix from field name.
        $pattern = '/(?P<prefix>(.*?))_custom_field_(\d+)$/';
        $matches = array();
        preg_match($pattern, $field, $matches);
        if (!empty($matches)) {
            $cf_prefix = $matches['prefix'];
            switch ($cf_prefix) {
                case 'org_type':
                    $prefix = 'organisation';
                    break;
                case 'pos_type':
                    $prefix = 'position';
                    break;
                case 'comp_type':
                    $prefix = 'competency';
                    break;
                case 'goal_type':
                    $prefix = 'goal';
                    break;
                case 'course':
                    $prefix = 'course';
                    break;
                case 'prog':
                    $prefix = 'program';
                    break;
                default:
                    debugging("Unknown prefix '$cf_prefix'' in custom field '$field'", DEBUG_DEVELOPER);
                    return '';
            }
        } else {
            debugging("Unknown type of custom field '$field'", DEBUG_DEVELOPER);
            return '';
        }

        $itemidfield = "{$field}_itemid";
        $extradata = array('prefix' => $prefix, 'itemid' => $extrafields->$itemidfield);
        $displaytext = \customfield_textarea::display_item_data($value, $extradata);

        if ($format !== 'html') {
            $displaytext = static::to_plaintext($displaytext, true);
        }

        return $displaytext;
    }
}
