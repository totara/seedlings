<?php // $Id$
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
 * @subpackage reportbuilder
 */

/**
 * Abstract base content class to be extended to create report builder
 * content restrictions. This file also contains some core content restrictions
 * that can be used by any report builder source
 *
 * Defines the properties and methods required by content restrictions
 */
abstract class rb_base_content {

    public $reportfor;

    /*
     * @param integer $reportfor User ID to determine who the report is for
     *                           Typically this will be $USER->id, except
     *                           in the case of scheduled reports run by cron
     */
    public function __construct($reportfor=null) {
        $this->reportfor = $reportfor;
    }

    /*
     * All sub classes must define the following functions
     */
    abstract public function sql_restriction($fields, $reportid);
    abstract public function text_restriction($title, $reportid);
    abstract public function form_template(&$mform, $reportid, $title);
    abstract public function form_process($reportid, $fromform);

}

///////////////////////////////////////////////////////////////////////////


/**
 * Restrict content by a position ID
 *
 * Pass in an integer that represents the position ID
 */
class rb_current_pos_content extends rb_base_content {

    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $userid = $this->reportfor;

        // get the user's primary position path
        $positionpath = $DB->get_field_sql(
            "SELECT p.path FROM {pos_assignment} pa
                JOIN {pos} p ON pa.positionid = p.id
                WHERE pa.userid = ? AND pa.type = ?",
            array($userid, POSITION_TYPE_PRIMARY));

        // we need the user to have a valid position path
        if (!$positionpath) {
            // using 1=0 instead of FALSE for MSSQL support
            return array('1=0', array());
        }

        if ($settings['recursive']) {
            // match all positions below the user's one
            $paramname = rb_unique_param('cpr');
            $sql = $DB->sql_like($field, ":$paramname");
            $params = array($paramname => $DB->sql_like_escape($positionpath) . '/%');
            if ($settings['recursive'] == 1) {
                // also include the current position
                $paramname2 = rb_unique_param('cpr');
                $sql .= " OR $field = :{$paramname2}";
                $params[$paramname2] = $positionpath;
            }
        } else {
            // the user's position only
            $paramname = rb_unique_param('cpr');
            $sql = "{$field} = :{$paramname}";
            $params = array($paramname => $positionpath);
        }

        return array("({$sql})", $params);
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        $userid = $this->reportfor;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        $posid = $DB->get_field('pos_assignment', 'positionid', array('userid' => $userid, 'type' => 1));
        $posname = $DB->get_field('pos', 'fullname', array('id' => $posid));

        switch ($settings['recursive']) {
        case 0:
            return $title . ' ' . get_string('is', 'totara_reportbuilder') .
                ': "' . $posname . '"';
        case 1:
            return $title . ' ' . get_string('is', 'totara_reportbuilder') .
                ': "' . $posname . '" ' . get_string('orsubpos', 'totara_reportbuilder');
        case 2:
            return $title . ' ' . get_string('isbelow', 'totara_reportbuilder') .
                ': "' . $posname . '"';
        default:
            return '';
        }
    }

    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $recursive = reportbuilder::get_setting($reportid, $type, 'recursive');

        $mform->addElement('header', 'current_pos_header',
            get_string('showbyx', 'totara_reportbuilder', lcfirst($title)));
        $mform->setExpanded('current_pos_header');
        $mform->addElement('checkbox', 'current_pos_enable', '',
            get_string('currentposenable', 'totara_reportbuilder'));
        $mform->setDefault('current_pos_enable', $enable);
        $mform->disabledIf('current_pos_enable', 'contentenabled', 'eq', 0);
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'current_pos_recursive',
            '', get_string('showrecordsinposandbelow', 'totara_reportbuilder'), 1);
        $radiogroup[] =& $mform->createElement('radio', 'current_pos_recursive',
            '', get_string('showrecordsinpos', 'totara_reportbuilder'), 0);
        $radiogroup[] =& $mform->createElement('radio', 'current_pos_recursive',
            '', get_string('showrecordsbelowposonly', 'totara_reportbuilder'), 2);
        $mform->addGroup($radiogroup, 'current_pos_recursive_group',
            get_string('includechildpos', 'totara_reportbuilder'), html_writer::empty_tag('br'), false);
        $mform->setDefault('current_pos_recursive', $recursive);
        $mform->disabledIf('current_pos_recursive_group', 'contentenabled',
            'eq', 0);
        $mform->disabledIf('current_pos_recursive_group', 'current_pos_enable',
            'notchecked');
        $mform->addHelpButton('current_pos_header', 'reportbuildercurrentpos', 'totara_reportbuilder');
    }

    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->current_pos_enable) &&
            $fromform->current_pos_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // recursive radio option
        $recursive = isset($fromform->current_pos_recursive) ?
            $fromform->current_pos_recursive : 0;

        $status = $status && reportbuilder::update_setting($reportid, $type,
            'recursive', $recursive);

        return $status;
    }
}



/**
 * Restrict content by an organisation ID
 *
 * Pass in an integer that represents the organisation ID
 */
class rb_current_org_content extends rb_base_content {

    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $userid = $this->reportfor;

        // get the user's primary organisation path
        $orgpath = $DB->get_field_sql(
            "SELECT o.path FROM {pos_assignment} pa
                JOIN {org} o ON pa.organisationid = o.id
                WHERE pa.userid = ? AND pa.type = ?",
            array($userid, POSITION_TYPE_PRIMARY));

