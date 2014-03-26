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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage totara_question
 */

abstract class multichoice extends question_base {
    /**
     * Display types
     */
    const DISPLAY_RADIO = 1;
    const DISPLAY_MENU = 2;

    /**
     * One table used for all choices questions. To distinguish between them each question element should provide own
     * unique type id. If two different elements can use same scales they both should have same type id.
     */
    const SCALE_TYPE_MULTICHOICE = 1;
    const SCALE_TYPE_RATING = 2;
    const SCALE_TYPE_REVIEW = 3;

    /**
     * Maximum number of choices to admin
     * Should be  as JS-disabled forms will always show all of them
     * It is prohibited to lower this number, as clients might use all of them and lower number of available options could make
     * some choices inaccessible to admin.
     */
    const MAX_CHOICES = 10;

    /**
     * Indicates if a multichoice question has yet been answered.
     */
    const ISANSWERED_FALSE = 0;
    const ISANSWERED_TRUE = -1;

    /**
     * Save choices as
     * @var string
     */
    protected $savechoice = '';

    /**
     * Type of scale
     * @var int
     */
    protected $scaletype = 0;

    /**
     * Flag indicates that this question was answered by the user
     * @var bool
     */
    protected $isanswered = false;

    /**
     * Constructor
     */
    public function __construct($storage, $subjectid = 0, $answerid = 0) {
        parent::__construct($storage, $subjectid, $answerid);
        if (!is_array($this->param3)) {
            $this->param3 = array();
        }
    }

    /**
     * Add database fields definition that represent current question
     *
     * @see question_base::get_xmldb()
     * @return array()
     */
    public function get_xmldb() {
        // Multiple answers store data in third party table.
        $fields = array();
        // Use ANSWER_UNANSWERED to indicate that the question has not yet been answered.
        $fields[$this->get_prefix_form()] = new xmldb_field($this->get_prefix_db(), XMLDB_TYPE_INTEGER, 10, null, null, null,
                self::ISANSWERED_FALSE);
        return $fields;
    }

    public function define_get(stdClass $toform) {
        global $DB;

        if ($this->param2) {
            $toform->{'listtype[list]'} = $this->param2;
        }

        $scaleid = $this->param1;
        if ($scaleid > 0) {
            $choices = array();
            $values = $DB->get_records($this->prefix.'_scale_value', array($this->prefix.'scaleid' => $scaleid), 'id');
            foreach ($values as $value) {
                $choice = array();
                $choice['option'] = $value->name;
                if (!is_null($value->score)) {
                    $choice['score'] = $value->score;
                }
                $choices[] = $choice;
            }
            if (!empty($this->param3) && is_array($this->param3)) {
                foreach ($this->param3 as $key) {
                    $choices[$key]['default'] = 1;
                }
            }
            $toform->choice = $choices;
        }

        // Get scale.
        $scale = $DB->get_record($this->prefix.'_scale', array('id' => $scaleid));
        if (isset($scale->name) && $scale->name != '') {
            $toform->selectchoices = $scaleid;
        }

        return $toform;
    }

    /**
     * Validate custom element configuration
     * @param stdClass $data
     * @param array $files
     */
    public function define_validate($data, $files) {
        $err = array();

        if (isset($data->listtype) && !$data->listtype['list']) {
            $err['listtype'] = get_string('required');
        }

        if ($data->saveoptions && trim($data->saveoptionsname) == '') {
            $err['savegroup'] = get_string('choicesmustbenamed', 'totara_question');
        }

        if (!isset($data->selectchoices) || !$data->selectchoices) {
            $atleastone = false;
            foreach ($data->choice as $choice) {
                if (trim($choice['option']) != "") {
                    $atleastone = true;
                    break;
                }
            }
            if (!$atleastone) {
                $err['choiceheader'] = get_string('atleastonerequired', 'totara_question');
            }
        }

        return $err;
    }

