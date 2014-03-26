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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_question
 */

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/form/static.php');
require_once(dirname(__FILE__).'/libforms.php');
require_once($CFG->libdir.'/ddllib.php');

/**
 * Custom fileds management class
 */
class question_manager {

    const GROUP_QUESTION = 1;
    const GROUP_REVIEW = 2;
    const GROUP_OTHER = 3;

    /**
     * Answer id (each question can have many answers)
     * @var int
     */
    public $answerid = 0;

    /**
     * Subject user id (used by elements that input or output some information about certain user
     * By default - user currently logged in
     * @var int
     */
    public $subjectid = 0;

    /**
     * Label for elements
     * @var string
     */
    public $label = '';

    /**
     * Should created element be view only
     * @var boolean
     */
    public $viewonly = false;

    /**
     * User image html fragment associated with answer
     * @var string
     */
    public $userimage = '';

    /**
     * Instances of same elements
     */
    protected static $registry = array();

    /**
     * Create form
     * @param int $subjectid user id that is subject of question
     * @param int $answerid answer id field value
     */
    public function __construct($subjectid = 0, $answerid = 0) {
        global $CFG, $USER;
        if (!$subjectid) {
            $subjectid = $USER->id;
        }
        if (!is_ajax_request($_SERVER)) {
            require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');
            local_js();
        }
        $this->subjectid = $subjectid;
        $this->answerid = $answerid;
    }

    /**
     * Get all registered elements
     *
     * @return array
     */
    public static function get_registered_elements() {
        $dir = dirname(__FILE__).'/field';
        $elemfiles = glob($dir.'/*.class.php');
        $info = array();
        foreach ($elemfiles as $file) {
            $element = basename($file, '.class.php');
            $classname = 'question_'.$element;
            if (strpos($file, '..' !== false)) {
                throw new exception('Custom field element file cannot have two dots \'..\' sequentially');
            }
            require_once($file);
            if (class_exists($classname)) {
                $info[$element] = $classname::get_info();
                $info[$element]['classname'] = $classname;
            }
        }
        uasort($info, function ($a, $b) {
            if ($a['group'] > $b['group']) {
                return 1;
            } else if ($a['group'] < $b['group']) {
                return -1;
            } else if ($a['type'] > $b['type']) {
                return 1;
            } else if ($a['type'] < $b['type']) {
                return -1;
            }
            return 0;
        });
        return $info;
    }

    /**
     * Factory method to instantiate element
     *
     * @param question_storage associated storage
     * @param mixed $datatype string with datatype or stdClass with record elements
     * @return question_base
     */
    public function create_element(question_storage $storage, $datatype = null) {
        $elems = self::get_registered_elements();

        if ($storage->datatype != '') {
            $classname = 'question_'.$storage->datatype;
        } else if (is_object($datatype)) {
            if (isset($datatype->datatype)) {
                return $this->create_element($storage, $datatype->datatype);
            }
        } else if (isset($elems[$datatype])) {
            $storage->datatype = $datatype;
            $classname = $elems[$datatype]['classname'];
        } else {
            throw new question_exception('Cannot find element');
        }

        if (isset(self::$registry[$classname.'_'.$storage->id.'_'.$this->subjectid.'_'.$this->answerid])) {
            $element = self::$registry[$classname.'_'.$storage->id.'_'.$this->subjectid.'_'.$this->answerid];
            // Code from Val might be used later: $element->change_storage($storage);.
        } else {
            $element = new $classname($storage, $this->subjectid, $this->answerid);
        }
        if (!($element instanceof question_base)) {
            throw new question_exception('Cannot find element: '.json_encode($datatype));
        }
        self::$registry[$classname.'_'.$storage->id.'_'.$this->subjectid.'_'.$this->answerid] = $element;
        return $element;
    }