        // we need the user to have a valid organisation path
        if (!$orgpath) {
            // using 1=0 instead of FALSE for MSSQL support
            return array('1=0', array());
        }

        if ($settings['recursive']) {
            // match all organisations below the user's one
            $paramname = rb_unique_param('cor');
            $sql = $DB->sql_like($field, ":$paramname");
            $params = array($paramname => $DB->sql_like_escape($orgpath) . '/%');
            if ($settings['recursive'] == 1) {
                // also include the current organisation
                $paramname2 = rb_unique_param('cor');
                $sql .= " OR $field = :{$paramname2}";
                $params[$paramname2] = $orgpath;
            }
        } else {
            // the user's organisation only
            $paramname = rb_unique_param('cor');
            $sql = "{$field} = :{$paramname}";
            $params = array($paramname => $orgpath);
        }

        return array("({$sql})", $params);
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        $userid = $this->reportfor;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        $orgid = $DB->get_field('pos_assignment', 'organisationid', array('userid' => $userid, 'type' => 1));
        $orgname = $DB->get_field('org', 'fullname', array('id' => $orgid));

        switch ($settings['recursive']) {
        case 0:
            return $title . ' ' . get_string('is', 'totara_reportbuilder') .
                ': "' . $orgname . '"';
        case 1:
            return $title . ' ' . get_string('is', 'totara_reportbuilder') .
                ': "' . $orgname . '" ' . get_string('orsuborg', 'totara_reportbuilder');
        case 2:
            return $title . ' ' . get_string('isbelow', 'totara_reportbuilder') .
                ': "' . $orgname . '"';
        default:
            return '';
        }
    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $recursive = reportbuilder::get_setting($reportid, $type, 'recursive');

        $mform->addElement('header', 'current_org_header',
            get_string('showbyx', 'totara_reportbuilder', lcfirst($title)));
        $mform->setExpanded('current_org_header');
        $mform->addElement('checkbox', 'current_org_enable', '',
            get_string('currentorgenable', 'totara_reportbuilder'));
        $mform->setDefault('current_org_enable', $enable);
        $mform->disabledIf('current_org_enable', 'contentenabled', 'eq', 0);
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'current_org_recursive',
            '', get_string('showrecordsinorgandbelow', 'totara_reportbuilder'), 1);
        $radiogroup[] =& $mform->createElement('radio', 'current_org_recursive',
            '', get_string('showrecordsinorg', 'totara_reportbuilder'), 0);
        $radiogroup[] =& $mform->createElement('radio', 'current_org_recursive',
            '', get_string('showrecordsbeloworgonly', 'totara_reportbuilder'), 2);
        $mform->addGroup($radiogroup, 'current_org_recursive_group',
            get_string('includechildorgs', 'totara_reportbuilder'), html_writer::empty_tag('br'), false);
        $mform->setDefault('current_org_recursive', $recursive);
        $mform->disabledIf('current_org_recursive_group', 'contentenabled',
            'eq', 0);
        $mform->disabledIf('current_org_recursive_group', 'current_org_enable',
            'notchecked');
        $mform->addHelpButton('current_org_header', 'reportbuildercurrentorg', 'totara_reportbuilder');
    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->current_org_enable) &&
            $fromform->current_org_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // recursive radio option
        $recursive = isset($fromform->current_org_recursive) ?
            $fromform->current_org_recursive : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'recursive', $recursive);

        return $status;
    }
}


/*
 * Restrict content by an organisation at time of completion
 *
 * Pass in an integer that represents an organisation ID
 */
class rb_completed_org_content extends rb_base_content {
    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        $userid = $this->reportfor;

        // get the user's primary organisation path
        $orgpath = $DB->get_field_sql(
            "SELECT o.path FROM {pos_assignment} pa
                JOIN {org} o ON pa.organisationid = o.id
                WHERE pa.userid = ? AND pa.type = ?",
            array($userid, POSITION_TYPE_PRIMARY));

        // we need the user to have a valid organisation path
        if (!$orgpath) {
            // using 1=0 instead of FALSE for MSSQL support
            return array('1=0', array());
        }

        if ($settings['recursive']) {
            // match all organisations below the user's one
            $paramname = rb_unique_param('ccor');
            $sql = $DB->sql_like($field, ":$paramname");
            $params = array($paramname => $DB->sql_like_escape($orgpath) . '/%');
            if ($settings['recursive'] == 1) {
                // also include the current organisation
                $paramname2 = rb_unique_param('ccor');
                $sql .= " OR $field = :{$paramname2}";
                $params[$paramname2] = $orgpath;
            }
        } else {
            // the user's organisation only
            $paramname = rb_unique_param('ccor');
            $sql = "{$field} = :{$paramname}";
            $params = array($paramname => $orgpath);
        }

        return array("({$sql})", $params);
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        $userid = $this->reportfor;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        $orgid = $DB->get_field('pos_assignment', 'organisationid', array('userid' => $userid, 'type' => 1));
        if (empty($orgid)) {
            return $title . ' ' . get_string('is', 'totara_reportbuilder') . ' "UNASSIGNED"';
        }
        $orgname = $DB->get_field('org', 'fullname', array('id' => $orgid));

