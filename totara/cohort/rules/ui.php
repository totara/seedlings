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
 * @subpackage cohort/rules
 */
/**
 * This class defines the cohort_rule_ui class and its subclasses, which
 * handle the front-end stuff for rules for dynamic cohorts
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
require_once($CFG->dirroot.'/totara/cohort/rules/lib.php');
require_once($CFG->dirroot.'/lib/formslib.php');


/**
 * An empty form, useful for individual UIs to create their own mini-forms
 */
class emptyruleuiform extends moodleform {
    public function __construct($action){
        parent::__construct($action, null, 'post', '', null, true, 'cohortruledialogform');
    }
    public function definition(){}

}

/**
 * Base class for a cohort ui. This handles all the content that goes inside the dialog for the rule,
 * also processing the input from the dialog, and printing a description of the rule
 */
abstract class cohort_rule_ui {
    /**
     * These variables will match one of the group & names in the rule definition list
     * @var string
     */
    public $group, $name;

    public $ruleinstanceid;

    /**
     * A list of the parameters this rule passes on to its sqlhandler. (The sqlhandler's $param
     * variable should match exactly.)
     * @var array
     */
    public $params = array(
        'operator' => 0,
        'lov' => 1
    );

    /**
     * The actual values to the parameters (if we're printing a dialog to edit an existing rule instance)
     * @var unknown_type
     */
    public $paramvalues = array();

    /**
     * Which dialog handler type should be used. The dialog handler types are defined in cohort/rules/ruledialog.js.php
     * @var string
     */
    public $handlertype = '';

    public function setGroupAndName($group, $name) {
        $this->group = $group;
        $this->name = $name;
    }

    public function setParamValues($paramvalues) {
        $this->paramvalues = $paramvalues;
        foreach ($paramvalues as $k=>$v) {
            $this->{$k} = $v;
        }
    }

    /**
     *
     * @param array $hidden hidden variables to add to forms in the dialog (if needed)
     * @param int $ruleinstanceid The instance of the rule, or false if for a new rule
     */
    abstract public function printDialogContent($hidden=array(), $ruleinstanceid=false);

    /**
     *
     * @param cohort_rule_sqlhandler $sqlhandler
     */
    abstract public function handleDialogUpdate($sqlhandler);

    /**
     * Get the description of the rule, to be printed on the cohort's rules list page
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    abstract public function getRuleDescription($ruleid, $static=true);

    /**
     * Print the user params (used in logging)
     */
    public function printParams() {
        $ret = '';
        foreach ($this->params as $k=>$v) {
            $ret .= $k.':'.print_r($this->{$k}, true)."\n";
        }
        return $ret;
    }

    /**
     * Validate the response
     */
    public function validateResponse() {
        return true;
    }

    public function param_delete_action_icon($paramid) {
        global $OUTPUT;

        return $OUTPUT->action_icon('#', new pix_icon('i/bullet_delete', get_string('deleteruleparam', 'totara_cohort'), 'totara_core', array('class' => 'ruleparam-delete', 'ruleparam-id' => $paramid)));
    }
}

/**
 * For cohorts that use the form handler as their UI
 */
abstract class cohort_rule_ui_form extends cohort_rule_ui {

    public $handlertype = 'form';
    public $formclass = 'emptyruleuiform';

    /**
     *
     * @var emptyruleuiform
     */
    public $form = null;

    public function validateResponse() {
        $form = $this->constructForm();
        if (!$form->is_validated()){
            return false;
        }
        return true;
    }

    public function constructForm(){
        global $CFG;
        if ($this->form == null) {
            $this->form = new $this->formclass($CFG->wwwroot.'/totara/cohort/rules/ruledetail.php');

            /* @var $mform MoodleQuickForm */
            $mform = $this->form->_form;

            // Add hidden variables
            $mform->addElement('hidden', 'update', 1);
            $mform->setType('update', PARAM_INT);

            $this->form->set_data($this->addFormData());
            $this->addFormFields($mform);
        }
        return $this->form;
    }

    /**
     *
     * @param array $hidden An array of values to be passed into the form as hidden variables
     */
    public function printDialogContent($hidden=array(), $ruleinstanceid=false) {
        global $OUTPUT;

        echo $OUTPUT->heading(get_string('ruledialogdesc', 'totara_cohort', $this->description), '2', 'cohort-rule-dialog-heading');
        echo $OUTPUT->box_start('cohort-rule-dialog-setting');

        $form = $this->constructForm();
        foreach ($hidden as $name=>$value) {
            $form->_form->addElement('hidden', $name, $value);
        }
        $form->display();
        echo $OUTPUT->box_end();
    }

    /**
     * Get items to add to the form's formdata
     * @return array The data to add to the form
     */
    protected function addFormData() {
        return $this->paramvalues;
    }

    /**
     * Add form fields to this form's dialog. (This should usually be over-ridden by subclasses.)
     * @param MoodleQuickForm $mform
     */
    protected function addFormFields(&$mform) {
        $mform->addElement('static', 'noconfig', '', get_string('ruleneedsnoconfiguration', 'totara_cohort'));
    }
}


/**
 * UI for a rule that is defined by a text field (which takes a comma-separated list of values) and an equal/not-equal operator.
 */
class cohort_rule_ui_text extends cohort_rule_ui_form {
    public $params = array(
        'equal' => 0,
        'listofvalues' => 1
    );

    /**
     *
     * @param string $description Brief description of this rule
     * @param string $example Example text to put below the text field
     */
    public function __construct($description, $example) {
        $this->description = $description;
        $this->example = $example;
    }

    /**
     * Fill in default form data. For this dialog, we need to take the listofvalues and concatenate it
     * into a comma-separated list
     * @return array
     */
    protected function addFormData() {
        // Figure out starting data
        $formdata = array();
        if (isset($this->equal)) {
            $formdata['equal'] = $this->equal;
        }
        if (isset($this->listofvalues)) {
            $formdata['listofvalues'] = implode(',',$this->listofvalues);
        }
        return $formdata;
    }

    /**
     * Form elements for this dialog. That'll be the equal/notequal menu, and the text field
     * @param MoodleQuickForm $mform
     */
    protected function addFormFields(&$mform) {

        // Put everything in one row to make it look cooler
        global $COHORT_RULES_OP_IN_LIST;
        $row = array();
        $row[0] = $mform->createElement(
            'select',
            'equal',
            '',
            $COHORT_RULES_OP_IN_LIST
        );
        $row[1] = $mform->createElement('text', 'listofvalues', '');
        $mform->addGroup($row, 'row1', ' ', ' ', false);
        if (isset($this->example)) {
            $mform->addElement('static', 'exampletext', '', $this->example);
        }

        // Make sure they filled in the text field
        $mform->addGroupRule(
            'row1',
                array(
                    1 => array(
                        array(0 => get_string('error:mustpickonevalue', 'totara_cohort'), 1 => 'callback', 2 => 'validate_emptyruleuiform', 3 => 'client')
                    )
                )
        );

        $error = get_string('error:mustpickonevalue', 'totara_cohort');
        $isemptyopt = COHORT_RULES_OP_IN_ISEMPTY;

        // Allow empty value for ​​listofvalues as long as the rule is "is empty"
        $js = <<<JS
<script type="text/javascript">
function validate_emptyruleuiform() {
    var sucess = true;

    if ($('#id_listofvalues').val() === '' && $('#id_equal').val() !== '$isemptyopt') {
        if ($('#id_error_listofvalues').length == 0 ) {
            $('div#fgroup_id_row1 > fieldset').prepend('<span id="id_error_listofvalues" class="error">{$error}</span><br>');
        }
        sucess = false;
    }
    return sucess;
}
</script>
JS;
        $mform->addElement('html', $js);
    }

    /**
     * Get the description of this rule for the list of rules
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    public function getRuleDescription($ruleid, $static=true) {
        global $COHORT_RULES_OP_IN_LIST;
        if (!isset($this->equal) || !isset($this->listofvalues)) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }

        $strvar = new stdClass();
        $strvar->desc = $this->description;
        $strvar->join = $COHORT_RULES_OP_IN_LIST[$this->equal];

        // Show list of values only if the rule is different from "is_empty"
        $strvar->vars = '';
        if ($this->equal != COHORT_RULES_OP_IN_ISEMPTY) {
            $strvar->vars = '"' . htmlspecialchars(implode('", "', $this->listofvalues)) . '"';
        }

        return get_string('ruleformat-descjoinvars', 'totara_cohort', $strvar);
    }

    /**
     * Process the data returned by this UI element's form elements
     * @param cohort_rule_sqlhandler $sqlhandler
     */
    public function handleDialogUpdate($sqlhandler) {
        $equal = required_param('equal', PARAM_INT);
        $listofvalues = required_param('listofvalues', PARAM_RAW);
        $listofvalues = explode(',', $listofvalues);
        array_walk(
            $listofvalues,
            function(&$value, $key){
                $value = trim($value);
            }
        );
        $this->equal = $sqlhandler->equal = $equal;
        $this->listofvalues = $sqlhandler->listofvalues = $listofvalues;
        $sqlhandler->write();
    }
}