    /**
     * Set values from configuration form
     *
     * @param stdClass $fromform
     * @return stdClass $fromform
     */
    public function define_set(stdClass $fromform) {
        global $USER, $DB;

        if (isset($fromform->listtype)) {
            $this->param2 = $fromform->listtype['list'];
        }

        $scaleid = null;
        $wasexistingquestion = $this->param1 > 0;

        // If this is not a new question then we should have a param1. If not then the db will create a new param1.
        if ($wasexistingquestion) {
            $scaleid = $this->param1;

            // Determine if the current scale is a saved scale.
            $savedscale = $DB->get_record($this->prefix . '_scale', array('id' => $scaleid));
            $wassavedscale = ($savedscale->name != '');
            if (!$wassavedscale) {
                // If is not a saved scale then we will delete all scale values.
                // Later we will either point to a saved scale or re-add the scale values.
                $DB->delete_records($this->prefix . '_scale_value', array($this->prefix . 'scaleid' => $this->param1));
            }
        }

        // User picked a saved scale.
        if (isset($fromform->selectchoices) && $fromform->selectchoices > 0) {
            // Delete the original scale if its not a saved scale.
            if ($wasexistingquestion && !$wassavedscale) {
                $DB->delete_records($this->prefix . '_scale', array('id' => $this->param1));
            }

            // Set the selected scale.
            $this->param1 = $fromform->selectchoices;
            $param3 = array();
            if (isset($fromform->choice)) {
                foreach ($fromform->choice as $key => $choice) {
                    if ($choice['default']) {
                        $param3[] = $key;
                    }
                }
            }
            $this->param3 = $param3;
        } else {
            // Save default.
            $options = array();
            foreach ($fromform->choice as $choice) {
                if (trim($choice['option']) != '') {
                    $options[] = $choice;
                }
            }

            $param3 = array();
            foreach ($options as $key => $option) {
                if (isset($option['default']) && $option['default']) {
                    $param3[] = $key;
                }
            }
            $this->param3 = $param3;

            // Save the custom scale.
            if ($fromform->saveoptions) {
                $this->savechoice = $fromform->saveoptionsname;
            }
            $scale = new stdClass();
            $scale->id = $scaleid;
            $scale->name = $this->savechoice;
            $scale->userid = $USER->id;
            $scale->scaletype = $this->scaletype;
            if ($wasexistingquestion && !$wassavedscale) {
                $DB->update_record($this->prefix.'_scale', $scale);
            } else {
                $scaleid = $DB->insert_record($this->prefix.'_scale', $scale);
                $this->param1 = $scaleid;
            }

            // Now save options.
            foreach ($options as $option) {
                $value = new stdClass();
                $value->{$this->prefix.'scaleid'} = $scaleid;
                $value->name = trim($option['option']);
                if (isset($option['score'])) {
                    $value->score = $option['score'];
                }
                $DB->insert_record($this->prefix.'_scale_value', $value);
            }
        }
        return $fromform;
    }

    /**
     * Add scale/choices options
     *
     * @param MoodleQuickForm $form
     * @param string $jsid Javascript container id (make sense only when more than one choices menu on a page).
     *                      Two choices menus in one form not supported
     * @param bool $limitone Only one choice can be selected
     * @return multichoice $this
     */
    protected function add_choices_menu(MoodleQuickForm $form, $readonly = false, $jsid = 'availablechoices',
            $headerstringkey = 'availablechoices', $limitone = true) {
        global $DB, $OUTPUT;

        $type = $this->scaletype;
        $requiredstr = html_writer::empty_tag('img', array('title' => get_string('requiredelement', 'form'),
                'src' => $OUTPUT->pix_url('req'), 'alt' => get_string('requiredelement', 'form'), 'class'=>'req'));
        $form->addElement('header', $jsid, get_string($headerstringkey, 'totara_question') . $requiredstr);
        $form->setExpanded($jsid);

        // Saved scales.
        $saved = $this->get_saved_choices($type);
        if (!empty($saved)) {
            $opsets = array();
            foreach ($saved as $opsetid => $opsetdata) {
                $opsets[$opsetid] = format_string($opsetdata['name']);
            }
            $savedchoices = array('0' => get_string('createnewchoices', 'totara_question')) + $opsets;
            $select = $form->addElement('select', 'selectchoices', '', $savedchoices);
            if ($this->id > 0) {
                $select->setSelected($this->id);
            }
        }

        if ($readonly) {
            $numchoices = $DB->count_records($this->prefix . '_scale_value', array($this->prefix . 'scaleid' => $this->param1));
        } else {
            $numchoices = self::MAX_CHOICES;
        }

        // Show the table/list.
        $this->add_choices_menu_header($form, $readonly);
        for ($i = 0; $i < $numchoices; $i++) {
            $this->add_choices_menu_item($form, $i, $readonly);
        }
        $this->add_choices_menu_footer($form, $readonly);

        // Option to add items.
        if (!$readonly) {
            $form->addElement('static', 'addoptionelem', '',
                html_writer::link('#', get_string('addanotheroption', 'totara_question'),
                        array('id' => "addoptionlink_$jsid", 'class'=>'addoptionlink')));

            $save = array();
            $save[] = $form->createElement('advcheckbox', 'saveoptions', 0, get_string('savechoicesas', 'totara_question'));
            $save[] = $form->createElement('text', 'saveoptionsname');
            $form->addGroup($save, 'savegroup', '', null, false);
            $form->disabledIf('saveoptionsname', 'saveoptions');
            $form->setType('saveoptionsname', PARAM_TEXT);
            $this->add_choices_js($form, $jsid, $saved, $limitone);
        }

        return $this;
    }

