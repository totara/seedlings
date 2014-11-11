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
class legacy extends base {
    public static function display($value, $format, \stdClass $row, \rb_column $column, \reportbuilder $report) {
        $isexport = ($format !== 'html');
        $source = $report->src;
        $displayfunc = $column->get_displayfunc();

        if ($displayfunc and $displayfunc !== 'legacy') {
            $func = 'rb_display_' . $displayfunc;
            if (method_exists($source, $func)) {
                // Get extrafields for column and rename them before passing them to display function.
                $extrafields = static::get_extrafields_row($row, $column);

                $result = $source->$func(format_text($value, FORMAT_HTML), $extrafields, $isexport);

                if ($isexport) {
                    $result = static::to_plaintext($result);
                }

                return $result;
            }
        }

        return parent::display($value, $format, $row, $column, $report);
    }
}
