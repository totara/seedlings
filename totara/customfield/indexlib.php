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
 * @author Jonathan Newman
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_customfield
 */

/**
 * customfieldslib.php
 *
 * Library file of custom field functions
 * Based on the custom user profile field functionality
 */

/**
 * Create a string containing the editing icons for custom fields
 * @param   object   the field object
 * @param   object   the fieldcount object
 * @param   object   the typeid of the object if used
 * @param   bool     $can_edit Can the user edit custom fields.
 * @param   bool     $can_delete Can the user delete custom fields.
 * @return  string   the icon string
 */
function customfield_edit_icons($field, $fieldcount, $typeid=0, $prefix, $can_edit, $can_delete) {
    global $OUTPUT;

    if (empty($str)) {
        $strdelete   = get_string('delete');
        $strmoveup   = get_string('moveup');
        $strmovedown = get_string('movedown');
        $stredit     = get_string('edit');
    }

    /// Edit
    if ($can_edit) {
        $params = array('prefix' => $prefix, 'id' => $field->id, 'action' => 'editfield');
        if ($typeid != null) {
            $params['typeid'] = $typeid;
        }
        $editstr = $OUTPUT->action_icon(new moodle_url('/totara/customfield/index.php', $params), new pix_icon('t/edit', $stredit), null, array('title' => $stredit));
    } else {
        $editstr = $OUTPUT->spacer(array('height' => 11, 'width' => 11));
    }

    /// Delete
    if ($can_delete) {
        $params = array('prefix' => $prefix, 'id' => $field->id, 'action' => 'deletefield');
        if ($typeid != null) {
            $params['typeid'] = $typeid;
        }
        $deletestr = $OUTPUT->action_icon(new moodle_url('/totara/customfield/index.php', $params), new pix_icon('t/delete', $strdelete), null, array('title' => $strdelete));
    } else {
        $deletestr = $OUTPUT->spacer(array('height' => 11, 'width' => 11));
    }

    /// Move up
    if ($field->sortorder > 1 && $can_edit) {
        $params = array('prefix' => $prefix, 'id' => $field->id, 'action' => 'movefield');
        if ($typeid != null) {
            $params['typeid'] = $typeid;
        }
        $params['dir'] = 'up';
        $params['sesskey'] = sesskey();
        $upstr = $OUTPUT->action_icon(new moodle_url('/totara/customfield/index.php', $params), new pix_icon('t/up', $strmoveup), null, array('title' => $strmoveup));
    } else {
        $upstr = $OUTPUT->spacer(array('height' => 11, 'width' => 11));
    }

    /// Move down
    if ($field->sortorder < $fieldcount && $can_edit) {
        $params = array('prefix' => $prefix, 'id' => $field->id, 'action' => 'movefield');
        if ($typeid != null) {
            $params['typeid'] = $typeid;
        }
        $params['dir'] = 'down';
        $params['sesskey'] = sesskey();
        $downstr = $OUTPUT->action_icon(new moodle_url('/totara/customfield/index.php', $params), new pix_icon('t/down', $strmovedown), null, array('title' => $strmovedown));
    } else {
        $downstr = $OUTPUT->spacer(array('height' => 11, 'width' => 11));
    }

    return $editstr . $deletestr . $upstr . $downstr;
}


function customfield_delete_field($id, $tableprefix) {
    global $DB;
    /// Remove any user data associated with this field
    $DB->delete_records($tableprefix.'_info_data', array('fieldid' => $id));

    /// Try to remove the record from the database
    $DB->delete_records($tableprefix.'_info_field', array('id' => $id));

    /// Reorder the remaining fields
    customfield_reorder_fields($tableprefix);
}

/**
 * Change the sortorder of a field
 * @param   integer   id of the field
 * @param   string    direction of move
 * @return  boolean   success of operation
 */
function customfield_move_field($id, $move, $tableprefix, $prefix) {
    global $DB;

    // Get typeid only for hierarchies
    $subfields = ($prefix == 'course') ? 'id, sortorder' : 'id, typeid, sortorder';
    /// Get the field object
    $field = $DB->get_record($tableprefix.'_info_field', array('id' => $id), $subfields);

    /// Count the number of fields
    $fieldcount = $DB->count_records($tableprefix.'_info_field');

    /// Calculate the new sortorder
    if (($move == 'up') and ($field->sortorder > 1)) {
        $neworder = $field->sortorder - 1;
    } elseif (($move == 'down') and ($field->sortorder < $fieldcount)) {
        $neworder = $field->sortorder + 1;
    } else {
        return false;
    }

    // Get typeid only for hierarchies
    $subfields = ($prefix == 'course') ? array('sortorder' => $neworder) : array('sortorder' => $neworder, 'typeid' => $field->typeid);
    /// Retrieve the field object that is currently residing in the new position
    $swapfield = $DB->get_record($tableprefix.'_info_field', $subfields);

    /// Swap the sortorders
    $swapfield->sortorder = $field->sortorder;
    $field->sortorder     = $neworder;

    /// Update the field records
    $DB->update_record($tableprefix.'_info_field', $field);
    $DB->update_record($tableprefix.'_info_field', $swapfield);

    customfield_reorder_fields($tableprefix);
}