    /**
     * Add a choices menu header to the form.
     *
     * @param bool $readonly
     */
    protected function add_choices_menu_header($form, $readonly) {
        // This is a placeholder for the error messages. If overriding, make sure the header has the id "choiceheader".
        $choice = array();
        $choice[] = $form->createElement('static', '');
        $form->addGroup($choice, 'choiceheader');
    }

    /**
     * Add a choices menu item to the form.
     *
     * @param bool $readonly
     */
    protected function add_choices_menu_item($form, $i, $readonly) {
        $choice = array();
        if ($readonly) {
            $choice[] = $form->createElement('advcheckbox', 'default');
            $choice[] = $form->createElement('static', 'option');
        } else {
            $choice[] = $form->createElement('text', 'option');
            $choice[] = $form->createElement('advcheckbox', 'default', '',
                    get_string('defaultmake', 'totara_question'), array('class' => 'makedefault'));
        }
        $form->addGroup($choice, "choice[$i]");
        $form->setType("choice[$i][option]", PARAM_TEXT);
    }

    /**
     * Add a choices menu footer to the form.
     *
     * @param bool $readonly
     */
    protected function add_choices_menu_footer($form, $readonly) {
    }

    /**
     * Add scale/choices options supporting JS
     * We don't use $PAGE->js_init_call() because it calls only functions.
     * However, we can generate JS code here as a function and run it using js_init_call()
     *
     * @param MoodleQuickForm $form
     * @param string $jsid Javascript container id (make sense only when more than one choices menu on a page)
     * @param array $savedchoices array of previously saved chices
     * @return multichoice $this
     */
    protected function add_choices_js($form, $jsid, $savedchoices=array(), $limitone = true) {
        global $PAGE;

        $limitone = (int)$limitone;
        $max = self::MAX_CHOICES;
        $jsonsavedchoices = json_encode($savedchoices);

        $PAGE->requires->strings_for_js(array('defaultmake', 'defaultselected', 'defaultunselect'), 'totara_question');
        $args = array('args' => '{"savedchoices": ' . $jsonsavedchoices . ', "oneAnswer": "' . $limitone . '", "jsid": "' .
                        $jsid . '", "max": ' . $max . '}');

        $jsmodule = array(
            'name' => 'totara_question_multichoice',
            'fullpath' => '/totara/question/field/multichoice.js',
            'requires' => array('json')
        );

        $PAGE->requires->js_init_call('M.totara_question_multichoice.init', $args, false, $jsmodule);

        return $this;
    }

    /**
     * Get saved choices
     * @return array
     */
    protected function get_saved_choices() {
        global $DB, $USER;
        $type = $this->scaletype;
        $sql = 'SELECT appsca.id, appsca.name AS scale_name, asv.name, asv.score
                FROM {'.$this->prefix.'_scale} appsca, {'.$this->prefix.'_scale_value} asv
                WHERE appsca.id = asv.'.$this->prefix.'scaleid
                    AND appsca.userid = ?
                    AND appsca.scaletype = ?
                    AND appsca.name <> \'\'
                    ORDER BY appsca.name, asv.id';
        $values = $DB->get_recordset_sql($sql, array($USER->id, $type));
        $scales = array();
        foreach ($values as $scaleid => $value) {
            $scales[$scaleid]['name'] = $value->scale_name;
            if (!isset($scales[$scaleid]['values'])) {
                $scales[$scaleid]['values'] = array();
            }
            $scales[$scaleid]['values'][] = array('name' => $value->name, 'score' => $value->score);
        }
        return $scales;
    }

