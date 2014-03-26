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
 * Class defining a report builder parameter
 *
 * A parameter is a restriction that can be applied to a report
 * by adding a certain field to the URL. If a report has a
 * parameter option enabled, then adding this to the url:
 *
 * <code>report.php?id=X&name=[value]</code>
 *
 * will add a restriction that limits the results to those with
 * the field $field equal to [value].
 */
class rb_param {
    /**
     * Name for the parameter
     *
     * This is the string that must be added to the URL to activate
     * the restriction
     *
     * @access public
     * @var string
     */
    public $name;

    /**
     * Database field to apply the restriction to
     *
     * This should include the join name as a prefix, e.g:
     *
     * <code>course.fullname</code>
     *
     * @access public
     * @var string
     */
    public $field;

    /**
     * The alias for database field used in cache and query
     *
     * It must not contain separators (like .) and be unique for different fields or
     * same fields in different tables
     *
     * @var string
     */
    public $fieldalias;

    /**
     * One or more join names required to access $field
     *
     * Either a string or an array of strings containing
     * names of {@link rb_join} objects that are required
     * to access the $field field
     *
     * @access public
     * @var mixed
     */
    public $joins;

    /**
     * Value of the parameter as set in the URL
     *
     * @access public
     * @var string
     */
    public $value;

    /**
     * Type of the parameter (int or string)
     *
     * @access public
     * @var string
     */
    public $type;

    /**
     * Generate a rb_param instance
     *
     * @param string $name Name of the parameter
     * @paramoptions array Array of {@link rb_param_option}
     *                     objects to search through to
     *                     populate this object
     */
    function __construct($name, $paramoptions) {

        $this->name = $name;

        foreach ($paramoptions as $paramoption) {
            if ($paramoption->name == $name) {
                $this->field = $paramoption->field;
                $this->joins = $paramoption->joins;
                $this->type  = $paramoption->type;
                $tohash = preg_match('/[\ \,\(\)\{\}\"\\\']/', $paramoption->field);
                if (!$tohash) {
                    $this->fieldalias = get_class($this).'_'.str_replace('.', '_', $paramoption->field);
                } else {
                    $this->fieldalias = get_class($this).'_'.substr(md5($paramoption->field), 0, 10);
                }
                break;
            }
        }
    }

} // end of rb_param class