        switch ($settings['recursive']) {
        case 0:
            return $title . ' ' . get_string('is', 'totara_reportbuilder') .
                ': "' . $orgname . '"';
        case 1:
            return $title . ' ' . get_string('is', 'totara_reportbuilder') .
                ': "' . $orgname . '" ' . get_string('orsuborg', 'totara_reportbuilder');
        case 2:
            return $title . ' ' . get_string('isbelow', 'totara_reportbuilder') .
                ': "' . $orgname . '"';
        default:
            return '';
        }
    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $recursive = reportbuilder::get_setting($reportid, $type, 'recursive');

        $mform->addElement('header', 'completed_org_header',
            get_string('showbyx', 'totara_reportbuilder', lcfirst($title)));
        $mform->setExpanded('completed_org_header');
        $mform->addElement('checkbox', 'completed_org_enable', '',
            get_string('completedorgenable', 'totara_reportbuilder'));
        $mform->setDefault('completed_org_enable', $enable);
        $mform->disabledIf('completed_org_enable', 'contentenabled', 'eq', 0);
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'completed_org_recursive',
            '', get_string('showrecordsinorgandbelow', 'totara_reportbuilder'), 1);
        $radiogroup[] =& $mform->createElement('radio', 'completed_org_recursive',
            '', get_string('showrecordsinorg', 'totara_reportbuilder'), 0);
        $radiogroup[] =& $mform->createElement('radio', 'completed_org_recursive',
            '', get_string('showrecordsbeloworgonly', 'totara_reportbuilder'), 2);
        $mform->addGroup($radiogroup, 'completed_org_recursive_group',
            get_string('includechildorgs', 'totara_reportbuilder'), html_writer::empty_tag('br'), false);
        $mform->setDefault('completed_org_recursive', $recursive);
        $mform->disabledIf('completed_org_recursive_group', 'contentenabled',
            'eq', 0);
        $mform->disabledIf('completed_org_recursive_group',
            'completed_org_enable', 'notchecked');
        $mform->addHelpButton('completed_org_header', 'reportbuildercompletedorg', 'totara_reportbuilder');
    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->completed_org_enable) &&
            $fromform->completed_org_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // recursive radio option
        $recursive = isset($fromform->completed_org_recursive) ?
            $fromform->completed_org_recursive : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'recursive', $recursive);

        return $status;
    }
}


/*
 * Restrict content by a particular user or group of users
 */
class rb_user_content extends rb_base_content {

    const USER_OWN = 1;
    const USER_DIRECT_REPORTS = 2;
    const USER_INDIRECT_REPORTS = 4;
    const USER_TEMP_REPORTS = 8;

