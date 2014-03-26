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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_question
 */

require_once('multichoice.class.php');

abstract class review extends multichoice {

    protected $value = array();

    protected $buttonlabel = '';


    public function __construct($storage, $subjectid = 0, $answerid = 0) {
        $this->scaletype = self::SCALE_TYPE_REVIEW;

        parent::__construct($storage, $subjectid, $answerid);
    }


    /**
     * Add database fields definition that represent current customfield
     *
     * @see question_base::get_xmldb()
     * @return array()
     */
    public function get_xmldb() {
        $fields = array();
        return $fields;
    }


    /**
     * Get data from element instance to save in db or put into form.
     *
     */
    public function edit_get($dest) {
        global $DB;
        $data = new stdClass();
        // Edit get used in two cases: when form is going to be populated and when data is going to be saved.
        if ($dest == 'form') {
            $name = $this->get_prefix_form() . '_reviewitem_';
            $data->$name = array();
            foreach ($this->value as $reviewdataid => $content) {
                $data->{$name . $reviewdataid} = $content;
            }
        } else {
            // Destination db -> we don't need to return any fields as data saved outside. We just save data.
            foreach ($this->value as $questreviewid => $content) {
                $todb = new stdClass();
                $todb->id = $questreviewid;
                $todb->content = $content;

                $DB->update_record($this->prefix.'_review_data', $todb);
            }
        }
        return $data;
    }


    /**
     * Custom set value for question instance
     *
     * @param stdClass $data
     * @param $source
     */
    public function edit_set(stdClass $data, $source) {
        global $DB;
        // Edit_set is not saving. It is value setter into object instance.
        if ($source == 'form') {

            $items = $this->get_grouped_items();

            $multifield = $this->param1;
            if ($multifield) {
                $scalevalues = $DB->get_records($this->prefix . '_scale_value',
                        array($this->prefix .'scaleid' => $this->param1), 'id');
            }

            foreach ($items as $scope) {
                foreach ($scope as $itemgroup) {
                    $currentuseritems = $itemgroup[$this->answerid];
                    if ($multifield) {
                        foreach ($scalevalues as $scalevalue) {
                            $field = $this->get_prefix_form() . '_reviewitem_' . $currentuseritems[$scalevalue->id]->id;
                            $this->value[$currentuseritems[$scalevalue->id]->id] = isset($data->$field) ? $data->$field : '';
                        }
                    } else {
                        $field = $this->get_prefix_form() . '_reviewitem_' . $currentuseritems[0]->id;
                        $this->value[$currentuseritems[0]->id] = isset($data->$field) ? $data->$field : '';
                    }
                }
            }
        } else {
            // Source is db
            // One quest field can be used in several assignments. We need to use assignment.
            $records = $DB->get_records($this->prefix.'_review_data',
                    array($this->prefix.'questfieldid' => $this->id, $this->answerfield => $this->answerid));
            foreach ($records as $record) {
                $this->value[$record->id] = $record->content;
            }
        }
    }


    /**
     * Get a list of all reviewdata records for this question and subject.
     *
     * @return array of reviewdata records, one per subquestion (scale value) per answerer (role)
     */
    public abstract function get_items();


    /** Get a list of all items that are linked to this question.
     *
     * @return array
     */
    public function get_review_items() {
        global $DB;

        $module = $this->prefix;
        list($answerssql, $answerids) = $DB->get_in_or_equal($module::get_related_answerids($this->answerid));

        $sql = 'SELECT DISTINCT reviewdata.itemid
                  FROM {'.$this->prefix.'_review_data} reviewdata
                 WHERE reviewdata.'.$this->prefix.'questfieldid = ?
                   AND reviewdata.'.$this->storage->answerfield.' '.$answerssql;
        return $DB->get_records_sql($sql, array_merge(array($this->id), $answerids));
    }


    /**
     * Check that ids are assigned to user.
     *
     * @param array $ids
     * @param int $userid the user which these ids should belong to
     * @return array $ids filtered
     */
    public function check_target_ids(array $ids, $userid) {
    }