/**
 * Retrieve a list of all the available data types
 * @return   array   a list of the datatypes suitable to use in a select statement
 */
function customfield_list_datatypes() {
    global $CFG;

    $datatypes = array();

    if ($dirlist = get_directory_list($CFG->dirroot.'/totara/customfield/field', '', false, true, false)) {
        foreach ($dirlist as $type) {
            $datatypes[$type] = get_string('customfieldtype'.$type, 'totara_customfield');
            if (strpos($datatypes[$type], '[[') !== false) {
                $datatypes[$type] = get_string('customfieldtype'.$type, 'admin');
            }
        }
    }
    asort($datatypes);

    return $datatypes;
}


function customfield_edit_field($id, $datatype, $typeid=0, $redirect, $tableprefix, $prefix, $navlinks=false) {
    global $CFG, $DB, $OUTPUT, $PAGE, $TEXTAREA_OPTIONS, $SITE;

    if (!$field = $DB->get_record($tableprefix.'_info_field', array('id' => $id))) {
        $field = new stdClass();
        $field->id = 0;
        $field->datatype = $datatype;
        $field->description = '';
        $field->defaultdata = '';
        $field->forceunique = 0;
    }

    $displayadminheader = $prefix == 'type' ? 1 : 0;
    require_once($CFG->dirroot.'/totara/customfield/index_field_form.php');
    $field->descriptionformat = FORMAT_HTML;
    $field = file_prepare_standard_editor($field, 'description', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_customfield', 'textarea', $field->id);
    if ($field->datatype == 'textarea') {
        $field->defaultdataformat = FORMAT_HTML;
        $field = file_prepare_standard_editor($field, 'defaultdata', $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_customfield', 'textarea', $field->id);
    }
    $datatosend = array('datatype' => $field->datatype, 'prefix' => $prefix,
                        'typeid' => $typeid, 'tableprefix' => $tableprefix);
    $fieldform = new field_form(null, $datatosend);
    $fieldform->set_data($field);

    if ($fieldform->is_cancelled()) {
        redirect($redirect);

    } else {
        if ($data = $fieldform->get_data()) {
            require_once($CFG->dirroot.'/totara/customfield/field/'.$datatype.'/define.class.php');
            $newfield = 'customfield_define_'.$datatype;
            $formfield = new $newfield();
            $formfield->define_save($data, $tableprefix);
            customfield_reorder_fields($tableprefix);
            redirect($redirect);
        }

        $datatypes = customfield_list_datatypes();

        if (empty($id)) {
            $strheading = get_string('createnewfield', 'totara_customfield', $datatypes[$datatype]);
        } else {
            $strheading = get_string('editfield', 'totara_customfield', format_string($field->fullname));
        }

        /// Print the page
        // Display page header
        $pagetitle = format_string($DB->get_field($tableprefix, 'fullname', array('id' => $typeid)));
        if ($navlinks == false) {
            $PAGE->navbar->add(get_string('administration'));
            $PAGE->navbar->add(get_string($prefix.'plural', 'totara_customfield'));
            $PAGE->navbar->add(get_string($prefix.'depthcustomfields', 'totara_customfield'));
        }

        if ($displayadminheader) {
            admin_externalpage_setup($prefix.'typemanage', '', array('prefix'=>$prefix));
            echo $OUTPUT->header();
        } else {
            $PAGE->set_title($pagetitle);
            $PAGE->set_heading(format_string($SITE->fullname));
            $PAGE->set_focuscontrol('');
            $PAGE->set_cacheable(true);
            echo $OUTPUT->header();
        }
        echo $OUTPUT->heading($strheading);
        $fieldform->display();
        echo $OUTPUT->footer();
        die;
    }
}


/**
 * Get list of fields that have been defined.
 *
 * @param string $tableprefix
 * @param array $where
 * @return array of records
 */
function customfield_get_defined_fields($tableprefix,  array $where = array()) {
    global $DB;
    return $DB->get_records($tableprefix.'_info_field', $where, 'sortorder ASC');
}


/**
 * Reorder the custom fields, with each type getting it's own sort numbering
 *
 * @param string $tableprefix
 * @return boolean true
 */
function customfield_reorder_fields($tableprefix) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/totara/core/utils.php');
    $rs = $DB->get_recordset($tableprefix.'_info_field', array(), 'sortorder ASC');
    if ($types = totara_group_records($rs, 'typeid')) {
        foreach ($types as $unused => $fields) {
            $i = 1;
            foreach ($fields as $field) {
                $f = new stdClass();
                $f->id = $field->id;
                $f->sortorder = $i++;
                $DB->update_record($tableprefix.'_info_field', $f);
            }
        }
    }
    $rs->close();
    return true;
}
