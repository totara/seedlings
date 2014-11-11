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
 *
 * @author Petr Skoda <petr.skoda@totaralms.com>
 * @package totara_reportbuilder
 */
abstract class base {
    /**
     * Return list of transformation classes.
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
            $classname = '\totara_reportbuilder\rb\transform\\' . $name;
            if (!class_exists($classname)) {
                debugging("Invalid tranform class $name found", DEBUG_DEVELOPER);
                continue;
            }
            $types[$name] = $classname;
        }

        return $types;
    }

    /**
     * Returns list of transformation options grouped by type.
     *
     * @return array
     */
    public static function get_options() {
        $options = array();
        foreach (self::get_types() as $name => $classname) {
            $options[$classname::get_typename()][$name] = get_string("transformtype{$name}_name", 'totara_reportbuilder');
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
            $PAGE->requires->string_for_js("transformtype{$name}_heading", 'totara_reportbuilder');
        }
    }

    /**
     * Return type name.
     * @return string
     */
    public static function get_typename() {
        return get_string('advancedgrouptimedate', 'totara_reportbuilder');
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

        if ($aliasmode == $column::CACHE or $aliasmode == $column::GROUPBYCACHE) {
            $field = "{$type}_{$value}";
        } else {
            $field = $column->field;
        }

        $field = static::get_field_transform($field);

        if ($aliasmode == $column::REGULAR
                or $aliasmode == $column::REGULARGROUPED
                or $aliasmode == $column::CACHE) {
            $field = "$field AS {$type}_{$value}";
        }

        return $field;
    }

    /**
     * Return field transformation.
     *
     * @param string $field
     * @return string
     */
    protected static function get_field_transform($field) {
        throw new \coding_exception('get_field_transform() method must be overridden');
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
        return ($option->dbdatatype === 'timestamp');
    }
}
