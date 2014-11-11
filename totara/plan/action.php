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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage plan
 */

/**
 * This script will perform general plan actions that can be posted from a number of pages
 * This script can also later be used by AJAX requests
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->dirroot.'/totara/message/messagelib.php');

// Check if Learning plans are enabled.
check_learningplan_enabled();

require_login();

///
/// Params
///

// Plan param
$id = required_param('id', PARAM_INT);      // the plan id

// Action params
$approve = optional_param('approve', 0, PARAM_BOOL);
$decline = optional_param('decline', 0, PARAM_BOOL);
$approvalrequest = optional_param('approvalrequest', 0, PARAM_BOOL);
$delete = optional_param('delete', 0, PARAM_BOOL);
$complete = optional_param('complete', 0, PARAM_BOOL);
$reactivate = optional_param('reactivate', 0, PARAM_BOOL);
$reasonfordecision = optional_param('reasonfordecision', '', PARAM_TEXT);

// Is this an ajax call?
$ajax = optional_param('ajax', 0, PARAM_BOOL);
$referer = optional_param('referer', get_referer(false), PARAM_URL);
//making sure that we redirect to somewhere inside platform
//in case passed param is invalid or even HTTP_REFERER is bogus
$referer = clean_param($referer, PARAM_LOCALURL);

if (!confirm_sesskey()) {
    if (empty($ajax)) {
        redirect($referer);
    } else {
        return;
    }
}

$context = context_system::instance();
$PAGE->set_context($context);
$pageparams = array('id' => $id, 'sesskey' => sesskey());
if (!empty($approve)) {$pageparams['approve'] = $approve;}
if (!empty($decline)) {$pageparams['decline'] = $decline;}
if (!empty($approvalrequest)) {$pageparams['approvalrequest'] = $approvalrequest;}
if (!empty($delete)) {$pageparams['delete'] = $delete;}
if (!empty($complete)) {$pageparams['complete'] = $complete;}
if (!empty($reactivate)) {$pageparams['reactivate'] = $reactivate;}
if (!empty($ajax)) {$pageparams['ajax'] = $ajax;}
if (!empty($referer)) {$pageparams['referer'] = $referer;}
$PAGE->set_url(new moodle_url('/totara/plan/action.php', $pageparams));

///
/// Load plan
///
$plan = new development_plan($id);
$PAGE->set_heading(format_string($SITE->fullname));

///
/// Permissions check
///
if (!dp_can_view_users_plans($plan->userid)) {
    print_error('error:nopermissions', 'totara_plan');
}


// @todo: handle action failure alerts
///
/// Approve
///
if (!empty($approve)) {
    if (in_array($plan->get_setting('approve'), array(DP_PERMISSION_ALLOW, DP_PERMISSION_APPROVE))) {
       $plan->set_status(DP_PLAN_STATUS_APPROVED, DP_PLAN_REASON_MANUAL_APPROVE, $reasonfordecision);
       $plan->send_approved_alert($reasonfordecision);
       totara_set_notification(get_string('planapproved', 'totara_plan', $plan->name), $referer, array('class' => 'notifysuccess'));
    } else {
        if (empty($ajax)) {
            totara_set_notification(get_string('nopermission', 'totara_plan'), $referer, array('class' => 'notifysuccess'));
        }
    }
}


///
/// Decline
///
if (!empty($decline)) {
    if (in_array($plan->get_setting('approve'), array(DP_PERMISSION_ALLOW, DP_PERMISSION_APPROVE))) {
        $plan->set_status(DP_PLAN_STATUS_UNAPPROVED, DP_PLAN_REASON_MANUAL_DECLINE, $reasonfordecision);
        $plan->send_declined_alert($reasonfordecision);
        add_to_log(SITEID, 'plan', 'declined', "view.php?id={$plan->id}", $plan->name);
        totara_set_notification(get_string('plandeclined', 'totara_plan', $plan->name), $referer, array('class' => 'notifysuccess'));
    } else {
        if (empty($ajax)) {
            totara_set_notification(get_string('nopermission', 'totara_plan'), $referer);
        }
    }
}


///
/// Approval request
///
if (!empty($approvalrequest)) {
    global $DB;
    $manager = $plan->get_manager();

    // If plan is a draft, must be asking for plan approval
    if ($plan->status == DP_PLAN_STATUS_UNAPPROVED) {
        if ($plan->get_setting('approve') == DP_PERMISSION_REQUEST) {
            // If a learner is updating their plan and now needs approval, notify manager
            if ($USER->id == $plan->userid) {
                if ($manager) {
                    //check for existing approval requests for that plan
                    $sql = 'SELECT id
                            FROM {message}
                            WHERE useridfrom = ?
                            AND useridto = ?
                            AND ' . $DB->sql_compare_text('contexturlname', 255) . ' = ?
                            AND ' . $DB->sql_like('contexturl', '?');

                    $duplicates = $DB->get_records_sql($sql, array($plan->userid, $manager->id, $plan->name, "%view.php?id={$plan->id}"));
                    if (empty($duplicates)) {
                        //only send email/task if there is not already one for that learning plan
                        $plan->send_manager_plan_approval_request();
                        add_to_log(SITEID, 'plan', 'requested approval', "view.php?id={$plan->id}", $plan->name);
                    }
                }
                else {
                    totara_set_notification(get_string('nomanager', 'totara_plan'), $referer);
                }
            }
            totara_set_notification(get_string('approvalrequestsent', 'totara_plan', $plan->name), $referer, array('class' => 'notifysuccess'));
            // @todo: send approval request email to relevant user(s)
        } else {
            if (empty($ajax)) {
                totara_set_notification(get_string('nopermission', 'totara_plan'), $referer);
            }
        }
    }
    // If plan is active, must be asking for item approval
    else if ($plan->status == DP_PLAN_STATUS_APPROVED) {

        // Check this is the owner of the plan
        if ($plan->role !== 'learner') {
            if (empty($ajax)) {
                totara_set_notification(get_string('nopermission', 'totara_plan'), $referer);
            }
        }

        // Get unapproved items
        $unapproved = $plan->get_unapproved_items();
        if ($unapproved) {
            if ($manager) {
                $plan->send_manager_item_approval_request($unapproved);
            }
            else {
                totara_set_notification(get_string('nomanager', 'totara_plan'), $referer);
            }

            if (empty($ajax)) {
                totara_set_notification(get_string('approvalrequestsent', 'totara_plan', $plan->name), $referer, array('class' => 'notifysuccess'));
            }

        }
    }
}

///
/// Delete
///
if (!empty($delete)) {
    if ($plan->get_setting('delete') == DP_PERMISSION_ALLOW) {
        $confirm = optional_param('confirm', 0, PARAM_BOOL);

        if (!$confirm && empty($ajax)) {
            // Show confirmation message
            $PAGE->set_title(get_string('confirmdeleteplantitle', 'totara_plan', $plan->name));
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('deleteplan', 'totara_plan'));
            $confirmurl = new moodle_url(qualified_me());
            $confirmurl->param('confirm', 'true');
            $confirmurl->param('referer', $referer);
            $strdelete = get_string('checkplandelete', 'totara_plan', $plan->name);
            $strbreak = html_writer::empty_tag('br') . html_writer::empty_tag('br');
            echo $OUTPUT->confirm("{$strdelete}{$strbreak}".format_string($plan->name), $confirmurl->out(), $referer);

            echo $OUTPUT->footer();
            exit;
        } else {
            // Delete the plan
            $is_active = $plan->is_active();
            $plan->delete();

            if ($plan->userid == $USER->id) {
                // don't bother unless the plan was active
                if ($is_active) {
                    // User was deleting their own plan, notify their manager
                    $plan->send_alert(false,'learningplan-remove','plan-remove-manager-short','plan-remove-manager-long');
                }
            } else {
                // Someone else was deleting the learner's plan, notify the learner
                $plan->send_alert(true,'learningplan-remove','plan-remove-learner-short','plan-remove-learner-long');
            }
            add_to_log(SITEID, 'plan', 'deleted', "index.php?userid={$plan->userid}", "{$plan->name} (ID:{$plan->id})");
            totara_set_notification(get_string('plandeletesuccess', 'totara_plan', $plan->name), $referer, array('class' => 'notifysuccess'));
        }
    } else {
        if (empty($ajax)) {
            totara_set_notification(get_string('nopermission', 'totara_plan'), $referer);
        }
    }
}


///
/// Complete
///
if (!empty($complete)) {
    if ($plan->get_setting('completereactivate') >= DP_PERMISSION_ALLOW) {
        $confirm = optional_param('confirm', 0, PARAM_BOOL);

        if (!$confirm && empty($ajax)) {
            // Show confirmation message
            echo $OUTPUT->header();
            $confirmurl = new moodle_url(qualified_me());
            $confirmurl->param('confirm', 'true');
            $confirmurl->param('referer', $referer);
            $strcomplete = get_string('checkplancomplete11', 'totara_plan', $plan->name);
            $strbreak = html_writer::empty_tag('br') . html_writer::empty_tag('br');
            echo $OUTPUT->confirm("{$strcomplete}{$strbreak}", $confirmurl->out(), $referer);

            echo $OUTPUT->footer();
            exit;
        } else {
            // Set plan status to complete
            $plan->set_status(DP_PLAN_STATUS_COMPLETE, DP_PLAN_REASON_MANUAL_COMPLETE);
            $plan->send_completion_alert();
            add_to_log(SITEID, 'plan', 'completed', "view.php?id={$plan->id}", $plan->name);
            totara_set_notification(get_string('plancompletesuccess', 'totara_plan', $plan->name), $referer, array('class' => 'notifysuccess'));
        }
    } else {
        if (empty($ajax)) {
            totara_set_notification(get_string('nopermission', 'totara_plan'), $referer);
        }
    }
}

///
/// Reactivate
///
if (!empty($reactivate)) {
    require_once($CFG->dirroot . '/totara/plan/reactivate_form.php');
    require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

    if ($plan->get_setting('completereactivate') >= DP_PERMISSION_ALLOW) {
        $form = new plan_reactivate_form(null, compact('id','referer'));

        if ($form->is_cancelled()) {
            redirect($referer);
        }

        if ($data = $form->get_data()) {

            $new_date = $data->enddate;

            $referer = $data->referer;

            // Reactivate plan
            if (!$plan->reactivate_plan($new_date)) {
                totara_set_notification(get_string('planreactivatefail', 'totara_plan', $plan->name), $referer);
            } else {
                add_to_log(SITEID, 'plan', 'reactivated', "view.php?id={$plan->id}", $plan->name);
                totara_set_notification(get_string('planreactivatesuccess', 'totara_plan', $plan->name), $referer, array('class' => 'notifysuccess'));
            }
        }

        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('planreactivate', 'totara_plan'), 2, 'reactivateheading');

        $form->display();

        echo build_datepicker_js('#id_enddate');

        echo $OUTPUT->footer();
        exit;

    } else {
        if (empty($ajax)) {
            totara_set_notification(get_string('nopermission', 'totara_plan'), $referer);
        }
    }
}

if (empty($ajax)) {
    totara_set_notification(get_string('error:incorrectparameters', 'totara_plan'), $referer);
}