    /**
     * Helper method to add all xmldb fields of module to a table definition
     *
     * @param array $elements
     * @param array $xmldbfields domain specific fields
     * @return array of xmld_filed
     */
    public function get_xmldb(array $elements, $xmldbfields = array()) {
        foreach ($elements as $elem) {
            if ($elem instanceof question_base) {
                $fields = $elem->get_xmldb();
            } else if ($elem instanceof question_storage) {
                $fields = $elem->get_element()->get_xmldb();
            } else {
                throw new question_exception('Cannot find element: '.json_encode($elem));
            }

            $xmldbfields = array_merge($xmldbfields, (array)$fields);
        }
        return $xmldbfields;
    }

    /**
     * Add question fields to db table definition
     * @param array $allfields of xmldb_*
     * @param xmldb_table $table
     */
    public static function add_db_table(array $allfields, xmldb_table $table) {

        foreach ($allfields as $field) {
            if ($field instanceof xmldb_field) {
                $table->addField($field);
            } else if ($field instanceof xmldb_key) {
                $table->addKey($field);
            } else if ($field instanceof xmldb_index) {
                $table->addIndex($field);
            }
        }
    }
    /**
     * Clean registry of elements
     */
    public static function reset() {
        self::$registry = array();
    }
}

/**
 * Custom fields element base class
 *
 * All methods started with define_* used for admin-end (configuration)
 * All methods started with edit_* used for user-end (answers)
 * All methods started with get_* used for getting element meta information
 */
abstract class question_base {
    /**
     * Question definition storage
     * @var question_storage
     */
    protected $storage = null;

    /**
     * Label of field
     *
     * @var string
     */
    public $label = '';

    /**
     * String with listing users that can
     * view current user's answer to this question
     */
    public $viewers = array();

    /**
     * Information about other roles that can answer
     * this question and current user has permissions to view
     */
    public $roleinfo = array();

    /**
     * Whether or not this user can answer this question
     */
    public $cananswer = true;

    /**
     * User must answer on questions
     * @var bool
     */
    protected $required = false;

    /**
     * Is preview of question
     * @var bool
     */
    protected $preview = false;

    /**
     * User can only see answer
     * @var bool
     */
    protected $viewonly = false;

    /**
     * Answer id (each question can have many answers)
     * @var int
     */
    protected $answerid = 0;

    /**
     * Subject user id (used by elements that input or output some information about certain user
     * By default - user currently logged in
     * @var int
     */
    protected $subjectid = 0;

    /**
     * Indicates that edit form was populated by elements and further changes not possible
     */
    protected $formsent = false;

    /**
     * Form values
     * @var array
     */
    protected $values = array();

    /**
     * Instantiate new field
     *
     * @param question_storage $storage storage of element definition
     * @param string $subjectid subject user id (user that is this element is about). Default: currently loggedin user
     * @param int $answerid id where answer on this question is stored
     */
    public function __construct(question_storage $storage, $subjectid = 0, $answerid = 0) {
        global $USER;
        $this->storage = $storage;
        $this->storage->datatype = $this->get_type();
        $this->subjectid = ($subjectid > 0) ? $subjectid : $USER->id;
        $this->answerid = $answerid;
    }

    /**
     * Allow read access to restricted properties
     * Proxies storage fields to elements
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (isset($this->storage->$name)) {
            return $this->storage->$name;
        }
        return $this->$name;
    }

    /**
     * Handle isset parameters
     * @param string $name
     */
    public function __isset($name) {
        if (isset($this->$name)) {
            return true;
        }
        return isset($this->storage->$name);
    }

    public function __clone() {
        $this->storage = clone($this->storage);
    }

    /**
     * Proxy getid request to storage
     * @return int
     */
    public function getid() {
        return $this->storage->getid();
    }

    /**
     * Change storage instance
     * All changes to current storage will be abandoned
     *
     * @param question_storage $storage
     */
    public function change_storage(question_storage $storage) {
        $this->storage = $storage;
    }

    /**
     * Proxies storage fields to elements
     *
     * @param string $name
     * @return mixed
     */
    public function __set($name, $value) {
        if (in_array($name, question_storage::$storagefields)) {
            $this->storage->$name = $value;
        } else {
            throw new question_exception('Cannot save property: '.$name);
        }
    }

    /**
     * Proxies save to storage
     */
    public function save() {
        $this->storage->save();
    }