/**
 * UI for a rule defined by a multi-select menu, and a equals/notequals operator
 */
class cohort_rule_ui_menu extends cohort_rule_ui_form {
    public $params = array(
        'equal' => 0,
        'listofvalues' => 1
    );

    /**
     * The list of options in the menu. $value=>$label
     * @var array
     */
    public $options;

    /**
     * Create a menu, passing in the list of options
     * @param $menu mixed An array of menu options (value=>label), or a user_info_field1 id
     */
    public function __construct($description, $options){
        $this->description = $description;

        // This may be a string rather than a proper array, but we'll wait to clean
        // it up until it's actually needed.
        $this->options = $options;
    }


    /**
     * The form fields needed for this dialog. That'll be, the "equal/notequal" menu, plus
     * the menu of options. Since the menu of options is a multiple select, it needs validation
     * @param MoodleQuickForm $mform
     */
    protected function addFormFields(&$mform) {

        // Put the two menus on one row so it'll look cooler
        $row = array();
        $row[0] = $mform->createElement(
            'select',
            'equal',
            '',
            array(
                COHORT_RULES_OP_IN_EQUAL=>get_string('equalto','totara_cohort'),
                COHORT_RULES_OP_IN_NOTEQUAL=>get_string('notequalto', 'totara_cohort')
            )
        );
        if (is_object($this->options)) {
            $options = $this->options_from_sqlobj($this->options);
        } else {
            $options = $this->options;
        }
        $row[1] = $mform->createElement(
            'select',
            'listofvalues',
            '',
            $options,
            array('size' => 10)
        );
        // todo: The UI mockup shows a fancy ajax thing to add/remove selected items.
        // For now, using a humble multi-select
        $row[1]->setMultiple(true);
        $mform->addGroup($row, 'row1', ' ', ' ', false);

        // Make sure they selected at least one item from the multi-select. Sadly, formslib's
        // client-side stuff is broken for multi-selects (because it adds "[]" to their name),
        // so we'll need to do this as a custom callback rule. And because it only executes
        // custom callback rules if the field actually contains a value, we'll key it to the
        // equal/notequal menu, which will always have a value.
        $mform->addGroupRule(
            'row1',
                array(
                    0=>array(
                        array(0=>get_string('error:mustpickonevalue', 'totara_cohort'), 1=>'callback',2=>'validate_menu', 3=>'client')
                    )
                )
        );
        $js = <<<JS
<script type="text/javascript">
function validate_menu(value) {
    return $('#id_listofvalues option:selected').length;
}
</script>
JS;
        $mform->addElement('html', $js);
    }


    /**
     * Process the data returned by this UI element's form elements
     * @param cohort_rule_sqlhandler $sqlhandler
     */
    public function handleDialogUpdate($sqlhandler) {
        $equal = required_param('equal', PARAM_INT);
        $listofvalues = required_param_array('listofvalues', PARAM_TEXT);
        if (!is_array($listofvalues)) {
            $listofvalues = array($listofvalues);
        }
        array_walk(
            $listofvalues,
            function(&$value, $key){
                $value = trim($value);
            }
        );
        $this->equal = $sqlhandler->equal = $equal;
        $this->listofvalues = $sqlhandler->listofvalues = $listofvalues;
        $sqlhandler->write();
    }

    /**
     * Get the description of this rule for the list of rules
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    public function getRuleDescription($ruleid, $static=true) {
        global $COHORT_RULES_OP_IN;
        if (!isset($this->equal) || !isset($this->listofvalues)) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }

        $strvar = new stdClass();
        $strvar->desc = $this->description;
        $strvar->join = get_string("is{$COHORT_RULES_OP_IN[$this->equal]}to", 'totara_cohort');

        if (is_object($this->options)) {
            $selected = $this->options_from_sqlobj($this->options, $this->listofvalues);
        } else {
            $selected = array_intersect_key($this->options, array_flip($this->listofvalues));
        }
        // append the list of selected items
        $strvar->vars = '"' . htmlspecialchars(implode('", "', $selected)) .'"';

        return get_string('ruleformat-descjoinvars', 'totara_cohort', $strvar);
    }

    /**
     * Retrieve menu options by constructing sql string from an sql object
     * and then querying the database
     *
     * @param object $sqlobj the sql object instance to construct the query from
     *                      e.g stdClass Object
                                (
                                    [select] => DISTINCT data AS mkey, data AS mval
                                    [from] => {user_info_data}
                                    [where] => fieldid = ?
                                    [orderby] => data
                                    [valuefield] => data
                                    [sqlparams] => Array
                                        (
                                            [0] => 1
                                        )

                                )
     * @param array $selectedvals selected values (optional)
     * @return array of menu options
     */
    protected function options_from_sqlobj($sqlobj, $selectedvals=null) {
        global $DB;

        $sql = "SELECT {$sqlobj->select} FROM {$sqlobj->from} ";

        $sqlparams = array();
        if ($selectedvals !== null) {
            if (!empty($selectedvals)) {
                list($sqlin, $sqlparams) = $DB->get_in_or_equal($selectedvals);
            } else {
                // dummiez to ensure nothing gets returned :D
                $sqlin = ' IN (?) ';
                $sqlparams = array(0);
            }
        }
        if (empty($sqlobj->where)) {
            $sql .= ' WHERE ';
        } else {
            $sql .= " WHERE {$sqlobj->where} ";
        }
        if (!empty($sqlin)) {
            $sql .= " AND {$DB->sql_compare_text($sqlobj->valuefield, 255)} {$sqlin} ";
        }

        if (!empty($sqlobj->orderby)) {
            $sql .= " ORDER BY {$sqlobj->orderby}";
        }

        if (!empty($sqlobj->sqlparams)) {
            $sqlparams = array_merge($sqlobj->sqlparams, $sqlparams);
        }

        return $DB->get_records_sql_menu($sql, $sqlparams, 0, COHORT_RULES_UI_MENU_LIMIT);
    }
}


/**
 * UI for a rule that indicates whether or not a checkbox is ticked
 */
class cohort_rule_ui_checkbox extends cohort_rule_ui_menu {
    public $params = array(
        'equal' => 0,
        'listofvalues' => 1
    );

    /**
     * The list of options in the menu. $value=>$label
     * @var array
     */
    public $options;

    /**
     * Create a menu, passing in the list of options
     * @param $menu mixed An array of menu options (value=>label), or a user_info_field1 id
     */
    public function __construct($description, $options=false){
        $this->description = $description;

        // This may be a string rather than a proper array, but we'll wait to clean
        // it up until it's actually needed.
        if (!$options){
            $this->options = array(
                0=>get_string('checkboxno', 'totara_cohort'),
                1=>get_string('checkboxyes', 'totara_cohort')
            );
        } else {
            $this->options = $options;
        }
    }

    /**
     * The form elements needed for this UI (just the "checked/not-checked" menu!)
     * @param MoodleQuickForm $mform
     */
    protected function addFormFields(&$mform) {
        $mform->addElement(
            'select',
            'listofvalues',
            '',
            $this->options
        );
    }


    /**
     * Process the data returned by this UI element's form elements
     * @param cohort_rule_sqlhandler $sqlhandler
     */
    public function handleDialogUpdate($sqlhandler) {
        $listofvalues = required_param('listofvalues', PARAM_BOOL);
        if (is_array($listofvalues)) {
            $listofvalues = array_pop($listofvalues);
        }
        // Checkbox operator is always "equal"
        $this->equal = $sqlhandler->equal = 1;
        $this->listofvalues = $sqlhandler->listofvalues = (int) $listofvalues;
        $sqlhandler->write();
        $this->listofvalues = array($listofvalues);
    }
}


/**
 * An empty form with validation for a cohort_rule_ui_date
 */
class ruleuidateform extends emptyruleuiform {
    public function validation($data, $files){
        $errors = parent::validation($data, $files);

        // If they haven't ticked the radio button (somehow), then print an error text over the top row,
        // and highlight the bottom row but without any error text
        if (empty($data['fixedordynamic']) || !in_array($data['fixedordynamic'], array(1,2))) {
            $errors['beforeafterrow'] = get_string('error:baddateoption', 'totara_cohort');
            $errors['durationrow'] = ' ';
        }

        if (
            $data['fixedordynamic'] == 1
            && (
                empty($data['beforeafterdate'])
                || !preg_match('/^[0-9]{1,2}[\/\-][0-9]{1,2}[\/\-](19|20)?[0-9]{2}$/', $data['beforeafterdate'])
            )
        ) {
            $errors['beforeafterrow'] = get_string('error:baddate', 'totara_cohort');
        }

        if (
            $data['fixedordynamic'] == 2
            && (
                !isset($data['durationdate'])
                || !preg_match('/^[0-9]+$/', $data['durationdate'])
            )
        ) {
            $errors['durationrow'] = get_string('error:badduration', 'totara_cohort');
        }

        return $errors;
    }
}


