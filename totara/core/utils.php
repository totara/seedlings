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
 * @subpackage totara_core
 */

/*
 * This file contains general purpose utility functions
 */

// Constants defined to be used in totara_search_for_value function.
define('TOTARA_SEARCH_OP_EQUAL', 0);
define('TOTARA_SEARCH_OP_NOT_EQUAL', 1);
define('TOTARA_SEARCH_OP_GREATER_THAN', 2);
define('TOTARA_SEARCH_OP_GREATER_THAN_OR_EQUAL', 3);
define('TOTARA_SEARCH_OP_LESS_THAN', 4);
define('TOTARA_SEARCH_OP_LESS_THAN_OR_EQUAL', 5);

// Type of icon.
define ('TOTARA_ICON_TYPE_COURSE', 'course');
define ('TOTARA_ICON_TYPE_PROGRAM', 'program');

/**
 * Pop N items off the beginning of $items and return them as an array
 *
 * @param array &$items Array of items (passed by reference)
 * @param integer $number Number of items to remove from the start of $items
 *
 * @return array Array of $number items from $items, or false if $items is empty
 */
function totara_pop_n(&$items, $number) {
    if (count($items) == 0) {
        // none left, return false
        return false;
    } else if (count($items) < $number) {
        // return all remaining items
        $return = $items;
        $items = array();
        return $return;
    } else {
        // return the first N and shorten $items
        $return = array_slice($items, 0, $number, true);
        $items = array_slice($items, $number, null, true);
        return $return;
    }
}


/**
 * Return the proper SQL to compare a field to multiple items
 *
 * By default it uses IN but can be negated (to NOT IN) using the 3rd argument
 *
 * The output from this is safe for Oracle, which has a limit of 1000 items in an
 * IN () call.
 *
 * @param string $field The field to compare against
 * @param array $items Array of items. If text they must already be quoted.
 * @param boolean $negate Return code for NOT IN () instead of IN ()
 *
 * @return array In the form array(sql, params) The SQL needed to compare $field to the items
 *              in $items and associated parameters
 */
function sql_sequence($field, $items, $type=SQL_PARAMS_QM, $negate = false) {
    global $DB;

    if (!is_array($items) || count($items) == 0) {
        return ($negate) ? array('1=1', array()) : array('1=0', array());
    }

    $not = $negate ? 'NOT' : '';
    if ($DB->get_dbfamily() != 'oracle' || $count($items) <= 1000) {
        list($sql, $params) = $DB->get_in_or_equal($items, $type, 'param', !$negate);

        return array(" $field " . $sql, $params);
    }

    $out = array();
    while ($some_items = totara_pop_n($items, 1000)) {
        list($sql, $params) = $DB->get_in_or_equal($items, $type, 'param', !$negate);
        $out[] =" $field " . $sql;
        $outparams = array_merge($outparams, $params);

    }

    $operator = $negate ? ' AND ' : ' OR ';
    return array('(' . implode($operator, $out) . ')', $outparams);
}



/**
 * Check if a specified language string already exists
 *
 * @deprecated Use string_exists() instead
 */
function check_string($identifier, $module='', $extralocations=null) {
    debugging('The function check_string() is deprecated. Use string_exists() instead.', DEBUG_DEVELOPER);
    return string_exists($identifier, $module);
}


/**
 * Returns an attribute variable used to limit the width of a pulldown
 *
 * This code is required to fix limited width pulldowns in IE. The
 * if(document.all) condition limits the javascript to only affect IE.
 *
 * @return array Array of the correct format to be used by a 'select'
 *               form element
 */
function totara_select_width_limiter() {
    return array(
        'class' => 'totara-limited-width'
    );
}

/**
 * Helper function to group a set of records, keyed by a particular field
 *
 * Returns and associative array where the keys are unique values of the grouped
 * field and the values are arrays of objects that contain the grouped key
 *
 * @param object $rs A recordset as returned by get_recordset() or similar functions
 * @param string $field Name of the field to group by. Must be a field in $rs
 *
 * @return array|false Associative array of results or false if none found or $field invalid
 */
function totara_group_records($rs, $field) {
    if (!$rs) {
        return false;
    }
    $out = array();
    foreach ($rs as $row) {
        // $field must exist in recordset
        if (!isset($row->$field)) {
            return false;
        }
        if (array_key_exists($row->$field, $out)) {
            $out[$row->$field][] = $row;
        } else {
            $out[$row->$field] = array($row);
        }
    }
    return $out;
}

