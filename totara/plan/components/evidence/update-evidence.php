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
 * @author Russell England <russell.england@totaralms.com>
 * @package totara
 * @subpackage plan
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');
require_once($CFG->dirroot.'/totara/plan/components/evidence/evidence.class.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

require_login();

$planid = required_param('planid', PARAM_INT);
$componentname = required_param('component', PARAM_ALPHA);
$itemid = required_param('itemid', PARAM_INT);
$idlist = optional_param('update', null, PARAM_SEQUENCE);

// Updated evidence lists
if ($idlist == null) {
    $idlist = array();
} else {
    $idlist = explode(',', $idlist);
}


$context = context_system::instance();
$PAGE->set_context($context);
require_capability('totara/plan:accessplan', $context);

$plan = new development_plan($planid);
$evidence = new dp_evidence_relation($planid, $componentname, $itemid);
$plancompleted = $plan->status == DP_PLAN_STATUS_COMPLETE;
$component = $plan->get_component($componentname);
$canupdate = $component->can_update_items();

if (!$canupdate) {
    print_error('error:cannotupdatecompetencies', 'totara_plan');
}
if ($plancompleted) {
    print_error('plancompleted', 'totara_plan');
}

$evidence->update_linked_evidence($idlist);

if ($evidence->linked_evidence_exists()) {
    echo $evidence->list_linked_evidence(!$plancompleted && $canupdate);
} else {
    $evidencename = strtolower(get_string('evidence', 'totara_plan'));
    echo html_writer::tag('p', get_string('nolinkedx', 'totara_plan', $evidencename),
            array('class' => 'noitems-assignevidence'));
}
