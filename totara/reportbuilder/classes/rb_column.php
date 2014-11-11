<?php
/*
 * This file is part of Totara LMS
 *
 * Copyright (C) 2010 onwards Totara Learning Solutions LTD
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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Class defining a report builder column
 *
 * This class contains properties and methods needed by a column
 * Instances of this class differ from rb_column_option instances
 * in that they refer to an actual column in a report instance, as
 * opposed to an available column option.
 *
 * As well as inheriting a number of properties from the column
 * option on which it is based, a column defines extra information
 * about the column such as the heading the user wishes to use to
 * describe it.
 */
class rb_column {
    /** Select columns from normal tables */
    const REGULAR = 0;
    /** Select from cached tables */
    const CACHE = 1;
    /** Selection from normal table for insertion into cache table */
    const NOGROUP = 2;
    /** @deprecated do not use! */
    const ALIASONLY = 3;
    /** @deprecated do not use! */
    const FIELDONLY = 4;
    /** GROUP BY for non-aggregated columns from normal tables */
    const GROUPBYREGULAR = 5;
    /** GROUP BY for non-aggregated columns from cache tables */
    const GROUPBYCACHE = 6;
    /** Select columns from normal tables when GROUP BY present */
    const REGULARGROUPED = 7;

    /**
     * Used with value to define a column. These properties are used
     * to specify a column - for example {@link rb_filter} provides
     * a type and value to define which column the filter is searching
     * against.
     *
     * Columns are grouped by type in the 'add a column' pulldown to
     * let you find similar columns easily
     *
     * @access public
     * @var string
     */
    public $type;

    /**
     * Used with type to define a column. These properties are used
     * to specify a column - for example {@link rb_filter} provides
     * a type and value to define which column the filter is searching
     * against.
     *
     * @access public
     * @var string
     */
    public $value;

    /**
     * Column heading is a text string that appears in the column heading
     * when a user views a report.
     *
     * When default columns are included, the {@link rb_column_option::$defaultheading}
     * property is used for the heading until changed by the user.
     *
     * @access public
     * @var string
     */
    public $heading;

    /**
     * Database field to use to display or search by this column
     *
     * Typically of the form 'join_name.column_name' but can use any
     * valid sql that refers to a column, for example:
     *
     * <code>"CASE WHEN join.column = 1 THEN 'Yes' ELSE 'No' END"</code>
     *
     * or even:
     *
     * <code>sql_fullname('join.first', 'join.last')</code>
     *
     * @access public
     * @var string
     */
    public $field;

    /**
     * Name of any {@link rb_join} objects needed to access this column
     *
     * Can be a string or an array of strings if multiple tables required.
     * Normally you only need to provide a single join name as any join's
     * dependencies will automatically be included in the right order.
     *
     * @access public
     * @var mixed
     */
    public $joins;

    /**
     * Function to pass the result through before displaying
     *
     * If displayfunc is set to 'name', a method of the source called
     * 'rb_display_name()' will be called if found, with the field passed as
     * the first argument and an object containing the whole row passed as the
     * second argument. Instead of displaying the field value, the return value
     * from the function is displayed instead.
     *
     * This can be useful for improving the formatting of a field, for example
     * converting a unix timestamp into a nice date. Some common display functions
     * are provided by {@link rb_base_source}, and more can be created by the
     * source that needs them
     *
     * @access public
     * @var string
     */
    public $displayfunc;

    /**
     * Array of additional database fields to get from the database when this
     * column is included in a report. Some columns that use display functions
     * need more than one field (for example, the 'Course name linked to course
     * page' column requires the course name and the course ID (in order to build
     * the link).
     *
     * $extrafields is an associative array, with the key being a string to
     * reference the field and the value being a string formatted the same as
     * {@link rb_column::$field}.
     *
     * Typically a specific display function will expect an extra field and access
     * it from the $row object using the key.
     *
     * @access public
     * @var array
     */
    public $extrafields;

    /**
     * True if the column is required by this source.
     *
     * If true, the column will always be included in the report, and the column
     * will no longer appear in the column options list for an administrator.
     *
     * Typically this is used to add an 'Options' to a report
     * @access public
     * @var boolean
     */
    public $required;

    /**
     * Capability required in order to view this column
     *
     * If set, only users with the specified capability at the site context will
     * see this column. For other users it will not be displayed.
     * @access public
     * @var string
     */
    public $capability;

    /**
     * True if this column should not be included when the report is exported
     *
     * Typically used for administrative columns that don't belong in an exported report
     * @access public
     * @var boolean
     */
    public $noexport;