/**
 * UI for a rule that needs a date picker
 */
class cohort_rule_ui_date extends cohort_rule_ui_form {

    public $params = array(
        'operator' => 0,
        'date' => 0,
    );

    public $description;

    public $formclass = 'ruleuidateform';

    public function __construct($description){
        $this->description = $description;
    }

    /**
     * Fill in the default form values. For this dialog, we need to specify which of the two
     * rows is active based on the selected operator. And if it's the date row, we need to
     * format the date from a timestamp to a user date
     */
    protected function addFormData() {
        global $CFG;

        // Set up default values and stuff
        $formdata = array();
        // default
        $formdata['fixedordynamic'] = 1;
        // todo: make this configurable!
        $formdata['beforeafterdate'] = get_string('datepickerlongyearplaceholder', 'totara_core');
        if (isset($this->operator)) {
            if ($this->operator == COHORT_RULE_DATE_OP_AFTER_FIXED_DATE || $this->operator == COHORT_RULE_DATE_OP_BEFORE_FIXED_DATE) {
                $formdata['fixedordynamic'] = 1;
                $formdata['beforeaftermenu'] = $this->operator;
                if (!empty($this->date)) {
                    // todo: make this configurable!
                    $formdata['beforeafterdate'] = userdate($this->date, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false);
                }
            } else if (
                    in_array(
                        $this->operator,
                        array(
                            COHORT_RULE_DATE_OP_BEFORE_PAST_DURATION,
                            COHORT_RULE_DATE_OP_WITHIN_PAST_DURATION,
                            COHORT_RULE_DATE_OP_WITHIN_FUTURE_DURATION,
                            COHORT_RULE_DATE_OP_AFTER_FUTURE_DURATION
                        )
                    )
            ) {
                $formdata['fixedordynamic'] = 2;
                $formdata['durationmenu'] = $this->operator;
                if (isset($this->date)) {
                    $formdata['durationdate'] = $this->date;
                }
            } else {
                $formdata['fixedordynamic'] = 1;
            }
        }
        return $formdata;
    }

    /**
     * Form fields for this dialog. We have the elements on two rows, with the top row being for before/after a fixed date,
     * and the bottom row being for before/after/within a fixed present/past duration. A radio button called "fixedordynamic"
     * indicates which one is selected
     *
     * @param MoodleQuickForm $mform
     */
    public function addFormFields(&$mform) {
        global $CFG;

        // Put everything on two rows to make it look cooler.
        $row = array();
        $row[0] = $mform->createElement('radio', 'fixedordynamic', '', '', 1);
        $row[1] = $mform->createElement(
            'select',
            'beforeaftermenu',
            '',
            array(
                COHORT_RULE_DATE_OP_BEFORE_FIXED_DATE=>get_string('datemenufixeddatebefore', 'totara_cohort'),
                COHORT_RULE_DATE_OP_AFTER_FIXED_DATE=>get_string('datemenufixeddateafter', 'totara_cohort')
            )
        );
        $row[2] = $mform->createElement('text', 'beforeafterdate', '');
        $mform->addGroup($row, 'beforeafterrow', ' ', ' ', false);

        $datepickerjs = <<<JS
<script type="text/javascript">

    $(function() {
        $('#id_beforeafterdate').datepicker(
            {
                dateFormat: '
JS;
        $datepickerjs .= get_string('datepickerlongyeardisplayformat', 'totara_core');
        $datepickerjs .= <<<JS
',
                showOn: 'both',
                buttonImage: M.util.image_url('t/calendar'),
                buttonImageOnly: true,
                beforeShow: function() { $('#ui-datepicker-div').css('z-index', 1600); },
                constrainInput: true
            }
        );
    });
    </script>
JS;
        $mform->addElement('html', $datepickerjs);

        $row = array();
        $row[0] = $mform->createElement('radio', 'fixedordynamic', '', '', 2);
        $row[1] = $mform->createElement(
            'select',
            'durationmenu',
            '',
            array(
                COHORT_RULE_DATE_OP_BEFORE_PAST_DURATION =>   get_string('datemenudurationbeforepast', 'totara_cohort'),
                COHORT_RULE_DATE_OP_WITHIN_PAST_DURATION =>   get_string('datemenudurationwithinpast', 'totara_cohort'),
                COHORT_RULE_DATE_OP_WITHIN_FUTURE_DURATION => get_string('datemenudurationwithinfuture', 'totara_cohort'),
                COHORT_RULE_DATE_OP_AFTER_FUTURE_DURATION =>  get_string('datemenudurationafterfuture', 'totara_cohort'),
            )
        );
        $row[2] = $mform->createElement('text', 'durationdate', '');
        $row[3] = $mform->createElement('static', '', '', get_string('durationdays', 'totara_cohort'));
        $mform->addGroup($row, 'durationrow', ' ', ' ', false);

        $mform->disabledIf('beforeaftermenu','fixedordynamic','neq',1);
        $mform->disabledIf('beforeafterdate','fixedordynamic','neq',1);
        $mform->disabledIf('durationmenu','fixedordynamic','neq',2);
        $mform->disabledIf('durationdate','fixedordynamic','neq',2);
    }

    /**
     * Print a description of the rule in text, for the rules list page
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    public function getRuleDescription($ruleid, $static=true) {
        global $CFG, $COHORT_RULE_DATE_OP;

        if (!isset($this->operator) || !isset($this->date)) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }

        $strvar = new stdClass();
        $strvar->desc = $this->description;

        switch ($this->operator) {
            case COHORT_RULE_DATE_OP_BEFORE_FIXED_DATE:
            case COHORT_RULE_DATE_OP_AFTER_FIXED_DATE:
                $a = userdate($this->date, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false);
                break;
            case COHORT_RULE_DATE_OP_BEFORE_PAST_DURATION:
            case COHORT_RULE_DATE_OP_WITHIN_PAST_DURATION:
            case COHORT_RULE_DATE_OP_WITHIN_FUTURE_DURATION:
            case COHORT_RULE_DATE_OP_AFTER_FUTURE_DURATION:
                $a = $this->date;
                break;
        }

        $strvar->vars = get_string("dateis{$COHORT_RULE_DATE_OP[$this->operator]}", 'totara_cohort', $a);

        return get_string('ruleformat-descvars', 'totara_cohort', $strvar);
    }

    /**
     *
     * @param cohort_rule_sqlhandler $sqlhandler
     */
    public function handleDialogUpdate($sqlhandler){
        $fixedordynamic = required_param('fixedordynamic', PARAM_INT);
        switch($fixedordynamic) {
            case 1:
                $operator = required_param('beforeaftermenu', PARAM_INT);
                $date = totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'), required_param('beforeafterdate', PARAM_TEXT));
                break;
            case 2:
                $operator = required_param('durationmenu', PARAM_INT);
                // Convert number to seconds
                $date = required_param('durationdate', PARAM_INT);
                break;
            default:
                return false;
        }
        $this->operator = $sqlhandler->operator = $operator;
        $this->date = $sqlhandler->date = $date;
        $sqlhandler->write();
    }
}


require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_hierarchy.class.php');
class totara_dialog_content_hierarchy_multi_cohortrule extends totara_dialog_content_hierarchy_multi {

    /**
    * Returns markup to be used in the selected pane of a multi-select dialog
    *
    * @param   $elements    array elements to be created in the pane
    * @return  $html
    */
    public function populate_selected_items_pane($elements) {

        $operatormenu = array();
        $operatormenu[1] = get_string('equalto', 'totara_cohort');
        $operatormenu[0] = get_string('notequalto', 'totara_cohort');
        $selected = isset($this->equal) ? $this->equal : '';
        $html = html_writer::select($operatormenu, 'equal', $selected, array(),
            array('id' => 'id_equal', 'class' => 'cohorttreeviewsubmitfield'));

        $childmenu = array();
        $childmenu[0] = get_string('includechildrenno', 'totara_cohort');
        $childmenu[1] = get_string('includechildrenyes', 'totara_cohort');
        $selected = isset($this->includechildren) ? $this->includechildren : '';
        $html .= html_writer::select($childmenu, 'includechildren', $selected, array(),
            array('id' => 'id_includechildren', 'class' => 'cohorttreeviewsubmitfield'));

        return $html . parent::populate_selected_items_pane($elements);
    }
}

require_once($CFG->dirroot.'/totara/hierarchy/lib.php');
class cohort_rule_ui_picker_hierarchy extends cohort_rule_ui {
    public $params = array(
        'equal'=>0,
        'includechildren'=>0,
        'listofvalues'=>1,
    );
    public $handlertype = 'treeview';
    public $prefix;
    public $shortprefix;

