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
 * @subpackage hierarchy
 */



/**
 * Returns the appropriate name of a hierarchy type
 */
function hierarchy_get_type_name($typeid, $shortprefix) {
    global $DB;

    if ($typeid != 0) {
        if ($typename = $DB->get_field($shortprefix . '_type', 'fullname', array('id' => $typeid))) {
            return format_string($typename);
        } else {
            return get_string('unknown');
        }
    } else {
        return get_string('unclassified', 'totara_hierarchy');
    }
}


/**
 * Given an array of custom field objects, return an array of strings describing them
 *
 * @param array $data Array of objects where the objects contain the custom field name and datatypes
 * @return array|false Array of formatted strings describing the fields or false if none found
 */
function hierarchy_get_formatted_custom_fields($data) {
    if (!is_array($data)) {
        return false;
    }
    $fields = array();
    foreach ($data as $item) {
        // object doesn't contain data we expect
        if (!isset($item->id) || !isset($item->fullname) || !isset($item->datatype)) {
            continue;
        }
        $hidden = ($item->hidden) ? get_string('hidden', 'totara_hierarchy') . ' ' : '';
        $fields[$item->id] = format_string($item->fullname) . ' (' . $hidden . get_string('customfieldtype' . $item->datatype, 'totara_customfield') . ')';
    }

    if (count($fields) == 0) {
        return false;
    }

    return $fields;
}


/**
 * Determines if a particular custom field type can be converted to another type
 *
 * Certain field type conversion make no sense - like converting a text field into
 * a checkbox. This function determines which conversions should be permitted.
 *
 * @param string $oldtype Old custom field data type
 * @param string $newtype New custom field data type
 * @return boolean True if a field of the old type can be safely converted to the new type
 */
function hierarchy_allowed_datatype_conversion($oldtype, $newtype) {
    // all okay if type stays the same
    // for 'menu' type we trust the user will ensure the new menu options will
    // match the old ones
    if ($oldtype == $newtype) {
        return true;
    }

    // file type can't be converted to anything else
    if ($oldtype == 'file') {
        return false;
    }

    // no other types can be converted to file, menu or checkbox
    if ($newtype == 'file' || $newtype == 'menu' || $newtype == 'checkbox') {
        return false;
    }

    // all other types can be converted to text or textarea
    if ($newtype == 'text' || $newtype == 'textarea') {
        return true;
    }

    // fail for unknown types
    return false;

}
