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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_hierarchy
 */
/**
 * competency/evidenceitem/lib.php
 *
 * Library of functions related to competency evidence items
 *
 * Note: Functions in this library should have names beginning with "comp_evitem_",
 * in order to avoid name collisions
 *
 */

/**
 * Retrieve an array of all the competencies related to the present one
 *
 * @global object $CFG
 * @param int $compid
 * @return array
 */
function comp_relation_get_relations($compid) {
    global $DB;

    $returnrecs = array();
    $reclist = $DB->get_records_sql("SELECT id, id1, id2 FROM {comp_relations} WHERE id1 = ? OR id2 = ?", array($compid, $compid));
    if (is_array($reclist)) {

        foreach ($reclist as $rec) {
            if ($rec->id1 != $compid) {
                $returnrecs[$rec->id1] = $rec->id1;
            } else if ($rec->id2 != $compid) {
                $returnrecs[$rec->id2] = $rec->id2;
            }
        }
    }
    return $returnrecs;
}