    /**
     * Generate the SQL to apply this content restriction
     *
     * @param array $fields SQL fields to apply the restriction against
     *
     *              Pass in the following data via $fields:
     *              'userid' = A moodle userid
     *              'managerid' = The userid of a person's manager
     *              'managerpath' = The managerpath field from the pos_assignment table
     *              'postype' = The integer position type from the pos_assignment table
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($fields, $reportid) {
        global $DB;

        $userid = $this->reportfor;
        $managerpath = $DB->get_field('pos_assignment', 'managerpath',
            array('userid' => $userid, 'type' => POSITION_TYPE_PRIMARY)
        );
        if (!$managerpath) {
            $managerpath = "/{$userid}";
        }

        $useridfield = $fields['userid'];
        $manageridfield = $fields['managerid'];
        $managerpathfield = $fields['managerpath'];
        $postypefield = $fields['postype'];

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $who = isset($settings['who']) ? $settings['who'] : null;

        // Generate SQL for matching a user's own records.
        $uniqueparam = rb_unique_param('curown');
        $ownsql = "{$useridfield} = :{$uniqueparam}";
        $ownparams = array($uniqueparam => $userid);

        // Generate SQL for matching direct reports.
        $uniqueparam = rb_unique_param('curdir');
        $uniqueparam2 = rb_unique_param('curdir');
        $directsql = "({$manageridfield} = :{$uniqueparam} AND {$postypefield} = :{$uniqueparam2})";
        $directparams = array(
            $uniqueparam => $userid,
            $uniqueparam2 => POSITION_TYPE_PRIMARY
        );

        // Generate SQL for matching temporary reports.
        $tempsql = '';
        $tempparams = array();
        if (($who & self::USER_TEMP_REPORTS) == self::USER_TEMP_REPORTS) {
            $tempmanids = $DB->get_fieldset_select('temporary_manager', 'userid',
                'tempmanagerid = :tmid AND expirytime > :extm', array('tmid' => $userid, 'extm' => time()));
            if (!empty($tempmanids)) {
                list($tempsql, $tempparams) = $DB->get_in_or_equal($tempmanids, SQL_PARAMS_NAMED);
                $tempsql = $useridfield . ' ' . $tempsql;
            }
        }

        // Generate SQL for matching all reports (direct and indirect).
        $uniqueparam3 = rb_unique_param('curall');
        $uniqueparam4 = rb_unique_param('curall');
        $sqllike = $DB->sql_like($managerpathfield, ":{$uniqueparam3}");
        $allreportssql = "({$sqllike} AND {$postypefield} = :{$uniqueparam4})";
        $allreportsparams = array(
            $uniqueparam3 => $DB->sql_like_escape($managerpath) . '/%',
            $uniqueparam4 => POSITION_TYPE_PRIMARY
        );

        $sql = '';
        $params = array();

        if (($who & self::USER_INDIRECT_REPORTS) == self::USER_INDIRECT_REPORTS) {
            if (($who & self::USER_DIRECT_REPORTS) == self::USER_DIRECT_REPORTS) {
                // Both direct and indirect reports wanted, we can use the
                // SQL for all reports from above.
                $sql = $allreportssql;
                $params = $allreportsparams;
            } else {
                // Only indirect reports wanted. We must remove the direct reports.
                $sql = "( {$allreportssql} AND NOT {$directsql} )";
                $params = array_merge($allreportsparams, $directparams);
            }
        } else {
            if (($who & self::USER_DIRECT_REPORTS) == self::USER_DIRECT_REPORTS) {
                // Just the direct reports.
                $sql = $directsql;
                $params = $directparams;
            }
        }

        // Add in the user's own records if necessary.
        if (($who & self::USER_OWN) == self::USER_OWN) {
            if (empty($sql)) {
                $sql = $ownsql;
                $params = $ownparams;
            } else {
                $sql = "( {$sql} OR {$ownsql} )";
                $params = array_merge($params, $ownparams);
            }
        }

        // Add in the user's temporary reports if necessary.
        if (($who & self::USER_TEMP_REPORTS) == self::USER_TEMP_REPORTS && !empty($tempsql)) {
            if (empty($sql)) {
                $sql = $tempsql;
                $params = $tempparams;
            } else {
                $sql = "( {$sql} OR {$tempsql} )";
                $params = array_merge($params, $tempparams);
            }
        }

        // If $sql is still empty then no options are selected.
        if (empty($sql)) {
            // Using 1=0 instead of FALSE for MSSQL support.
            return array('1=0', array());
        }

        return array($sql, $params);

    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $who = isset($settings['who']) ? $settings['who'] : 0;
        $userid = $this->reportfor;

        $user = $DB->get_record('user', array('id' => $userid));

        $strings = array();

        if (($who & self::USER_OWN) == self::USER_OWN) {
            $strings[] = $title . ' ' . get_string('is', 'totara_reportbuilder') . ' "' .
                fullname($user) . '"';
        }

        if (($who & self::USER_DIRECT_REPORTS) == self::USER_DIRECT_REPORTS) {
            $strings[] = $title . ' ' . get_string('reportsdirectlyto', 'totara_reportbuilder') . ' "' .
                fullname($user) . '"';
        }

        if (($who & self::USER_INDIRECT_REPORTS) == self::USER_INDIRECT_REPORTS) {
            $strings[] = $title . ' ' . get_string('reportsindirectlyto', 'totara_reportbuilder') . ' "' .
                fullname($user) . '"';
        }

        if (empty($strings)) {
            return $title . ' ' . get_string('isnotfound', 'totara_reportbuilder');
        }

        return implode(get_string('or', 'totara_reportbuilder'), $strings);

    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {

        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $who = reportbuilder::get_setting($reportid, $type, 'who');

        $mform->addElement('header', 'user_header', get_string('showbyx',
            'totara_reportbuilder', lcfirst($title)));
        $mform->setExpanded('user_header');
        $mform->addElement('checkbox', 'user_enable', '',
            get_string('showbasedonx', 'totara_reportbuilder', lcfirst($title)));
        $mform->disabledIf('user_enable', 'contentenabled', 'eq', 0);
        $mform->setDefault('user_enable', $enable);
        $checkgroup = array();
        $checkgroup[] =& $mform->createElement('advcheckbox', 'user_who['.self::USER_OWN.']', '',
            get_string('userownrecords', 'totara_reportbuilder'), null, array(0, 1));
        $mform->setType('user_who['.self::USER_OWN.']', PARAM_INT);
        $checkgroup[] =& $mform->createElement('advcheckbox', 'user_who['.self::USER_DIRECT_REPORTS.']', '',
            get_string('userdirectreports', 'totara_reportbuilder'), null, array(0, 1));
        $mform->setType('user_who['.self::USER_DIRECT_REPORTS.']', PARAM_INT);
        $checkgroup[] =& $mform->createElement('advcheckbox', 'user_who['.self::USER_INDIRECT_REPORTS.']', '',
            get_string('userindirectreports', 'totara_reportbuilder'), null, array(0, 1));
        $mform->setType('user_who['.self::USER_INDIRECT_REPORTS.']', PARAM_INT);
        $checkgroup[] =& $mform->createElement('advcheckbox', 'user_who['.self::USER_TEMP_REPORTS.']', '',
            get_string('usertempreports', 'totara_reportbuilder'), null, array(0, 1));
        $mform->setType('user_who['.self::USER_TEMP_REPORTS.']', PARAM_INT);

        $mform->addGroup($checkgroup, 'user_who_group',
            get_string('includeuserrecords', 'totara_reportbuilder'), html_writer::empty_tag('br'), false);
        $usergroups = array(self::USER_OWN, self::USER_DIRECT_REPORTS, self::USER_INDIRECT_REPORTS, self::USER_TEMP_REPORTS);
        foreach ($usergroups as $usergroup) {
            // Bitwise comparison.
            if (($who & $usergroup) == $usergroup) {
                $mform->setDefault('user_who['.$usergroup.']', 1);
            }
        }
        $mform->disabledIf('user_who_group', 'contentenabled', 'eq', 0);
        $mform->disabledIf('user_who_group', 'user_enable', 'notchecked');
        $mform->addHelpButton('user_header', 'reportbuilderuser', 'totara_reportbuilder');
    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->user_enable) &&
            $fromform->user_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // Who checkbox option.
        // Enabled options are stored as user_who[key] = 1 when enabled.
        // Key is a bitwise value to be summed and stored.
        $whovalue = 0;
        $who = isset($fromform->user_who) ?
            $fromform->user_who : array();
        foreach ($who as $key => $option) {
            if ($option) {
                $whovalue += $key;
            }
        }
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'who', $whovalue);

        return $status;
    }
}


/*
 * Restrict content by a particular date
 *
 * Pass in an integer that contains a unix timestamp
 */
class rb_date_content extends rb_base_content {
    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $DB;
        $now = time();
        $financialyear = get_config('reportbuilder', 'financialyear');
        $month = substr($financialyear, 2, 2);
        $day = substr($financialyear, 0, 2);

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        // option to include empty date fields
        $includenulls = (isset($settings['incnulls']) &&
            $settings['incnulls']) ?
            " OR {$field} IS NULL OR {$field} = 0 " : " AND {$field} != 0 ";

