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

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/appraisal/lib.php');

// Check if Appraisals are enabled.
appraisal::check_feature_enabled();

$action = optional_param('action', '', PARAM_ACTION);

admin_externalpage_setup('manageappraisals');
$systemcontext = context_system::instance();
require_capability('totara/appraisal:manageappraisals', $systemcontext);

$output = $PAGE->get_renderer('totara_appraisal');

$appraisals = appraisal::get_manage_list();

switch ($action) {
    case 'delete':
        $returnurl = new moodle_url('/totara/appraisal/manage.php');
        $id = required_param('id', PARAM_INT);
        $appraisal = new appraisal($id);
        if ($appraisal->status == appraisal::STATUS_ACTIVE) {
            totara_set_notification(get_string('error:appraisalisactive', 'totara_appraisal'), $returnurl,
                    array('class' => 'notifyproblem'));
        } else {
            $confirm = optional_param('confirm', 0, PARAM_INT);
            if ($confirm == 1) {
                if (!confirm_sesskey()) {
                    print_error('confirmsesskeybad', 'error');
                }
                $appraisal->delete();
                totara_set_notification(get_string('deletedappraisal', 'totara_appraisal'), $returnurl,
                        array('class' => 'notifysuccess'));
            } else {
                $stages = appraisal_stage::fetch_appraisal($appraisal->id);
            }
        }
        break;
    case 'copy':
        if (!confirm_sesskey()) {
            print_error('confirmsesskeybad', 'error');
        }
        $id = required_param('id', PARAM_INT);
        $clonedappraisal = appraisal::duplicate_appraisal($id);
        $returnurl = new moodle_url('/totara/appraisal/general.php', array('id' => $clonedappraisal->id));
        totara_set_notification(get_string('appraisalcloned', 'totara_appraisal'), $returnurl, array('class' => 'notifysuccess'));
        break;
}

echo $output->header();
switch ($action) {
    case 'delete':
        echo $output->heading(get_string('deleteappraisals', 'totara_appraisal', $appraisal->name));
        $stages = isset($stages) ? $stages : array();
        echo $output->confirm_delete_appraisal($appraisal->id, array('stages' => $stages));
        break;
    default:
        echo $output->heading(get_string('manageappraisals', 'totara_appraisal'));
        echo $output->create_appraisal_button();
        echo $output->appraisal_manage_table($appraisals);
}
echo $output->footer();