    /**
     * If grouping is set to anything but 'none', a method of the source called
     * 'rb_group_name()' will be called if found, passing in the field as an
     * argument. The value returned from this method will be used instead of the
     * field, and the SQL will be executed as a GROUP BY query, grouped by all
     * fields without grouping enabled.
     *
     * For example, if grouping is set to 'max' on a column with $field set to
     * 'join.col', then the method source->rb_group_max() will be called. It will
     * return 'MAX(join.col)' when passed 'join.col' as a parameter, and that
     * output will be used in place of 'join.col' in any SQL queries.
     *
     * Some common group functions are provided by {@link rb_base_source}, and more
     * can be created by the source that needs them.
     *
     * @access public
     * @var string
     */
    public $grouping;

    /**
     * Inline style information to be applied to this column
     *
     * Array of CSS properties like this:
     *
     * <code>array('color' => 'red', 'font-weight' => 'bold')</code>
     *
     * The CSS properties are added to the column via inline styles
     *
     * @access public
     * @var array
     */
    public $style;

    /**
     * Class to be applied to this column
     *
     * Array of CSS classes like this:
     *
     * <code>array('vertical')</code>
     *
     * The CSS classes are added to the column class property
     *
     * @access public
     * @var array
     */
    public $class;

    /**
     * Default visibility status for this column
     *
     * If set to true, users will not see this column by default, but they
     * will have the option to show the column using the show/hide button.
     *
     * It is important to realise that users do have access to hidden
     * columns, just not by default.
     *
     * @access public
     * @var boolean
     */
    public $hidden;

    /**
     * Determines if the column has been customised or not
     *
     * @access public
     * @var boolean
     */
    public $customheading;

    /**
     * Column transform function
     *
     * @access public
     * @var string
     */
    public $transform;

    /**
     * Determines if the column is aggregated
     *
     * @access public
     * @var string
     */
    public $aggregate;

    /** @var bool is this column already grouped? */
    public $grouped;

    /**
     * Generate a new column instance
     *
     * Options provided by an associative array, e.g.:
     *
     * <code>array('joins' => 'courses', 'displayfunc' => 'nicedate')</code>
     *
     * Will provide default values for any optional parameters that aren't set
     *
     * @param string $type Type of the column
     * @param string $value Value of the column
     * @param string $heading Heading for the column
     * @param string $field Database field to use for this column
     * @param array $options Associative array of optional settings for the column
     */
    function __construct($type, $value, $heading, $field, $options=array()) {

        // use defaults if options not set
        $defaults = array(
            'joins' => null,
            'displayfunc' => null,
            'extrafields' => null,
            'required' => false,
            'capability' => null,
            'noexport' => false,
            'grouping' => 'none',
            'nosort' => false,
            'style' => null,
            'class' => null,
            'hidden' => 0,
            'customheading' => true,
            'transform' => null,
            'aggregate' => null,
        );
        $options = array_merge($defaults, $options);

        $this->type = $type;
        $this->value = $value;
        $this->heading = $heading;
        $this->field = $field;

        // assign optional properties
        foreach ($defaults as $property => $unused) {
            $this->$property = $options[$property];
        }
    }


    /**
     * Obtain an array of SQL snippets describing field information for this column
     *
     * @param rb_base_source $src Source object containing grouping methods
     * @param int $aliasmode mode of alias handle (@see rb_column::REGULAR)
     * @param bool $returnextrafields whether to return the $extrafields (true) or just the main field (false)
     * @return array Array of field names with aliases used to build a query
     */
    public function get_fields($src = null, $aliasmode = self::REGULAR, $returnextrafields=true) {
        $field = $this->field;
        $type = $this->type;
        $value = $this->value;
        $fields = array();

        if ($field === null) {
            // Not much to do...

        } else if ($this->grouping !== 'none') {
            // field is grouped
            // if grouping function doesn't exist, exit with error
            $groupfunc = 'rb_group_' . $this->grouping;
            if (!method_exists($src, $groupfunc)) {
                throw new ReportBuilderException(get_string('groupingfuncnotinfieldoftypeandvalue',
                    'totara_reportbuilder',
                    (object)array('groupfunc' => $groupfunc, 'type' => $type, 'value' => $value)));
            }
            // apply grouping function and ignore extrafields
            switch ($aliasmode) {
                case self::FIELDONLY:
                    $fields[] = $src->$groupfunc($field);
                    break;
                case self::ALIASONLY:
                    $fields[] = "{$type}_{$value}";
                    break;
                case self::NOGROUP:
                    // Grouping disabled in cache preparation when grouping cannot be performed as sensitive data will be removed.
                    $fields[] = $field . " AS {$type}_{$value}";
                    break;
                case self::CACHE:
                    // Request will be pointed to cache instead of normal database table.
                    $fields[] = $src->$groupfunc("{$type}_{$value}") . " AS {$type}_{$value}";
                    break;
                case self::REGULAR:
                default:
                    // Cache disabled.
                    $fields[] = $src->$groupfunc($field) . " AS {$type}_{$value}";
                    break;
            }

        } else if ($this->transform) {
            $classname = "\\totara_reportbuilder\\rb\\transform\\$this->transform";
            if (class_exists($classname)) {
                $fields[] = $classname::get_field($this, $src, $aliasmode, $returnextrafields);
            }

        } else if ($this->aggregate) {
            $classname = "\\totara_reportbuilder\\rb\\aggregate\\$this->aggregate";
            if (class_exists($classname)) {
                $fields[] = $classname::get_field($this, $src, $aliasmode, $returnextrafields);
            }

        } else { // Grouping is 'none'.
            switch ($aliasmode) {
                case self::FIELDONLY:
                    $fields[] = "{$field}";
                    break;
                case self::ALIASONLY:
                    $fields[] = "{$type}_{$value}";
                    break;
                case self::CACHE:
                case self::GROUPBYCACHE:
                    $fields[] = "{$type}_{$value}";
                    break;
                case self::GROUPBYREGULAR:
                    if (!self::is_static_field($field)) {
                        $fields[] = "{$field}";
                    }
                    break;
                case self::REGULARGROUPED:
                    if (self::is_static_field($field)) {
                        $fields[] = "MIN({$field}) AS {$type}_{$value}";
                    } else {
                        $fields[] = "{$field} AS {$type}_{$value}";
                    }
                    break;
                case self::REGULAR:
                case self::NOGROUP:
                default:
                    $fields[] = "{$field} AS {$type}_{$value}";
                    break;
            }
        }

        $fields = array_merge($fields, $this->get_extra_fields($aliasmode));

        return $fields;
    }