        switch ($settings['when']) {
        case 'past':
            return array("({$field} < {$now} {$includenulls})", array());
        case 'future':
            return array("({$field} > {$now} {$includenulls})", array());
        case 'last30days':
            $sql = "( ({$field} < {$now}  AND {$field}  >
                ({$now} - 60*60*24*30)) {$includenulls})";
            return array($sql, array());
        case 'next30days':
            $sql = "( ({$field} > {$now} AND {$field} <
                ({$now} + 60*60*24*30)) {$includenulls})";
            return array($sql, array());
        case 'currentfinancial':
            $required_year = date('Y', $now);
            $year_before = $required_year - 1;
            $year_after = $required_year + 1;
            if (date('z', $now) >= date('z', mktime(0, 0, 0, $month, $day, $required_year))) {
                $start = mktime(0, 0, 0, $month, $day, $required_year);
                $end = mktime(0, 0, 0, $month, $day, $year_after);
            } else {
                $start = mktime(0, 0, 0, $month, $day, $year_before);
                $end = mktime(0, 0, 0, $month, $day, $required_year);
            }
            $sql = "( ({$field} >= {$start} AND {$field} <
                {$end}) {$includenulls})";
            return array($sql, array());
        case 'lastfinancial':
            $required_year = date('Y', $now) - 1;
            $year_before = $required_year - 1;
            $year_after = $required_year + 1;
            if (date('z', $now) >= date('z', mktime(0, 0, 0, $month, $day, $required_year))) {
                $start = mktime(0, 0, 0, $month, $day, $required_year);
                $end = mktime(0, 0, 0, $month, $day, $year_after);
            } else {
                $start = mktime(0, 0, 0, $month, $day, $year_before);
                $end = mktime(0, 0, 0, $month, $day, $required_year);
            }
            $sql = "( ({$field} >= {$start} AND {$field} <
                {$end}) {$includenulls})";
            return array($sql, array());
        default:
            // no match
            // using 1=0 instead of FALSE for MSSQL support
            return array("(1=0 {$includenulls})", array());
        }

    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        // option to include empty date fields
        $includenulls = (isset($settings['incnulls']) &&
                         $settings['incnulls']) ? " (or $title is empty)" : '';

        switch ($settings['when']) {
        case 'past':
            return $title . ' ' . get_string('occurredbefore', 'totara_reportbuilder') . ' ' .
                userdate(time(), '%c'). $includenulls;
        case 'future':
            return $title . ' ' . get_string('occurredafter', 'totara_reportbuilder') . ' ' .
                userdate(time(), '%c'). $includenulls;
        case 'last30days':
            return $title . ' ' . get_string('occurredafter', 'totara_reportbuilder') . ' ' .
                userdate(time() - 60*60*24*30, '%c') . get_string('and', 'totara_reportbuilder') .
                get_string('occurredbefore', 'totara_reportbuilder') . userdate(time(), '%c') .
                $includenulls;

        case 'next30days':
            return $title . ' ' . get_string('occurredafter', 'totara_reportbuilder') . ' ' .
                userdate(time(), '%c') . get_string('and', 'totara_reportbuilder') .
                get_string('occurredbefore', 'totara_reportbuilder') .
                userdate(time() + 60*60*24*30, '%c') . $includenulls;
        case 'currentfinancial':
            return $title . ' ' . get_string('occurredthisfinancialyear', 'totara_reportbuilder') .
                $includenulls;
        case 'lastfinancial':
            return $title . ' ' . get_string('occurredprevfinancialyear', 'totara_reportbuilder') .
                $includenulls;
        default:
            return 'Error with date content restriction';
        }
    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        // get current settings
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $when = reportbuilder::get_setting($reportid, $type, 'when');
        $incnulls = reportbuilder::get_setting($reportid, $type, 'incnulls');

        $mform->addElement('header', 'date_header', get_string('showbyx',
            'totara_reportbuilder', lcfirst($title)));
        $mform->setExpanded('date_header');
        $mform->addElement('checkbox', 'date_enable', '',
            get_string('showbasedonx', 'totara_reportbuilder',
            lcfirst($title)));
        $mform->setDefault('date_enable', $enable);
        $mform->disabledIf('date_enable', 'contentenabled', 'eq', 0);
        $radiogroup = array();
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('thepast', 'totara_reportbuilder'), 'past');
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('thefuture', 'totara_reportbuilder'), 'future');
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('last30days', 'totara_reportbuilder'), 'last30days');
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('next30days', 'totara_reportbuilder'), 'next30days');
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('currentfinancial', 'totara_reportbuilder'), 'currentfinancial');
        $radiogroup[] =& $mform->createElement('radio', 'date_when', '',
            get_string('lastfinancial', 'totara_reportbuilder'), 'lastfinancial');
        $mform->addGroup($radiogroup, 'date_when_group',
            get_string('includerecordsfrom', 'totara_reportbuilder'), html_writer::empty_tag('br'), false);
        $mform->setDefault('date_when', $when);
        $mform->disabledIf('date_when_group', 'contentenabled', 'eq', 0);
        $mform->disabledIf('date_when_group', 'date_enable', 'notchecked');
        $mform->addHelpButton('date_header', 'reportbuilderdate', 'totara_reportbuilder');

        $mform->addElement('checkbox', 'date_incnulls',
            get_string('includeemptydates', 'totara_reportbuilder'));
        $mform->setDefault('date_incnulls', $incnulls);
        $mform->disabledIf('date_incnulls', 'date_enable', 'notchecked');
        $mform->disabledIf('date_incnulls', 'contentenabled', 'eq', 0);
    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        $status = true;
        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->date_enable) &&
            $fromform->date_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // when radio option
        $when = isset($fromform->date_when) ?
            $fromform->date_when : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'when', $when);

        // include nulls checkbox option
        $incnulls = (isset($fromform->date_incnulls) &&
            $fromform->date_incnulls) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'incnulls', $incnulls);

        return $status;
    }
}