/**
 * Convert an integer to a 'vancode'
 *
 * Vancodes are a system used by Drupal for sorting hierarchical comments they provide a way of efficiently sorting
 * hierarchical structures.
 *
 * A vancode is a base 36 representation of an integer, prefixed by a base 36 digit: (length(base 36 string) - 1)
 *
 * The advantages of this format are:
 *  - It automatically sorts in the correct order without natural order sorting
 *
 *    e.g. 2.1.4, 2.1.8, 2.1.200 sorts as: 2.1.200, 2.1.4, 2.1.8
 *    but with vancodes:
 *        02.01.04, 02.01.08, 02.01.15k sorts correctly as: 02.01.04, 02.01.08, 02.01.15k
 *
 *  - It is relatively compact, meaning the db field doesn't need to be too big
 *    This is important as we need the field to be big enough to support long as well as deep trees
 *
 *  @param integer Integer to convert to a vancode. Must be < pow(36, 10)
 *  @return string Vancode for the specified integer
 */
function totara_int2vancode($int = 0) {
    $num = base_convert((int) $int, 10, 36);
    $length = strlen($num);
    return chr($length + ord('0') - 1) . $num;
}

/**
 * Convert a vancode to an integer
 *
 * See {@link totara_int2vancode()} for details
 *
 * @param string $char Vancode to convert. Must be <= '9zzzzzzzzzz'
 * @return integer The integer representation of the specified vancode
 */
function totara_vancode2int($char = '00') {
    return base_convert(substr($char, 1), 36, 10);
}

/**
 * Increment a vancode by N (or decrement if negative)
 *
 * Returns the vancode, incremented by the specified amount
 *
 * See {@link totara_int2vancode()} for details
 *
 * @param string $char Vancode to increment
 * @param integer $inc Number to increment by (optional, defaults to 1)
 * @return string Vancode of $char + increment
 */
function totara_increment_vancode($char, $inc = 1) {
    return totara_int2vancode(totara_vancode2int($char) + (int) $inc);
}

/**
 * Given a set of items as an associative array of id/parentid pairs, and an
 * item, returns an array of the item's descendants (including the item)
 *
 * @param array $items Associative array
 *                     (e.g. array(['itemid'] => 'parentid', ['itemid2'] = 'parentid2') )
 * @param integer $itemid ID of the item to build the path for
 *
 * @return An array of IDs, from the first parent right back to the item
 */
function totara_get_lineage($items, $itemid, $pathsofar = array()) {
    // protection against bad items list and circular references
    if (!is_array($items) || in_array($itemid, $pathsofar)) {
        return $pathsofar;
    }

    // add this item to the list
    array_unshift($pathsofar, $itemid);
    if (!isset($items[$itemid]) || empty($items[$itemid])) {
        // finished when an item has no parent
        return $pathsofar;
    } else {
        // keep going
        return totara_get_lineage($items, $items[$itemid], $pathsofar);
    }
}
/**
 * Filter an array of objects whose property matches the condition of the searched word
 *
 * @param array $arraytosearch array of objects in which the search is performed
 * @param string $property property that needs to be evaluated
 * @param int $operator operator used in the search
 * @param mixed $searchvalue the value that need to be found
 * @return $objectsfound An array of objects filtered by the search
 */
function totara_search_for_value($arraytosearch, $property, $operator, $searchvalue) {
    $objectsfound = array();
    switch ($operator) {
        case TOTARA_SEARCH_OP_EQUAL:
            $objectsfound = array_filter($arraytosearch,
                function ($objtofind) use($searchvalue, $property) {
                    return $objtofind->{$property} == $searchvalue;
                }
            );
            break;
        case TOTARA_SEARCH_OP_NOT_EQUAL:
            $objectsfound = array_filter($arraytosearch,
                function ($objtofind) use($searchvalue, $property) {
                    return $objtofind->{$property} != $searchvalue;
                }
            );
            break;
        case TOTARA_SEARCH_OP_GREATER_THAN:
            $objectsfound = array_filter($arraytosearch,
                function ($objtofind) use($searchvalue, $property) {
                    return $objtofind->{$property} > $searchvalue;
                }
            );
            break;
        case TOTARA_SEARCH_OP_GREATER_THAN_OR_EQUAL:
            $objectsfound = array_filter($arraytosearch,
                function ($objtofind) use($searchvalue, $property) {
                    return $objtofind->{$property} >= $searchvalue;
                }
            );
            break;
        case TOTARA_SEARCH_OP_LESS_THAN:
            $objectsfound = array_filter($arraytosearch,
                function ($objtofind) use($searchvalue, $property) {
                    return $objtofind->{$property} < $searchvalue;
                }
            );
            break;
        case TOTARA_SEARCH_OP_LESS_THAN_OR_EQUAL:
            $objectsfound = array_filter($arraytosearch,
                function ($objtofind) use($searchvalue, $property) {
                    return $objtofind->{$property} <= $searchvalue;
                }
            );
            break;
    }

    return $objectsfound;
}
