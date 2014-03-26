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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage totara_customfield
 */

//this file is also included by hierarchy/lib so it seems a good place to put these
require_once($CFG->libdir . '/formslib.php');

/**
 * Base class for the custom fields.
 */
class customfield_base {

    /// These 2 variables are really what we're interested in.
    /// Everything else can be extracted from them
    var $fieldid; //{tableprefix}_info_field field id
    var $itemid; //hierarchy item id
    var $dataid; //id field of the data record
    var $prefix;
    var $tableprefix;
    var $field;
    var $inputname;
    var $data;
    var $context;

    /**
     * Constructor method.
     * @param   integer   field id from the _info_field table
     * @param   integer   id using the data
     */
    function customfield_base($fieldid=0, &$item, $prefix, $tableprefix) {
        $this->set_fieldid($fieldid);
        $this->set_itemid($item->id);
        $this->load_data($item, $prefix, $tableprefix);
        $this->prefix = $prefix;
    }

    /**
     * Display the data for this field
     */
    function display_data() {
        // call the static method belonging to this object's class
        // or the one below if not re-defined by child class
        return $this->display_item_data($this->data, array('prefix' => $this->prefix, 'itemid' => $this->dataid));
    }


/***** The following methods must be overwritten by child classes *****/

    /**
     * Abstract method: Adds the custom field to the moodle form class
     * @param  form  instance of the moodleform class
     */
    function edit_field_add(&$mform) {
        print_error('error:abstractmethod', 'totara_customfield');
    }


/***** The following methods may be overwritten by child classes *****/

    static function display_item_data($data, $extradata=array()) {
        $options = new stdClass();
        $options->para = false;
        return format_text($data, FORMAT_MOODLE, $options);
    }
    /**
     * Print out the form field in the edit page
     * @param   object   instance of the moodleform class
     * $return  boolean
     */
    function edit_field(&$mform) {

        if ($this->field->hidden == false) {
            $this->edit_field_add($mform);
            $this->edit_field_set_default($mform);
            $this->edit_field_set_required($mform);
            return true;
        }
        return false;
    }

    /**
     * Tweaks the edit form
     * @param   object   instance of the moodleform class
     * $return  boolean
     */
    function edit_after_data(&$mform) {

        if ($this->field->hidden == false) {
            $this->edit_field_set_locked($mform);
            return true;
        }
        return false;
    }

    /**
     * Saves the data coming from form
     * @param   mixed   data coming from the form
     * @param   string  name of the prefix (ie, competency)
     * @return  mixed   returns data id if success of db insert/update, false on fail, 0 if not permitted
     */
    function edit_save_data($itemnew, $prefix, $tableprefix) {
        global $DB;
        if (!isset($itemnew->{$this->inputname})) {
            // field not present in form, probably locked and invisible - skip it
            return;
        }
        $rawdata = $itemnew->{$this->inputname};
        $itemnew->{$this->inputname} = $this->edit_save_data_preprocess($rawdata);

        $data = new stdClass();
        $data->{$prefix.'id'} = $itemnew->id;
        $data->fieldid      = $this->field->id;
        $data->data         = $itemnew->{$this->inputname};

        if ($dataid = $DB->get_field($tableprefix.'_info_data', 'id', array($prefix.'id' => $itemnew->id, 'fieldid' => $data->fieldid))) {
            $data->id = $dataid;
            $DB->update_record($tableprefix.'_info_data', $data);
        } else {
            $this->dataid = $DB->insert_record($tableprefix.'_info_data', $data);
        }
        $this->edit_save_data_postprocess($rawdata);
    }