    /**
     * @param string $description Brief description of this rule
     */
    public function __construct($description, $prefix) {
        $this->description = $description;
        $this->prefix = $prefix;
        $this->shortprefix = hierarchy::get_short_prefix($prefix);
    }

    public function printDialogContent($hidden=array(), $ruleinstanceid=false) {
        global $CFG, $DB;
        require_once($CFG->libdir.'/adminlib.php');

        require_once($CFG->dirroot.'/totara/hierarchy/prefix/competency/lib.php');
        require_once($CFG->dirroot.'/totara/core/js/lib/setup.php');


        ///
        /// Setup / loading data
        ///

        // Competency id
//        $compid = required_param('id', PARAM_INT);

        // Parent id
        $parentid = optional_param('parentid', 0, PARAM_INT);

        // Framework id
        $frameworkid = optional_param('frameworkid', 0, PARAM_INT);

        // Only return generated tree html
        $treeonly = optional_param('treeonly', false, PARAM_BOOL);

        // should we show hidden frameworks?
        $showhidden = optional_param('showhidden', false, PARAM_BOOL);

        // check they have permissions on hidden frameworks in case parameter is changed manually
        $context = context_system::instance();
        if ($showhidden && !has_capability('totara/hierarchy:updatecompetencyframeworks', $context)) {
            print_error('nopermviewhiddenframeworks', 'hierarchy');
        }

        // show search tab instead of browse
        $search = optional_param('search', false, PARAM_BOOL);

        // Setup page
        $alreadyrelated = array();
        $hierarchy = $this->shortprefix;
        if ($ruleinstanceid) {
            $sql = "SELECT hier.id, hier.fullname
                FROM {{$hierarchy}} hier
                INNER JOIN {cohort_rule_params} crp
                    ON hier.id=" . $DB->sql_cast_char2int('crp.value') . "
                INNER JOIN {{$hierarchy}_framework} fw
                    ON hier.frameworkid = fw.id
                WHERE crp.ruleid = {$ruleinstanceid} AND crp.name='listofvalues'
                ORDER BY fw.sortorder, hier.sortthread
                ";
            $alreadyselected = $DB->get_records_sql($sql);
            if (!$alreadyselected) {
                $alreadyselected = array();
            }
        } else {
            $alreadyselected = array();
        }

        ///
        /// Display page
        ///
        // Load dialog content generator
        $dialog = new totara_dialog_content_hierarchy_multi_cohortrule($this->prefix, $frameworkid, $showhidden);

        // Toggle treeview only display
        $dialog->show_treeview_only = $treeonly;

        // Load items to display
        $dialog->load_items($parentid);

        if (!empty($hidden)) {
            $dialog->urlparams = $hidden;
        }

        // Set disabled/selected items
        $dialog->disabled_items = $alreadyrelated;
        $dialog->selected_items = $alreadyselected;
        if (isset($this->equal)) {
            $dialog->equal = $this->equal;
        }
        if (isset($this->includechildren)) {
            $dialog->includechildren = $this->includechildren;
        }

        // Set title
        $dialog->select_title = '';
        $dialog->selected_title = '';

        // Display
        $markup = $dialog->generate_markup();
        // Hack to get around the hack that prevents deleting items via dialogs
        $markup = str_replace('<td class="selected" ', '<td class="selected selected-shown" ', $markup);
        echo $markup;
    }

    public function handleDialogUpdate($sqlhandler){
        $equal = required_param('equal', PARAM_BOOL);
        $includechildren = required_param('includechildren', PARAM_BOOL);
        $listofvalues = required_param('selected', PARAM_SEQUENCE);
        $listofvalues = explode(',',$listofvalues);
        $this->includechildren = $sqlhandler->includechildren = (int) $includechildren;
        $this->equal = $sqlhandler->equal = (int) $equal;
        $this->listofvalues = $sqlhandler->listofvalues = $listofvalues;
        $sqlhandler->write();
    }

    /**
     * Get the description of the rule, to be printed on the cohort's rules list page
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    public function getRuleDescription($ruleid, $static=true) {
        global $CFG, $COHORT_RULES_OP_IN, $DB;

        if (
            !isset($this->equal)
            || !isset($this->listofvalues)
            || !is_array($this->listofvalues)
            || !count($this->listofvalues)
        ) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }

        $strvar = new stdClass();
        $strvar->desc = $this->description;
        $strvar->join = get_string("is{$COHORT_RULES_OP_IN[$this->equal]}to", 'totara_cohort');
        if ($this->includechildren) {
            $strvar->ext = get_string('orachildof', 'totara_cohort');
        }

        list($sqlin, $sqlparams) = $DB->get_in_or_equal($this->listofvalues);
        $sqlparams[] = $ruleid;
        $hierarchy = $this->shortprefix;
        $sql = "SELECT h.id, h.fullname, h.sortthread, hfw.sortorder, crp.id AS paramid
            FROM {{$hierarchy}} h
            INNER JOIN {{$hierarchy}_framework} hfw ON h.frameworkid = hfw.id
            INNER JOIN {cohort_rule_params} crp ON h.id = " . $DB->sql_cast_char2int('crp.value') . "
            WHERE h.id {$sqlin}
            AND crp.name = 'listofvalues' AND crp.ruleid = ?
            ORDER BY hfw.sortorder, h.sortthread";
        $items = $DB->get_records_sql($sql, $sqlparams);
        if (!$items) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }

        foreach ($items as $i => $h) {
            $value = '"' . $h->fullname . '"';
            if (!$static) {
                $value .= $this->param_delete_action_icon($h->paramid);
            }
            $items[$i] = html_writer::tag('span', $value, array('class' => 'ruleparamcontainer'));
        };

        $paramseparator = html_writer::tag('span', ', ', array('class' => 'ruleparamseparator'));
        $strvar->vars = implode($paramseparator, $items);

        if (!empty($strvar->ext)) {
            return get_string('ruleformat-descjoinextvars', 'totara_cohort', $strvar);
        } else {
            return get_string('ruleformat-descjoinvars', 'totara_cohort', $strvar);
        }
    }
}


require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_courses.class.php');
class totara_dialog_content_cohort_rules_courses extends totara_dialog_content_courses {

    /**
    * Returns markup to be used in the selected pane of a multi-select dialog
    *
    * @param   $elements    array elements to be created in the pane
    * @return  $html
    */
    public function populate_selected_items_pane($elements) {
        $html = $this->cohort_rule_ui->getExtraSelectedItemsPaneWidgets();
        return $html . parent::populate_selected_items_pane($elements);
    }
}

abstract class cohort_rule_ui_picker_course_program extends cohort_rule_ui {
    public $handlertype = 'treeview';
    protected $pickertype;

    /**
     * @param string $description Brief description of this rule
     */
    public function __construct($description, $pickertype) {
        $this->description = $description;
        $this->pickertype = $pickertype;
    }


    public function printDialogContent($hidden=array(), $ruleinstanceid=false) {
        global $CFG, $DB;

        if ($this->pickertype == COHORT_PICKER_COURSE_COMPLETION) {
            require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_courses.class.php');
        } else if ($this->pickertype == COHORT_PICKER_PROGRAM_COMPLETION) {
            require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_programs.class.php');
        } else {
            echo get_string('error:typecompletion', 'totara_cohort');
            return;
        }

        ///
        /// Setup / loading data
        ///

        // Category id
        $categoryid = optional_param('parentid', 'cat0', PARAM_ALPHANUM);

        // Strip cat from begining of categoryid
        $categoryid = (int) substr($categoryid, 3);

        ///
        /// Setup dialog
        ///

        // Load dialog content generator.
        if ($this->pickertype == COHORT_PICKER_COURSE_COMPLETION) {
            $dialog = new totara_dialog_content_cohort_rules_courses($categoryid);
        } else {
            $dialog = new totara_dialog_content_cohort_rules_programs($categoryid);
        }

        // Set type to multiple.
        $dialog->type = totara_dialog_content::TYPE_CHOICE_MULTI;
        $dialog->selected_title = '';

        $dialog->urlparams = $hidden;

        // Add data.
        if ($this->pickertype == COHORT_PICKER_COURSE_COMPLETION) {
            $dialog->load_courses();
        } else {
            $dialog->load_programs();
        }

        // Set selected items.
        if ($ruleinstanceid) {
            if ($this->pickertype == COHORT_PICKER_COURSE_COMPLETION) {
                $sql = "SELECT course.id, course.fullname
                        FROM {course} course
                        INNER JOIN {cohort_rule_params} crp
                            ON course.id=" . $DB->sql_cast_char2int('crp.value') . "
                        WHERE crp.ruleid = ? and crp.name='listofids'
                        ORDER BY course.fullname
                        ";
            } else {
                $sql = "SELECT program.id, program.fullname
                        FROM {prog} program
                        INNER JOIN {cohort_rule_params} crp
                            ON program.id=" . $DB->sql_cast_char2int('crp.value') . "
                        WHERE crp.ruleid = ? and crp.name='listofids'
                        ORDER BY program.fullname
                        ";
            }
            $alreadyselected = $DB->get_records_sql($sql, array($ruleinstanceid));
            if (!$alreadyselected) {
                $alreadyselected = array();
            }
        } else {
            $alreadyselected = array();
        }
        $dialog->selected_items = $alreadyselected;

        // Set unremovable items.
        $dialog->unremovable_items = array();

        // Semi-hack to allow for callback to this ui class to generate some elements of the treeview.
        $dialog->cohort_rule_ui = $this;

        // Display.
        $markup = $dialog->generate_markup();

        echo $markup;
    }

