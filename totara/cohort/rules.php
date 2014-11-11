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
 * @package totara
 * @subpackage cohort
 */
/**
 * This file displays a page with the list of rules for a dynamic cohort
 */
require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/cohort/lib.php');
require_once($CFG->dirroot.'/totara/cohort/rules/lib.php');
require_once($CFG->dirroot.'/totara/cohort/cohort_forms.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

$id = required_param('id', PARAM_INT);
$debug = optional_param('debug', false, PARAM_BOOL);

$url = new moodle_url('/totara/cohort/rules.php', array('id' => $id));
if ($debug) {
    $url->param('debug', $debug);
}
admin_externalpage_setup('cohorts', '', null, $url, array('pagelayout'=>'report'));

$context = context_system::instance();
require_capability('totara/cohort:managerules', $context);

$canapproverules = true;  // TODO: maybe another capability check here?

// Setup custom javascript
local_js(array(
    TOTARA_JS_DIALOG,
    TOTARA_JS_TREEVIEW,
    TOTARA_JS_DATEPICKER
));

// Include cohort rule js module
$args = array('args' => '{"cohortid":' . $id . ',
    "operator_type_cohort":'  . COHORT_OPERATOR_TYPE_COHORT  . ',
    "operator_type_ruleset":' . COHORT_OPERATOR_TYPE_RULESET .'}');
$PAGE->requires->strings_for_js(
    array('error:baddate', 'error:badduration', 'addrule', 'orcohort', 'andcohort', 'or', 'and', 'rulesupdatesuccess', 'rulesupdatefailure'),
    'totara_cohort');
$PAGE->requires->strings_for_js(array('datepickerlongyearregexjs', 'datepickerlongyeardisplayformat'), 'totara_core');
$jsmodule = array(
        'name' => 'totara_cohortrules',
        'fullpath' => '/totara/cohort/rules/ruledialog.js',
        'requires' => array('json'));
$PAGE->requires->js_init_call('M.totara_cohortrules.init', $args, false, $jsmodule);
// Include rule delete js handlers
$PAGE->requires->strings_for_js(array('deleteruleconfirm', 'deleteruleparamconfirm', 'savingrule', 'error:noresponsefromajax',
    'error:badresponsefromajax'), 'totara_cohort');
$jsmodule = array(
        'name' => 'totara_cohortruledelete',
        'fullpath' => '/totara/cohort/rules/ruledelete.js',
        'requires' => array('json'));
$PAGE->requires->js_init_call('M.totara_cohortruledelete.init', null, false, $jsmodule);

///
/// Data
///
$sql = "SELECT c.*, crc.rulesetoperator, crc.status
    FROM {cohort} c
    INNER JOIN {cohort_rule_collections} crc ON c.draftcollectionid = crc.id
    WHERE c.id = ?";
$cohort = $DB->get_record_sql($sql, array($id), '*', MUST_EXIST);

if (!$cohort->cohorttype == cohort::TYPE_DYNAMIC) {
    print_error('error:notdynamiccohort', 'totara_cohort');
}

$rulesets = $DB->get_records('cohort_rulesets', array('rulecollectionid' => $cohort->draftcollectionid), 'sortorder');
if (!$rulesets) {
    $rulesets = array();
}
foreach ($rulesets as &$ruleset) {
    $rules = $DB->get_records('cohort_rules', array('rulesetid' => $ruleset->id), 'sortorder');
    if (!$rules) {
        // todo: empty ruleset... delete it?
    }

    $ruleset->rules = $rules;
}
unset($ruleset);
$customdata = array(
    'cohort' => $cohort,
    'rulesets' => $rulesets
);
$mform = new cohort_rules_form(qualified_me(), $customdata, 'post');


///
/// Actions
///

// Rule changes approval/cancelation
if (($data = data_submitted()) && confirm_sesskey()) {
    if ($canapproverules && !empty($data->approverulechanges)) {
        if (!cohort_rules_approve_changes($cohort)) {
            print_error('error:couldnotapprovechanges', 'totara_cohort');
        }
        totara_set_notification(get_string('rulesapprovesuccess', 'totara_cohort'), $url->out(), array('class' => 'notifysuccess'));
    }
    if ($canapproverules && isset($data->cancelrulechanges)) {
        if (!cohort_rules_cancel_changes($cohort)) {
            print_error('error:couldnotcancelchanges', 'totara_cohort');
        }
        totara_set_notification(get_string('rulescancelsuccess', 'totara_cohort'), $url->out());
    }
}

if ($formdata = $mform->get_data()) {

    // Update the cohort operator?
    if (isset($formdata->cohortoperator) && $formdata->cohortoperator <> $cohort->rulesetoperator) {
        $todb = new stdClass();
        $todb->id = $cohort->draftcollectionid;
        $todb->rulesetoperator = $formdata->cohortoperator;
        $todb->status = COHORT_COL_STATUS_DRAFT_CHANGED;
        $todb->timemodified = time();
        $todb->modifierid = $USER->id;
        $DB->update_record('cohort_rule_collections', $todb);
    }

    if (isset($formdata->rulesetoperator) && is_array($formdata->rulesetoperator)) {
        $operatorschanged = false;
        foreach ($formdata->rulesetoperator as $rulesetid => $operator) {
            if (array_key_exists($rulesetid, $rulesets) && $operator <> $rulesets[$rulesetid]->operator) {
                $todb = new stdClass();
                $todb->id = $rulesetid;
                $todb->operator = $operator;
                $todb->timemodified = time();
                $todb->modifierid = $USER->id;
                $DB->update_record('cohort_rulesets', $todb);
                $operatorschanged = true;
            }
        }

        if ($operatorschanged) {
            $todb = new stdClass;
            $todb->id = $cohort->draftcollectionid;
            $todb->status = COHORT_COL_STATUS_DRAFT_CHANGED;
            $DB->update_record('cohort_rule_collections', $todb);
        }
    }
    add_to_log(SITEID, 'cohort', 'edit rule operators', 'cohort/view.php?id='.$cohort->id, $cohort->idnumber);
    totara_set_notification(get_string('rulesupdatesuccess', 'totara_cohort'), $url->out(), array('class' => 'notifysuccess'));

    // Regenerate the form so that it'll show the correct values for all the operators.
    // (We need to do this because we're showing all the operators as static items, which
    // are not automatically updated by formslib)
    $customdata = array(
        'cohort' => $cohort,
        'rulesets' => $rulesets
    );
    $mform = new cohort_rules_form(qualified_me(), $customdata, 'post');

} else {
    $formdata = array();
    $formdata['cohortoperator'] = $cohort->rulesetoperator;
    foreach ($rulesets as $ruleset) {
        $formdata["rulesetoperator[{$ruleset->id}]"] = $ruleset->operator;
    }
    $mform->set_data($formdata);
}


///
/// Output
///
$strheading = get_string('editrules', 'totara_cohort');
totara_cohort_navlinks($cohort->id, $cohort->name, $strheading);
echo $OUTPUT->header();
// Print out a map of what cohort rules should use which handlers,
// for JS to access
echo <<<JS
<script type="text/javascript">

var ruleHandlerMap = new Array();
JS;
$ruledefs = cohort_rules_list();
foreach ($ruledefs as $groupname => $group) {
    foreach ($group as $typename => $def) {
        /* @var $def cohort_rule_option */
        echo "ruleHandlerMap['{$groupname}-{$typename}'] = '{$def->ui->handlertype}';\n";
    }
}
echo <<<JS

</script>
JS;

echo $OUTPUT->heading(format_string($cohort->name));
echo cohort_print_tabs('editrules', $cohort->id, $cohort->cohorttype, $cohort);

// Verify if the cohort has a broken rule.
$trace = new null_progress_trace();
$cohortbrokenrules = totara_cohort_broken_rules(null, $cohort->id, $trace);
if (!empty($cohortbrokenrules)) {
    totara_display_broken_rules_box();
}

display_approval_action_box($cohort->id, $debug,
    $style=$cohort->status == COHORT_COL_STATUS_DRAFT_CHANGED ? null : 'display:none;');

echo '<div id="reportarea"></div>';

// Print the generated query
if ($debug) {
    $whereclause = totara_cohort_get_dynamic_cohort_whereclause($id);
    echo $OUTPUT->heading(get_string('querydebugheader', 'totara_cohort'), 3);
    echo html_writer::tag('pre', "select count(*) from {user} u where " . $whereclause->sql, array('class' => 'notifymessage'));
    echo $OUTPUT->heading(get_string('querydebugparams', 'totara_cohort'), 3);
    echo html_writer::tag('pre', s(print_r($whereclause->params, true)), array('class' => 'notifymessage'));
}

print '<div id="cohort-rules">';
$mform->display();
print '</div>';
echo $OUTPUT->footer();

function display_approval_action_box($cohortid, $debug=false, $style='display:block') {
    $attrs = array('class' => 'notifynotice clearfix', 'id' => 'cohort_rules_action_box', 'style' => $style);
    echo html_writer::start_tag('div', $attrs);
    $attrs = array('action' => new moodle_url("/totara/cohort/rules.php"), 'method' => 'POST', 'class' => 'approvalform');
    echo html_writer::start_tag('form', $attrs);
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $cohortid));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    if ($debug) {
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'debug', 'value' => 1));
    }
    echo html_writer::start_tag('span');

    echo get_string('cohortruleschanged', 'totara_cohort');
    $attrs = array('type' => 'submit', 'name' => 'approverulechanges', 'value' => get_string('approvechanges', 'totara_cohort'));
    echo html_writer::empty_tag('input', $attrs);
    $attrs = array('type' => 'submit', 'name' => 'cancelrulechanges', 'value' => get_string('cancelchanges', 'totara_cohort'));
    echo html_writer::empty_tag('input', $attrs);
    echo html_writer::end_tag('span');
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('div'); // cohort_rules_action_box
}
