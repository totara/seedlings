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
 *
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara_reportbuilder
 */
abstract class base {
    /**
     * Returns list of aggregation classes.
     * @return array short name=>class name
     */
    public static function get_types() {
        $types = array();

        foreach (scandir(__DIR__) as $filename) {
            if (substr($filename, -4) !== '.php') {
                continue;
            }
            if ($filename === 'base.php') {
                continue;
            }
            $name = str_replace('.php', '', $filename);
            $classname = '\totara_reportbuilder\rb\aggregate\\' . $name;
            if (!class_exists($classname)) {
                debugging("Invalid aggregation class $name found", DEBUG_DEVELOPER);
                continue;
            }
            $types[$name] = $classname;
        }

        return $types;
    }

    /**
     * Returns list of aggregation options grouped by type.
     *
     * @return array
     */
    public static function get_options() {
        $options = array();
        foreach (self::get_types() as $name => $classname) {
            $options[$classname::get_typename()][$name] = get_string("aggregatetype{$name}_name", 'totara_reportbuilder');
        }
        \core_collator::asort($options);
        foreach ($options as $k => $unused) {
            \core_collator::asort($options[$k]);
        }

        return $options;
    }

    /**
     * Add all heading strings to $PAGE.
     */
    public static function require_column_heading_strings() {
        global $PAGE;

        foreach (self::get_types() as $name => $classname) {
            $PAGE->requires->string_for_js("aggregatetype{$name}_heading", 'totara_reportbuilder');
        }
    }

    /**
     * Return type name.
     * @return string
     */
    public static function get_typename() {
        return get_string('advancedgroupaggregate', 'totara_reportbuilder');
    }

    /**
     * Get the field string.
     *
     * @param \rb_column $column
     * @param \rb_base_source$src
     * @param int $aliasmode
     * @param bool $returnextrafields
     * @return string
     */
    public static function get_field(\rb_column $column, \rb_base_source $src, $aliasmode, $returnextrafields) {
        $type = $column->type;
        $value = $column->value;
        $field = $column->field;

        if ($aliasmode == $column::ALIASONLY) {
            return "{$type}_{$value}";
        }
        if ($aliasmode == $column::FIELDONLY) {
            return $field;
        }

        if ($aliasmode == $column::NOGROUP) {
            return "$field AS {$type}_{$value}";
        }

        if ($aliasmode == $column::CACHE) {
            $field = "{$type}_{$value}";
        }

        $field = static::get_field_aggregate($field);

        if ($aliasmode == $column::GROUPBYCACHE or $aliasmode == $column::GROUPBYREGULAR) {
            debugging('Aggregated collumns should not be "grouped by"!');
        }

        return "{$field} AS {$type}_{$value}";
    }

    /**
     * Return field aggregation.
     *
     * @param string $field
     * @return string
     */
    protected static function get_field_aggregate($field) {
        throw new \coding_exception('get_field_aggregate() method must be overridden');
    }

    /**
     * Returns appropriate display function.
     * @param \rb_column $column
     * @return string
     */
    public static function get_displayfunc(\rb_column $column) {
        return null;
    }

    /**
     * Is this transformation compatible with given column option?
     * @param \rb_column_option $option
     * @return bool
     */
    public static function is_column_option_compatible(\rb_column_option $option) {
        return false;
    }
}
