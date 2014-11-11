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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_appraisal
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/appraisal/lib.php');
require_once($CFG->dirroot . '/totara/appraisal/appraisal_forms.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

ajax_require_login();

admin_externalpage_setup('manageappraisals');
$systemcontext = context_system::instance();
require_capability('totara/appraisal:managepageelements', $systemcontext);

$appraisalid = optional_param('appraisalid', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT); // Stage id.
$action = optional_param('action', '', PARAM_ACTION);

// If appraisalid is not specified then get it from the stage.
if ($appraisalid < 1) {
    if ($id < 1) {
        throw new appraisal_exception('Stage not found', 23);
    }
    $stage = new appraisal_stage($id);
    $appraisalid = $stage->appraisalid;
}

$stages = appraisal_stage::get_list($appraisalid);
$pages = array();
$stage = null;

if ($stages && count($stages)) {
    if (!$id) {
        $id = current($stages)->id;
    }
    $stage = new appraisal_stage($id);

    if (isset($stage)) {
        $pages = appraisal_page::fetch_stage($stage->id);
        if ($stage->appraisalid != $appraisalid) {
            throw new appraisal_exception('Stage must be within current appraisal');
        }
    }
}

$output = $PAGE->get_renderer('totara_appraisal');

switch ($action) {
    case 'getroles':
        foreach ($stages as $stagerecord) {
            $stage = new appraisal_stage($stagerecord->id);
            $rolestring = html_writer::tag('div', $output->display_roles($stagerecord->roles), array('class' => 'cananswer'));
            $allviewers = $stage->get_roles_involved(appraisal::ACCESS_CANVIEWOTHER);
            $viewers = array_diff_key($allviewers, $stagerecord->roles);
            $rolestring .= html_writer::tag('div', $output->display_roles($viewers), array('class' => 'canview'));
            echo html_writer::tag('div', $rolestring, array('id' => $stage->id, 'class' => 'stageroles'));
        }
        break;
    default:
        echo $output->stage_page_container($stage, $pages);
        break;
}