/*
 * Restrict content by offical tags
 *
 * Pass in a column that contains a pipe '|' separated list of official tag ids
 */
class rb_tag_content extends rb_base_content {
    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        global $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);

        $include_sql = array();
        $exclude_sql = array();

        // get arrays of included and excluded tags
        $settings = reportbuilder::get_all_settings($reportid, $type);
        $itags = ($settings['included']) ?
            explode('|', $settings['included']) : array();
        $etags = ($settings['excluded']) ?
            explode('|', $settings['excluded']) : array();
        $include_logic = (isset($settings['include_logic']) &&
            $settings['include_logic'] == 0) ? ' AND ' : ' OR ';
        $exclude_logic = (isset($settings['exclude_logic']) &&
            $settings['exclude_logic'] == 0) ? ' OR ' : ' AND ';

        // loop through current official tags
        $tags = $DB->get_records('tag', array('tagtype' => 'official'), 'name');
        $params = array();
        $count = 1;
        foreach ($tags as $tag) {
            // if found, add the SQL
            // we can't just use LIKE '%tag%' because we might get
            // partial number matches
            if (in_array($tag->id, $itags)) {
                $uniqueparam = rb_unique_param("cctre_{$count}_");
                $elike = $DB->sql_like($field, ":{$uniqueparam}");
                $params[$uniqueparam] = $DB->sql_like_escape($tag->id);

                $uniqueparam = rb_unique_param("cctrew_{$count}_");
                $ewlike = $DB->sql_like($field, ":{$uniqueparam}");
                $params[$uniqueparam] = $DB->sql_like_escape($tag->id).'|%';

                $uniqueparam = rb_unique_param("cctrsw_{$count}_");
                $swlike = $DB->sql_like($field, ":{$uniqueparam}");
                $params[$uniqueparam] = '%|'.$DB->sql_like_escape($tag->id);

                $uniqueparam = rb_unique_param("cctrsc_{$count}_");
                $clike = $DB->sql_like($field, ":{$uniqueparam}");
                $params[$uniqueparam] = '%|'.$DB->sql_like_escape($tag->id).'|%';

                $include_sql[] = "({$elike} OR
                {$ewlike} OR
                {$swlike} OR
                {$clike})\n";

                $count++;
            }
            if (in_array($tag->id, $etags)) {
                $uniqueparam = rb_unique_param("cctre_{$count}_");
                $enotlike = $DB->sql_like($field, ":{$uniqueparam}", true, true, true);
                $params[$uniqueparam] = $DB->sql_like_escape($tag->id);

                $uniqueparam = rb_unique_param("cctrew_{$count}_");
                $ewnotlike = $DB->sql_like($field, ":{$uniqueparam}", true, true, true);
                $params[$uniqueparam] = $DB->sql_like_escape($tag->id).'|%';

                $uniqueparam = rb_unique_param("cctrsw_{$count}_");
                $swnotlike = $DB->sql_like($field, ":{$uniqueparam}", true, true, true);
                $params[$uniqueparam] = '%|'.$DB->sql_like_escape($tag->id);

                $uniqueparam = rb_unique_param("cctrsc_{$count}_");
                $cnotlike = $DB->sql_like($field, ":{$uniqueparam}", true, true, true);
                $params[$uniqueparam] = '%|'.$DB->sql_like_escape($tag->id).'|%';

                $include_sql[] = "({$enotlike} AND
                {$ewnotlike} AND
                {$swnotlike} AND
                {$cnotlike})\n";

                $count++;
            }
        }

        // merge the include and exclude strings separately
        $includestr = implode($include_logic, $include_sql);
        $excludestr = implode($exclude_logic, $exclude_sql);

        // now merge together
        if ($includestr && $excludestr) {
            return array(" ($includestr AND $excludestr) ", $params);
        } else if ($includestr) {
            return array(" $includestr ", $params);
        } else if ($excludestr) {
            return array(" $excludestr ", $params);
        } else {
            // using 1=0 instead of FALSE for MSSQL support
            return array('1=0', $params);
        }
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        global $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $settings = reportbuilder::get_all_settings($reportid, $type);

        $include_text = array();
        $exclude_text = array();

        $itags = ($settings['included']) ?
            explode('|', $settings['included']) : array();
        $etags = ($settings['excluded']) ?
            explode('|', $settings['excluded']) : array();
        $include_logic = (isset($settings['include_logic']) &&
            $settings['include_logic'] == 0) ? 'and' : 'or';
        $exclude_logic = (isset($settings['exclude_logic']) &&
            $settings['exclude_logic'] == 0) ? 'and' : 'or';

        $tags = $DB->get_records('tag', array('tagtype' => 'official'), 'name');
        foreach ($tags as $tag) {
            if (in_array($tag->id, $itags)) {
                $include_text[] = '"' . $tag->name . '"';
            }
            if (in_array($tag->id, $etags)) {
                $exclude_text[] = '"' . $tag->name . '"';
            }
        }

        if (count($include_text) > 0) {
            $includestr = $title . ' ' . get_string('istaggedwith', 'totara_reportbuilder') .
                ' ' . implode(get_string($include_logic, 'totara_reportbuilder'), $include_text);
        } else {
            $includestr = '';
        }
        if (count($exclude_text) > 0) {
            $excludestr = $title . ' ' . get_string('isnttaggedwith', 'totara_reportbuilder') .
                ' ' . implode(get_string($exclude_logic, 'totara_reportbuilder'), $exclude_text);
        } else {
            $excludestr = '';
        }

        if ($includestr && $excludestr) {
            return $includestr . get_string('and', 'totara_reportbuilder') . $excludestr;
        } else if ($includestr) {
            return $includestr;
        } else if ($excludestr) {
            return $excludestr;
        } else {
            return '';
        }

    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        global $DB;

        // remove rb_ from start of classname
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');
        $include_logic = reportbuilder::get_setting($reportid, $type, 'include_logic');
        $exclude_logic = reportbuilder::get_setting($reportid, $type, 'exclude_logic');
        $activeincludes = explode('|',
            reportbuilder::get_setting($reportid, $type, 'included'));
        $activeexcludes = explode('|',
            reportbuilder::get_setting($reportid, $type, 'excluded'));

        $mform->addElement('header', 'tag_header',
            get_string('showbytag', 'totara_reportbuilder'));
        $mform->setExpanded('tag_header');
        $mform->addHelpButton('tag_header', 'reportbuildertag', 'totara_reportbuilder');

        $mform->addElement('checkbox', 'tag_enable', '',
            get_string('tagenable', 'totara_reportbuilder'));
        $mform->setDefault('tag_enable', $enable);
        $mform->disabledIf('tag_enable', 'contentenabled', 'eq', 0);

        $mform->addElement('html', html_writer::empty_tag('br'));

        // include the following tags
        $tags = $DB->get_records('tag', array('tagtype' => 'official'), 'name');
        if (!empty($tags)) {
            $checkgroup = array();
            $opts = array(1 => get_string('anyofthefollowing', 'totara_reportbuilder'),
                          0 => get_string('allofthefollowing', 'totara_reportbuilder'));
            $mform->addElement('select', 'tag_include_logic', get_string('includetags', 'totara_reportbuilder'), $opts);
            $mform->setDefault('tag_include_logic', $include_logic);
            $mform->disabledIf('tag_enable', 'contentenabled', 'eq', 0);
            foreach ($tags as $tag) {
                $checkgroup[] =& $mform->createElement('checkbox',
                    'tag_include_option_' . $tag->id, '', $tag->name, 1);
                $mform->disabledIf('tag_include_option_' . $tag->id,
                    'tag_exclude_option_' . $tag->id, 'checked');
                if (in_array($tag->id, $activeincludes)) {
                    $mform->setDefault('tag_include_option_' . $tag->id, 1);
                }
            }
            $mform->addGroup($checkgroup, 'tag_include_group', '', html_writer::empty_tag('br'), false);
        }
        $mform->disabledIf('tag_include_group', 'contentenabled', 'eq', 0);
        $mform->disabledIf('tag_include_group', 'tag_enable',
            'notchecked');

        $mform->addElement('html', str_repeat(html_writer::empty_tag('br'), 2));

        // exclude the following tags
        if (!empty($tags)) {
            $checkgroup = array();
            $opts = array(1 => get_string('anyofthefollowing', 'totara_reportbuilder'),
                          0 => get_string('allofthefollowing', 'totara_reportbuilder'));
            $mform->addElement('select', 'tag_exclude_logic', get_string('excludetags', 'totara_reportbuilder'), $opts);
            $mform->setDefault('tag_exclude_logic', $exclude_logic);
            $mform->disabledIf('tag_enable', 'contentenabled', 'eq', 0);
            foreach ($tags as $tag) {
                $checkgroup[] =& $mform->createElement('checkbox',
                    'tag_exclude_option_' . $tag->id, '', $tag->name, 1);
                $mform->disabledIf('tag_exclude_option_' . $tag->id,
                    'tag_include_option_' . $tag->id, 'checked');
                if (in_array($tag->id, $activeexcludes)) {
                    $mform->setDefault('tag_exclude_option_' . $tag->id, 1);
                }
            }
            $mform->addGroup($checkgroup, 'tag_exclude_group', '', html_writer::empty_tag('br'), false);
        }
        $mform->disabledIf('tag_exclude_group', 'contentenabled', 'eq', 0);
        $mform->disabledIf('tag_exclude_group', 'tag_enable',
            'notchecked');

    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        global $DB;

        $status = true;
        // remove the rb_ from class
        $type = substr(get_class($this), 3);

        // enable checkbox option
        $enable = (isset($fromform->tag_enable) &&
            $fromform->tag_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        // include with any or all
        $includelogic = (isset($fromform->tag_include_logic) &&
            $fromform->tag_include_logic) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'include_logic', $includelogic);

        // exclude with any or all
        $excludelogic = (isset($fromform->tag_exclude_logic) &&
            $fromform->tag_exclude_logic) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'exclude_logic', $excludelogic);

        // tag settings
        $tags = $DB->get_records('tag', array('tagtype' => 'official'));
        if (!empty($tags)) {
            $activeincludes = array();
            $activeexcludes = array();
            foreach ($tags as $tag) {
                $includename = 'tag_include_option_' . $tag->id;
                $excludename = 'tag_exclude_option_' . $tag->id;

                // included tags
                if (isset($fromform->$includename)) {
                    if ($fromform->$includename == 1) {
                        $activeincludes[] = $tag->id;
                    }
                }

                // excluded tags
                if (isset($fromform->$excludename)) {
                    if ($fromform->$excludename == 1) {
                        $activeexcludes[] = $tag->id;
                    }
                }

            }

            // implode into string and update setting
            $status = $status && reportbuilder::update_setting($reportid,
                $type, 'included', implode('|', $activeincludes));

            // implode into string and update setting
            $status = $status && reportbuilder::update_setting($reportid,
                $type, 'excluded', implode('|', $activeexcludes));
        }
        return $status;
    }
}

