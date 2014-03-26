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
 * Class defining a report builder content option
 *
 * A content option contains information about how a content
 * restriction should be applied to a particular source.
 */
/*
 * Class defining a report builder content option
 */
class rb_content_option {
    /**
     * Name of the content restriction to apply against
     *
     * If 'current_org' is chosen, will try to create an instance
     * of a class called 'rb_current_org_content' which should
     * extend {@link rb_base_content}.
     *
     * @access public
     * @var string
     */
    public $classname;

    /**
     * Title for the content restriction
     *
     * Content restrictions can be applied to different fields, so
     * each source needs to provide a human readable title which
     * is used to explain which field it is being applied to.
     *
     * An example might be "The user's current position" if the
     * content restriction was restricting based on that field
     *
     * @access public
     * @var string
     */
    public $title;

    /**
     * The database fields to apply the restriction against
     *
     * A string or array of strings each containing an SQL snippet.
     * If an array is passed the array key is used internally to
     * identify the field. In queries the key is prefixed with
     * 'rb_content_option_' to prevent collisions with other aliases.
     * The alias is also used for the column name when caching a report.
     *
     * @access public
     * @var array|string
     */
    public $fields;

    /**
     * The names of any {@link rb_join::$name} required to get access
     * to the {@link rb_content_option::$fields}. This can be a string
     * or an array of strings if multiple joins are required.
     *
     * Their is no need to include join dependencies, these will
     * be added automatically.
     *
     * @access public
     * @var mixed
     */
    public $joins;

    /**
     * Generate a new rb_content_option instance
     *
     * @param string $classname Name of the content restriction class
     * @param string $title Human readable description of the field
     * @param array $fields Database fields to apply the restriction to
     * @param mixed $joins {@link rb_join::$name} required to access
     *             {@link rb_content_option::$field}
     */
    function __construct($classname, $title, $fields, $joins=null) {

        $this->classname = $classname;
        $this->title = $title;
        // If only one field provided, convert to an array.
        // This provides backward compatibility in the case of a single field.
        if (!is_array($fields)) {
            $fields = array('field' => $fields);
        }
        $this->fields = $fields;
        $this->joins = $joins;
    }

} // end of rb_content_option class
