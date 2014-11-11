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

namespace totara_reportbuilder\rb\aggregate;

/**
 * Class describing column aggregation options.
 */
class groupconcatdistinct extends base {
    protected static function get_field_aggregate($field) {
        global $DB;

        $dbfamily = $DB->get_dbfamily();
        if ($dbfamily === 'mysql') {
            $field = "GROUP_CONCAT(DISTINCT $field SEPARATOR ', ')";
        } else if ($dbfamily === 'mssql') {
            $field = "dbo.GROUP_CONCAT_D(DISTINCT $field, ', ')";
        } else {
            $field = "string_agg(DISTINCT CAST($field AS text), ', ')";
        }

        return $field;
    }

    public static function is_column_option_compatible(\rb_column_option $option) {
        return ($option->dbdatatype !== 'timestamp');
    }
}
