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
class base {
    /**
     * Get extrafields for a column from a database record.
     * This removes the stuff that was added to make the name unique when processed in a query.
     *
     * @param \stdClass $row record returned from sql query
     * @param \rb_column $column which has a display function with extra fields (else returns empty array)
     * @return \stdClass $extrafieldsrow only the extrafields specified by the column, with unique identifier removed.
     */
    public static final function get_extrafields_row(\stdClass $row, \rb_column $column) {
        $extrafieldsrow = new \stdClass();

        if (!isset($column->extrafields) || empty($column->extrafields)) {
            return $extrafieldsrow;
        }

        $extrafields = $column->extrafields;
        foreach ($extrafields as $extrafield => $value) {
            $extrafieldalias = reportbuilder_get_extrafield_alias($column->type, $column->value, $extrafield);
            if (isset($row->$extrafieldalias)) {
                $extrafieldsrow->$extrafield = $row->$extrafieldalias;
            } else {
                $extrafieldsrow->$extrafield = null;
            }
        }

        return $extrafieldsrow;
    }

    /**
     * Convert html to simplified plaintext.
     *
     * @param string $html
     * @param bool $para true means keep paragraphs, false means one line text
     * @return string plain text
     */
    public static function to_plaintext($html, $para = false) {
        if ($para) {
            $result = html_to_text($html, 0, false);
        } else {
            $result = strip_tags($html);
        }
        return \core_text::entities_to_utf8($result);
    }

    /**
     * Format the value.
     *
     * @param string $value
     * @param string $format
     * @param \stdClass $row
     * @param \rb_column $column
     * @param \reportbuilder $report
     * @return string
     */
    public static function display($value, $format, \stdClass $row, \rb_column $column, \reportbuilder $report) {
        $result = format_text($value, FORMAT_HTML);

        if ($format !== 'html') {
            $result = static::to_plaintext($result);
        }

        return $result;
    }
}