    /**
     * Provide extra elements to insert into the top of the "selected items" pane of the treeview
     */
    abstract public function getExtraSelectedItemsPaneWidgets();
}

class cohort_rule_ui_picker_course_allanynotallnone extends cohort_rule_ui_picker_course_program {
    public $params = array(
        'operator' => 0,
        'listofids' => 1
    );

    public function getExtraSelectedItemsPaneWidgets(){
        $operatormenu = array();
        $operatormenu[COHORT_RULE_COMPLETION_OP_ALL] = get_string('completionmenuall', 'totara_cohort');
        $operatormenu[COHORT_RULE_COMPLETION_OP_ANY] = get_string('completionmenuany', 'totara_cohort');
        $operatormenu[COHORT_RULE_COMPLETION_OP_NOTALL] = get_string('completionmenunotall', 'totara_cohort');
        $operatormenu[COHORT_RULE_COMPLETION_OP_NONE] = get_string('completionmenunotany', 'totara_cohort');
        $selected = isset($this->operator) ? $this->operator : '';

        return html_writer::select($operatormenu, 'operator', $selected, array(),
            array('id' => 'id_operator', 'class' => 'cohorttreeviewsubmitfield'));
    }

    public function handleDialogUpdate($sqlhandler){
        $operator = required_param('operator', PARAM_INT);
        $listofids = required_param('selected', PARAM_SEQUENCE);
        $listofids = explode(',',$listofids);
        $this->operator = $sqlhandler->operator = $operator;
        $this->listofids = $sqlhandler->listofids = $listofids;
        $sqlhandler->write();
    }

    /**
     * Get the description of this rule for the list of rules
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    public function getRuleDescription($ruleid, $static=true) {
        global $DB;
        if (!isset($this->operator) || !isset($this->listofids)) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }

        $strvar = new stdClass();
        switch ($this->operator) {
            case COHORT_RULE_COMPLETION_OP_ALL:
                $strvar->desc = get_string('ccdescall', 'totara_cohort');
                break;
            case COHORT_RULE_COMPLETION_OP_ANY:
                $strvar->desc = get_string('ccdescany', 'totara_cohort');
                break;
            case COHORT_RULE_COMPLETION_OP_NOTALL:
                $strvar->desc = get_string('ccdescnotall', 'totara_cohort');
                break;
            default:
                $strvar->desc = get_string('ccdescnotany', 'totara_cohort');
        }

        list($sqlin, $sqlparams) = $DB->get_in_or_equal($this->listofids);
        $sqlparams[] = $ruleid;
        $sql = "SELECT c.id, c.fullname, crp.id AS paramid
            FROM {course} c
            INNER JOIN {cohort_rule_params} crp ON c.id = " . $DB->sql_cast_char2int('crp.value') . "
            WHERE c.id {$sqlin}
            AND crp.name = 'listofids' AND crp.ruleid = ?
            ORDER BY sortorder, fullname";
        $courselist = $DB->get_records_sql($sql, $sqlparams);

        foreach ($courselist as $i => $c) {
            $value = '"' . format_string($c->fullname) . '"';
            if (!$static) {
                $value .= $this->param_delete_action_icon($c->paramid);
            }
            $courselist[$i] = html_writer::tag('span', $value, array('class' => 'ruleparamcontainer'));
        };

        $paramseparator = html_writer::tag('span', ', ', array('class' => 'ruleparamseparator'));
        $strvar->vars .= implode($paramseparator, $courselist);

        return get_string('ruleformat-descvars', 'totara_cohort', $strvar);
    }
}

class cohort_rule_ui_picker_course_duration extends cohort_rule_ui_picker_course_program {
    public $params = array(
        'operator' => 0,
        'date' => 0,
        'listofids' => 1
    );

    public function getExtraSelectedItemsPaneWidgets(){
        $html = '<div class="mform cohort-treeview-dialog-extrafields">';
        $operatormenu = array();
        $operatormenu[COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN] = get_string('completiondurationmenulessthan', 'totara_cohort');
        $operatormenu[COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN] = get_string('completiondurationmenumorethan', 'totara_cohort');
        $selected = isset($this->operator) ? $this->operator : '';
        $html .= html_writer::select($operatormenu, 'operator', $selected, array(),
            array('id' => 'id_operator', 'class' => 'cohorttreeviewsubmitfield'));

        $html .= '<fieldset>';
        $html .= '<input class="cohorttreeviewsubmitfield" id="completionduration" name="date" value="';
        if (isset($this->date)) {
            $html .= htmlspecialchars($this->date);
        }
        $html .= '" /> ' . get_string('completiondurationdays', 'totara_cohort');
        $html .= '</fieldset>';
        $html .= '</div>';
        $validnumberstr = get_string('error:badduration', 'totara_cohort');
        $html .= <<<JS

<script type="text/javascript">
$(function() {
    var valfunc = function(element){
        element = $(element);
        var parent = element.parent();
        if (!element.val().match(/[1-9]+[0-9]*/)){
            parent.addClass('error');
            if ( $('#id_error_completionduration').length == 0 ) {
                parent.prepend('<span id="id_error_completionduration" class="error">{$validnumberstr}</span>');
            }
            return false;
        } else {
            $('#id_error_completionduration').remove();
            parent.removeClass('error');
            return true;
        }
    };
    $('#completionduration').get(0).cohort_validation_func = valfunc;
    $('#completionduration').change(
        function(){
            valfunc(this);
        }
    );
});
</script>

