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
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage cohort
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/totara/cohort/cohort_forms.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->dirroot.'/totara/plan/lib.php');
require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');

$context = context_system::instance();
require_capability('moodle/cohort:view', $context);
require_capability('totara/plan:cancreateplancohort', $context);

// Raise timelimit as this could take a while for big cohorts
set_time_limit(0);
raise_memory_limit(MEMORY_HUGE);

define('COHORT_HISTORY_PER_PAGE', 50);

$id = required_param('id', PARAM_INT);

$url = new moodle_url('/totara/cohort/learningplan.php', array('id' => $id));
admin_externalpage_setup('cohorts', '', null, $url, array('pagelayout'=>'report'));

$cohort = $DB->get_record('cohort', array('id' => $id), '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url('/totara/cohort/learningplan.php', array('id' => $id));

$currenturl = qualified_me();

// Javascript include
local_js(array(TOTARA_JS_DIALOG));

$PAGE->requires->strings_for_js(array('confirmcreateplans'), 'totara_plan');
$PAGE->requires->strings_for_js(array('continue', 'cancel'), 'moodle');
$args = array('args' => '{"id":"'.$cohort->id.'"}');
$jsmodule = array(
        'name' => 'totara_cohortplans',
        'fullpath' => '/totara/cohort/dialog/learningplan.js',
        'requires' => array('json', 'totara_core'));
$PAGE->requires->js_init_call('M.totara_cohortplans.init', $args, false, $jsmodule);

$form = new cohort_learning_plan_settings_form($currenturl, array('data' => $cohort));

if ($data = $form->get_data()) {
    if (isset($data->submitbutton)) {

        // Get data needed for logic
        $templateid = $data->plantemplate;
        $manualplan = $data->manualplan;
        $autoplan   = $data->autoplan;
        $completeplan = $data->completeplan;
        $createstatus = $data->planstatus;

        // Get all members
        $audience_members = $DB->get_records('cohort_members', array('cohortid' => $data->cohortid));

        // Get details of template
        $plantemplate = $DB->get_record('dp_template', array('id' => $templateid));

        $createdplancount = 0;

        $createplan = false;
        $sql = 'SELECT DISTINCT cm.userid
                FROM {cohort_members} cm
                WHERE';
        $params = array();
        //are we excluding anyone at all?
        if ($manualplan || $autoplan || $completeplan) {
            $planwhere = 'templateid = ?';
            $params[] = $templateid;
            $whereclauses = array();
            $createdby = array();
            if ($manualplan) {
                $createdby[] = PLAN_CREATE_METHOD_MANUAL;
            }
            if ($autoplan) {
                $createdby[] = PLAN_CREATE_METHOD_COHORT;
            }
            if (!empty($createdby)) {
                list($insql, $inparams) = $DB->get_in_or_equal($createdby);
                $whereclauses[] = " p.createdby $insql";
                $params = array_merge($params, $inparams);
            }
            if ($completeplan) {
                $whereclauses[] = ' p.status = ? ';
                $params[] = DP_PLAN_STATUS_COMPLETE;
            }
            //we only have two clauses now but just in case we add more
            $numclauses = count($whereclauses);
            if ($numclauses > 0) {
                $planwhere .= ' AND (';
                for ($i=0; $i<$numclauses; $i++) {
                    $planwhere .= $whereclauses[$i];
                    if ($i < ($numclauses - 1)) {
                        $planwhere .= ' OR ';
                    }
                }
                $planwhere .= ')';
            }
            //add the exclusion SQL clause
            $sql .= '
                    NOT EXISTS
                        (SELECT p.userid
                        FROM {dp_plan} p
                        WHERE ' . $planwhere . ' AND cm.userid = p.userid)
                    AND ';
        }

        $where = ' cm.cohortid = ?';
        $params[] = $cohort->id;
        $sql .= $where;

        $affected_members = $DB->get_records_sql($sql, $params);
        $now = time();
        $newplans = array();
        $newplanids = array();

        $transaction = $DB->start_delegated_transaction();

        foreach ($affected_members as $member) {
            $plan = new stdClass();
            $plan->templateid = $plantemplate->id;
            $plan->name = $plantemplate->fullname;
            $plan->startdate = $now;
            $plan->enddate = $plantemplate->enddate;
            $plan->userid = $member->userid;
            $plan->status = $createstatus;
            $plan->createdby = PLAN_CREATE_METHOD_COHORT;

            $newplanids[] = $DB->insert_record('dp_plan', $plan);
            unset($plan);
        }

        $plan_history_records = array();

        foreach ($newplanids as $planid) {
            $history = new stdClass;
            $history->planid = $planid;
            $history->status = $createstatus;
            $history->reason = DP_PLAN_REASON_CREATE;
            $history->timemodified = time();
            $history->usermodified = $USER->id;

            $plan_history_records[] = $history;
        }

        // Batch insert history records
        $DB->insert_records_via_batch('dp_plan_history', $plan_history_records);

        // Since all plans are the same template the components
        // list will be the same for all
        $components = array();

        foreach ($newplanids as $planid) {
            $plan = new development_plan($planid);

            if (!$components) {
                $components = $plan->get_components();
            }

            foreach ($components as $componentname => $details) {
                $component = $plan->get_component($componentname);
                if ($component->get_setting('enabled')) {

                    // Automatically add items from this component
                    $component->plan_create_hook();
                }

                // Free memory
                unset($component);
            }

            // Free memory
            unset($plan);
            $createdplancount++;
        }

        // Add record to history table
        $now = time();

        $history = new stdClass();
        $history->cohortid = $cohort->id;
        $history->templateid = $plantemplate->id;
        $history->usercreated = $USER->id;
        $history->timecreated = $now;
        $history->planstatus = $createstatus;
        $history->affectedusers = $createdplancount;
        $history->manual = $manualplan;
        $history->auto = $autoplan;
        $history->completed = $completeplan;
        $DB->insert_record('cohort_plan_history', $history);
        $transaction->allow_commit();
        totara_set_notification(get_string('successfullycreatedplans', 'totara_cohort', $createdplancount), $currenturl, array('class' => 'notifysuccess'));
    }
}

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($cohort->name));
echo cohort_print_tabs('plans', $cohort->id, $cohort->cohorttype, $cohort);