    /**
     * Check if the place for storing answer on review question exists.
     *
     * @param stdClass $item review item as base
     * @return bool true is the stub already exists in the db.
     */
    public function stub_exists(array $item) {
        global $DB;

        $item[$this->storage->prefix.'questfieldid'] = $this->storage->id;
        $item[$this->storage->answerfield] = $this->answerid;

        return $DB->record_exists($this->storage->prefix.'_review_data', $item);
    }


    /**
     * Create place for storing answer on review question.
     *
     * Make sure that you check that the record doesn't already exist before calling this method.
     *
     * @param stdClass $item review item as base for new item
     * @return stdClass *_review_quest_data record
     */
    public function prepare_stub(stdclass $item) {
        global $DB;
        $stubs = array();

        $multifield = $this->param1;

        if ($multifield) {
            $scalevalues = $DB->get_records($this->prefix .'_scale_value',
                    array($this->prefix . 'scaleid' => $multifield), 'id');
        } else {
            $scalevalue = new stdClass();
            $scalevalue->id = 0;
            $scalevalues = array($scalevalue);
        }

        foreach ($scalevalues as $scalevalue) {
            $stub = clone($item);
            $stub->id = 0;
            $stub->content = '';
            $stub->{$this->storage->prefix.'questfieldid'} = $this->storage->id;
            $stub->{$this->prefix.'scalevalueid'} = $scalevalue->id;
            $stub->{$this->storage->answerfield} = $this->answerid;

            $stub->id = $DB->insert_record($this->storage->prefix.'_review_data', $stub);
            $stubs[$stub->{$this->prefix.'scalevalueid'}] = $stub;
        }
        return $stubs;
    }


    /**
     * Get answers, group them and add answer stubs if user cananswer
     */
    public function get_grouped_items() {
        $reviewitems = $this->get_items();

        $answerfield = $this->storage->answerfield;
        $groups = array();
        while (count($reviewitems)) {
            $reviewdata = array_shift($reviewitems);
            if (!isset($groups[$reviewdata->scope])) {
                $groups[$reviewdata->scope] = array();
            }
            if (!isset($groups[$reviewdata->scope][$reviewdata->itemid])) {
                $groups[$reviewdata->scope][$reviewdata->itemid] = array();
                if ($reviewdata->$answerfield != $this->answerid) {
                    $found = false;
                    foreach ($reviewitems as $reviewsearch) {
                        if ($reviewsearch->itemid == $reviewdata->itemid && $reviewsearch->$answerfield == $this->answerid) {
                            if (!$reviewsearch->scope || ($reviewsearch->scope == $reviewdata->scope)) {
                                $found = true;
                            }
                            break;
                        }
                    }
                    if (!$found && $this->cananswer) {
                        $groups[$reviewdata->scope][$reviewdata->itemid][$this->answerid] = $this->prepare_stub($reviewdata);
                    }
                }
            }
            if (!isset($groups[$reviewdata->scope][$reviewdata->itemid][$reviewdata->$answerfield])) {
                $groups[$reviewdata->scope][$reviewdata->itemid][$reviewdata->$answerfield] = array();
            }
            $groups[$reviewdata->scope][$reviewdata->itemid][$reviewdata->$answerfield]
                    [$reviewdata->{$this->prefix.'scalevalueid'}] = $reviewdata;
        }
        return $groups;
    }


    /**
     * If this element has any answerable form fields, or it's a view only (informational or static) element.
     *
     * @see question_base::is_answerable()
     * @return bool
     */
    public function is_answerable() {
        return true;
    }


    /**
     * Customfield specific settings elements
     *
     * @param MoodleQuickForm $form
     * @param bool $readonly
     * @param object $moduleinfo
     */
    protected function add_field_specific_settings_elements(MoodleQuickForm $form, $readonly, $moduleinfo) {
        global $PAGE;

        // JS for multiple fields.
        $jsmodule = array(
            'name' => 'totara_review',
            'fullpath' => '/totara/question/field/reviewsettings.js',
            'requires' => array('json'));
        $PAGE->requires->js_init_call('M.totara_review_settings.init', null, false, $jsmodule);

        // Form for choosing range.
        $form->addElement('advcheckbox', 'hasmultifield', get_string('multiplefields', 'totara_question'));
        $form->setType('hasmultifield', PARAM_BOOL);
        $form->addHelpButton('hasmultifield', 'multiplefields', 'totara_question');

        $this->add_choices_menu($form, $readonly, 'multiplefields', 'fieldsper' . $this->datatype);
    }