    /**
     * Encode values from form to paramX for saving configuration
     *
     * @param stdClass $fromform
     */
    public function define_set(stdClass $fromform) {
        $this->param1 = $fromform;
    }

    /**
     * Set saved configuration to form object
     *
     * @param stdClass $toform
     * @return stdClass $toform
     */
    public function define_get(stdClass $toform) {
        $toform = (object) $this->storage->param1;
        return $toform;
    }

    /**
     * Adds the form elements for creating or editing a question
     *
     * @param MoodleQuickForm $form instance of the moodleform class
     */
    public function add_settings_form_elements(MoodleQuickForm $form, $readonly = false, $info = null) {
        if ($this->requires_name()) {
            if ($readonly) {
                $form->addElement('static', 'name', get_string('question', 'totara_question'), 'size="50"');
            } else {
                $form->addElement('text', 'name', get_string('question', 'totara_question'), 'size="50"');
            }
            $form->addRule('name', null, 'required');
            $form->setType('name', PARAM_MULTILANG);
            $form->addHelpButton('name', 'question', 'totara_question');
        }
        $this->add_field_specific_settings_elements($form, $readonly, $info);
    }

    /**
     * Validate the data from the add/edit custom field form.
     * Generally this method should not be overwritten by child
     * classes.
     *
     * @param stdClass data from the add/edit custom field form
     * @param array $files
     * @return  array    associative array of error messages
     */
    public function define_validate_all($data, $files) {
        $data = (object)$data;
        $err = array();
        if ($this->requires_name()) {
            if (empty($data->name)) {
                $err['name'] = get_string('customfieldrequired', 'totara_customfield');
            }
        }
        $err += $this->define_validate($data, $files);
        return $err;
    }

    /**
     * Validate the data from the add/edit custom field form
     * that is specific to the current data type
     *
     * @param stdClass $data from the add/edit custom field form
     * @param array $files
     * @return array associative array of error messages
     */
    protected function define_validate($data, $files) {
        // Do nothing - override if necessary.
        return array();
    }

    /**
     * Populate edit form with question elements
     * @param MoodleQuickForm $form
     */
    public function add_field_form_elements(MoodleQuickForm $form) {
        $this->formsent = true;

        // Adding the header causes a new div to start in the output, containing all following elements until the next header.
        $form->addElement('header', 'question', $this->name);

        if ($this->cananswer) {
            if ($this->viewonly) {
                $this->add_field_specific_view_elements($form);
            } else {
                $this->add_field_specific_edit_elements($form);
            }
            if (!empty($this->viewers)) {
                $viewersstring = '<small class="visibleto">' . get_string('visibleto', 'totara_question') .
                            '<br>' . implode(', ', $this->viewers) . '</small>';
                $form->addElement('html', $viewersstring);
            }
        }

        foreach ($this->roleinfo as $info) {
            $question = $info->create_element($this->storage, $this);
            $question->label = $info->label;
            $form->addElement('html', $info->userimage);
            if ($question->cananswer) {
                $question->add_field_specific_view_elements($form);
            }
        }
    }

    /**
     * Set current element as required/not required
     * Must be set before added to form
     * Turns off question_base::set_viewonly()
     *
     * @param bool $is_required
     * @return question_base $this
     */
    public function set_required($is_required = true) {
        if ($this->formsent) {
            throw new question_exception('Form already populated');
        }
        $this->required = $is_required;
        if ($is_required) {
            $this->set_viewonly(false);
        }
        return $this;
    }

    /**
     * Set current element as view only
     * Must be set before added to form
     * Turns off question_base::set_required()
     *
     * @param bool $isviewonly
     * @return question_base $this
     */
    public function set_viewonly($isviewonly = true) {
        if ($this->formsent) {
            throw new question_exception('Form already rendered');
        }
        $this->viewonly = $isviewonly;
        if ($isviewonly) {
            $this->set_required(false);
        }
        return $this;
    }

    /**
     * Set current element as preview
     * Must be set before added to form
     *
     * @param bool $ispreview
     * @return question_base $this
     */
    public function set_preview($ispreview = true) {
        if ($this->formsent) {
            throw new question_exception('Form already rendered');
        }
        $this->preview = $ispreview;
        return $this;
    }