/*
 * Restrict content by availability
 *
 * Pass in a column that contains a pipe '|' separated list of official tag ids
 */
class rb_prog_availability_content extends rb_base_content {
    /**
     * Generate the SQL to apply this content restriction
     *
     * @param string $field SQL field to apply the restriction against
     * @param integer $reportid ID of the report
     *
     * @return array containing SQL snippet to be used in a WHERE clause, as well as array of SQL params
     */
    public function sql_restriction($field, $reportid) {
        // The restriction snippet based on the available fields was moved to totara_visibility_where.
        // So no restriction for programs or certifications.
        $restriction = " 1=1 ";

        return array($restriction, array());
    }

    /**
     * Generate a human-readable text string describing the restriction
     *
     * @param string $title Name of the field being restricted
     * @param integer $reportid ID of the report
     *
     * @return string Human readable description of the restriction
     */
    public function text_restriction($title, $reportid) {
        return get_string('contentavailability', 'totara_program');
    }


    /**
     * Adds form elements required for this content restriction's settings page
     *
     * @param object &$mform Moodle form object to modify (passed by reference)
     * @param integer $reportid ID of the report being adjusted
     * @param string $title Name of the field the restriction is acting on
     */
    public function form_template(&$mform, $reportid, $title) {
        global $DB;

        // Get current settings and
        // remove rb_ from start of classname.
        $type = substr(get_class($this), 3);
        $enable = reportbuilder::get_setting($reportid, $type, 'enable');

        $mform->addElement('header', 'prog_availability_header',
            get_string('showbyx', 'totara_reportbuilder', lcfirst($title)));
        $mform->setExpanded('prog_availability_header');
        $mform->addElement('checkbox', 'prog_availability_enable', '',
            get_string('contentavailability', 'totara_program'));
        $mform->setDefault('prog_availability_enable', $enable);
        $mform->disabledIf('prog_availability_enable', 'contentenabled', 'eq', 0);
        $mform->addHelpButton('prog_availability_header', 'contentavailability', 'totara_program');

    }


    /**
     * Processes the form elements created by {@link form_template()}
     *
     * @param integer $reportid ID of the report to process
     * @param object $fromform Moodle form data received via form submission
     *
     * @return boolean True if form was successfully processed
     */
    public function form_process($reportid, $fromform) {
        global $DB;

        $status = true;
        // Remove rb_ from start of classname.
        $type = substr(get_class($this), 3);

        // Enable checkbox option.
        $enable = (isset($fromform->prog_availability_enable) &&
            $fromform->prog_availability_enable) ? 1 : 0;
        $status = $status && reportbuilder::update_setting($reportid, $type,
            'enable', $enable);

        return $status;

    }
}

// Include trainer content restriction
include_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_trainer_content.php');
