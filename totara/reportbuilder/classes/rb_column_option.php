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
 * Class defining a report builder column option
 *
 * A column option is an object that defines a possible column
 * within a source. When an administrator includes this column
 * option in a report, they provide some additional information
 * (such as a heading), and a {@link rb_column} object is created
 * based on the column option's properties.
 */
class rb_column_option {
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
     * A description of the column. This appears in the 'add a column'
     * pulldown when an administrator updates a report's columns.
     *
     * @access public
     * @var string
     */
    public $name;

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
     * Class or function to pass the result through before displaying.
     *
     * The classes need to be defined in \xx_yy\rb\display\ namespace,
     * source classes may define list of other components for lookup
     * in usedcomponents property.
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
     * Column heading is a text string that appears in the column heading
     * when a user views a report.
     *
     * When default columns are included, the {@link rb_column_option::$defaultheading}
     * property is used for the heading until changed by the user.
     *
     * If no default heading is set then the {@link rb_column_option::$name}
     * property is used.
     *
     * @access public
     * @var string
     */
    public $defaultheading;

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
     * Determine whether the sorting capability is added to a column
     *
     * @access public
     * @var bool
     */
    public $nosort;

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
     * Determines if the column can be selected by an administrator building
     * a report. If not the column option can never be picked. This is useful
     * if you need to create a column to be used by a filter, but it would
     * clutter up the column option list
     */
    public $selectable;

    /**
     * Name of the method to call to generate actual columns, rather than performing the default action.
     */
    public $columngenerator;

    /**
     * String indicating the data type of this column when retrieved from the database. Valid options are:
     * 'unspecified' (default if parameter is not specified)
     * 'char'
     * 'text'
     * 'integer' - not intended for ids and foreign keys, the value must have some numerical meaning
     * 'decimal'
     * 'timestamp'
     * Other formats may be defined in the future.
     */
    public $dbdatatype;

    /**
     * String indicating the format that the column will output. Valid options are:
     * 'unspecified' (default if parameter is not specified)
     * 'text'
     * Other formats may be defined in the future.
     */
    public $outputformat;

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

    /**
     * Generate a new column option instance
     *
     * Options provided by an associative array, e.g.:
     *
     * <code>array('joins' => 'courses', 'displayfunc' => 'nicedate')</code>
     *
     * Will provide default values for any optional parameters that aren't set
     *
     * @param string $type Type of the column
     * @param string $value Value of the column
     * @param string $heading Name for the column
     * @param string $field Database field to use for this column
     * @param array $options Associative array of optional settings for the column
     */
    function __construct($type, $value, $name, $field, $options=array()) {

        // use defaults if options not set
        $defaults = array(
            'joins' => null,
            'displayfunc' => null,
            'defaultheading' => null,
            'extrafields' => null,
            'capability' => null,
            'noexport' => false,
            'grouping' => 'none',
            'style' => null,
            'class' => null,
            'nosort' => false,
            'hidden' => 0,
            'selectable' => true,
            'columngenerator' => null,
            'dbdatatype' => 'unspecified',
            'outputformat' => 'unspecified',
            'transform' => null,
            'aggregate' => null,
        );
        $options = array_merge($defaults, $options);

        $this->type = $type;
        $this->value = $value;
        $this->name = $name;
        $this->field = $field;

        // assign optional properties
        foreach ($defaults as $property => $unused) {
            $this->$property = $options[$property];
        }

    }

    /**
     * Determines if this column option can be used in the toolbar search.
     *
     * @return bool true if it can be searched
     */
    public function is_searchable() {
        return (($this->dbdatatype == 'char' || $this->dbdatatype == 'text') &&
                $this->outputformat == 'text' &&
                $this->grouping == 'none' && !$this->aggregate);
    }

} // end of rb_column_option class