    /**
     * Static fields such as 'grrr' or (10) cannot be in GROUP BY clause in
     * PostgreSQL and MSSQL - the trick is to apply aggregation function
     * to them instead of putting them to GROUP BY list.
     * @param string $field
     * @return bool True if field is static (all rows have the same value).
     */
    protected static function is_static_field($field) {
        $trimmed = trim($field, '()');
        if (is_numeric($trimmed)) {
            return true;
        }
        if (substr($field, 0, 1) === "'" and substr($field, -1)) {
            $trimmed = substr($field, 1, strlen($field) - 2);
            if (strpos($trimmed, "'") === false) {
                // This looks like a SQL string constant.
                return true;
            }
        }
        return false;
    }

    /**
     * Obtain an array of SQL snippets describing field information for extra fields of this column
     *
     * @param int $aliasmode
     * @return array SQL fields
     */
    public function get_extra_fields($aliasmode) {
        $fields = array();
        if (!$this->extrafields) {
            return $fields;
        }
        if ($this->aggregate) {
            // Aggregated columns do not need any extras because the aggregation is the whole result.
            return $fields;
        }

        $type = $this->type;
        $value = $this->value;

        foreach ($this->extrafields as $extrafieldname => $extrafield) {
            $alias = reportbuilder_get_extrafield_alias($type, $value, $extrafieldname);
            switch ($aliasmode) {
                case self::FIELDONLY:
                    $fields[] = $extrafield;
                    break;
                case self::ALIASONLY:
                    $fields[] = $alias;
                    break;
                case self::GROUPBYREGULAR:
                    if (!self::is_static_field($extrafield)) {
                        $fields[] = $extrafield;
                    }
                    break;
                case self::CACHE:
                case self::GROUPBYCACHE:
                    $fields[] = $alias;
                    break;
                case self::REGULARGROUPED:
                    if (self::is_static_field($extrafield)) {
                        $fields[] = "MIN({$extrafield}) AS $alias";
                    } else {
                        $fields[] = "$extrafield AS $alias";
                    }
                    break;
                case self::REGULAR:
                case self::NOGROUP:
                default:
                    $fields[] = "$extrafield AS $alias";
            }
        }

        return $fields;
    }

    /**
     * Examine a column to determine if it should be displayed in the current context
     *
     * @param boolean $isexport If true, data is being exported
     * @return boolean True if the column should be shown, false otherwise
     */
    function display_column($isexport=false) {
        // Don't print the column if we do not have any heading,
        // value comes from default heading or user defined heading.
        if ($this->heading == '') {
            return false;
        }

        // don't print the column if column has noexport set and this is an export
        if ($isexport && $this->noexport) {
            return false;
        }

        // don't display column if capability is required and user doesn't have it
        $context = context_system::instance();
        if (isset($this->capability) && !has_capability($this->capability, $context)) {
            return false;
        }

        return true;
    }

    /**
     * Get appropriate display function name without prefix,
     * this uses transformation and aggregation classes if necessary
     * @return string
     */
    public function get_displayfunc() {
        $displayfunc = null;
        if ($this->grouping === 'none' and $this->transform) {
            $classname = '\totara_reportbuilder\rb\transform\\' . $this->transform;
            $displayfunc = $classname::get_displayfunc($this);
        } else if ($this->grouping === 'none' and $this->aggregate) {
            $classname = '\totara_reportbuilder\rb\aggregate\\' . $this->aggregate;
            $displayfunc = $classname::get_displayfunc($this);
        } else {
            $displayfunc = $this->displayfunc;
        }

        return $displayfunc;
    }
}