    /**
     * Validate the form field from edit page
     * @return  string  contains error message otherwise NULL
     **/
    function edit_validate_field($itemnew, $prefix, $tableprefix) {
        global $DB, $TEXTAREA_OPTIONS, $FILEPICKER_OPTIONS;

        $errors = array();
        /// Check for uniqueness of data if required
        if ($this->is_unique()) {

            switch ($this->field->datatype) {
                case 'menu':
                    $data = $this->options[$itemnew->{$this->inputname}];
                    break;
                case 'textarea':
                    $shortinputname = substr($this->inputname, 0, strlen($this->inputname)-7);
                    $itemnew = file_postupdate_standard_editor($itemnew, $shortinputname, $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'], 'totara_customfield', $prefix, $itemnew->id);
                    $data = $itemnew->{$shortinputname};
                    break;
                default:
                    $data = $itemnew->{$this->inputname};
            }

            // search for a match
            if ($data != '' && $DB->record_exists_select($tableprefix.'_info_data',
                            "fieldid = ? AND " . $DB->sql_compare_text('data', 1024) . ' = ? AND ' .
                            $prefix . "id != ?",
                            array($this->field->id, $data, $itemnew->id))) {
                    $errors["{$this->inputname}"] = get_string('valuealreadyused');
            }
        }
        return $errors;
    }

    /**
     * Sets the default data for the field in the form object
     * @param   object   instance of the moodleform class
     */
    function edit_field_set_default(&$mform) {
        if (!empty($this->field->defaultdata)) {
            $mform->setDefault($this->inputname, $this->field->defaultdata);
        }
    }

    /**
     * Sets the required flag for the field in the form object
     * @param   object   instance of the moodleform class
     */
    function edit_field_set_required(&$mform) {
        if ($this->is_required()) {
            $mform->addRule($this->inputname, get_string('customfieldrequired', 'totara_customfield'), 'required', null, 'client');
        }
    }

    /**
     * HardFreeze the field if locked.
     * @param   object   instance of the moodleform class
     */
    function edit_field_set_locked(&$mform) {
        if (!$mform->elementExists($this->inputname)) {
            return;
        }
        if ($this->is_locked()) {
            $mform->hardFreeze($this->inputname);
            $mform->setConstant($this->inputname, $this->data);
        }
    }

    /**
     * Hook for child classess to process the data before it gets saved in database
     * @param   mixed
     * @return  mixed
     */
    function edit_save_data_preprocess($data) {
        return $data;
    }

    /**
     * Hook for child classes to process the data after it gets saved in database (dataid is set)
     * @param   mixed
     * @return  null
     */
    public function edit_save_data_postprocess($data) {
        return null;
    }
    /**
     * Loads an object with data for this field ready for the edit form
     * form
     * @param   object a object
     */
    function edit_load_item_data(&$item) {
        if ($this->data !== NULL) {
            $item->{$this->inputname} = $this->data;
        }
    }

    /**
     * Check if the field data should be loaded into the object
     * By default it is, but for field prefixes where the data may be potentially
     * large, the child class should override this and return false
     * @return boolean
     */
    function is_object_data() {
        return true;
    }

/***** The following methods generally should not be overwritten by child classes *****/
    /**
     * Accessor method: set the itemid for this instance
     * @param   integer   id from the prefix (competency etc) table
     */
    function set_itemid($itemid) {
        $this->itemid = $itemid;
    }

    /**
     * Accessor method: set the fieldid for this instance
     * @param   integer   id from the _info_field table
     */
    function set_fieldid($fieldid) {
        $this->fieldid = $fieldid;
    }

    /**
     * Accessor method: Load the field record and prefix data and tableprefix associated with the prefix
     * object's fieldid and itemid
     */
    function load_data($itemid, $prefix, $tableprefix) {
        global $DB;

        /// Load the field object
        if (($this->fieldid == 0) || (!($field = $DB->get_record($tableprefix.'_info_field', array('id' => $this->fieldid))))) {
            $this->field = NULL;
            $this->inputname = '';
        } else {
            $this->field = $field;
            $this->inputname = 'customfield_'.$field->shortname;
        }
        if (!empty($this->field)) {
            if ($datarecord = $DB->get_record($tableprefix.'_info_data', array($prefix.'id' => $this->itemid, 'fieldid' => $this->fieldid), 'id, data')) {
                $this->data = $datarecord->data;
                $this->dataid = $datarecord->id;
            } else {
                $this->data = $this->field->defaultdata;
            }
        } else {
            $this->data = NULL;
        }
    }

    /**
     * Check if the field data is hidden to the current item 
     * @return  boolean
     */
    function is_hidden() {
        if ($this->field->hidden) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if the field data is considered empty
     * return boolean
     */
    function is_empty() {
        return ( ($this->data != '0') and empty($this->data));
    }

    /**
     * Check if the field is required on the edit page
     * @return   boolean
     */
    function is_required() {
        return (boolean)$this->field->required;
    }

    /**
     * Check if the field is locked on the edit page
     * @return   boolean
     */
    function is_locked() {
        return (boolean)$this->field->locked;
    }

    /**
     * Check if the field data should be unique
     * @return   boolean
     */
    function is_unique() {
        return (boolean)$this->field->forceunique;
    }

} /// End of class efinition


/***** General purpose functions for custom fields *****/

function customfield_load_data(&$item, $prefix, $tableprefix) {
    global $CFG, $DB, $TEXTAREA_OPTIONS;

    $typestr = '';
    $params = array();
    if (isset($item->typeid)) {
        $typestr = 'typeid = ?';
        $params[] = $item->typeid;
    }

    $fields = $DB->get_records_select($tableprefix.'_info_field', $typestr, $params);

    foreach ($fields as $field) {
        require_once($CFG->dirroot.'/totara/customfield/field/'.$field->datatype.'/field.class.php');
        $newfield = 'customfield_'.$field->datatype;
        $formfield = new $newfield($field->id, $item, $prefix, $tableprefix);
        //edit_load_item_data adds the field and data to the $item object
        $formfield->edit_load_item_data($item);
        //if an unlocked textfield we also need to prepare the editor fields
        if ($field->datatype == 'textarea' && !$formfield->is_locked()) {
            // Get short form by removing trailing '_editor' from $this->inputname.
            $shortinputname = substr($formfield->inputname, 0, strlen($formfield->inputname) - 7);
            $formatstr = $shortinputname . 'format';
            $item->$formatstr = FORMAT_HTML;
            if ($formfield->data == $formfield->field->defaultdata) {
                $item->$shortinputname = $formfield->field->defaultdata;
                $item = file_prepare_standard_editor($item, $shortinputname, $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                'totara_customfield', 'textarea', $formfield->fieldid);
            } else {
                $item = file_prepare_standard_editor($item, $shortinputname, $TEXTAREA_OPTIONS, $TEXTAREA_OPTIONS['context'],
                                'totara_customfield', $prefix, $formfield->dataid);
            }
        }
    }
}

/**
 * Print out the customisable fields
 * @param  object  instance of the moodleform class
 */
function customfield_definition(&$mform, $item, $prefix, $typeid=0, $tableprefix) {
    global $DB, $CFG;

    $typestr = '';
    $params = array();
    if ($typeid != 0) {
        $typestr = 'typeid = ?';
        $params[] = $typeid;
    }

    $fields = $DB->get_records_select($tableprefix.'_info_field', $typestr, $params, 'sortorder ASC');

    // check first if *any* fields will be displayed
    $display = false;
    foreach ($fields as $field) {
        if ($field->hidden == false) {
            $display = true;
        }
    }

    // display the header and the fields
    if ($display) {
        $mform->addElement('header', 'customfields', get_string('customfields', 'totara_customfield'));
        foreach ($fields as $field) {
            require_once($CFG->dirroot.'/totara/customfield/field/'.$field->datatype.'/field.class.php');
            $newfield = 'customfield_'.$field->datatype;
            $formfield = new $newfield($field->id, $item, $prefix, $tableprefix);
            $formfield->edit_field($mform);
        }
    }
}

function customfield_definition_after_data(&$mform, $item, $prefix, $typeid=0, $tableprefix) {
    global $CFG, $DB;

    $typestr = '';
    $params = array();
    if ($typeid != 0) {
        $typestr = 'typeid = ?';
        $params[] = $typeid;
    }

    $fields = $DB->get_records_select($tableprefix.'_info_field', $typestr, $params, 'sortorder ASC');

    foreach ($fields as $field) {
        require_once($CFG->dirroot.'/totara/customfield/field/'.$field->datatype.'/field.class.php');
        $newfield = 'customfield_'.$field->datatype;
        $formfield = new $newfield($field->id, $item, $prefix, $tableprefix);
        $formfield->edit_after_data($mform);
    }
}

function customfield_validation($itemnew, $prefix, $tableprefix) {
    global $CFG, $DB;

    $err = array();

    $typestr = '';
    $params = array();
    if (!empty($itemnew->typeid)) {
        $typestr = 'typeid = ?';
        $params[] = $itemnew->typeid;
    }

    $fields = $DB->get_records_select($tableprefix.'_info_field', $typestr, $params);

    foreach ($fields as $field) {
        require_once($CFG->dirroot.'/totara/customfield/field/'.$field->datatype.'/field.class.php');
        $newfield = 'customfield_'.$field->datatype;
        $formfield = new $newfield($field->id, $itemnew, $prefix, $tableprefix);
        $err += $formfield->edit_validate_field($itemnew, $prefix, $tableprefix);
    }

    return $err;
}

function customfield_save_data($itemnew, $prefix, $tableprefix) {
    global $CFG, $DB;

    $typestr = '';
    $params = array();
    if (isset($itemnew->typeid)) {
        $typestr = 'typeid = ?';
        $params[] = $itemnew->typeid;
    }

    $fields = $DB->get_records_select($tableprefix.'_info_field', $typestr, $params);

    foreach ($fields as $field) {
        require_once($CFG->dirroot.'/totara/customfield/field/'.$field->datatype.'/field.class.php');
        $newfield = 'customfield_'.$field->datatype;
        $formfield = new $newfield($field->id, $itemnew, $prefix, $tableprefix);
        $formfield->edit_save_data($itemnew, $prefix, $tableprefix);
    }
}

/**
 * Return an associative array of custom field name/value pairs for display
 *
 * The array contains values formatted for printing to the page. Hidden and
 * empty fields are not returned. Data has been passed through the appropriate
 * display_data() method.
 *
 * @param integer $itemid ID of the item the fields belong to
 * @param string $tableprefix Prefix to append '_info_field' to
 * @param string $prefix Custom field prefix (e.g. 'course' or 'position')
 *
 * @return array Associate array of field names and data values
 */
function customfield_get_fields($item, $tableprefix, $prefix) {
    global $CFG, $DB;
    $out = array();

    $fields = $DB->get_records($tableprefix.'_info_field', array(), 'sortorder ASC');

    foreach ($fields as $field) {
        require_once($CFG->dirroot.'/totara/customfield/field/'.$field->datatype.'/field.class.php');
        $newfield = 'customfield_'.$field->datatype;
        $formfield = new $newfield($field->id, $item, $prefix, $tableprefix);
        if (!$formfield->is_hidden() and !$formfield->is_empty()) {
            $out[s($formfield->field->fullname)] = $formfield->display_data();
        }
    }
    return $out;
}


/**
 * Returns an object with the custom fields set for the given id
 * @param  integer  id
 * @return  object
 */
function customfield_record($id, $tableprefix) {
    global $CFG, $DB;
    $item = new stdClass();

    $fields = $DB->get_records($tableprefix.'_info_field');

    foreach ($fields as $field) {
        require_once($CFG->dirroot.'/totara/customfield/field/'.$field->datatype.'/field.class.php');
        $newfield = 'customfield_'.$field->datatype;
        $formfield = new $newfield($field->id, $id);
        if ($formfield->is_object_data()) $item->{$field->shortname} = $formfield->data;
    }

    return $item;
}
