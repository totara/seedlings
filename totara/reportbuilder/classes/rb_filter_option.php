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
 * Class defining a report builder filter option (search option)
 *
 * A filter option is an object that defines a possible filter
 * within a source. When an administrator includes this filter
 * option in a report, they provide some additional information
 * (such as if it is an advanced option), and a {@link rb_filter}
 * object is created based on the filter option's properties.
 */
class rb_filter_option {
    /**
     * Type of the {@link rb_column} that this filter should act against
     *
     * @access public
     * @var string
     */
    public $type;

    /**
     * Value of the {@link rb_column} that this filter should act against
     *
     * @access public
     * @var string
     */
    public $value;

    /**
     * Text label to appear next to the filter
     *
     * @access public
     * @var string
     */
    public $label;

    /**
     * Name of the kind of {@link rb_filter_type} to display
     *
     * Options include 'text', 'date', 'select', 'number', 'textarea', 'simpleselect'.
     * See {@link rb_filter_type} child classes for a full list of options
     *
     * Each type of filter appears differently, and offers different search options.
     * Some filter types (like select) require additional parameters
     *
     * @access public
     * @var string
     */
    public $filtertype;

    /**
     * Array containing a set of options to be passed directly to the filter
     *
     * The filters vary depending on the filtertype, see each filter class for available
     * options. One or more of these options may be required by the particular filter
     * type.
     *
     * @access public;
     * @var array
     */
    public $filteroptions;

    /**
     * Optional sql snippet representing the field to filter against
     * (instead of using field from a matching column option).
     * If provided the joins and grouping options will also be used
     *
     * @access public;
     * @var string
     */
    public $field;

    /**
     * Optional string or array of join names to be included when
     * filtering using this type
     *
     * @access public;
     * @var string|array
     */
    public $joins;

    /**
     * Optional string representing how the field should be grouped
     * when filtering
     *
     * @access public;
     * @var string
     */
    public $grouping;

    /**
     * Generate a new filter option instance
     *
     * Options provided by an associative array, e.g.:
     *
     * <code>array('selectfunc' => 'yesno')</code>
     *
     * Will provide default values for any optional parameters that aren't set
     *
     * @param string $type Type of the column to base the filter on
     * @param string $value Value of the column to base the filter on
     * @param string $label Text label to appear next to the filter
     * @param string $filtertype Kind of filter this is (text, select, date, etc)
     * @param array $filteroptions Associative array of options to be passed to the filter (optional)
     * @param string $field Optional sql snippet representing the field to filter against (instead of using field from a matching column option). If provided the joins and grouping options will also be used
     * @param string|array $joins Optional string or array of join names to be included when filtering using this type
     * @param string $grouping Optional string representing how the field should be grouped when filtering
     */
    function __construct($type, $value, $label, $filtertype,
        $filteroptions=array(), $field = null, $joins = null,
        $grouping = null) {

        $this->type = $type;
        $this->value = $value;
        $this->label = $label;
        $this->filtertype = $filtertype;
        $this->filteroptions = $filteroptions;
        $this->field = isset($field) ? $field : '';
        $this->joins = isset($joins) ? $joins : array();
        $this->grouping = isset($grouping) ? $grouping : 'none';
    }

    /**
     * Returns an attribute variable used to limit the width of a pulldown
     *
     * This code is required to fix limited width pulldowns in IE. The
     * if (document.all) condition limits the javascript to only affect IE.
     *
     * @return array Array of the correct format to be used by a 'select'
     *               form element
     */
    static function select_width_limiter() {
        return array(
            'class' => 'totara-limited-width-150'
        );
    }

} // end of rb_filter_option class

