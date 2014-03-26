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
 * @subpackage totara_plan
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->dirroot . '/totara/plan/lib.php');

// Permissions
require_sesskey();
require_capability('totara/hierarchy:updatecompetency', context_system::instance());

$compevid = required_param('c', PARAM_INT);
$linkval = required_param('t', PARAM_INT);
$component = optional_param('type','course', PARAM_ALPHA);

if (!in_array($linkval, $PLAN_AVAILABLE_LINKTYPES)) {
    die(get_string('error:nosuchlinktype', 'totara_plan'));
}

if ($component == 'course') {
    $todb = new stdClass();
    $todb->id = $compevid;
    $todb->linktype = $linkval;
    $result = $DB->update_record('comp_criteria', $todb);

} else if ($component == 'pos') {
    $todb = new stdClass();
    $todb->id = $compevid;
    $todb->linktype = $linkval;
    $result = $DB->update_record('pos_competencies', $todb);

} else if ($component == 'org') {
    $todb = new stdClass();
    $todb->id = $compevid;
    $todb->linktype = $linkval;
    $result = $DB->update_record('org_competencies', $todb);
}


if ($result) {
    echo "OK";
} else {
    echo get_string('error:updatinglinktype', 'totara_plan');
}