    /**
     * Add a choices menu header to the settings form.
     *
     * @param bool $readonly
     */
    protected function add_choices_menu_item($form, $i, $readonly) {
        $choice = array();
        if ($readonly) {
            $choice[] = $form->createElement('static', 'option');
        } else {
            $choice[] = $form->createElement('text', 'option');
        }
        $form->addGroup($choice, "choice[$i]");
        $form->setType("choice[$i][option]", PARAM_TEXT);
    }


    /**
     * Override the form rendering for review questions
     */
    public function add_field_form_elements(MoodleQuickForm $form) {
        global $OUTPUT;

        $canasnweritems = $this->can_answer_items();

        // Add the header - starts a new frameset.
        if ($canasnweritems && $this->required) {
            $requiredstr = html_writer::empty_tag('img', array('title' => get_string('requiredelement', 'form'),
                    'src' => $OUTPUT->pix_url('req'), 'alt' => get_string('requiredelement', 'form'), 'class'=>'req'));
        } else {
            $requiredstr = '';
        }
        $form->addElement('header', 'question', $this->name . $requiredstr);

        if ($canasnweritems) {
            $form->addElement('hidden', $this->get_prefix_form() . 'reviewitems')->setValue('');
        }
        // Get form prefix for all items.
        foreach ($this->roleinfo as $role => $info) {
            $element = $info->create_element($this->storage, $this);
            $this->roleinfo[$role]->formprefix = $element->get_prefix_form();
        }
        $this->add_field_specific_edit_elements($form);
    }


    /**
     * Add form elements that represent current field
     *
     * @see question_base::add_field_specific_edit_elements()
     * @param MoodleQuickForm $form Form to alter
     */
    public function add_field_specific_edit_elements(MoodleQuickForm $form) {
        $this->add_common_review_edit_elements($form);
    }


    /**
     * Check if the user can add and remove review items.
     *
     * @return bool
     */
    public function can_select_items() {
        return $this->cananswer && !$this->viewonly;
    }


    /**
     * Check if the user can give answers to review items.
     *
     * @return bool
     */
    public function can_answer_items() {
        return $this->cananswer && !$this->viewonly;
    }


    /**
     * Check if the item can be deleted.
     *
     * @param array $itemgroup the review item group to test
     * @return boolean
     */
    public function can_delete_item($itemgroup) {
        foreach ($itemgroup as $answeridx => $answeritems) {
            if ($this->answerid != $answeridx) {
                foreach ($answeritems as $answeritem) {
                    if ($answeritem->content != '') {
                        return false;
                    }
                }
            }
        }
        return true;
    }


    /**
     * Common for review questions form
     *
     * @param MoodleQuickForm $form
     */
    protected function add_common_review_edit_elements(MoodleQuickForm $form) {
        global $DB, $PAGE, $USER;

        local_js(array(
            TOTARA_JS_DIALOG,
            TOTARA_JS_TREEVIEW
        ));
        $PAGE->requires->string_for_js('save', 'totara_core');
        $PAGE->requires->string_for_js('selectall', 'totara_question');

        // Load selected items.
        $items = $this->get_grouped_items();

        $renderer = $PAGE->get_renderer('totara_question');

        // Choose items.
        if ($this->has_review_items() || $this->preview) {
            if ($this->can_select_items()) {
                // Add the chooser button.
                $PAGE->requires->strings_for_js(array('choose' . $this->datatype . 'review', 'removeconfirm'), 'totara_question');
                if ($this->preview) {
                    $form->addElement('button', $this->get_prefix_form() . '_disabledforpreview', $this->buttonlabel);
                } else {
                    $form->addElement('button', $this->get_prefix_form() . '_choosereviewitem', $this->buttonlabel);
                }
            }
        } else if (empty($items)) {
            // There are no items available to select.
            if ($this->subjectid == $USER->id) {
                $form->addElement('static', 'noitems', '', get_string('noself' . $this->datatype, 'totara_question'));
            } else {
                $user = $DB->get_record('user', array('id' => $this->subjectid));
                $form->addElement('static', 'noitems', '', get_string('nolearner' . $this->datatype, 'totara_question',
                        format_string(fullname($user))));
            }
            return;
        } // Else they have items but they cannot add items, so just show the current items.

        $form_prefix = $this->get_prefix_form();
        $args = array('args' => '{"questionid":'.$this->id.', "answerid":'.$this->answerid.',
            "formprefix": "'.$form_prefix.'", "prefix": "'.$this->prefix.'", "subjectid": '.$this->subjectid.',
            "datatype": "'.$this->datatype.'"}');

        $jsmodule = array(
            'name' => 'totara_review',
            'fullpath' => '/totara/question/field/review.js',
            'requires' => array('json'));
        $PAGE->requires->js_init_call('M.totara_review.init', $args, false, $jsmodule);

        // Show selected items.
        if (!empty($items)) {
            // Add the currently selected items.
            $renderer->add_review_items($form, $items, $this);
        } else {
            // There are no items selected.
            if (!$this->can_select_items()) {
                $form->addElement('static', '', '', html_writer::tag('em', get_string('nothingselected', 'totara_question')));
            } // Else don't show anything because there will be a "Choose items" button.
        }
    }


