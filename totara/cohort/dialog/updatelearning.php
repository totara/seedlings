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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */
/**
 * This file is the ajax handler which adds the selected course/program to a cohort's learning items
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))) .'/config.php');
require_once($CFG->dirroot .'/cohort/lib.php');
global $COHORT_ASSN_VALUES;

// this could take a while
set_time_limit(0);

$context = context_system::instance();
require_capability('moodle/cohort:manage', $context);

require_sesskey();

$type = required_param('type', PARAM_TEXT);
$cohortid = required_param('cohortid', PARAM_INT);

$updateids = optional_param('u', 0, PARAM_SEQUENCE);
$value = optional_param('v', COHORT_ASSN_VALUE_ENROLLED, PARAM_INT);
if (!empty($updateids)) {
    $updateids = explode(',', $updateids);

    foreach ($updateids as $instanceid) {

        $assnid = totara_cohort_add_association($cohortid, $instanceid, $type, $value);
        $logaction = 'add '
            . $COHORT_ASSN_VALUES[$value]
            . ' '
            . (($type == COHORT_ASSN_ITEMTYPE_COURSE) ? 'course' : 'program');
        add_to_log(SITEID, 'cohort', $logaction, 'cohort/view.php?id='.$cohortid, "itemid={$instanceid};associationid={$assnid}");
    }
}

$delid = optional_param('d', 0, PARAM_INT);
if (!empty($delid)) {

    if (!empty($type) && !empty($delid)) {
        totara_cohort_delete_association($cohortid, $delid, $type, $value);
        add_to_log(SITEID, 'cohort', 'remove learning item', "cohort/view.php?id={$cohortid}", "associationid={$delid}");
    }
}