JS;
        return $html;
    }

    public function handleDialogUpdate($sqlhandler){
        $date = required_param('date', PARAM_INT);
        $operator = required_param('operator', PARAM_INT);
        $listofids = required_param('selected', PARAM_SEQUENCE);
        $listofids = explode(',',$listofids);
        $this->date = $sqlhandler->date = $date;
        $this->operator = $sqlhandler->operator = $operator;
        $this->listofids = $sqlhandler->listofids = $listofids;
        $sqlhandler->write();
    }

    /**
     * Get the description of this rule for the list of rules
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    public function getRuleDescription($ruleid, $static=true) {
        global $DB;
        if (!isset($this->operator) || !isset($this->listofids)) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }
        switch ($this->operator) {
            case COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN:
                $descstr = 'ccdurationdesclessthan';
                break;
            case COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN:
                $descstr = 'ccdurationdescmorethan';
                break;
        }

        $strvar = new stdClass();
        $strvar->desc = get_string($descstr, 'totara_cohort', $this->date);

        list($sqlin, $sqlparams) = $DB->get_in_or_equal($this->listofids);
        $sqlparams[] = $ruleid;
        $sql = "SELECT c.id, c.fullname, crp.id AS paramid
            FROM {course} c
            INNER JOIN {cohort_rule_params} crp ON c.id = " . $DB->sql_cast_char2int('crp.value') . "
            WHERE c.id {$sqlin}
            AND crp.name = 'listofids' AND crp.ruleid = ?
            ORDER BY sortorder, fullname";
        $courselist = $DB->get_records_sql($sql, $sqlparams);

        foreach ($courselist as $i => $c) {
            $value = '"' . $c->fullname . '"';
            if (!$static) {
                $value .= $this->param_delete_action_icon($c->paramid);
            }
            $courselist[$i] = html_writer::tag('span', $value, array('class' => 'ruleparamcontainer'));
        };

        $paramseparator = html_writer::tag('span', ', ', array('class' => 'ruleparamseparator'));
        $strvar->vars = implode($paramseparator, $courselist);

        return get_string('ruleformat-descvars', 'totara_cohort', $strvar);
    }
}

class cohort_rule_ui_picker_course_program_date extends cohort_rule_ui_picker_course_program {
    public $params = array(
        'operator' => 0,
        'date' => 0,
        'listofids' => 1
    );

    public function getExtraSelectedItemsPaneWidgets(){
        global $CFG;

        $html = '';
        $html .= html_writer::start_div('mform cohort-treeview-dialog-extrafields');
        $html .= html_writer::start_tag('form', array('id' => 'form_course_program_date'));

        $opmenufix = array(); // Operator menu for fixed date options.
        $opmenurel = array(); // Operator menu for relative date options.

        $opmenufix[COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN] = get_string('datemenufixeddatebefore', 'totara_cohort');
        $opmenufix[COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN] = get_string('datemenufixeddateafter', 'totara_cohort');

        $opmenurel[COHORT_RULE_COMPLETION_OP_BEFORE_PAST_DURATION] = get_string('datemenudurationbeforepast', 'totara_cohort');
        $opmenurel[COHORT_RULE_COMPLETION_OP_WITHIN_PAST_DURATION] = get_string('datemenudurationwithinpast', 'totara_cohort');
        $opmenurel[COHORT_RULE_COMPLETION_OP_WITHIN_FUTURE_DURATION] = get_string('datemenudurationwithinfuture', 'totara_cohort');
        $opmenurel[COHORT_RULE_COMPLETION_OP_AFTER_FUTURE_DURATION] = get_string('datemenudurationafterfuture', 'totara_cohort');

        // Set default values.
        $selected = isset($this->operator) ? $this->operator : '';
        $htmldate = get_string('datepickerlongyearplaceholder', 'totara_core');
        $class = 'cohorttreeviewsubmitfield';
        $duration = '';
        $radio2prop = $radio1prop = array('type' => 'radio', 'name' => 'fixeddynamic', 'checked' => 'checked', 'class' => $class);
        if (isset($this->operator) && array_key_exists($this->operator, $opmenufix)) {
            array_splice($radio2prop, 2);
            $htmldate = userdate($this->date, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false);
        } else if (isset($this->operator) && array_key_exists($this->operator, $opmenurel)) {
            array_splice($radio1prop, 2);
            $duration = htmlspecialchars($this->date);
        } else {
            array_splice($radio2prop, 2);
        }

        // Fixed date.
        $html .= get_string('completionusercompletedbeforeafter', 'totara_cohort');
        $html .= html_writer::start_tag('fieldset');
        $html .= html_writer::empty_tag('input', array_merge(array('id' => 'fixedordynamic1', 'value' => '1'), $radio1prop));
        $html .= html_writer::select($opmenufix, 'beforeaftermenu', $selected, array(), array('class' => $class));
        $html .= html_writer::empty_tag('input', array('type' => 'text', 'size' => '10', 'id' => 'completiondate',
            'name' => 'date', 'value' => htmlspecialchars($htmldate), 'class' => $class));
        $html .= html_writer::end_tag('fieldset');

        // Relative date.
        $html .= get_string('or', 'totara_cohort');
        $html .= html_writer::start_tag('fieldset');
        $html .= html_writer::empty_tag('input', array_merge(array('id' => 'fixedordynamic2', 'value' => '2'), $radio2prop));
        $html .= html_writer::select($opmenurel, 'durationmenu', $selected, array(), array('class' => $class));
        $html .= html_writer::empty_tag('input', array('type' => 'text', 'size' => '3', 'id' => 'completiondurationdate',
            'name' => 'durationdate', 'value' => $duration, 'class' => $class));
        $html .= get_string('completiondurationdays', 'totara_cohort');
        $html .= html_writer::end_tag('fieldset');

        $html .= html_writer::end_tag('form');
        $html .= html_writer::end_div();

        return $html;
    }

    public function handleDialogUpdate($sqlhandler){
        $fixedordynamic = required_param('fixeddynamic', PARAM_INT);
        switch($fixedordynamic) {
            case 1:
                $operator = required_param('beforeaftermenu', PARAM_INT);
                $date = totara_date_parse_from_format(get_string('datepickerlongyearparseformat', 'totara_core'),
                    required_param('date', PARAM_TEXT));
                break;
            case 2:
                $operator = required_param('durationmenu', PARAM_INT);
                $date = required_param('durationdate', PARAM_INT); // Convert number to seconds.
                break;
            default:
                return false;
        }
        $this->date = $sqlhandler->date = $date;
        $this->operator = $sqlhandler->operator = $operator;
        $this->listofids = $sqlhandler->listofids = explode(',', required_param('selected', PARAM_SEQUENCE));
        $sqlhandler->write();
    }

    /**
     * Get the description of this rule for the list of rules
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    public function getRuleDescription($ruleid, $static=true) {
        global $DB, $CFG, $COHORT_RULE_COMPLETION_OP;
        if (!isset($this->operator) || !isset($this->listofids)) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }

        $strvar = new stdClass();
        $strvar->desc = $this->description;
        switch ($this->operator) {
            case COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN:
            case COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN:
                $a = userdate($this->date, get_string('datepickerlongyearphpuserdate', 'totara_core'), $CFG->timezone, false);
                break;
            case COHORT_RULE_COMPLETION_OP_BEFORE_PAST_DURATION:
            case COHORT_RULE_COMPLETION_OP_WITHIN_PAST_DURATION:
            case COHORT_RULE_COMPLETION_OP_WITHIN_FUTURE_DURATION:
            case COHORT_RULE_COMPLETION_OP_AFTER_FUTURE_DURATION:
                $a = $this->date;
                break;
        }
        $strvar->join = get_string("dateis{$COHORT_RULE_COMPLETION_OP[$this->operator]}", 'totara_cohort', $a);

        list($sqlin, $sqlparams) = $DB->get_in_or_equal($this->listofids);
        $sqlparams[] = $ruleid;
        if ($this->pickertype == COHORT_PICKER_COURSE_COMPLETION) {
            $sql = "SELECT c.id, c.fullname, crp.id AS paramid
                FROM {course} c
                INNER JOIN {cohort_rule_params} crp ON c.id = " . $DB->sql_cast_char2int('crp.value') . "
                WHERE c.id {$sqlin}
                AND crp.name = 'listofids' AND crp.ruleid = ?
                ORDER BY sortorder, fullname";
        } else {
            $sql = "SELECT p.id, p.fullname, crp.id AS paramid
                FROM {prog} p
                INNER JOIN {cohort_rule_params} crp ON p.id = " . $DB->sql_cast_char2int('crp.value') . "
                WHERE p.id {$sqlin}
                AND crp.name = 'listofids' AND crp.ruleid = ?
                ORDER BY sortorder, fullname";
        }

        $courseprogramlist = $DB->get_records_sql($sql, $sqlparams);

        foreach ($courseprogramlist as $i => $c) {
            $value = '"' . format_string($c->fullname) . '"';
            if (!$static) {
                $value .= $this->param_delete_action_icon($c->paramid);
            }
            $courselist[$i] = html_writer::tag('span', $value, array('class' => 'ruleparamcontainer'));
        };

        $paramseparator = html_writer::tag('span', ', ', array('class' => 'ruleparamseparator'));
        $strvar->vars = implode($paramseparator, $courselist);

        return get_string('ruleformat-descjoinvars', 'totara_cohort', $strvar);
    }
}

// todo: Refactor to remove the shameful amount of code duplication between courses & programs
require_once($CFG->dirroot.'/totara/core/dialogs/dialog_content_programs.class.php');
class totara_dialog_content_cohort_rules_programs extends totara_dialog_content_programs {

    /**
    * Returns markup to be used in the selected pane of a multi-select dialog
    *
    * @param   $elements    array elements to be created in the pane
    * @return  $html
    */
    public function populate_selected_items_pane($elements) {
        $html = $this->cohort_rule_ui->getExtraSelectedItemsPaneWidgets();
        return $html .= parent::populate_selected_items_pane($elements);
    }
}

class cohort_rule_ui_picker_program_allanynotallnone extends cohort_rule_ui_picker_course_program {
    public $params = array(
        'operator' => 0,
        'listofids' => 1
    );

    public function getExtraSelectedItemsPaneWidgets(){
        $operatormenu = array();
        $operatormenu[COHORT_RULE_COMPLETION_OP_ALL] = get_string('completionmenuall', 'totara_cohort');
        $operatormenu[COHORT_RULE_COMPLETION_OP_ANY] = get_string('completionmenuany', 'totara_cohort');
        $operatormenu[COHORT_RULE_COMPLETION_OP_NOTALL] = get_string('completionmenunotall', 'totara_cohort');
        $operatormenu[COHORT_RULE_COMPLETION_OP_NONE] = get_string('completionmenunotany', 'totara_cohort');
        $selected = isset($this->operator) ? $this->operator : '';
        return html_writer::select($operatormenu, 'operator', $selected, array(),
            array('id' => 'id_operator', 'class' => 'cohorttreeviewsubmitfield'));
    }

    public function handleDialogUpdate($sqlhandler){
        $operator = required_param('operator', PARAM_INT);
        $listofids = required_param('selected', PARAM_SEQUENCE);
        $listofids = explode(',',$listofids);
        $this->operator = $sqlhandler->operator = $operator;
        $this->listofids = $sqlhandler->listofids = $listofids;
        $sqlhandler->write();
    }