    /**
     * Override to add any form elements required for each review item.
     *
     * @param MoodleQuickForm $form
     * @param object $item
     */
    public function add_item_specific_edit_elements(MoodleQuickForm $form, $item) {
    }

    /**
     * Remove the review item.
     */
    public function delete() {
        global $DB;
        $DB->delete_records($this->prefix.'_review_data', array($this->prefix.'questfieldid' => $this->id));
        parent::delete();
    }


    /**
     * Validate custom element configuration
     * @param stdClass $data
     * @param array $files
     */
    public function define_validate($data, $files) {
        if ($data->hasmultifield) {
            $err = parent::define_validate($data, $files);
        } else {
            $err = array();
        }
        return $err;
    }


    /**
     * Set saved configuration to form object
     *
     * @param stdClass $toform
     * @return stdClass $toform
     */
    public function define_get(stdClass $toform) {
        parent::define_get($toform);

        $toform->hasmultifield = ($this->param1 > 0);

        return $toform;
    }


    /**
     * Set values from configuration form
     *
     * @param stdClass $fromform
     * @return stdClass $fromform
     */
    public function define_set(stdClass $fromform) {
        if ($fromform->hasmultifield) {
            $fromform = parent::define_set($fromform);
        } else {
            $this->param1 = 0;
        }
        return $fromform;
    }


    public function duplicate(question_base $old) {
        if ($this->param1) {
            parent::duplicate($old);
        }
    }


    public function edit_validate($fromform) {
        global $DB;

        $errors = parent::edit_validate($fromform);

        if ($this->required) {
            $items = $this->get_grouped_items();

            if ($this->has_review_items() && empty($items)) {
                $errors[$this->get_prefix_form() . '_choosereviewitem'] =
                        get_string('error:reviewmustselectitem', 'totara_question');
            }

            $multifield = $this->param1;
            if ($multifield) {
                $scalevalues = $DB->get_records($this->prefix . '_scale_value',
                        array($this->prefix .'scaleid' => $this->param1), 'id');
            }

            foreach ($items as $scope) {
                foreach ($scope as $itemgroup) {
                    $currentuseritems = $itemgroup[$this->answerid];
                    if ($multifield) {
                        foreach ($scalevalues as $scalevalue) {
                            $field = $this->get_prefix_form() . '_reviewitem_' . $currentuseritems[$scalevalue->id]->id;
                            if (!isset($fromform[$field]) || $fromform[$field] == '') {
                                $errors[$field] = get_string('required', 'totara_question');
                            }
                        }
                    } else {
                        $field = $this->get_prefix_form() . '_reviewitem_' . $currentuseritems[0]->id;
                        if (!isset($fromform[$field]) || $fromform[$field] == '') {
                            $errors[$field] = get_string('required', 'totara_question');
                        }
                    }
                }
            }
        }

        return $errors;
    }

}
