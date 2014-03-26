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
 * @subpackage hierarchy
 */


require_once($CFG->dirroot.'/lib/formslib.php');

class type_change_form extends moodleform {

    // Define the form
    function definition() {
        global $CFG, $OUTPUT;

        $mform =& $this->_form;

        $prefix   = $this->_customdata['prefix'];
        $typeid   = $this->_customdata['typeid'];
        $newtypeid   = $this->_customdata['newtypeid'];
        $itemid   = $this->_customdata['itemid'];
        $current_type_cfs  = $this->_customdata['current_type_cfs'];
        $new_type_cfs   = $this->_customdata['new_type_cfs'];
        $affected_data_count = $this->_customdata['affected_data_count'];
        $page  = $this->_customdata['page'];

        /// Add some extra hidden fields
        $mform->addElement('hidden', 'typeid', $typeid);
        $mform->setType('typeid', PARAM_INT);
        $mform->addElement('hidden', 'newtypeid', $newtypeid);
        $mform->setType('newtypeid', PARAM_INT);
        $mform->addElement('hidden', 'itemid', $itemid);
        $mform->setType('itemid', PARAM_INT);
        $mform->addElement('hidden', 'prefix', $prefix);
        $mform->setType('prefix', PARAM_ALPHA);
        $mform->addElement('hidden', 'page', $page);
        $mform->setType('page', PARAM_INT);

        if (!$current_type_cfs || $affected_data_count == false) {
            // old type has no custom fields
            // (or it has fields, but there's no data in them)
            $confirmtext = get_string('confirmproceed', 'totara_hierarchy');
            $buttontext = get_string('reclassifyitems', 'totara_hierarchy');

        } else if (!$new_type_cfs) {
            // new type has no custom fields
            $message = array();
            if ($cfs = hierarchy_get_formatted_custom_fields($current_type_cfs)) {
                foreach ($cfs as $cf) {
                    $message[] = $cf;
                }
            }
            $confirmtext = get_string('deletedataconfirmproceed', 'totara_hierarchy', html_writer::alist($message));
            $buttontext = get_string('reclassifyitemsanddelete', 'totara_hierarchy');

            // mark old fields for deletion
            if ($old_cfs = hierarchy_get_formatted_custom_fields($current_type_cfs)) {
                foreach ($old_cfs as $id => $string) {
                    $mform->addElement('hidden', 'field[' . $id . ']', 0);
                    $mform->setType('field[' . $id . ']', PARAM_INT);
                }
            }

        } else {
            // old and new types both have custom fields
            $mform->addElement('html', get_string('choosewhattodowithdata', 'totara_hierarchy'));

            $old_cfs = hierarchy_get_formatted_custom_fields($current_type_cfs);
            $new_cfs = hierarchy_get_formatted_custom_fields($new_type_cfs);
            if ($old_cfs && $new_cfs) {
                // create one pulldown per custom field from the old type
                foreach ($current_type_cfs as $old_cf) {
                    // @todo consider including count of data records
                    // affected here

                    // build list of options for the pulldown menu
                    $options = array(0 => get_string('deletethisdata', 'totara_hierarchy'));
                    foreach ($new_type_cfs as $new_cf) {
                        if (hierarchy_allowed_datatype_conversion($old_cf->datatype, $new_cf->datatype)) {
                            $options[$new_cf->id] = get_string('transfertox', 'totara_hierarchy', $new_cfs[$new_cf->id]);
                        }
                    }

                    if (array_key_exists($old_cf->id, $affected_data_count) && count($options) > 1) {
                        $mform->addElement('select', 'field[' . $old_cf->id . ']', get_string('datainx', 'totara_hierarchy', $old_cfs[$old_cf->id]), $options);
                        $mform->setType('field[' . $old_cf->id . ']', PARAM_INT);
                    } else if (array_key_exists($old_cf->id, $affected_data_count)) {
                        $mform->addElement('static', 'message[' . $old_cf->id . ']', get_string('datainx', 'totara_hierarchy', $old_cfs[$old_cf->id]), get_string('deletethisdata', 'totara_hierarchy'));
                        $mform->addElement('hidden', 'field[' . $old_cf->id . ']', 0);
                        $mform->setType('field[' . $old_cf->id . ']', PARAM_INT);
                    } else {
                        $mform->addElement('static', 'field[' . $old_cf->id . ']', get_string('datainx', 'totara_hierarchy', $old_cfs[$old_cf->id]), get_string('nodata', 'totara_hierarchy'));
                    }
                }
            }

            $confirmtext = '';
            $buttontext = get_string('reclassifyitemsandtransfer', 'totara_hierarchy');
        }

        $mform->addElement('html', $confirmtext);

        $this->add_action_buttons(true, $buttontext);
    }

    /**
     * Carries out validation of submitted form values
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    function validation($data, $files) {

        global $CFG;
        $errors = array();
        $data = (object) $data;

        $mform =& $this->_form;

        $current_type_cfs  = $this->_customdata['current_type_cfs'];
        $new_type_cfs   = $this->_customdata['new_type_cfs'];
        $affected_data_count = $this->_customdata['affected_data_count'];

        if ($current_type_cfs && $new_type_cfs) {

            // check each new field is only assigned to once
            $used = array();
            foreach ($current_type_cfs as $oldcf) {
                // skip fields with no data (as we don't provide a form
                // element for them)
                if (!array_key_exists($oldcf->id, $affected_data_count)) {
                    continue;
                }

                // get the fieldid that this field is being changed to
                $newfield = $data->field[$oldcf->id];

                // no need for validation on 'delete this data' selections
                if ($newfield == 0) {
                    continue;
                }

                // each field can only be assigned once
                if (in_array($newfield, $used)) {
                    $errors['field[' . $oldcf->id . ']'] = get_string('error:alreadyassigned', 'totara_hierarchy');
                }

                // add this one to the list of fields that have been used
                $used[] = $newfield;

                $newcf = $new_type_cfs[$newfield];
                // check that the old field can be converted to the new field's type
                if (!hierarchy_allowed_datatype_conversion($oldcf->datatype, $newcf->datatype)) {
                    $a = new stdClass();
                    $a->from = get_string('customfieldtype' . $oldcf->datatype, 'customfields');
                    $a->to = get_string('customfieldtype' . $newcf->datatype, 'customfields');
                    $errors['field[' . $oldcf->id . ']'] = get_string('error:cannotconvertfieldfromxtoy', 'totara_hierarchy', $a);

                }
            }
        }

        return $errors;
    }

}
