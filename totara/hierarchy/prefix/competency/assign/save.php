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

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');

// non JS page only
// return to the form with the competency set

// Non JS parameters
$nojs = optional_param('nojs', false, PARAM_BOOL);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$s = optional_param('s', '', PARAM_TEXT);
$add = required_param('add',PARAM_SEQUENCE);
// Setup page
admin_externalpage_setup('competencymanage', '', array(), '/totara/hierarchy/prefix/competency/evidence/save.php');

// Check permissions
$sitecontext = context_system::instance();
require_capability('totara/hierarchy:updatecompetency', $sitecontext);

if ($s == sesskey()) {
    $murl = new moodle_url($returnurl);
    $returnurl = $murl->out(false, array('nojs' => 1, 'competencyid' => $add));
} else {
    $returnurl = $CFG->wwwroot;
}
redirect($returnurl);
