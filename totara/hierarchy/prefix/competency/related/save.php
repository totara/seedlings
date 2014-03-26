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
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))) . '/config.php');
require_once('lib.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');


///
/// Setup / loading data
///

// Competency id
$compid = required_param('id', PARAM_INT);

// Competencies to relate
$relidlist = required_param('add', PARAM_SEQUENCE);

// Non JS parameters
$nojs = optional_param('nojs', false, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$s = optional_param('s', '', PARAM_TEXT);

// Indicates whether current related items, not in $relidlist, should be deleted
$deleteexisting = optional_param('deleteexisting', 0, PARAM_BOOL);

// Get currently-related competencies
if (!$currentlyrelated = comp_relation_get_relations($compid)) {
    $currentlyrelated = array();
}

// Setup page
admin_externalpage_setup('competencymanage', '', array(), '/totara/hierarchy/prefix/competency/related/save.php');

// Check permissions
$sitecontext = context_system::instance();
require_capability('totara/hierarchy:updatecompetency', $sitecontext);

// Setup hierarchy object
$hierarchy = new competency();

// Load competency
if (!$competency = $hierarchy->get_item($compid)) {
    print_error('competencynotfound', 'totara_hierarchy');
}

$str_remove = get_string('remove');

// Parse input
$relidlist = $relidlist ? explode(',', $relidlist) : array();
$time = time();

///
/// Delete removed relationships (if specified)
///
if ($deleteexisting) {
    $removeditems = array_diff($currentlyrelated, $relidlist);
    foreach ($removeditems as $ritem) {
        $DB->delete_records('comp_relations', array('id1' => $compid, 'id2' => $ritem));
        $DB->delete_records('comp_relations', array('id2' => $compid, 'id1' => $ritem));
    }
}


///
/// Add related competencies
///

foreach ($relidlist as $relid) {
    // Check id
    if (!is_numeric($relid)) {
        print_error('baddatanonnumeric', 'totara_hierarchy', null, $relid);
    }

    // Don't relate a competency to itself
    if ($compid == $relid) {
        continue;
    }

    // Check to see if the relationship already exists.
    $alreadyrelated = $DB->get_records_select(
            'comp_relations',
            "(id1 = ? and id2 = ?) or (id1 = ? and id2 = ?)",
            array($compid, $relid, $relid, $compid),
            '',
            'id',
            0,
            1
    );
    if (is_array($alreadyrelated) && count($alreadyrelated) > 0) {
        continue;
    }

    // Load competency
    $related = $hierarchy->get_item($relid);

    // Load framework
    $framework = $hierarchy->get_framework($related->frameworkid);

    // Load type
    $types = $hierarchy->get_types();

    // Add relationship
    $relationship = new stdClass();
    $relationship->id1 = $competency->id;
    $relationship->id2 = $related->id;

    $relationship->id = $DB->insert_record('comp_relations', $relationship);
}

if ($nojs) {
    // If JS disabled, redirect back to original page (only if session key matches)
    $url = ($s == sesskey()) ? $returnurl : $CFG->wwwroot;
    redirect($url);
} else {
    $hierarchy->display_extra_view_info($competency, 'related');
}