    /**
     * Get list of choices
     *
     * @param int $scaleid Scale to use. If not set, scale will be taken from $this->param1
     * @return array
     */
    public function get_choice_list($scaleid = null) {
        global $DB;
        if (is_null($scaleid)) {
            if (is_array($this->param1)) {
                $options = array();
                foreach ($this->param1 as $o) {
                    $options[$o['score']] = $o['option'];
                }
                return $options;
            } else {
                $scaleid = $this->param1;
            }
        }
        // If we add editing of scales then we need a sortorder field. Don't forget to update activate() as well.
        $choices = $DB->get_records($this->prefix.'_scale_value', array($this->prefix.'scaleid' => $scaleid), 'id');
        $options = array();
        foreach ($choices as $id => $choice) {
            $options[$id] = $choice->name;
        }

        return $options;
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

    public function delete() {
        global $DB;
        $scaleid = $this->param1;
        if ($scaleid > 0) {
            $scale = $DB->get_record($this->prefix . '_scale', array('id' => $scaleid));
            if ($scale->name == '') {
                $DB->delete_records($this->prefix . '_scale_value', array($this->prefix . 'scaleid' => $scaleid));
                $DB->delete_records($this->prefix . '_scale', array('id' => $scaleid));
            }
        }
        parent::delete();
    }

    public function duplicate(question_base $old) {
        global $DB;
        // Duplicate scale if it was not saved.
        $oldscaleid = (int)$old->param1;
        if ($oldscaleid > 0) {
            $scale = $DB->get_record($this->prefix . '_scale', array('id' => $oldscaleid));
            if ($scale->name == '') {
                // It is not a saved scale, so duplicate.
                $values = $DB->get_records($this->prefix . '_scale_value', array($this->prefix . 'scaleid' => $oldscaleid), 'id');
                $scale->id = 0;
                $newscaleid = $DB->insert_record($this->prefix . '_scale', $scale);
                foreach ($values as $value) {
                    $value->id = 0;
                    $value->{$this->prefix . 'scaleid'} = $newscaleid;
                    $DB->insert_record($this->prefix . '_scale_value', $value);
                }
                $this->param1 = $newscaleid;
                $this->storage->save();
            }
        }
    }

    public function edit_set(stdClass $data, $source) {
        if ($source == 'form') {
            $name = $this->get_prefix_form();
            $this->isanswered = true;
            if (!isset($data->$name)) {
                $data->$name = self::ISANSWERED_TRUE;
            }
        } else {
            $name = $this->get_prefix_db();
            if (isset($data->$name) && $data->$name != self::ISANSWERED_FALSE) {
                $this->isanswered = true;
            }
        }
    }

    /**
     * Add form elements that represent current field
     *
     * @see question_base::add_field_specific_edit_elements()
     * @param MoodleQuickForm $form Form to alter
     */
    public function add_field_specific_edit_elements(MoodleQuickForm $form) {
        $optionsdirty = $this->get_choice_list();
        $options = array();
        foreach ($optionsdirty as $key => $option) {
            $options[$key] = format_string($option);
        }

        $name = $this->get_prefix_form();
        $param3 = $this->param3;

        if ($this->param2 < 1) {
            $this->param2 = self::DISPLAY_RADIO;
        }

        switch ($this->param2) {
            case self::DISPLAY_RADIO:
                $elements = array();
                foreach ($options as $key => $option) {
                    $elements[] = $form->createElement('radio', $name, '', $option, $key);
                }
                $form->addGroup($elements, $name, $this->label, array('<br/>'), false);
                break;
            case self::DISPLAY_MENU:
                $select = $form->createElement('select', $name, $this->label);
                if ($this->required && empty($param3)) {
                    $select->addOption('', self::ISANSWERED_TRUE, array( 'disabled' => 'disabled' ) );
                } else if (!$this->required) {
                    $select->addOption('', self::ISANSWERED_TRUE);
                }
                foreach ($options as $key => $option) {
                    $select->addOption( $option, $key );
                }
                $form->addElement($select);
                $select->setMultiple(false);
                break;
        }

        // Set the defaults.
        if (!$form->exportValue($name) && !$this->isanswered) {
            $default = current($param3);
            if (!empty($param3)) {
                $keys = array_slice($options, $default, 1, true);
                $form->setDefault($name, key($keys));
            } else if ($this->param2 == self::DISPLAY_MENU) {
                $form->setDefault($name, self::ISANSWERED_TRUE);
            }
        }

        if ($this->required) {
            $form->addRule($name, get_string('required'), 'required');
        }
    }

    public function activate() {
        global $DB;

        $scaleid = $this->param1;
        if ($scaleid > 0) {
            $scale = $DB->get_record($this->prefix . '_scale', array('id' => $scaleid));
            if ($scale->name != '') {
                // Sort values by id. Later, if we add editing of scales, we need to sort by sortorder field.
                $values = $DB->get_records($this->prefix . '_scale_value', array($this->prefix . 'scaleid' => $scaleid), 'id');
                $scale->id = 0;
                $scale->name = '';
                $newscaleid = $DB->insert_record($this->prefix . '_scale', $scale);
                foreach ($values as $value) {
                    $value->id = 0;
                    $value->{$this->prefix . 'scaleid'} = $newscaleid;
                    $DB->insert_record($this->prefix . '_scale_value', $value);
                }
                $this->param1 = $newscaleid;
                $this->storage->save();
            }
        }
    }

    public function to_html($value) {
        global $DB;

        $scalevalue = $DB->get_record($this->prefix . '_scale_value', array('id' => $value));
        if ($scalevalue) {
            return format_string($scalevalue->name);
        } else {
            return get_string('userselectednothing', 'totara_question');
        }
    }

}