    /**
     * Reset the formsent variable so that the question can be reused.
     * If you are getting 'Form already rendered' exceptions then only call this method if you know what you are doing.
     */
    public function reset_form_sent() {
        $this->formsent = false;
    }

    /**
     * Validate elements input
     *
     * @see question_base::edit_validate
     * @return array
     */
    public function edit_validate($fromform) {
        return array();
    }

    /**
     * Load answer to object
     *
     * @param stdClass $data
     * @param string $source source of data 'form' (otherwise 'db')
     * @return question_base $this
     */
    private function set_data(stdClass $data, $source) {
        $this->edit_set($data, $source);
        $dbfields = $this->get_xmldb();
        foreach ($dbfields as $elem => $field) {
            if (!is_numeric($elem)) {
                if ($source == 'form') {
                    $name = $elem;
                } else {
                    $name = $field->getName();
                }
                if (isset($data->$name)) {
                    $this->values[$elem] = $data->$name;
                } else {
                    $this->values[$elem] = null;
                }
            }
        }
        return $this;
    }

    /**
     * Take answer from object
     *
     * @param stdClass $data
     * @param string $dest destination of data 'form' (otherwise 'db')
     * @return stdClass $data
     */
    private function get_data(stdClass $data, $dest) {
        $dbfields = $this->get_xmldb();
        foreach ($dbfields as $elem => $field) {
            if (!is_numeric($elem)) {
                if ($dest == 'form') {
                    $name = $elem;
                } else {
                    $name = $field->getName();
                }
                $data->$name = $this->values[$elem];
            }
        }
        $customdata = (array)$this->edit_get($dest);
        foreach ($customdata as $key => $value) {
            $data->$key = $value;
        }
        return $data;
    }

    /**
     * Set answer from Db Row object
     * @param stdClass $data
     * @return stdClass $data
     */
    final public function set_as_db(stdClass $data) {
        return $this->set_data($data, 'db');
    }

    /**
     * Set answer from form object
     * @param stdClass $data
     * @return stdClass $data
     */
    final public function set_as_form(stdClass $data) {
        return $this->set_data($data, 'form');
    }

    /**
     * Get answer as Db Row object
     * @param stdClass $data
     * @return stdClass $data
     */
    final public function get_as_db(stdClass $data) {
        return $this->get_data($data, 'db');
    }

    /**
     * Get answer as form object
     * @param stdClass $data
     * @return stdClass $data
     */
    final public function get_as_form(stdClass $data) {
        return $this->get_data($data, 'form');
    }

    /**
     * Get question shortname to use as datatype
     * @return string
     */
    public function get_type() {
        return str_replace('question_', '', get_class($this));
    }

    /**
     * Get the name for this question field - used to identify the element during setup.
     *
     * @return string
     */
    public function get_name() {
        return $this->storage->name;
    }

    /**
     * Get the title to display for this question field - shown to the user when answering.
     *
     * @return string
     */
    public function get_title() {
        return $this->storage->name;
    }

    /**
     * Return prefix name for db
     *
     * @return string
     */
    public function get_prefix_db() {
        return 'data_' . $this->storage->id;
    }

    /**
     * Return prefix name for form
     *
     * @return string
     */
    public function get_prefix_form() {
        return 'data_' . $this->storage->id . '_' . $this->answerid;
    }

    /**
     * Add form elements related to questions to form for user answers
     * Default implementation for first mapped field.
     * Override for all other cases.
     *
     * @param MoodleQuickForm $form
     */
    public function add_field_specific_view_elements(MoodleQuickForm $form) {
        $form->addElement('staticcallback', $this->get_prefix_form(), $this->label, $this);
    }

    /**
     * Add data for view other roles questions
     * This will be used when rendering the question
     */
    public function add_question_role_info($role, question_manager $info) {
        $this->roleinfo[$role] = $info;
    }

