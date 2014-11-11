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
 *
 * Class defining a report builder join
 *
 * A join object contains all the information required to
 * generate the SQL to include the join in a query, as well
 * as information about any dependencies (other joins) that
 * the join may have, and its relationship to the table(s)
 * it is joining to.
 */

/**
 * A one-to-one relation
 */
define('REPORT_BUILDER_RELATION_ONE_TO_ONE', 1);
/**
 * A one-to-many relation
 */
define('REPORT_BUILDER_RELATION_ONE_TO_MANY', 2);
/**
 * A many-to-one relation
 */
define('REPORT_BUILDER_RELATION_MANY_TO_ONE', 3);
/**
 * A many-to-many relation
 */
define('REPORT_BUILDER_RELATION_MANY_TO_MANY', 4);

class rb_join {
    /**
     * A name for the join
     *
     * Should be unique within a joinlist and must not use
     * any SQL keywords as it is used as the table alias
     *
     * There is some basic checking to ensure these rules
     * are met in {@link rb_base_source}
     *
     * @access public
     * @var string
     */
    public $name;

    /**
     * Type of join to use
     *
     * Any option that can be put in front of 'JOIN' in
     * a standard SQL query should work.
     *
     * Common options include 'INNER', 'LEFT OUTER' and 'RIGHT OUTER'
     *
     * Where possible use 'LEFT' with ONE_TO_ONE relations
     * as that is optimised for performance by the query builder
     *
     * @access public
     * @var string
     */
    public $type;

    /**
     * Name of the table to join
     *
     * The name should include the moodle database prefix (e.g. mdl_).
     * It is possible to pass a subquery instead of a table name by
     * surrounding a query with brackets, e.g.:
     *
     * <code>(SELECT id, MAX(field) AS maxfield FROM tablename GROUP BY id)</code>
     *
     * @access public
     * @var string
     */
    public $table;

    /**
     * Conditions to apply to complete the join
     *
     * A string that represents the 'ON' part of a SQL join. When referring
     * to a field, always prefix with the {@rb_join::$name} of the join
     * required to access that field, or 'base' if the column is in the base
     * table. To reference the current join, use the current join's name.
     *
     * So if creating a join with the name 'sessions', the conditions might
     * be:
     *
     * <code>sessions.id = base.sessionid</code>
     *
     * to join the id field to the sessionid field in the base table.
     *
     * @access public
     * @var string
     */
    public $conditions;

    /**
     * The relationship between this join and the tables it is joining to
     *
     * If you know that this join will *never* result in a different number
     * of rows then you can use {@link REPORT_BUILDER_RELATION_ONE_TO_ONE}.
     * This is the case if you are performing a LEFT JOIN onto a primary
     * key field, as a LEFT join will never result in *less* rows, and there
     * will never be multiple identical primary keys, so there won't ever
     * be *more* rows.
     *
     * If you known that it is *possible* that this join will lead to more,
     * or less rows you should use a different relation.
     *
     * This field is used to avoid unnecessary joins when counting the total
     * number of records. See {@link reportbuilder::prune_joins()} and
     * {@link rb_join::pruneable()}.
     *
     * @access public
     * @var integer
     */
    public $relation;

    /**
     * The names of any joins that are required by this join
     *
     * This can be a string or an array of strings, referencing the
     * {@link rb_join::$name} property of any joins that this join is
     * dependent on.
     *
     * Dependency on the base table can be assumed, or passed explicitly
     * with a name of 'base'.
     *
     * Dependencies are used to automatically add required tables to
     * the query, and to sort the joins into an order where dependencies
     * are satisfied. See {@link reportbuilder::get_joins()} and
     * {@link reportbuilder::sort_joins()}
     *
     * @access public
     * @var mixed
     */
    public $dependencies;

    /**
     * Generate a new join instance
     *
     * @param string $name Name of the join
     * @param string $type Type of join e.g. 'LEFT OUTER'
     * @param string $table Table or subquery to join
     * @param string $conditions The 'ON' part of the join
     * @param integer $relation Relationship to the joined table
     * @param mixed $dependencies Names of any dependencies required by this table
     */
    function __construct($name, $type, $table, $conditions,
        $relation=null, $dependencies='base') {

        $this->name = $name;
        $this->type = $type;
        $this->table = $table;
        $this->conditions = $conditions;
        $this->relation = $relation;
        $this->dependencies = $dependencies;

    }

    /**
     * Returns true if performing this join won't change the number of records
     *
     * Used by {@link reportbuilder::prune_joins()} to avoid making unnecessary
     * joins when counting records
     */
    public function pruneable() {

        // only left joins can be guaranteed not to change the number
        // of records
        if (!preg_match('/^\s*left(\s+outer)?\s*/i', $this->type)) {
            return false;
        }

        // even left joins can result in more records, unless the table
        // being joined has the a *-to-one relationship
        switch($this->relation) {
        case REPORT_BUILDER_RELATION_ONE_TO_ONE:
        case REPORT_BUILDER_RELATION_MANY_TO_ONE:
            return true;
        default:
            return false;
        }

    }

} // end of rb_join class