    /**
     * Get the description of this rule for the list of rules
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    public function getRuleDescription($ruleid, $static=true) {
        global $DB;
        if (!isset($this->operator) || !isset($this->listofids)) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }
        switch ($this->operator) {
            case COHORT_RULE_COMPLETION_OP_ALL:
                $getstr = 'pcdescall';
                break;
            case COHORT_RULE_COMPLETION_OP_ANY:
                $getstr = 'pcdescany';
                break;
            case COHORT_RULE_COMPLETION_OP_NOTALL:
                $getstr = 'pcdescnotall';
                break;
            default:
                $getstr = 'pcdescnotany';
        }

        $strvar = new stdClass();
        $strvar->desc = get_string($getstr, 'totara_cohort');

        list($sqlin, $sqlparams) = $DB->get_in_or_equal($this->listofids);
        $sqlparams[] = $ruleid;
        $sql = "SELECT p.id, p.fullname, crp.id AS paramid
            FROM {prog} p
            INNER JOIN {cohort_rule_params} crp ON p.id = " . $DB->sql_cast_char2int('crp.value') . "
            WHERE p.id {$sqlin}
            AND crp.name = 'listofids' AND crp.ruleid = ?
            ORDER BY sortorder, fullname";
        $proglist = $DB->get_records_sql($sql, $sqlparams);

        foreach ($proglist as $i => $p) {
            $value = '"' . format_string($p->fullname) . '"';
            if (!$static) {
                $value .= $this->param_delete_action_icon($p->paramid);
            }
            $proglist[$i] = html_writer::tag('span', $value, array('class' => 'ruleparamcontainer'));
        };

        $paramseparator = html_writer::tag('span', ', ', array('class' => 'ruleparamseparator'));
        $strvar->vars = implode($paramseparator, $proglist);

        return get_string('ruleformat-descvars', 'totara_cohort', $strvar);
    }
}

class cohort_rule_ui_picker_program_duration extends cohort_rule_ui_picker_course_program {
    public $params = array(
        'operator' => 0,
        'date' => 0,
        'listofids' => 1
    );

    public function getExtraSelectedItemsPaneWidgets(){
        $html = '<div class="mform cohort-treeview-dialog-extrafields">';
        $operatormenu = array();
        $operatormenu[COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN] =    get_string('completiondurationmenulessthan', 'totara_cohort');
        $operatormenu[COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN] = get_string('completiondurationmenumorethan', 'totara_cohort');
        $selected = isset($this->operator) ? $this->operator : '';
        $html .= html_writer::select($operatormenu, 'operator', $selected, array(),
            array('id' => 'id_operator', 'class' => 'cohorttreeviewsubmitfield'));
        $html .= '<fieldset>';
        $html .= '<input class="cohorttreeviewsubmitfield" id="completionduration" name="date" value="';
        if (isset($this->date)) {
            $html .= htmlspecialchars($this->date);
        }
        $html .= '" /> day(s)';
        $html .= '</fieldset>';
        $html .= '</div>';
        $badduration = get_string('error:badduration', 'totara_cohort');
        $html .= <<<JS

<script type="text/javascript">
$(function() {
    var valfunc = function(element){
        element = $(element);
        var parent = element.parent();
        if (!element.val().match(/[1-9]+[0-9]*/)){
            parent.addClass('error');
            if ( $('#id_error_completionduration').length == 0 ) {
                parent.prepend('<span id="id_error_completionduration" class="error">{$badduration}</span>');
            }
            return false;
        } else {
            $('#id_error_completionduration').remove();
            parent.removeClass('error');
            return true;
        }
    };
    $('#completionduration').get(0).cohort_validation_func = valfunc;
    $('#completionduration').change(
        function(){
            valfunc(this);
        }
    );
});
</script>

JS;
        return $html;
    }

    public function handleDialogUpdate($sqlhandler){
        $date = required_param('date', PARAM_INT);
        $operator = required_param('operator', PARAM_INT);
        $listofids = required_param('selected', PARAM_SEQUENCE);
        $listofids = explode(',',$listofids);
        $this->date = $sqlhandler->date = $date;
        $this->operator = $sqlhandler->operator = $operator;
        $this->listofids = $sqlhandler->listofids = $listofids;
        $sqlhandler->write();
    }

    /**
     * Get the description of this rule for the list of rules
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    public function getRuleDescription($ruleid, $static=true) {
        global $DB;
        if (!isset($this->operator) || !isset($this->listofids)) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }
        switch ($this->operator) {
            case COHORT_RULE_COMPLETION_OP_DATE_LESSTHAN:
                $getstr = 'pcdurationdesclessthan';
                break;
            case COHORT_RULE_COMPLETION_OP_DATE_GREATERTHAN:
                $getstr = 'pcdurationdescmorethan';
                break;
        }

        $strvar = new stdClass();
        $strvar->desc = get_string($getstr, 'totara_cohort', $this->date);

        list($sqlin, $sqlparams) = $DB->get_in_or_equal($this->listofids);
        $sqlparams[] = $ruleid;
        $sql = "SELECT p.id, p.fullname, crp.id AS paramid
            FROM {prog} p
            INNER JOIN {cohort_rule_params} crp ON p.id = " . $DB->sql_cast_char2int('crp.value') . "
            WHERE p.id {$sqlin}
            AND crp.name = 'listofids' AND crp.ruleid = ?
            ORDER BY sortorder, fullname";
        $proglist = $DB->get_records_sql($sql, $sqlparams);

        foreach ($proglist as $i => $p) {
            $value = '"' . format_string($p->fullname) . '"';
            if (!$static) {
                $value .= $this->param_delete_action_icon($p->paramid);
            }
            $proglist[$i] = html_writer::tag('span', $value, array('class' => 'ruleparamcontainer'));
        };

        $paramseparator = html_writer::tag('span', ', ', array('class' => 'ruleparamseparator'));
        $strvar->vars = implode($paramseparator, $proglist);

        return get_string('ruleformat-descvars', 'totara_cohort', $strvar);
    }
}

require_once($CFG->dirroot . '/totara/core/dialogs/dialog_content_manager.class.php');
class totara_dialog_content_manager_cohortreportsto extends totara_dialog_content_manager {
    /**
    * Returns markup to be used in the selected pane of a multi-select dialog
    *
    * @param   $elements    array elements to be created in the pane
    * @return  $html
    */
    public function populate_selected_items_pane($elements) {
        $operatormenu = array();
        $operatormenu[0] = get_string('reportsto', 'totara_cohort');
        $operatormenu[1] = get_string('reportsdirectlyto', 'totara_cohort');
        $selected = isset($this->isdirectreport) ? $this->isdirectreport : '';
        $html = html_writer::select($operatormenu, 'isdirectreport', $selected, array(),
            array('id' => 'id_isdirectreport', 'class' => 'cohorttreeviewsubmitfield'));
        return $html . parent::populate_selected_items_pane($elements);
    }
}

class cohort_rule_ui_reportsto extends cohort_rule_ui {
    public $handlertype = 'treeview';
    public $params = array(
        'isdirectreport' => 0,
        'managerid' => 1
    );

    public function printDialogContent($hidden=array(), $ruleinstanceid=false) {
        global $CFG, $DB;

        // Parent id
        $parentid = optional_param('parentid', 0, PARAM_INT);

        // Only return generated tree html
        $treeonly = optional_param('treeonly', false, PARAM_BOOL);

        $dialog = new totara_dialog_content_manager_cohortreportsto();

        // Toggle treeview only display
        $dialog->show_treeview_only = $treeonly;

        // Load items to display
        $dialog->load_items($parentid);

        // Set selected items
        $alreadyselected = array();
        if ($ruleinstanceid) {
            $sql = "SELECT u.id, " . $DB->sql_fullname('u.firstname', 'u.lastname') . " AS fullname
                FROM {user} u
                INNER JOIN {cohort_rule_params} crp
                    ON u.id = " . $DB->sql_cast_char2int('crp.value') . "
                WHERE crp.ruleid = ? AND crp.name='managerid'
                ORDER BY u.firstname, u.lastname
                ";
            $alreadyselected = $DB->get_records_sql($sql, array($ruleinstanceid));
        }
        $dialog->selected_items = $alreadyselected;
        $dialog->isdirectreport = isset($this->isdirectreport) ? $this->isdirectreport : '';

        $dialog->urlparams = $hidden;

        // Display page
        // Display
        $markup = $dialog->generate_markup();
        // Hack to get around the hack that prevents deleting items via dialogs
        $markup = str_replace('<td class="selected" ', '<td class="selected selected-shown" ', $markup);
        echo $markup;
    }