    /**
     * Modify a form element's renderer to exclude the 'label' portion.
     * Used for "other" elements that can take up the full form width.
     *
     * @param MoodleQuickForm $form
     * @param string $element Name of the form element to modify (value returned by {@link get_prefix_form()}
     * @return void
     */
    public function render_without_label(MoodleQuickForm $form, $element) {
        global $OUTPUT;
        $renderer = $form->defaultRenderer();
        $elementtemplate = $OUTPUT->container($OUTPUT->container('{element}'), 'fitem');
        $renderer->setElementTemplate($elementtemplate, $element);
    }

    /**
     * Render current element's answer as HTML
     * @param string $value value to render
     */
    public function to_html($value) {
        return format_string($value);
    }

    /**
     * Delete all data created by element
     * Override only if element add definition/data directly using global $DB object/files/etc.
     * If overriding, make sure to call "parent::delete();".
     */
    public function delete() {
        global $DB;

        // Delete linked elements.
        // Todo: move it to "redisplay" element.
        $module = $this->prefix;
        $sql = "SELECT mqf.id
                  FROM {" . $module . "_quest_field} mqf
                 WHERE datatype = 'redisplay'
                   AND " . $DB->sql_compare_text('param1', 40) . " = ?";
        $questions = $DB->get_records_sql($sql, array($this->id));
        $modulequestion =  $module . '_question';
        foreach ($questions as $question) {
            $modulequestion::delete($question->id);
        }
    }

    /**
     * Clone question properties (if they are stored in third party tables)
     * @param question_base $old old question instance
     */
    public function duplicate(question_base $old) {
    }

    /**
     * Override load answer to object
     *
     * @see question_base::set_data()
     * @param stdClass $data
     * @param string $source
     */
    public function edit_set(stdClass $data, $source) {
    }

    /**
     * Override take answer from object
     *
     * @see question_base::get_data()
     * @param string $dest
     * @return stdClass
     */
    public function edit_get($dest) {
    }

    /**
     * Add configuration settings form elements which are specific to a field
     *
     * @param MoodleQuickForm $form instance of the moodleform class
     */
    protected function add_field_specific_settings_elements(MoodleQuickForm $form, $readonly, $info) {
    }

    /**
     * Add form elements related to questions to form for user answers
     *
     * @param MoodleQuickForm $form
     */
    abstract public function add_field_specific_edit_elements(MoodleQuickForm $form);

    /**
     * Return array with information about current field
     * Late Static Binding used
     * Array structure:
     * array('group' => question::GROUP_*, 'type'=>'Localised element type')
     *
     *
     * @return array
     */
    public static function get_info() {

    }

    /**
     * Return array of field/indexes/keys definitions needed to store question data
     * One question can use several db fields if needed.
     * Question fields can be mapped to form elements by setting returning array keys queals to form element names
     * If data stored in outer tables and no extra field required this array can be empty
     *
     * @return array of xmldb_field
     */
    abstract public function get_xmldb();

    /**
     * If this element has any answerable form fields, or it's a view only (informational or static) element.
     *
     * @return bool
     */
    abstract public function is_answerable();


    /**
     * If this element requires that a name be set up for its use.
     *
     * @return bool
     */
    public function requires_name() {
        return $this->is_answerable();
    }


    /**
     * If this element requires that permissions be set up for its use.
     *
     * @return bool
     */
    public function requires_permissions() {
        return true;
    }


    /**
     * Allows the question to perform any actions that are required when it is about to go into active use.
     */
    public function activate() {
    }
}

/**
 * Definition storage class
 * All questions parameters stored in question_storage as questions themselves cannot save and load their configuration.
 */
abstract class question_storage {

    /**
     * Unique identifier of element
     * Usualy it's database element row id
     *
     * @param int
     */
    protected $id = 0;

    /**
     * Optional definition parameters for elements that will be saved to db
     *
     * @var string
     */
    private $param1 = null;
    private $param2 = null;
    private $param3 = null;
    private $param4 = null;
    private $param5 = null;

    /**
     * Answer field name.
     * Answer id stored in this field.
     * @var string
     */
    public $answerfield = null;

    /**
     * Tables and fields prefixes of components where question are used.
     * @var string
     */
    public $prefix = '';

