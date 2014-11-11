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

namespace totara_reportbuilder\rb\transform;

/**
 * Class describing column transformation options.
 */
class weekday extends base {
    protected static function get_field_transform($field) {
        global $DB;
        $dbfamily = $DB->get_dbfamily();

        if ($dbfamily === 'mysql') {
            $expr = "FROM_UNIXTIME($field, '%w') + 1";
        } else if ($dbfamily === 'mssql') {
            // NOTE: this may have problems with timezones.
            $expr = "(((DATEPART(dw, DATEADD(second, $field, {d '1970-01-01'})) + @@DATEFIRST - 1) % 7) + 1)";
        } else {
            $expr = "TO_CHAR(TO_TIMESTAMP($field), 'D')";
        }
        return "CASE WHEN ($field IS NULL OR $field = 0) THEN NULL ELSE $expr END";
    }

    public static function get_displayfunc(\rb_column $column) {
        return 'weekday';
    }
}
