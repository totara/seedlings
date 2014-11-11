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
 * This file defines all the legal rule options for dynamic cohorts
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}
require_once($CFG->dirroot . '/totara/cohort/rules/ui.php');
require_once($CFG->dirroot . '/totara/cohort/rules/sqlhandler.php');
require_once($CFG->dirroot . '/totara/cohort/rules/sqlhandlers/inlist.php');
require_once($CFG->dirroot . '/totara/cohort/rules/sqlhandlers/date.php');
require_once($CFG->dirroot . '/totara/cohort/rules/sqlhandlers/completion.php');
require_once($CFG->dirroot . '/totara/cohort/rules/sqlhandlers/manager.php');
require_once($CFG->dirroot . '/totara/cohort/rules/sqlhandlers/cohortmember.php');
require_once($CFG->dirroot . '/totara/cohort/rules/option.php');

/* Constants to identify if the rule comes from a menu or a text input */
define('COHORT_RULES_TYPE_MENU', 1);
define('COHORT_RULES_TYPE_TEXT', 0);

/**
 * Get the list of defined cohort rules
 */
function cohort_rules_list(){
    global $CFG, $DB;
    static $rules = false;

    if (!$rules){
        $rules = array();

        // User's idnumber
        $rules[] = new cohort_rule_option(
            'user',
            'idnumber',
            new cohort_rule_ui_text(
                get_string('ruledesc-user-idnumber', 'totara_cohort'),
                get_string('rulehelp-user-idnumber', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_userfield_char('idnumber', COHORT_RULES_TYPE_TEXT)
        );
        // User's username
        $rules[] = new cohort_rule_option(
            'user',
            'username',
            new cohort_rule_ui_text(
                get_string('ruledesc-user-username', 'totara_cohort'),
                get_string('rulehelp-user-username', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_userfield_char('username', COHORT_RULES_TYPE_TEXT)
        );
        // User's email address
        $rules[] = new cohort_rule_option(
            'user',
            'email',
            new cohort_rule_ui_text(
                get_string('ruledesc-user-email', 'totara_cohort'),
                get_string('rulehelp-user-email', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_userfield_char('email', COHORT_RULES_TYPE_TEXT)
        );
        // User's lang preference
        $rules[] = new cohort_rule_option(
            'user',
            'lang',
            new cohort_rule_ui_menu(
                get_string('ruledesc-user-lang', 'totara_cohort'),
                get_string_manager()->get_list_of_translations()
            ),
            new cohort_rule_sqlhandler_in_userfield_char('lang', COHORT_RULES_TYPE_MENU)
        );
        // User's First Name
        $rules[] = new cohort_rule_option(
            'user',
            'firstname',
            new cohort_rule_ui_text(
                get_string('ruledesc-user-firstname', 'totara_cohort'),
                get_string('separatemultiplebycommas', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_userfield_char('firstname', COHORT_RULES_TYPE_TEXT)
        );
        // User's last name
        $rules[] = new cohort_rule_option(
            'user',
            'lastname',
            new cohort_rule_ui_text(
                get_string('ruledesc-user-lastname', 'totara_cohort'),
                get_string('separatemultiplebycommas', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_userfield_char('lastname', COHORT_RULES_TYPE_TEXT)
        );
        // User's city
        $rules[] = new cohort_rule_option(
            'user',
            'city',
            new cohort_rule_ui_text(
                get_string('ruledesc-user-city', 'totara_cohort'),
                get_string('separatemultiplebycommas', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_userfield_char('city', COHORT_RULES_TYPE_TEXT)
        );
        // User's country
        $rules[] = new cohort_rule_option(
            'user',
            'country',
            new cohort_rule_ui_menu(
                get_string('ruledesc-user-country', 'totara_cohort'),
                get_string_manager()->get_list_of_countries()
            ),
            new cohort_rule_sqlhandler_in_userfield_char('country', COHORT_RULES_TYPE_MENU)
        );
        // User's institution
        $rules[] = new cohort_rule_option(
            'user',
            'institution',
            new cohort_rule_ui_text(
                get_string('ruledesc-user-institution', 'totara_cohort'),
                get_string('separatemultiplebycommas', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_userfield_char('institution', COHORT_RULES_TYPE_TEXT)
        );
        // User's department
        $rules[] = new cohort_rule_option(
            'user',
            'department',
            new cohort_rule_ui_text(
                get_string('ruledesc-user-department', 'totara_cohort'),
                get_string('separatemultiplebycommas', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_userfield_char('department', COHORT_RULES_TYPE_TEXT)
        );
        // User custom fields
        $usercustomfields = $DB->get_records_sql(
            "SELECT usinfi.id, usinfi.name, usinfi.datatype, usinfi.param1
               FROM {user_info_field} usinfi
         INNER JOIN {user_info_category} usinca
                 ON usinfi.categoryid = usinca.id
              WHERE usinfi.datatype != ?
           ORDER BY usinca.sortorder, usinfi.sortorder",
            array('textarea')
        );
        if (!$usercustomfields) {
            $usercustomfields = array();
        }
        foreach ($usercustomfields as $id => $field) {
            $dialogs = array();
            switch($field->datatype) {
                case 'menu':
                    $options = explode("\n", $field->param1);
                    $dialogs[] = new cohort_rule_ui_menu(
                        get_string('usersx', 'totara_cohort', format_string($field->name)),
                        array_combine($options, $options)
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_in_usercustomfield($id, $field->datatype);
                    break;
                case 'text':
                    // text input
                    $dialogui = new cohort_rule_ui_text(
                        get_string('usersx', 'totara_cohort', format_string($field->name)),
                        get_string('separatemultiplebycommas', 'totara_cohort')
                    );
                    $dialogui->selectoptionstr = format_string($field->name) . ' (' . get_string('text', 'totara_cohort') . ')';
                    $dialogs[] = $dialogui;
                    $sqlhandler_text = new cohort_rule_sqlhandler_in_usercustomfield($field->name, $field->datatype);

                    // choose from distinct customfield values
                    $sql = new stdClass;
                    $sql->select = "DISTINCT {$DB->sql_compare_text('data', 255)} AS mkey, {$DB->sql_compare_text('data', 255)} AS mval";
                    $sql->from = "{user_info_data}";
                    $sql->where = "fieldid = ?";
                    $sql->orderby = 'data';
                    $sql->valuefield = "{$DB->sql_compare_text('data', 255)}";
                    $sql->sqlparams = array($id);
                    $dialogui = new cohort_rule_ui_menu(
                        get_string('usersx', 'totara_cohort', format_string($field->name)),
                        $sql
                    );
                    $dialogui->selectoptionstr = format_string($field->name) . ' (' . get_string('choose', 'totara_cohort') . ')';
                    $dialogs[] = $dialogui;

                    $sqlhandler = new cohort_rule_sqlhandler_in_usercustomfield($id, $field->datatype);
                    unset($dialogui);
                    break;
                case 'datetime':
                    $dialogs[] = new cohort_rule_ui_date(
                        get_string('usersx', 'totara_cohort', format_string($field->name))
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_date_usercustomfield($id);
                    break;
                case 'checkbox':
                    $dialogs[] = new cohort_rule_ui_checkbox(
                        get_string('usersx', 'totara_cohort', format_string($field->name)),
                        array(
                            1 => get_string('checkboxyes','totara_cohort'),
                            0 => get_string('checkboxno', 'totara_cohort')
                        )
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_in_usercustomfield($id, $field->datatype);
                    break;
                default:
                    // Skip fields that we haven't defined a rule type for
                    unset($dialogs);
                    unset($sqlhandler);
                    continue 2;
            }
            foreach ($dialogs as $i => $dialog) {
                $rules[] = new cohort_rule_option(
                    'usercustomfields',
                    "customfield{$id}_{$i}",
                    $dialog,
                    (get_class($dialog) == 'cohort_rule_ui_text' ) ? $sqlhandler_text : $sqlhandler,
                    !empty($dialog->selectoptionstr) ? $dialog->selectoptionstr : format_string($field->name)
                );
            }
        }

        // Positions!
        // The user's position
        $rules[] = new cohort_rule_option(
            'pos',
            'id',
            new cohort_rule_ui_picker_hierarchy(
                get_string('ruledesc-pos-id', 'totara_cohort'),
                'position'
            ),
            new cohort_rule_sqlhandler_in_listofids_pos()
        );
        // If the user is a manager
        $rules[] = new cohort_rule_option(
            'pos',
            'hasdirectreports',
            new cohort_rule_ui_checkbox(
                get_string('ruledesc-pos-hasdirectreports', 'totara_cohort'),
                array(
                    1 => get_string('directreportsyes', 'totara_cohort'),
                    0 => get_string('directreportsno', 'totara_cohort')
                )
            ),
            new cohort_rule_sqlhandler_hasreports()
        );
        // The user's manager
        $rules[] = new cohort_rule_option(
            'pos',
            'reportsto',
            new cohort_rule_ui_reportsto(),
            new cohort_rule_sqlhandler_reportsto()
        );
        // User's position's name
        $rules[] = new cohort_rule_option(
            'pos',
            'name',
            new cohort_rule_ui_text(
                get_string('ruledesc-pos-name', 'totara_cohort'),
                get_string('rulehelp-pos-name', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_posfield('fullname', true)
        );
        // User's position's id number
        $rules[] = new cohort_rule_option(
            'pos',
            'idnumber',
            new cohort_rule_ui_text(
                get_string('ruledesc-pos-idnumber', 'totara_cohort'),
                get_string('rulehelp-pos-idnumber', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_posfield('idnumber', true)
        );
        // User's position's date assigned.
        $rules[] = new cohort_rule_option(
            'pos',
            'startdate',
            new cohort_rule_ui_date(
                get_string('ruledesc-pos-startdate', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_date_posstarted()
        );
        // User's position's start date.
        $rules[] = new cohort_rule_option(
            'pos',
            'timevalidfrom',
            new cohort_rule_ui_date(
                get_string('ruledesc-pos-timevalidfrom', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_date_postimevalidfrom()
        );
        // User's position's end date.
        $rules[] = new cohort_rule_option(
            'pos',
            'timevalidto',
            new cohort_rule_ui_date(
                get_string('ruledesc-pos-timevalidto', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_date_postimevalidto()
        );
        // Custom fields for user's primary position
        $poscustomfields = $DB->get_records_sql(
            "SELECT potyinfi.id, potyinfi.fullname as name, potyinfi.datatype, potyinfi.param1
               FROM {pos_type_info_field} potyinfi
         INNER JOIN {pos_type} poty
                 ON potyinfi.typeid = poty.id
              WHERE potyinfi.datatype != ?
           ORDER BY poty.fullname, potyinfi.sortorder", array('textarea')
        );
        if (!$poscustomfields) {
            $poscustomfields = array();
        }
        foreach ($poscustomfields as $id=>$field) {
            switch ($field->datatype) {
                case 'menu':
                    $options = explode("\n", $field->param1);
                    $dialog = new cohort_rule_ui_menu(
                        get_string('usersposx', 'totara_cohort', $field->name),
                        array_combine($options, $options)
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_in_poscustomfield($id);
                    break;
                case 'text':
                    $dialog = new cohort_rule_ui_text(
                        get_string('usersposx', 'totara_cohort', $field->name),
                        get_string('separatemultiplebycommas', 'totara_cohort')
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_in_poscustomfield($id);
                    break;
                case 'datetime':
                    $dialog = new cohort_rule_ui_date(
                        get_string('usersposx', 'totara_cohort', $field->name)
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_date_poscustomfield($id);
                    break;

                case 'checkbox':
                    $dialog = new cohort_rule_ui_checkbox(
                        get_string('usersposx', 'totara_cohort', $field->name),
                        array(
                            1 => get_string('checkboxyes','totara_cohort'),
                            0 => get_string('checkboxno', 'totara_cohort')
                        )
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_in_poscustomfield($id);
                    break;
                default:
                    // Skip field types we haven't defined a rule for yet.
                    unset($dialog);
                    unset($sqlhandler);
                    continue 2;
            }

            $rules[] = new cohort_rule_option(
                'pos',
                "customfield{$id}",
                $dialog,
                $sqlhandler,
                s($field->name)
            );
        }
        $pos = new position();
        $postypes = $pos->get_types();
        array_walk($postypes, function(&$item) { $item = $item->fullname; });
        $postypes[0] = get_string('unclassified', 'totara_hierarchy');
        $rules[] = new cohort_rule_option(
            'pos',
            'postype',
            new cohort_rule_ui_menu(
                get_string('ruledesc-pos-postype', 'totara_cohort'),
                $postypes
            ),
            new cohort_rule_sqlhandler_in_posfield('typeid', false)
        );

        // Organizations!
        // Organization by direct selection
        $rules[] = new cohort_rule_option(
            'org',
            'id',
            new cohort_rule_ui_picker_hierarchy(
                get_string('ruledesc-org-id', 'totara_cohort'),
                'organisation'
            ),
            new cohort_rule_sqlhandler_in_listofids_org()
        );
        // ID number from user's primary position's organization
        $rules[] = new cohort_rule_option(
            'org',
            'idnumber',
            new cohort_rule_ui_text(
                get_string('ruledesc-org-idnumber', 'totara_cohort'),
                get_string('rulehelp-org-idnumber', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_in_posorgfield('idnumber', true)
        );
        // Custom fields for user's primary position's organization
        $orgcustomfields = $DB->get_records_sql(
            "SELECT ortyinfi.id, ortyinfi.fullname as name, ortyinfi.datatype, ortyinfi.param1
               FROM {org_type_info_field} ortyinfi
         INNER JOIN {org_type} orty
                 ON ortyinfi.typeid = orty.id
              WHERE ortyinfi.datatype != ?
           ORDER BY orty.fullname, ortyinfi.sortorder", array('textarea')
        );
        if (!$orgcustomfields) {
            $orgcustomfields = array();
        }
        foreach ($orgcustomfields as $id=>$field) {
            switch ($field->datatype) {
                case 'menu':
                    $options = explode("\n", $field->param1);
                    $dialog = new cohort_rule_ui_menu(
                        get_string('usersorgx', 'totara_cohort', $field->name),
                        array_combine($options, $options)
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_in_posorgcustomfield($id);
                    break;
                case 'text':
                    $dialog = new cohort_rule_ui_text(
                        get_string('usersorgx', 'totara_cohort', $field->name),
                        get_string('separatemultiplebycommas', 'totara_cohort')
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_in_posorgcustomfield($id);
                    break;
                case 'datetime':
                    $dialog = new cohort_rule_ui_date(
                        get_string('usersorgx', 'totara_cohort', $field->name)
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_date_orgcustomfield($id);
                    break;
                case 'checkbox':
                    $dialog = new cohort_rule_ui_checkbox(
                        get_string('usersorgx', 'totara_cohort', $field->name),
                        array(
                            1 => get_string('checkboxyes','totara_cohort'),
                            0 => get_string('checkboxno', 'totara_cohort')
                        )
                    );
                    $sqlhandler = new cohort_rule_sqlhandler_in_posorgcustomfield($id);
                    break;
                default:
                    // Skip field types we haven't defined a rule for yet.
                    unset($dialog);
                    unset($sqlhandler);
                    continue 2;
            }

            $rules[] = new cohort_rule_option(
                'org',
                "customfield{$id}",
                $dialog,
                $sqlhandler,
                s($field->name)
            );
        }
        $org = new organisation();
        $orgtypes = $org->get_types();
        array_walk($orgtypes, function(&$item) { $item = $item->fullname; });
        $orgtypes[0] = get_string('unclassified', 'totara_hierarchy');
        $rules[] = new cohort_rule_option(
            'org',
            'orgtype',
            new cohort_rule_ui_menu(
                get_string('ruledesc-org-orgtype', 'totara_cohort'),
                $orgtypes
            ),
            new cohort_rule_sqlhandler_in_posorgfield('typeid', false)
        );

        // Learning (i.e. course & program completion)
        // Completion of all/any/none/not-all courses in a list
        $rules[] = new cohort_rule_option(
            'learning',
            'coursecompletionlist',
            new cohort_rule_ui_picker_course_allanynotallnone(
                get_string('ruledesc-learning-coursecompletionlist', 'totara_cohort'),
                COHORT_PICKER_COURSE_COMPLETION
            ),
            new cohort_rule_sqlhandler_completion_list_course()
        );
        // Completion of all courses in a list before/after a fixed date
        $rules[] = new cohort_rule_option(
            'learning',
            'coursecompletiondate',
            new cohort_rule_ui_picker_course_program_date(
                get_string('ruledesc-learning-coursecompletiondate', 'totara_cohort'),
                COHORT_PICKER_COURSE_COMPLETION
            ),
            new cohort_rule_sqlhandler_completion_date_course()
        );
        // Completion of all courses in a list within a given duration
        $rules[] = new cohort_rule_option(
            'learning',
            'coursecompletionduration',
            new cohort_rule_ui_picker_course_duration(
                get_string('ruledesc-learning-coursecompletionduration', 'totara_cohort'),
                COHORT_PICKER_COURSE_COMPLETION
            ),
            new cohort_rule_sqlhandler_completion_duration_course()
        );
        // Completion of all/any/not-all/none of programs in a list
        $rules[] = new cohort_rule_option(
            'learning',
            'programcompletionlist',
            new cohort_rule_ui_picker_program_allanynotallnone(
                get_string('ruledesc-learning-programcompletionlist', 'totara_cohort'),
                COHORT_PICKER_PROGRAM_COMPLETION
            ),
            new cohort_rule_sqlhandler_completion_list_program()
        );
        // Completion of all programs in list before/after a fixed date
        $rules[] = new cohort_rule_option(
            'learning',
            'programcompletiondate',
            new cohort_rule_ui_picker_course_program_date(
                get_string('ruledesc-learning-programcompletiondate', 'totara_cohort'),
                COHORT_PICKER_PROGRAM_COMPLETION
            ),
            new cohort_rule_sqlhandler_completion_date_program()
        );
        // Completion of all programs in a list within a given duration
        $rules[] = new cohort_rule_option(
            'learning',
            'programcompletionduration',
            new cohort_rule_ui_picker_program_duration(
                get_string('ruledesc-learning-programcompletionduration', 'totara_cohort'),
                COHORT_PICKER_PROGRAM_COMPLETION
            ),
            new cohort_rule_sqlhandler_completion_duration_program()
        );

        // Cohort member
        $rules[] = new cohort_rule_option(
            'cohort',
            'cohortmember',
            new cohort_rule_ui_cohortmember(),
            new cohort_rule_sqlhandler_cohortmember()
        );

        // System access! (though I think maybe this is better group under "user"?
        // User's first login date
        $rules[] = new cohort_rule_option(
            'systemaccess',
            'firstlogin',
            new cohort_rule_ui_date(
                get_string('ruledesc-systemaccess-firstlogin', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_date_userfield('firstaccess')
        );
        // User's last login date
        $rules[] = new cohort_rule_option(
            'systemaccess',
            'lastlogin',
            new cohort_rule_ui_date(
                get_string('ruledesc-systemaccess-lastlogin', 'totara_cohort')
            ),
            new cohort_rule_sqlhandler_date_userfield('currentlogin')
        );

        $indexedrules = array();
        foreach ($rules as $option) {
            $group = $option->group;
            $name = $option->name;
            if (!array_key_exists($group, $indexedrules)) {
                $indexedrules[$group] = array();
            }
            $indexedrules[$group][$name] = $option;
        }
        $rules = $indexedrules;
    }

    return $rules;
}