    /**
     * Question element associated with storage
     * @var question_base
     */
    protected $element = null;

    /**
     * Questions element name
     * @var string
     */
    public $datatype = null;

    /**
     * Name of quesiton
     * @var string
     */
    public $name = '';

    /**
     * Default answer for question
     * @var string
     */
    public $defaultdata = null;

    /**
     * List of fields that must be stored/loaded
     * @var array
     */
    public static $storagefields = array('id', 'param1', 'param2', 'param3', 'param4', 'param5', 'datatype', 'name', 'defaultdata');

    /**
     * Save method used to save all params and retreive id
     * Save method must save all fields above
     *
     * @return question_storage $this
     */
    abstract public function save();

    /**
     * Get definition storage id
     * If id not set, it will be retreived from function @see question_storage::save()
     *
     * @return int $this->id
     */
    public function getid() {
        if (!$this->id) {
            $this->save();
        }
        return $this->id;
    }

    /**
     * Allow read access to restricted properties
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        if (in_array($name, array('param1', 'param2', 'param3', 'param4', 'param5'))) {
            if (isset($this->$name)) {
                return json_decode($this->$name, true);
            }
            return null;
        }

        if (isset($this->$name)) {
            return $this->$name;
        }
        throw new question_exception('Property not found: '.$name);
    }

    /**
     * Support isset parameters
     *
     * @param string $name
     */
    public function __isset($name) {
        if (in_array($name, self::$storagefields)) {
            return true;
        }
        return false;
    }

    /**
     * Write values to storage
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value) {
        if (in_array($name, array('param1', 'param2', 'param3', 'param4', 'param5'))) {
            $this->$name = json_encode($value);
            return;
        }
        throw new question_exception('Property not found: '.$name);
    }

    /**
     * Get question element associated with storage
     *
     * @return question_base
     */
    public function get_element() {
        return $this->element;
    }

    /**
     * Attach element to question
     *
     * @param mixed $elem question_base, or stdClass, or string with element name
     */
    public function attach_element($elem) {
        if ($elem instanceof question_base) {
            return $this->element = $elem;
        }
        // Add default element (without edit support).
        $manager = new question_manager();
        $this->element = $manager->create_element($this, $elem);
    }

    /**
     * Add storage fields to object for saving
     * @param type $obj
     */
    protected function export_storage_fields(stdClass $obj) {
        foreach (self::$storagefields as $field) {
            $obj->$field = $this->$field;
        }
    }

    /**
     * Add storage fields to object for saving
     * @param type $obj
     */
    protected function import_storage_fields(stdClass $obj) {
        foreach (self::$storagefields as $field) {
            $this->$field = $obj->$field;
        }
    }


    public function get_name() {
        if (isset($this->element)) {
            return $this->element->get_name();
        }

        return '';
    }
}

/**
 * Form element that during display his value calls callback of an any element
 */
class MoodleQuickForm_staticcallback extends MoodleQuickForm_static {
    /**
     * Function to call during display
     * @var type
     */
    protected $callback = null;

    /**
     * constructor
     *
     * @param string $elementname (optional) name of the text field
     * @param string $elementlabel (optional) text field label
     * @param string $callback (optional) function that returns value to display
     */
    public function MoodleQuickForm_staticcallback($elementname = null, $elementlabel = null, $callback = null) {
        parent::MoodleQuickForm_static($elementname, $elementlabel, '');
        $this->callback = $callback;
        $this->_text = html_writer::tag('em', get_string('notanswered', 'totara_question'));
    }

    /**
     * Overriden rendering method
     * @param string $text
     * @return string
     */
    public function setText($text) {
        if (empty($text)) {
            return;
        }
        $this->_text = $text;
        if (is_object($this->callback) && $this->callback instanceof question_base) {
            $this->_text = $this->callback->to_html($text);
        } else if (is_callable($this->callback)) {
            $this->_text = $this->callback($text);
        }
    }
}

// Register question specific form element.
MoodleQuickForm::registerElementType('staticcallback', "$CFG->dirroot/totara/question/lib.php", 'MoodleQuickForm_staticcallback');

class question_exception extends Exception {

}