echo $OUTPUT->heading(get_string('createlpforaudience', 'totara_cohort'));

echo get_string('createlpforaudienceblurb', 'totara_cohort');

echo $form->display();

$tableheaders = array(
    get_string('template', 'totara_core'),
    get_string('user'),
    get_string('date'),
    get_string('planstatus', 'totara_plan'),
    get_string('numaffectedusers', 'totara_plan'),
    get_string('manuallycreated', 'totara_plan'),
    get_string('autocreated', 'totara_plan'),
    get_string('complete')
);
$tablecolumns = array(
    'template',
    'user',
    'date',
    'planstatus',
    'numusers',
    'manual',
    'auto',
    'complete'
);

$table = new flexible_table('cohortplancreatehistory');
$table->define_baseurl(qualified_me());
$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);

$table->attributes['class'] = 'fullwidth';

$table->setup();

echo $OUTPUT->heading(get_string('history', 'totara_cohort'));

$history_sql = 'SELECT cph.id,
                       t.fullname as template,
                       u.firstname,
                       u.lastname,
                       cph.planstatus,
                       cph.affectedusers,
                       cph.timecreated,
                       cph.manual,
                       cph.auto,
                       cph.completed
                    FROM {cohort_plan_history} cph
                    JOIN {user} u
                        ON cph.usercreated = u.id
                    JOIN {dp_template} t
                        ON cph.templateid = t.id
                        WHERE cph.cohortid = ?
                    ORDER BY
                        cph.id';

$perpage = COHORT_HISTORY_PER_PAGE;

$countsql = 'SELECT COUNT(*) FROM
                {cohort_plan_history} cph
            JOIN {user} u
              ON cph.usercreated = u.id
            JOIN {dp_template} t
              ON cph.templateid = t.id
            WHERE cph.cohortid = ?';

$totalcount = $DB->count_records_sql($countsql, array($cohort->id));

$table->initialbars($totalcount > $perpage);
$table->pagesize($perpage, $totalcount);

if ($history_records = $DB->get_records_sql($history_sql, array($cohort->id), $table->get_page_start(), $table->get_page_size())) {
    foreach ($history_records as $record) {
        $row = array();

        $row[] = $record->template;

        $user = new stdClass();
        $user->firstname = $record->firstname;
        $user->lastname = $record->lastname;
        $fullname = fullname($user);
        $row[] = $fullname;
        unset($user);
        $row[] = userdate($record->timecreated, get_string('strfdateattime', 'langconfig'));
        switch ($record->planstatus) {
            case DP_PLAN_STATUS_UNAPPROVED:
                $status = get_string('unapproved', 'totara_plan');
                break;

            case DP_PLAN_STATUS_APPROVED:
                $status = get_string('approved', 'totara_plan');
                break;

            default:
                $status = '';
                break;
        }
        $row[] = $status;
        $row[] = $record->affectedusers;
        $row[] = display_yes_no($record->manual);
        $row[] = display_yes_no($record->auto);
        $row[] = display_yes_no($record->completed);

        $table->add_data($row);
    }
}

$table->finish_html();

echo $OUTPUT->footer();

function display_yes_no($value) {
    return (isset($value) && $value == 1) ? get_string('yes') : get_string('no');
}