    public function handleDialogUpdate($sqlhandler) {
        $isdirectreport = required_param('isdirectreport', PARAM_BOOL);
        $managerid = required_param('selected', PARAM_SEQUENCE);
        $managerid = explode(',', $managerid);
        $this->isdirectreport = $sqlhandler->isdirectreport = (int) $isdirectreport;
        $this->managerid = $sqlhandler->managerid = $managerid;
        $sqlhandler->write();
    }

    /**
     * Get the description of the rule, to be printed on the cohort's rules list page
     * @param int $ruleid
     * @param boolean $static only display static description, without action controls
     * @return string
     */
    public function getRuleDescription($ruleid, $static=true) {
        global $DB;

        if (!isset($this->isdirectreport) || !isset($this->managerid)) {
            return get_string('error:rulemissingparams', 'totara_cohort');
        }

        $strvar = new stdClass();
        if ($this->isdirectreport) {
            $strvar->desc = get_string('userreportsdirectlyto', 'totara_cohort');
        } else {
            $strvar->desc = get_string('userreportsto', 'totara_cohort');
        }

        $usernamefields = get_all_user_name_fields(true, 'u');
        list($sqlin, $sqlparams) = $DB->get_in_or_equal($this->managerid);
        $sqlparams[] = $ruleid;
        $sql = "SELECT u.id, {$usernamefields}, crp.id AS paramid
            FROM {user} u
            INNER JOIN {cohort_rule_params} crp ON u.id = " . $DB->sql_cast_char2int('crp.value') . "
            WHERE u.id {$sqlin}
            AND crp.name = 'managerid' AND crp.ruleid = ?";
        $userlist = $DB->get_records_sql($sql, $sqlparams);

        foreach ($userlist as $i => $u) {
            $value = '"' . fullname($u) . '"';
            if (!$static) {
                $value .= $this->param_delete_action_icon($u->paramid);
            }
            $userlist[$i] = html_writer::tag('span', $value, array('class' => 'ruleparamcontainer'));
        };
        // Sort by fullname
        sort($userlist);

        $paramseparator = html_writer::tag('span', ', ', array('class' => 'ruleparamseparator'));
        $strvar->vars = implode($paramseparator, $userlist);

        return get_string('ruleformat-descvars', 'totara_cohort', $strvar);
    }
}


require_once($CFG->dirroot . '/totara/core/dialogs/dialog_content_manager.class.php');
class totara_dialog_content_manager_cohortmember extends totara_dialog_content_manager {
    /**
    * Returns markup to be used in the selected pane of a multi-select dialog
    *
    * @param   $elements    array elements to be created in the pane
    * @return  $html
    */
    public function populate_selected_items_pane($elements) {

        $operatormenu = array();
        $operatormenu[1] = get_string('incohort', 'totara_cohort');
        $operatormenu[0] = get_string('notincohort', 'totara_cohort');
        $selected = isset($this->incohort) ? $this->incohort : '';
        $html = html_writer::select($operatormenu, 'incohort', $selected, array(),
            array('id' => 'id_incohort', 'class' => 'cohorttreeviewsubmitfield'));
        return $html . parent::populate_selected_items_pane($elements);
    }
}

class cohort_rule_ui_cohortmember extends cohort_rule_ui {
    public $handlertype = 'treeview';
    public $params = array(
        'cohortids' => 1,
        'incohort' => 0
    );

    public function printDialogContent($hidden=array(), $ruleinstanceid=false) {
        global $CFG, $DB;

        $type = !empty($hidden['type']) ? $hidden['type'] : '';
        $id = !empty($hidden['id']) ? $hidden['id'] : 0;
        $rule = !empty($hidden['rule']) ? $hidden['rule'] : '';
        // Get sql to exclude current cohort
        switch ($type) {
            case 'rule':
                $sql = "SELECT DISTINCT crc.cohortid
                    FROM {cohort_rules} cr
                    INNER JOIN {cohort_rulesets} crs ON crs.id = cr.rulesetid
                    INNER JOIN {cohort_rule_collections} crc ON crc.id = crs.rulecollectionid
                    WHERE cr.id = ? ";
                $currentcohortid = $DB->get_field_sql($sql, array($id), IGNORE_MULTIPLE);
                break;
            case 'ruleset':
                $sql = "SELECT DISTINCT crc.cohortid
                    FROM {cohort_rulesets} crs
                    INNER JOIN {cohort_rule_collections} crc ON crc.id = crs.rulecollectionid
                    WHERE crs.id = ? ";
                $currentcohortid = $DB->get_field_sql($sql, array($id), IGNORE_MULTIPLE);
                break;
            case 'cohort':
                $currentcohortid = $id;
                break;
            default:
                $currentcohortid =  0;
                break;
        }

        // Get cohorts
        $sql = "SELECT c.id,
                CASE WHEN c.idnumber IS NULL OR c.idnumber = '' OR c.idnumber = '0'
                    THEN c.name
                    ELSE " . $DB->sql_concat("c.name", "' ('", "c.idnumber", "')'") .
                "END AS fullname
            FROM {cohort} c";
        if (!empty($currentcohortid)) {
            $sql .= ' WHERE c.id != ? ';
        }
        $sql .= ' ORDER BY c.name, c.idnumber';
        $items = $DB->get_records_sql($sql, array($currentcohortid));

        // Set up dialog
        $dialog = new totara_dialog_content_manager_cohortmember();
        $dialog->type = totara_dialog_content::TYPE_CHOICE_MULTI;
        $dialog->items = $items;
        $dialog->selected_title = 'itemstoadd';
        $dialog->searchtype = 'cohort';
        $dialog->urlparams = array('id' => $id, 'type' => $type, 'rule' => $rule);
        if (!empty($currentcohortid)) {
            $dialog->disabled_items = array($currentcohortid);
            $dialog->customdata['current_cohort_id'] = $currentcohortid;
        }

        // Set selected items
        if ($ruleinstanceid) {
            $sql = "SELECT c.id,
                CASE WHEN c.idnumber IS NULL OR c.idnumber = '' OR c.idnumber = '0'
                    THEN c.name
                    ELSE " . $DB->sql_concat("c.name", "' ('", "c.idnumber", "')'") .
                "END AS fullname
                FROM {cohort} c
                INNER JOIN {cohort_rule_params} crp
                    ON c.id = " . $DB->sql_cast_char2int('crp.value') . "
                WHERE crp.ruleid = ? AND crp.name = 'cohortids'
                ORDER BY c.name, c.idnumber
                ";
            $alreadyselected = $DB->get_records_sql($sql, array($ruleinstanceid));
        } else {
            $alreadyselected = array();
        }
        $dialog->selected_items = $alreadyselected;
        $dialog->unremovable_items = $alreadyselected;
        $dialog->incohort = isset($this->incohort) ? $this->incohort : '';

        // Display
        $markup = $dialog->generate_markup();
        echo $markup;
    }

    public function handleDialogUpdate($sqlhandler) {
        $cohortids = required_param('selected', PARAM_SEQUENCE);
        $cohortids = explode(',', $cohortids);
        $this->cohortids = $sqlhandler->cohortids = $cohortids;

        $incohort = required_param('incohort', PARAM_BOOL);
        $this->incohort = $sqlhandler->incohort = $incohort;

        $sqlhandler->write();
    }

    public function getRuleDescription($ruleid, $static=true) {
        global $DB;

        $strvar = new stdClass();
        if ($this->incohort) {
            $strvar->desc = get_string('useriscohortmember', 'totara_cohort');
        } else {
            $strvar->desc = get_string('userisnotcohortmember', 'totara_cohort');
        }

        list($sqlin, $sqlparams) = $DB->get_in_or_equal($this->cohortids);
        $sqlparams[] = $ruleid;
        $sql = "SELECT c.id,
                CASE WHEN c.idnumber IS NULL OR c.idnumber = '' OR c.idnumber = '0'
                    THEN c.name
                    ELSE " . $DB->sql_concat("c.name", "' ('", "c.idnumber", "')'") .
                "END AS fullname, crp.id AS paramid
            FROM {cohort} c
            INNER JOIN {cohort_rule_params} crp ON c.id = " . $DB->sql_cast_char2int('crp.value') . "
            WHERE c.id {$sqlin}
            AND crp.name = 'cohortids' AND crp.ruleid = ?
            ORDER BY c.name, c.idnumber";
        $cohortlist = $DB->get_records_sql($sql, $sqlparams);

        foreach ($cohortlist as $i => $c) {
            $value = '"' . $c->fullname . '"';
            if (!$static) {
                $value .= $this->param_delete_action_icon($c->paramid);
            }
            $cohortlist[$i] = html_writer::tag('span', $value, array('class' => 'ruleparamcontainer'));
        };

        $paramseparator = html_writer::tag('span', ', ', array('class' => 'ruleparamseparator'));
        $strvar->vars = implode($paramseparator, $cohortlist);

        return get_string('ruleformat-descvars', 'totara_cohort', $strvar);
    }
}
