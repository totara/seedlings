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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Alastair Munro <alastair.munro@totaralms.com>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Main Class definition and library functions for report builder
 */

require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/filters/lib.php');
require_once($CFG->dirroot . '/totara/core/lib/scheduler.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/totaratablelib.php');
require_once($CFG->dirroot . '/totara/core/lib.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_base_source.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_base_content.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_base_access.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_base_preproc.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_base_embedded.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_join.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_column.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_column_option.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_filter_option.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_param.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_param_option.php');
require_once($CFG->dirroot . '/totara/reportbuilder/classes/rb_content_option.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

/**
 * Content mode options
 */
define('REPORT_BUILDER_CONTENT_MODE_NONE', 0);
define('REPORT_BUILDER_CONTENT_MODE_ANY', 1);
define('REPORT_BUILDER_CONTENT_MODE_ALL', 2);

/**
 * Access mode options
 */
define('REPORT_BUILDER_ACCESS_MODE_NONE', 0);
define('REPORT_BUILDER_ACCESS_MODE_ANY', 1);
define('REPORT_BUILDER_ACCESS_MODE_ALL', 2);

/**
 * Export option codes
 *
 * Bitwise flags, so new ones should be double highest value
 */
define('REPORT_BUILDER_EXPORT_EXCEL', 1);
define('REPORT_BUILDER_EXPORT_CSV', 2);
define('REPORT_BUILDER_EXPORT_ODS', 4);
define('REPORT_BUILDER_EXPORT_FUSION', 8);
define('REPORT_BUILDER_EXPORT_PDF_PORTRAIT', 16);
define('REPORT_BUILDER_EXPORT_PDF_LANDSCAPE', 32);

/*
 * Initial Display Options
 */
define('RB_INITIAL_DISPLAY_SHOW', 0);
define('RB_INITIAL_DISPLAY_HIDE', 1);

/**
 * Report cache status flags
 */
define('RB_CACHE_FLAG_NONE', -1);   // Cache not used.
define('RB_CACHE_FLAG_OK', 0);      // Everything ready.
define('RB_CACHE_FLAG_CHANGED', 1); // Cache table needs to be rebuilt.
define('RB_CACHE_FLAG_FAIL', 2);    // Cache table creation failed.
define('RB_CACHE_FLAG_GEN', 3);     // Cache table is being generated.

global $REPORT_BUILDER_EXPORT_OPTIONS;
$REPORT_BUILDER_EXPORT_OPTIONS = array(
    'xls'           => REPORT_BUILDER_EXPORT_EXCEL,
    'csv'           => REPORT_BUILDER_EXPORT_CSV,
    'ods'           => REPORT_BUILDER_EXPORT_ODS,
    'fusion'        => REPORT_BUILDER_EXPORT_FUSION,
    'pdf_portrait'  => REPORT_BUILDER_EXPORT_PDF_PORTRAIT,
    'pdf_landscape' => REPORT_BUILDER_EXPORT_PDF_LANDSCAPE,
);

/**
 *  Export to file system constants.
 *
 */
define('REPORT_BUILDER_EXPORT_EMAIL', 0);
define('REPORT_BUILDER_EXPORT_EMAIL_AND_SAVE', 1);
define('REPORT_BUILDER_EXPORT_SAVE', 2);

global $REPORT_BUILDER_EXPORT_FILESYSTEM_OPTIONS;
$REPORT_BUILDER_EXPORT_FILESYSTEM_OPTIONS = array(
    'exporttoemail' => REPORT_BUILDER_EXPORT_EMAIL,
    'exporttoemailandsave' => REPORT_BUILDER_EXPORT_EMAIL_AND_SAVE,
    'exporttosave' => REPORT_BUILDER_EXPORT_SAVE
);

// Maximum allowed time for report caching
define('REPORT_CACHING_TIMEOUT', 3600);

/**
 *  Pdf export constants.
 *
 */
define('REPORT_BUILDER_PDF_FONT_SIZE_DATA', 10);
define('REPORT_BUILDER_PDF_FONT_SIZE_RECORD', 14);
define('REPORT_BUILDER_PDF_FONT_SIZE_TITLE', 20);
define('REPORT_BUILDER_PDF_MARGIN_FOOTER', 10);
define('REPORT_BUILDER_PDF_MARGIN_BOTTOM', 20);
/**
 * PDF export memory limit (in MBs).
 */
define('REPORT_BUILDER_EXPORT_PDF_MEMORY_LIMIT', 1024);

/**
 * Main report builder object class definition
 */
class reportbuilder {
    /**
     * Available filter settings
     */
    const FILTERNONE = 0;
    const FILTER = 1;
    const FILTERALL = 2;

    /** @var rb_base_source */
    public $src;

    /** @var rb_column_option[] */
    public $columnoptions;

    /** @var rb_column[] */
    public $columns;

    public $fullname, $shortname, $source, $hidden, $searchcolumns, $filters, $filteroptions, $requiredcolumns;
    public $_filtering, $contentoptions, $contentmode, $embeddedurl, $description;
    public $_id, $recordsperpage, $defaultsortcolumn, $defaultsortorder;
    private $_joinlist, $_base, $_params, $_sid;

    private $_paramoptions, $_embeddedparams, $_fullcount, $_filteredcount, $_isinitiallyhidden;
    public $grouped, $reportfor, $embedded, $toolbarsearch;

    private $_post_config_restrictions;

    /**
     * @var bool $cache Cache state for current report
     */
    public $cache;

    /**
     *
     * @var bool $cacheignore If true cache will be ignored during report preparation
     */
    public $cacheignore = false;

    /**
     * @var stdClass $cacheschedule Record of cache scheduling and readyness
     */
    public $cacheschedule;

    /** @var string|bool name of caching table if used and up-to-date, false if not present */
    protected $cachetable = null;

    /**
     *
     * @var bool $ready State variable. True when reportbuilder finished construction.
     */
    protected $ready = false;

    /**
     * Constructor for reportbuilder object
     *
     * Generates a new reportbuilder report instance.
     *
     * Requires either a valid ID or shortname as parameters.
     *
     * Note: If a report is embedded then it is now guaranteed to have its embedded object loaded.
     * Previously, embedded reports were required to create the embedded object and pass it to this constructor in the
     * $embed_deprecated parameter. Now, this constructor will create the embedded object. The data required by the embedded
     * object should be passed in the $embeddata parameter.
     *
     * Note: If a report is embedded and it implements is_capable (all embedded reports SHOULD implement this, but are
     * not required to) then is_capable will be called: If the user does not have access then an exception is thrown. If
     * the function is not implemented then a debug warning is generated and an exception will NOT be thrown.
     *
     * @param integer $id ID of the report to generate
     * @param string $shortname Shortname of the report to generate
     * @param stdClass|bool $embed_deprecated Object containing settings for an embedded report - see note above
     * @param integer $sid Saved search ID if displaying a saved search
     * @param integer $reportfor User ID of user who is viewing the report
     *                           (or null to use the current user)
     * @param bool $nocache Force no cache usage. Only works if cache for current report is enabled
     *                       and generated
     * @param array $embeddata data to be passed to the embedded object constructor
     *
     */
    public function __construct($id=null, $shortname=null, $embed_deprecated=false, $sid=null, $reportfor=null,
            $nocache = false, $embeddata = array()) {
        global $USER, $DB;

        $report = false;
        if ($id != null) {
            // look for existing report by id
            $report = $DB->get_record('report_builder', array('id' => $id), '*', IGNORE_MISSING);
        } else if ($shortname != null) {
            // look for existing report by shortname
            $report = $DB->get_record('report_builder', array('shortname' => $shortname), '*', IGNORE_MISSING);
        } else {
            // either id or shortname is required
            print_error('noshortnameorid', 'totara_reportbuilder');
        }

        // Handle if report not found in db.
        $embed = null;
        if (!$report) {
            // Determine if this is an embedded report with a missing embedded record.
            if ($embed_deprecated) {
                $embed = $embed_deprecated;
            } else if ($shortname !== null) {
                $embed = reportbuilder_get_embedded_report_object($shortname, $embeddata);
            }
            if ($embed) {
                // This is an embedded report - maybe this is the first time we have run it, so try to create it.
                if (! $id = reportbuilder_create_embedded_record($shortname, $embed, $error)) {
                    print_error('error:creatingembeddedrecord', 'totara_reportbuilder', '', $error);
                }
                $report = $DB->get_record('report_builder', array('id' => $id));
            }
        }

        if (!$report) {
            print_error('reportwithidnotfound', 'totara_reportbuilder', '', $id);
        }

        // If this is an embedded report then load the embedded report object.
        if ($report->embedded && !$embed) {
            $embed = reportbuilder_get_embedded_report_object($report->shortname, $embeddata);
        }

        $this->_id = $report->id;
        $this->source = $report->source;
        $this->src = self::get_source_object($this->source);
        $this->shortname = $report->shortname;
        $this->fullname = $report->fullname;
        $this->hidden = $report->hidden;
        $this->initialdisplay = $report->initialdisplay;
        $this->toolbarsearch = $report->toolbarsearch;
        $this->description = $report->description;
        $this->embedded = $report->embedded;
        $this->contentmode = $report->contentmode;
        // Store the embedded URL for embedded reports only.
        if ($report->embedded && $embed) {
            $this->embeddedurl = $embed->url;
        }
        $this->embedobj = $embed;
        $this->recordsperpage = $report->recordsperpage;
        $this->defaultsortcolumn = $report->defaultsortcolumn;
        $this->defaultsortorder = $report->defaultsortorder;
        $this->_sid = $sid;
        // Assume no grouping initially.
        $this->grouped = false;

        $this->cacheignore = $nocache;
        if ($this->src->cacheable) {
            $this->cache = $report->cache;
            $this->cacheschedule = $DB->get_record('report_builder_cache', array('reportid' => $this->_id), '*', IGNORE_MISSING);
        } else {
            $this->cache = 0;
            $this->cacheschedule = false;
        }

        // Determine who is viewing or receiving the report.
        // Used for access and content restriction checks.
        if (isset($reportfor)) {
            $this->reportfor = $reportfor;
        } else {
            $this->reportfor = $USER->id;
        }

        if ($sid) {
            $this->restore_saved_search();
        }

        $this->_paramoptions = $this->src->paramoptions;

        if ($embed) {
            $this->_embeddedparams = $embed->embeddedparams;
        }
        $this->_params = $this->get_current_params();

        // Run the embedded report's capability checks.
        if ($embed) {
            if (method_exists($embed, 'is_capable')) {
                if (!$embed->is_capable($this->reportfor, $this)) {
                    print_error('nopermission', 'totara_reportbuilder');
                }
            } else {
                debugging('This report doesn\'t implement is_capable().
                    Sidebar filters will only use form submission rather than instant filtering.', DEBUG_DEVELOPER);
            }
        }

        // Allow sources to modify itself based on params.
        $this->src->post_params($this);

        $this->_base = $this->src->base . ' base';

        $this->requiredcolumns = array();
        if (!empty($this->src->requiredcolumns)) {
            foreach ($this->src->requiredcolumns as $column) {
                $key = $column->type . '-' . $column->value;
                $this->requiredcolumns[$key] = $column;
            }
        }

        $this->columnoptions = array();
        foreach ($this->src->columnoptions as $columnoption) {
            $key = $columnoption->type . '-' . $columnoption->value;
            if (isset($this->columnoptions[$key])) {
                debugging("Duplicate column option $key detected in source " . get_class($this->src), DEBUG_DEVELOPER);
            }
            $this->columnoptions[$key] = $columnoption;
        }

        $this->columns = $this->get_columns();

        // Some sources add joins when generating new columns.
        $this->_joinlist = $this->src->joinlist;

        $this->contentoptions = $this->src->contentoptions;

        $this->filteroptions = $this->src->filteroptions;
        $this->filters = $this->get_filters();

        $this->searchcolumns = $this->get_search_columns();

        $this->process_filters();

        // Allow the source to configure additional restrictions,
        // note that columns must not be changed any more here
        // because we may have already decided if cache is used.
        $colkeys = array_keys($this->columns);
        $reqkeys = array_keys($this->requiredcolumns);
        $this->src->post_config($this);
        if ($colkeys != array_keys($this->columns) or $reqkeys != array_keys($this->requiredcolumns)) {
            throw new coding_exception('Report source ' . get_class($this->src) .
                                            '::post_config() must not change report columns!');
        }

        $this->ready = true;
    }


    /**
     * Return if reportbuilder is ready to work.
     * @return bool
     */
    public function is_ready() {
        return $this->ready;
    }
    /**
     * Shortcut to function in report source.
     *
     * This may be called before data is generated for a report (e.g. embedded report page, report.php).
     * It should not be called when data will not be generated (e.g. report setup/config pages).
     */
    public function handle_pre_display_actions() {
        $this->src->pre_display_actions();
    }

    /**
     * Include javascript code needed by report builder
     */
    function include_js() {
        global $CFG, $PAGE;

        // Array of options for local_js
        $code = array();

        // Get any required js files that are specified by the source.
        $js = $this->src->get_required_jss();

        // Only include show/hide code for tabular reports.
        $graph = (substr($this->source, 0, strlen('graphical_feedback_questions')) == 'graphical_feedback_questions');
        if (!$graph) {
            $code[] = TOTARA_JS_DIALOG;
            $jsdetails = new stdClass();
            $jsdetails->initcall = 'M.totara_reportbuilder_showhide.init';
            $jsdetails->jsmodule = array('name' => 'totara_reportbuilder_showhide',
                'fullpath' => '/totara/reportbuilder/showhide.js');
            $jsdetails->args = array('hiddencols' => $this->js_get_hidden_columns());
            $jsdetails->strings = array(
                'totara_reportbuilder' => array('showhidecolumns'),
                'moodle' => array('ok')
            );
            $js[] = $jsdetails;

            // Add saved search.js.
            $jsdetails = new stdClass();
            $jsdetails->initcall = 'M.totara_reportbuilder_savedsearches.init';
            $jsdetails->jsmodule = array('name' => 'totara_reportbuilder_savedsearches',
                'fullpath' => '/totara/reportbuilder/saved_searches.js');
            $jsdetails->strings = array(
                'totara_reportbuilder' => array('managesavedsearches'),
                'form' => array('close')
            );
            $js[] = $jsdetails;
        }

        local_js($code);
        foreach ($js as $jsdetails) {
            if (!empty($jsdetails->strings)) {
                foreach ($jsdetails->strings as $scomponent => $sstrings) {
                    $PAGE->requires->strings_for_js($sstrings, $scomponent);
                }
            }

            $PAGE->requires->js_init_call($jsdetails->initcall,
                empty($jsdetails->args) ? null : $jsdetails->args,
                false, $jsdetails->jsmodule);
        }


        // Load Js for these filters.
        foreach ($this->filters as $filter) {
            $classname = get_class($filter);
            $filtertype = $filter->filtertype;
            $filterpath = $CFG->dirroot.'/totara/reportbuilder/filters/'.$filtertype.'.php';
            if (file_exists($filterpath)) {
                require_once $filterpath;
                if (method_exists($classname, 'include_js')) {
                    call_user_func(array($filter, 'include_js'));
                }
            }
        }
    }


    /**
     * Method for debugging SQL statement generated by report builder
     */
    function debug($level=1) {
        global $OUTPUT;
        if (!is_siteadmin()) {
            return false;
        }
        list($sql, $params) = $this->build_query(false, true);
        $sql .= $this->get_report_sort();
        echo $OUTPUT->heading('Query', 3);
        echo html_writer::tag('pre', $sql, array('class' => 'notifymessage'));
        echo $OUTPUT->heading('Query params', 3);
        echo html_writer::tag('pre', s(print_r($params, true)), array('class' => 'notifymessage'));
        if ($level > 1) {
            echo $OUTPUT->heading('Reportbuilder Object', 3);
            echo html_writer::tag('pre', s(print_r($this, true)), array('class' => 'notifymessage'));
        }
    }

    /**
     * Searches for and returns an instance of the specified preprocessor class
     * for a particular activity group
     *
     * @param string $preproc The name of the preproc class to return
     *                       (excluding the rb_preproc prefix)
     * @param integer $groupid The group id to create the preprocessor for
     * @return object An instance of the preproc. Returns false if
     *                the preproc can't be found
     */
    static function get_preproc_object($preproc, $groupid) {
        $sourcepaths = self::find_source_dirs();
        foreach ($sourcepaths as $sourcepath) {
            $classfile = $sourcepath . 'rb_preproc_' . $preproc . '.php';
            if (is_readable($classfile)) {
                include_once($classfile);
                $classname = 'rb_preproc_' . $preproc;
                if (class_exists($classname)) {
                    return new $classname($groupid);
                }
            }
        }
        return false;
    }

    /**
     * Searches for and returns an instance of the specified source class
     *
     * @param string $source The name of the source class to return
     *                       (excluding the rb_source prefix)
     * @return rb_base_source An instance of the source. Returns false if
     *                the source can't be found
     */
    static function get_source_object($source) {
        $sourcepaths = self::find_source_dirs();
        foreach ($sourcepaths as $sourcepath) {
            $classfile = $sourcepath . 'rb_source_' . $source . '.php';
            if (is_readable($classfile)) {
                include_once($classfile);
                $classname = 'rb_source_' . $source;
                if (class_exists($classname)) {
                    return new $classname();
                }
            }
        }

        // if exact match not found, look for match with group suffix
        // of the form: [sourcename]_grp_[grp_id]
        // if found, call the base source passing the groupid as an argument
        if (preg_match('/^(.+)_grp_([0-9]+)$/', $source, $matches)) {
            $basesource = $matches[1];
            $groupid = $matches[2];
            foreach ($sourcepaths as $sourcepath) {
                $classfile = $sourcepath . 'rb_source_' . $basesource . '.php';
                if (is_readable($classfile)) {
                    include_once($classfile);
                    $classname = 'rb_source_' . $basesource;
                    if (class_exists($classname)) {
                        return new $classname($groupid);
                    }
                }
            }
        }

        // if still not found, look for match with group suffix
        // of the form: [sourcename]_grp_all
        // if found, call the base source passing a groupid of 0 as an argument
        if (preg_match('/^(.+)_grp_all$/', $source, $matches)) {
            $basesource = $matches[1];
            foreach ($sourcepaths as $sourcepath) {
                $classfile = $sourcepath . 'rb_source_' . $basesource . '.php';
                if (is_readable($classfile)) {
                    include_once($classfile);
                    $classname = 'rb_source_' . $basesource;
                    if (class_exists($classname)) {
                        return new $classname(0);
                    }
                }
            }
        }


        // bad source
        throw new ReportBuilderException("Source '$source' not found");
    }

    /**
     * Searches codebase for report builder source files and returns a list
     *
     * @param bool $includenonselectable If true then include sources even if they can't be used in custom reports (for testing)
     * @return array Associative array of all available sources, formatted
     *               to be used in a select element.
     */
    public static function get_source_list($includenonselectable = false) {
        global $DB;

        $output = array();

        foreach (self::find_source_dirs() as $dir) {
            if (is_dir($dir) && $dh = opendir($dir)) {
                while(($file = readdir($dh)) !== false) {
                    if (is_dir($file) ||
                    !preg_match('|^rb_source_(.*)\.php$|', $file, $matches)) {
                        continue;
                    }
                    $source = $matches[1];
                    $src = reportbuilder::get_source_object($source);
                    $sourcename = $src->sourcetitle;
                    $preproc = $src->preproc;

                    if ($src->selectable || $includenonselectable) {
                        if ($src->grouptype == 'all') {
                            $sourcestr = $source . '_grp_all';
                            $output[$sourcestr] = $sourcename;
                        } else if ($src->grouptype != 'none') {
                            // Create a source for every group that's based on this source's preprocessor.
                            $groups = $DB->get_records('report_builder_group', array('preproc' => $preproc));
                            foreach ($groups as $group) {
                                $sourcestr = $source . '_grp_' . $group->id;
                                $output[$sourcestr] = $sourcename . ': ' . $group->name;
                            }
                        } else {
                            // Otherwise, just create a single source.
                            $output[$source] = $sourcename;
                        }
                    }
                }
                closedir($dh);
            }
        }
        asort($output);
        return $output;
    }

    /**
     * Gets list of source directories to look in for source files
     *
     * @return array An array of paths to source directories
     */
    static function find_source_dirs() {
        global $CFG;

        $sourcepaths = array();

        $locations = array(
            'mod',
            'block',
            'tool',
            'totara',
            'local',
            'enrol'
        );

        // Search for rb_sources directories for each plugin type.
        foreach ($locations as $modtype) {
            foreach (core_component::get_plugin_list($modtype) as $mod => $path) {
                $dir = "$path/rb_sources/";
                if (file_exists($dir) && is_dir($dir)) {
                    $sourcepaths[] = $dir;
                }
            }
        }

        return $sourcepaths;
    }


    /**
     * Reduces an array of objects to those that match all specified conditions
     *
     * @param array $items An array of objects to reduce
     * @param array $conditions An associative array of conditions.
     *                          key is the object's property, value is the value
     *                          to match against
     * @param boolean $multiple If true, returns all matches, as an array,
     *                          otherwise returns first match as an object
     *
     * @return mixed An array of objects or a single object that match all
     *               the conditions
     */
    static function reduce_items($items, $conditions, $multiple=true) {
        if (!is_array($items)) {
            throw new ReportBuilderException('Input not an array');
        }
        if (!is_array($conditions)) {
            throw new ReportBuilderException('Conditions not an array');
        }
        $output = array();
        foreach ($items as $item) {
            $status = true;
            foreach ($conditions as $name => $value) {
                // condition fails if property missing
                if (!property_exists($item, $name)) {
                    $status = false;
                    break;
                }
                if ($item->$name != $value) {
                    $status = false;
                    break;
                }
            }
            if ($status && $multiple) {
                $output[] = $item;
            } else if ($status) {
                return $item;
            }
        }
        return $output;
    }

    static function get_single_item($items, $type, $value) {
        $cond = array('type' => $type, 'value' => $value);
        return self::reduce_items($items, $cond, false);
    }


    /**
     * Check the joins provided are in the joinlist
     *
     * @param array $joinlist Join list to check for joins
     * @param mixed $joins Single, or array of joins to check
     * @returns boolean True if all specified joins are in the list
     *
     */
    static function check_joins($joinlist, $joins) {
        // nothing to check
        if ($joins === null) {
            return true;
        }

        // get array of available names from join list provided
        $joinnames = array('base');
        foreach ($joinlist as $item) {
            $joinnames[] = $item->name;
        }

        // return false if any listed joins don't exist
        if (is_array($joins)) {
            foreach ($joins as $join) {
                if (!in_array($join, $joinnames)) {
                    return false;
                }
            }
        } else {
            if (!in_array($joins, $joinnames)) {
                return false;
            }
        }
        return true;
    }


    /**
     * Looks up the saved search ID specified and attempts to restore
     * the SESSION variable if access is permitted
     *
     * @return Boolean True if user can view, error otherwise
     */
    function restore_saved_search() {
        global $SESSION, $DB;
        if ($saved = $DB->get_record('report_builder_saved', array('id' => $this->_sid))) {

            if ($saved->ispublic != 0 || $saved->userid == $this->reportfor) {
                $SESSION->reportbuilder[$this->_id] = unserialize($saved->search);
            } else {
                if (defined('FULLME') and FULLME === 'cron') {
                    mtrace('Saved search not found or search is not public');
                } else {
                    print_error('savedsearchnotfoundornotpublic', 'totara_reportbuilder');
                }
                return false;
            }
        } else {
            if (defined('FULLME') and FULLME === 'cron') {
                mtrace('Saved search not found or search is not public');
            } else {
                print_error('savedsearchnotfoundornotpublic', 'totara_reportbuilder');
            }
            return false;
        }
        return true;
    }



    /**
     * Gets any filters set for the current report from the database
     *
     * @return array Array of filters for current report or empty array if none set
     */
    public function get_filters() {
        global $DB;

        $out = array();
        $filters = $DB->get_records('report_builder_filters', array('reportid' => $this->_id), 'sortorder');
        foreach ($filters as $filter) {
            $type = $filter->type;
            $value = $filter->value;
            $advanced = $filter->advanced;
            $region = $filter->region;
            $name = "{$filter->type}-{$filter->value}";

            // To properly support multiple languages - only use value in database if it's different from the default.
            // If it's the same as the default for that filter, use the default string directly.
            if (isset($filter->customname)) {
                // Use value from database.
                $filtername = $filter->filtername;
            } else {
                // Use default value.
                $defaultnames = $this->get_default_headings_array();
                $filtername = isset($defaultnames[$filter->type . '-' . $filter->value]) ?
                    $defaultnames[$filter->type . '-' . $filter->value] : null;
            }
            // Only include filter if a valid object is returned.
            if ($filterobj = rb_filter_type::get_filter($type, $value, $advanced, $region, $this)) {
                $filterobj->filterid = $filter->id;
                $filterobj->filtername = $filtername;
                $filterobj->customname = isset($filter->customname) ? $filter->customname : 0;
                // Change label if there is a customname for this filter.
                $filterobj->label = ($filter->customname == 1) ? $filtername : $filterobj->label;
                $out[$name] = $filterobj;

                // enabled report grouping if any filters are grouped
                if (isset($filterobj->grouping) && $filterobj->grouping != 'none') {
                    $this->grouped = true;
                }
            }
        }
        return $out;
    }

    /**
     * Gets any search columns set for the current report from the database
     *
     * @return array Array of search columns for current report or empty array if none set
     */
    public function get_search_columns() {
        global $DB;

        $searchcolumns = $DB->get_records('report_builder_search_cols', array('reportid' => $this->_id));

        return $searchcolumns;
    }

    /**
     * Returns sql where statement based on active filters
     * @param string $extrasql
     * @param array $extraparams for the extra sql clause (named params)
     * @return array containing one array of SQL clauses and one array of params
     */
    function fetch_sql_filters($extrasql='', $extraparams=array()) {
        global $SESSION;

        $where_sqls = array();
        $having_sqls = array();
        $filterparams = array();

        if ($extrasql != '') {
            if (strpos($extrasql, '?')) {
                print_error('extrasqlshouldusenamedparams', 'totara_reportbuilder');
            }
            $where_sqls[] = $extrasql;
        }

        if (!empty($SESSION->reportbuilder[$this->_id])) {
            foreach ($SESSION->reportbuilder[$this->_id] as $fname => $data) {
                if ($fname == 'toolbarsearchtext') {
                    if ($this->toolbarsearch && $this->has_toolbar_filter() && $data) {
                        list($where_sqls[], $params) = $this->get_toolbar_sql_filter($data);
                        $filterparams = array_merge($filterparams, $params);
                    }
                } else if (array_key_exists($fname, $this->filters)) {
                    $filter = $this->filters[$fname];
                    if ($filter->grouping != 'none') {
                        list($having_sqls[], $params) = $filter->get_sql_filter($data);
                    } else {
                        list($where_sqls[], $params) = $filter->get_sql_filter($data);
                    }
                    $filterparams = array_merge($filterparams, $params);
                }
            }
        }

        $out = array();
        if (!empty($having_sqls)) {
            $out['having'] = implode(' AND ', $having_sqls);
        }
        if (!empty($where_sqls)) {
            $out['where'] = implode(' AND ', $where_sqls);
        }

        return array($out, array_merge($filterparams, $extraparams));
    }

    /**
     * Same as fetch_sql_filters() but returns array of strings
     * describing active filters instead of SQL
     *
     * @return array of strings
     */
    function fetch_text_filters() {
        global $SESSION;
        $out = array();
        if (!empty($SESSION->reportbuilder[$this->_id])) {
            foreach ($SESSION->reportbuilder[$this->_id] as $fname => $data) {
                if ($fname == 'toolbarsearchtext') {
                    if ($this->toolbarsearch && $this->has_toolbar_filter() && $data) {
                        $out[] = $this->get_toolbar_text_filter($data);
                    }
                } else if (array_key_exists($fname, $this->filters)) {
                    $field = $this->filters[$fname];
                    $out[] = $field->get_label($data);
                }
            }
        }
        return $out;
    }

    /**
     * Determine if there are columns defined for the toolbar search for this report
     *
     * @return bool true if there are toolbar search columns defined
     */
    private function has_toolbar_filter() {
        $columns = $this->get_search_columns();
        return (!empty($columns));
    }

    /**
     * Returns the condition to be used with SQL where
     *
     * @param string $toolbarsearchtext filter settings
     * @return array containing filtering condition SQL clause and params
     */
    private function get_toolbar_sql_filter($toolbarsearchtext) {
        global $CFG;

        require_once($CFG->dirroot . '/totara/core/searchlib.php');

        $keywords = totara_search_parse_keywords($toolbarsearchtext);
        $columns = $this->get_search_columns();

        if (empty($keywords) || empty($columns)) {
            return array('1=1', array());
        }

        $dbfields = array();
        foreach ($columns as $column) {
            if ($this->is_cached()) {
                $dbfields[] = $column->type . '_' . $column->value;
            } else {
                $columnobject = self::get_single_item($this->columnoptions, $column->type, $column->value);
                $dbfields[] = $columnobject->field;
            }
        }

        return totara_search_get_keyword_where_clause($keywords, $dbfields, SQL_PARAMS_NAMED);
    }

    /**
     * Returns a human friendly description of the toolbar search criteria
     *
     * @param array $toolbarsearchtext the text that is being looked for
     * @return string active toolbar search criteria
     */
    private function get_toolbar_text_filter($toolbarsearchtext) {
        $columns = $this->get_search_columns();

        $numberoffields = count($columns);

        if ($numberoffields == 0) {
            return '';

        } else if ($numberoffields == 1) {
            $column = reset($columns);
            $columnobject = self::get_single_item($this->columnoptions, $column->type, $column->value);
            $a = new stdClass();
            $a->searchtext = $toolbarsearchtext;
            $a->column = $columnobject->name;
            return get_string('toolbarsearchtextiscontainedinsingle', 'totara_reportbuilder', $a);

        } else {
            $result = get_string('toolbarsearchtextiscontainedinmultiple', 'totara_reportbuilder', $toolbarsearchtext);
            $columnnames = array();
            foreach ($columns as $column) {
                $columnobject = self::get_single_item($this->columnoptions, $column->type, $column->value);
                $columnnames[] = $columnobject->name;
            }
            $result .= implode(', ', $columnnames);
            return $result;
        }
    }

    private function process_filters() {
        global $CFG, $SESSION;
        require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');
        $clearfilters = optional_param('clearfilters', 0, PARAM_INT);
        $mformstandard = new report_builder_standard_search_form($this->get_current_url(),
                array('fields' => $this->get_standard_filters()));
        $adddatastandard = $mformstandard->get_data(false);
        if ($adddatastandard || $clearfilters) {
            foreach ($this->get_standard_filters() as $field) {
                if (isset($adddatastandard->submitgroupstandard['clearstandardfilters']) || $clearfilters) {
                    // Clear out any existing filters.
                    $field->unset_data();
                } else {
                    $data = $field->check_data($adddatastandard);
                    if ($data === false) {
                        // Unset existing result if field has been set back to "not set" position.
                        $field->unset_data();
                    } else {
                        $field->set_data($data);
                    }
                }
            }
        }
        $mformsidebar = new report_builder_sidebar_search_form($this->get_current_url(),
                array('report' => $this, 'fields' => $this->get_sidebar_filters()));
        $adddatasidebar = $mformsidebar->get_data(false);
        if ($adddatasidebar || $clearfilters) {
            foreach ($this->get_sidebar_filters() as $field) {
                if (isset($adddatasidebar->submitgroupsidebar['clearsidebarfilters']) || $clearfilters) {
                    // Clear out any existing filters.
                    $field->unset_data();
                } else {
                    $data = $field->check_data($adddatasidebar);
                    if ($data === false) {
                        // Unset existing result if field has been set back to "not set" position.
                        $field->unset_data();
                    } else {
                        $field->set_data($data);
                    }
                }
            }
        }
        $mformtoolbar = new report_builder_toolbar_search_form($this->get_current_url());
        $adddatatoolbar = $mformtoolbar->get_data(false);
        if ($adddatatoolbar || $clearfilters) {
            if (isset($adddatatoolbar->cleartoolbarsearchtext) || $clearfilters) {
                // Clear out any existing data.
                unset($SESSION->reportbuilder[$this->_id]['toolbarsearchtext']);
                unset($_POST['toolbarsearchtext']);
            } else {
                $data = $adddatatoolbar->toolbarsearchtext;
                if (empty($data)) {
                    // Unset existing result if field has been set back to "not set" position.
                    unset($SESSION->reportbuilder[$this->_id]['toolbarsearchtext']);
                    unset($_POST['toolbarsearchtext']);
                } else {
                    $SESSION->reportbuilder[$this->_id]['toolbarsearchtext'] = $data;
                }
            }
        }
    }

    /**
     * Get column names in resulting query
     *
     * @return array
     */
    function get_column_aliases() {
        $fields = array();
        foreach ($this->columns as $column) {
            $fields[] = $column->value;
        }
        return $fields;
    }

    /**
     * Get fields and aliases from appropriate source
     *
     * @param array $source soruce should object with 'field' and 'fieldalias' properties
     * @param bool $aliasonly if enabled will return only aliases of field
     * @return array of SQL snippets
     */
    function get_alias_fields(array $source, $aliasonly = false) {
        $result = array();
        foreach($source as $fields) {
            if (is_object($fields) && (method_exists($fields, 'get_field') || isset($fields->field))) {
                if (method_exists($fields, 'get_field')) {
                    $fieldname = $fields->get_field();
                }
                else {
                    $fieldname = $fields->field;
                }
                // support of several fields in one filter/column/etc
                if (is_array($fieldname)) {
                    $field = array();
                    foreach ($fieldname as $key => $value) {
                        // need to namespace these extra keys to avoid collisions
                        $field["rb_composite_{$key}"] = $value;
                    }
                } else {
                     if (isset($fields->fieldalias)) {
                         $field = array($fields->fieldalias => $fieldname);
                     }
                }

                foreach ($field as $alias=>$name) {
                    if ($aliasonly) {
                        $result[] = $alias;
                    } else {
                        $result[] = "{$name} AS {$alias}";
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Returns user visible column heading name
     *
     * @param rb_column $column
     * @param bool false means return html, true means utf-8 plaintext for exports
     * @return string
     */
    public function format_column_heading(rb_column $column, $plaintext) {
        if ($column->customheading) {
            // Use value from database.
            $heading = format_string($column->heading);
        } else {
            // Use default value.
            $defaultheadings = $this->get_default_headings_array();
            $heading = isset($defaultheadings[$column->type . '-' . $column->value]) ?
                $defaultheadings[$column->type . '-' . $column->value] : null;

            if ($column->grouping === 'none') {
                if ($column->transform) {
                    $heading = get_string("transformtype{$column->transform}_heading", 'totara_reportbuilder', $heading);
                } else if ($column->aggregate) {
                    $heading = get_string("aggregatetype{$column->aggregate}_heading", 'totara_reportbuilder', $heading);
                }
            }
        }

        if ($plaintext) {
            $heading = strip_tags($heading);
            $heading = core_text::entities_to_utf8($heading);
        }

        return $heading;
    }

    /**
     * Gets any columns set for the current report from the database
     *
     * @return array Array of columns for current report or empty array if none set
     */
    public function get_columns() {
        global $DB;

        $out = array();
        $id = isset($this->_id) ? $this->_id : null;
        if (empty($id)) {
            return $out;
        }

        $columns = $DB->get_records('report_builder_columns', array('reportid' => $id), 'sortorder ASC, id ASC');

        foreach ($columns as $column) {
            // Find the column option that matches this column.
            $key = $column->type . '-' . $column->value;
            if (!isset($this->columnoptions[$key])) {
                continue;
            }
            $columnoption = $this->columnoptions[$key];

            if (!empty($columnoption->columngenerator)) {
                /* Rather than putting the column into the list, we call the generator and it
                 * will supply an array of columns (0 or more) that should be included. We pass
                 * all available information to the generator (columnoption and hidden). */
                $columngenerator = 'rb_cols_generator_' . $columnoption->columngenerator;
                $results = $this->src->$columngenerator($columnoption, $column->hidden);
                foreach ($results as $result) {
                    $key = $result->type . '-' . $result->value;
                    if (isset($this->requiredcolumns[$key])) {
                        debugging("Generated column $key duplicates required column in source " . get_class($this->src),
                                    DEBUG_DEVELOPER);
                        continue;
                    }
                    if (isset($out[$key])) {
                        debugging("Generated column $key overrides column in source " . get_class($this->src), DEBUG_DEVELOPER);
                        continue;
                    }
                    $out[$key] = $result;
                    if ($out[$key]->grouping != 'none' or $out[$key]->aggregate) {
                        $this->grouped = true;
                    }
                }
            } else {
                if (isset($this->requiredcolumns[$key])) {
                    debugging("Column $key duplicates required column in source " . get_class($this->src), DEBUG_DEVELOPER);
                    continue;
                }

                try {
                    $out[$key] = $this->src->new_column_from_option(
                        $column->type,
                        $column->value,
                        $column->transform,
                        $column->aggregate,
                        $column->heading,
                        $column->customheading,
                        $column->hidden
                    );
                    // Enabled report grouping if any columns are grouped.
                    if ($out[$key]->grouping !== 'none' or $out[$key]->aggregate) {
                        $this->grouped = true;
                    }
                } catch (ReportBuilderException $e) {
                    debugging($e->getMessage(), DEBUG_NORMAL);
                }
            }
        }

        // Now append any required columns.
        foreach ($this->requiredcolumns as $column) {
            $key = $column->type . '-' . $column->value;
            $column->required = true;
            $out[$key] = $column;
            // Enabled report grouping if any columns are grouped.
            if ($column->grouping !== 'none' or $column->aggregate) {
                $this->grouped = true;
            }
        }

        return $out;
    }


    /**
     * Returns an associative array of the default headings for this report
     *
     * Looks up all the columnoptions (from this report's source)
     * For each one gets the default heading according the the following criteria:
     *  - if the report is embedded get the heading from the embedded source
     *  - if not embedded or the column's heading isn't specified in the embedded source,
     *    get the defaultheading from the columnoption
     *  - if that isn't specified, use the columnoption name
     *
     * @return array Associtive array of default headings for all the column options in this report
     *               Key is "{$type}-{$value]", value is the default heading string
     */
    function get_default_headings_array() {
        if (!isset($this->columnoptions) || !is_array($this->columnoptions)) {
            return false;
        }

        $out = array();
        foreach ($this->columnoptions as $option) {
            $key = $option->type . '-' . $option->value;

            if ($this->embedobj && $embeddedheading = $this->embedobj->get_embedded_heading($option->type, $option->value)) {
                // use heading from embedded source
                $defaultheading = $embeddedheading;
            } else {
                if (isset($option->defaultheading)) {
                    // use default heading
                    $defaultheading = $option->defaultheading;
                } else {
                    // fall back to columnoption name
                    $defaultheading = $option->name;
                }
            }

            $out[$key] = $defaultheading;
        }
        return $out;
    }

    /**
     * Given a report fullname, try to generate a sensible shortname that will be unique
     *
     * @param string $fullname The report's full name
     * @return string A unique shortname suitable for this report
     */
    public static function create_shortname($fullname) {
        global $DB;

        // leaves only letters and numbers
        // replaces spaces + dashes with underscores
        $validchars = strtolower(preg_replace(array('/[^a-zA-Z\d\s-_]/', '/[\s-]/'), array('', '_'), $fullname));
        $shortname = "report_{$validchars}";
        $try = $shortname;
        $i = 1;
        while($i < 1000) {
            if ($DB->get_field('report_builder', 'id', array('shortname' => $try))) {
                // name exists, try adding a number to make unique
                $try = $shortname . $i;
                $i++;
            } else {
                // return the shortname
                return $try;
            }
        }
        // if all 1000 name tries fail, give up and use a timestamp
        return "report_" . time();
    }


    /**
     * Return the URL to view the current report
     *
     * @return string URL of current report
     */
    function report_url() {
        global $CFG;
        if ($this->embeddedurl === null) {
            return $CFG->wwwroot . '/totara/reportbuilder/report.php?id=' . $this->_id;
        } else {
            return $CFG->wwwroot . $this->embeddedurl;
        }
    }


    /**
     * Get the current page url, minus any pagination or sort order elements
     * Good for submitting forms
     *
     * @return string Current URL, minus any spage and ssort parameters
     */
    function get_current_url() {
        // Array of parameters to remove from query string.
        $strip_params = array('spage', 'ssort', 'sid', 'clearfilters');

        $url = new moodle_url(qualified_me());
        foreach ($url->params() as $name =>$value) {
            if (in_array($name, $strip_params)) {
                $url->remove_params($name);
            }
        }
        return html_entity_decode($url->out());
    }


    /**
     * Returns an array of arrays containing information about any currently
     * set URL parameters. Used to determine which joins are required to
     * match against URL parameters
     *
     * @param bool $all Return all params including unused in current request
     *
     * @return array Array of set URL parameters and their values
     */
    function get_current_params($all = false) {
        global $SESSION;

        $clearfiltersparam = optional_param('clearfilters', 0, PARAM_INT);

        // This hack is necessary because the report instance may be constructed
        // on pages with colliding GET or POST page parameters.
        $ignorepageparams = false;
        if (defined('REPORT_BUILDER_IGNORE_PAGE_PARAMETERS')) {
            $ignorepageparams = REPORT_BUILDER_IGNORE_PAGE_PARAMETERS;
        }

        $out = array();
        if (empty($this->_paramoptions)) {
            return $out;
        }
        foreach ($this->_paramoptions as $param) {
            $name = $param->name;
            if ($ignorepageparams) {
                $var = null;
            } else if ($param->type == 'string') {
                $var = optional_param($name, null, PARAM_TEXT);
            } else {
                $var = optional_param($name, null, PARAM_INT);
            }
            if (isset($this->_embeddedparams[$name])) {
                // Embedded params take priority over url params.
                $res = new rb_param($name, $this->_paramoptions);
                $res->value = $this->_embeddedparams[$name];
                $out[] = $res;
            } else if ($all) {
                // When all parameters required, they are not restricted to particular value.
                if (!empty($param->field)) {
                    $out[] = new rb_param($name, $this->_paramoptions);
                }
            } else if (isset($var) || $clearfiltersparam) {
                if (isset($var)) {
                    // This url param exists, add to params to use.
                    $res = new rb_param($name, $this->_paramoptions);
                    $res->value = $var; // Save the value.
                    $out[] = $res;
                    $SESSION->reportbuilder[$this->_id][$name] = $var; // And save to session variable.
                } else {
                    unset($SESSION->reportbuilder[$this->_id][$name]);
                }
            } else if (isset($SESSION->reportbuilder[$this->_id][$name])) {
                // This param is stored in the session variable.
                $res = new rb_param($name, $this->_paramoptions);
                $res->value = $SESSION->reportbuilder[$this->_id][$name];
                $out[] = $res;
            }

        }
        return $out;
    }


    /**
     * Wrapper for displaying search form from filtering class
     *
     * @return Nothing returned but prints the search box
     */
    public function display_search() {
        global $CFG;

        require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');
        $mformstandard = new report_builder_standard_search_form($this->get_current_url(),
                array('fields' => $this->get_standard_filters()));
        $mformstandard->display();
    }


    /**
     * Wrapper for displaying search form from filtering class
     *
     * @return Nothing returned but prints the search box
     */
    public function display_sidebar_search() {
        global $CFG, $PAGE;

        require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');
        $mformsidebar = new report_builder_sidebar_search_form($this->get_current_url(),
                array('report' => $this, 'fields' => $this->get_sidebar_filters()), 'post', '', array('class' => 'rb-sidebar'));
        $mformsidebar->display();

        // If is_capable is not implemented on an embedded report then don't activate instant filters.
        // Instead, we force the user to use standard form submission (the same as when javascript is not available).
        if ($this->embedobj && !method_exists($this->embedobj, 'is_capable')) {
            return;
        }

        local_js();
        $jsmodule = array(
            'name' => 'totara_reportbuilder_instantfilter',
            'fullpath' => '/totara/reportbuilder/js/instantfilter.js',
            'requires' => array('json'));
        $PAGE->requires->js_init_call('M.totara_reportbuilder_instantfilter.init', array('id' => $this->_id), true, $jsmodule);
    }


    public function get_standard_filters() {
        $result = array();
        foreach ($this->filters as $key => $filter) {
            if ($filter->region == rb_filter_type::RB_FILTER_REGION_STANDARD) {
                $result[$key] = $filter;
            }
        }
        return $result;
    }

    public function get_sidebar_filters() {
        $result = array();
        foreach ($this->filters as $key => $filter) {
            if ($filter->region == rb_filter_type::RB_FILTER_REGION_SIDEBAR) {
                $result[$key] = $filter;
            }
        }
        return $result;
    }

    /** Returns true if the current user has permission to view this report
     *
     * @param integer $id ID of the report to be viewed
     * @param integer $userid ID of user to check permissions for
     * @return boolean True if they have any of the required capabilities
     */
    public static function is_capable($id, $userid=null) {
        global $USER;

        $foruser = isset($userid) ? $userid : $USER->id;
        $allowed = array_keys(reportbuilder::get_permitted_reports($foruser, true));
        $permitted = in_array($id, $allowed);
        return $permitted;
    }


    /**
    * Returns an array of defined reportbuilder access plugins
    *
    * @return array Array of access plugin names
    */
    public static function get_all_access_plugins() {
        $plugins = array();
        // loop round classes, only considering classes that extend rb_base_access
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, 'rb_base_access')) {
                // remove rb_ prefix
                $plugins[] = substr($class, 3);
            }
        }
        return $plugins;
    }

    /**
    * Returns an array of associative arrays keyed by reportid,
    * each associative array containing ONLY the plugins actually enabled on each report,
    * with a 0/1 value of whether the report passes each plugin checks for the specified user
    * For example a return array in the following form
    *
    * array[1] = array('role_access' => 1, 'individual_access' => 0)
    * array[4] = array('role_access' => 0, 'individual_access' => 0, 'hierarchy_access' => 0)
    *
    * would mean:
    * report id 1 has 'role_access' and 'individual_access' plugins enabled,
    * this user passed role_access checks but failed the individual_access checks;
    * report id 4 has 'role_access', 'individual_access and 'hierarchy_access' plugins enabled,
    * and the user failed access checks in all three.
    *
    * @param int $userid The user to check which reports they have access to
    * @param array $plugins array of particular plugins to check
    * @return array Array of reports, with enabled plugin names and access status
    */
    public static function get_reports_plugins_access($userid, $plugins=NULL) {
        global $DB;
        //create return variable
        $report_plugin_access = array();
        //if no list of plugins specified, check them all
        if (empty($plugins)) {
            $plugins = self::get_all_access_plugins();
        }
        //keep track of which plugins are actually active according to report_builder_settings
        $active_plugins = array();
        //now get the info for plugins that are actually enabled for any reports
        list($insql, $params) = $DB->get_in_or_equal($plugins);
        $sql = "SELECT id,reportid,type
                  FROM {report_builder_settings}
                 WHERE type $insql
                   AND name = ?
                   AND value = ?";
        $params[] = 'enable';
        $params[] = '1';
        $reportinfo = $DB->get_records_sql($sql, $params);

        foreach ($reportinfo as $id => $plugin) {
            //foreach scope variables for efficiency
            $rid = $plugin->reportid;
            $ptype = '' . $plugin->type;
            //add to array of plugins that are actually active
            if (!in_array($ptype, $active_plugins)) {
                $active_plugins[] = $ptype;
            }
            //set up enabled plugin info for this report
            if (isset($report_plugin_access[$rid])) {
                $report_plugin_access[$rid][$ptype] = 0;
            } else {
                $report_plugin_access[$rid] = array($ptype => 0);
            }
        }
        //now call the plugin class to get the accessible reports for each actually used plugin
        foreach ($active_plugins as $plugin) {
            $class = "rb_" . $plugin;
            $obj = new $class($userid);
            $accessible = $obj->get_accessible_reports();
            foreach ($accessible as $key => $rid) {
                if (isset($report_plugin_access[$rid]) && is_array($report_plugin_access[$rid])) {
                    //report $rid has passed checks in $plugin
                    //the plugin should already have an entry with value 0 from above
                    if (isset($report_plugin_access[$rid][$plugin])) {
                        $report_plugin_access[$rid][$plugin] = 1;
                    }
                }
            }
        }

        return $report_plugin_access;
    }

    /**
     * Returns an array of reportbuilder records that the user can view
     *
     * @param int $userid The user to check which reports they have access to
     * @param boolean $showhidden If true, reports which are hidden
     *                            will also be included
     * @return array Array of results from the report_builder table
     */
    public static function get_permitted_reports($userid=NULL, $showhidden=false) {
        global $DB, $USER;

        // check access for specified user, or the current user if none set
        $foruser = isset($userid) ? $userid : $USER->id;
        //array to hold the final list
        $permitted_reports = array();
        //get array of plugins
        $all_plugins = self::get_all_access_plugins();
        //get array of all reports with enabled plugins and whether they passed or failed each enabled plugin
        $enabled_plugins = self::get_reports_plugins_access($foruser, $all_plugins);
        //get basic reports list
        $hidden = (!$showhidden) ? ' WHERE hidden = 0 ' : '';
        $sql = "SELECT *
                  FROM {report_builder}
                 $hidden
                 ORDER BY fullname ASC";
        $reports = $DB->get_records_sql($sql);
        //we now have all the information we need
        if ($reports) {
            foreach ($reports as $report) {
                $report->url = reportbuilder_get_report_url($report);

                if ($report->accessmode == REPORT_BUILDER_ACCESS_MODE_NONE) {
                    $permitted_reports[$report->id] = $report;
                    continue;
                }
                if ($report->accessmode == REPORT_BUILDER_ACCESS_MODE_ANY) {
                    if (!empty($enabled_plugins) && isset($enabled_plugins[$report->id])) {
                        foreach ($enabled_plugins[$report->id] as $plugin => $value) {
                            if ($value == 1) {
                                //passed in some plugin so allow it
                                $permitted_reports[$report->id] = $report;
                                break;
                            }
                        }
                        continue;
                    } else {
                        // Bad data - set to "any plugin passing", but no plugins actually have settings to check for this report.
                        continue;
                    }
                }
                if ($report->accessmode == REPORT_BUILDER_ACCESS_MODE_ALL) {
                    if (!empty($enabled_plugins) && isset($enabled_plugins[$report->id])) {
                        $status=true;
                        foreach ($enabled_plugins[$report->id] as $plugin => $value) {
                            if ($value == 0) {
                                //failed in some expected plugin, reject
                                $status = false;
                                break;
                            }
                        }
                        if ($status) {
                            $permitted_reports[$report->id] = $report;
                            continue;
                        }
                    } else {
                        // bad data - set to "all plugins passing", but no plugins actually have settings to check for this report
                        continue;
                    }
                }
            }
        }
        return $permitted_reports;
    }


    /**
     * Get the value of the specified parameter, or null if not found
     *
     * @param string $name name of the parameter
     * @return mixed the value
     */
    public function get_param_value($name) {
        foreach ($this->_params as $param) {
            if ($param->name == $name) {
                return $param->value;
            }
        }
        return null;
    }


    /**
     * Returns an SQL snippet that, when applied to the WHERE clause of the query,
     * reduces the results to only include those matched by any specified URL parameters
     * @param bool $cache if enabled only field alias will be used
     *
     * @return array containing SQL snippet (created from URL parameters) and SQL params
     */
    function get_param_restrictions($cache = false) {
        $out = array();
        $sqlparams = array();
        $params = $this->_params;
        if (is_array($params)) {
            $count = 1;
            foreach ($params as $param) {
                $field = ($cache) ? $param->fieldalias : $param->field;
                $value = $param->value;
                $type = $param->type;
                // don't include if param not set to anything
                if (!isset($value) || strlen(trim($value)) == 0 || $param->field == '') {
                    continue;
                }

                $wherestr = $field;

                // if value starts with '!', do a not equals match
                // to the rest of the string
                $uniqueparam = rb_unique_param("pr{$count}_");
                if (substr($value, 0, 1) == '!') {
                    $wherestr .= " != :{$uniqueparam}";
                    // Strip off the leading '!'
                    $sqlparams[$uniqueparam] = substr($value, 1);
                } else {
                    // normal match
                    $wherestr .= " = :{$uniqueparam}";
                    $sqlparams[$uniqueparam] = $value;
                }

                $out[] = $wherestr;
                $count++;
            }
        }
        if (count($out) == 0) {
            return array('', array());
        }
        return array('(' . implode(' AND ', $out) . ')', $sqlparams);
    }


    /**
     * Returns an SQL snippet that, when applied to the WHERE clause of the query,
     * reduces the results to only include those matched by any specified content
     * restrictions
     * @param bool $cache if enabled, only alias fields will be used
     *
     * @return array containing SQL snippet created from content restrictions, as well as SQL params array
     */
    function get_content_restrictions($cache = false) {
        // if no content restrictions enabled return a TRUE snippet
        // use 1=1 instead of TRUE for MSSQL support
        if ($this->contentmode == REPORT_BUILDER_CONTENT_MODE_NONE) {
            return array("( 1=1 )", array());
        } else if ($this->contentmode == REPORT_BUILDER_CONTENT_MODE_ALL) {
            // require all to match
            $op = "\n    AND ";
        } else {
            // require any to match
            $op = "\n    OR ";
        }

        $reportid = $this->_id;
        $out = array();
        $params = array();

        // go through the content options
        if (isset($this->contentoptions) && is_array($this->contentoptions)) {
            foreach ($this->contentoptions as $option) {
                $name = $option->classname;
                $classname = 'rb_' . $name . '_content';
                $settingname = $name . '_content';

                $fields = array();
                foreach ($option->fields as $key => $field) {
                    if ($cache) {
                        $fields[$key] = 'rb_content_option_' . $key;
                    } else {
                        $fields[$key] = $field;
                    }
                }
                // Collapse array to string if it consists of only one element
                // with a specific key.
                // This provides backward compatibility in case fields is just
                // a string instead of an array.
                if (count($fields) == 1 && isset($fields['field'])) {
                    $fields = $fields['field'];
                }

                if (class_exists($classname)) {
                    $class = new $classname($this->reportfor);

                    if (reportbuilder::get_setting($reportid, $settingname, 'enable')) {
                        // this content option is enabled
                        // call function to get SQL snippet
                        list($out[], $contentparams) = $class->sql_restriction($fields, $reportid);
                        $params = array_merge($params, $contentparams);
                    }
                } else {
                    print_error('contentclassnotexist', 'totara_reportbuilder', '', $classname);
                }
            }
        }
        // show nothing if no content restrictions enabled
        if (count($out) == 0) {
            // use 1=0 instead of FALSE for MSSQL support
            return array('(1=0)', array());
        }

        return array('(' . implode($op, $out) . ')', $params);
    }

    /**
     * Returns human readable descriptions of any content or
     * filter restrictions that are limiting the number of results
     * shown. Used to let the user known what a report contains
     *
     * @param string $which Which restrictions to return, defaults to all
     *                      but can be 'filter' or 'content' to just return
     *                      restrictions of that type
     * @return array An array of strings containing descriptions
     *               of any restrictions applied to this report
     */
    function get_restriction_descriptions($which='all') {
        // include content restrictions
        $content_restrictions = array();
        $reportid = $this->_id;
        $res = array();
        if ($this->contentmode != REPORT_BUILDER_CONTENT_MODE_NONE) {
            foreach ($this->contentoptions as $option) {
                $name = $option->classname;
                $classname = 'rb_' . $name . '_content';
                $settingname = $name . '_content';
                $title = $option->title;
                if (class_exists($classname)) {
                    $class = new $classname($this->reportfor);
                    if (reportbuilder::get_setting($reportid, $settingname, 'enable')) {
                        // this content option is enabled
                        // call function to get text string
                        $res[] = $class->text_restriction($title, $reportid);
                    }
                } else {
                    print_error('contentclassnotexist', 'totara_reportbuilder', '', $classname);
                }
            }
            if ($this->contentmode == REPORT_BUILDER_CONTENT_MODE_ALL) {
                // 'and' show one per line
                $content_restrictions = $res;
            } else {
                // 'or' show as a single line
                $content_restrictions[] = implode(get_string('or', 'totara_reportbuilder'), $res);
            }
        }

        $filter_restrictions = $this->fetch_text_filters();

        switch($which) {
        case 'content':
            $restrictions = $content_restrictions;
            break;
        case 'filter':
            $restrictions = $filter_restrictions;
            break;
        default:
            $restrictions = array_merge($content_restrictions, $filter_restrictions);
        }
        return $restrictions;
    }




    /**
     * Returns an array of fields that must form part of the SQL query
     * in order to provide the data need to display the columns required
     *
     * Each element in the array is an SQL snippet with an alias built
     * from the $type and $value of that column
     *
     * @param int $mode How aliases for grouping columns should be prepared
     * @return array Array of SQL snippets for use by SELECT query
     *
     */
    function get_column_fields($mode = rb_column::REGULAR) {
        $fields = array();
        $src = $this->src;
        foreach ($this->columns as $column) {
            $fields = array_merge($fields, $column->get_fields($src, $mode, true));
        }
        return $fields;
    }


    /**
     * Returns the names of all the joins in the joinlist
     *
     * @return array Array of join names from the joinlist
     */
    function get_joinlist_names() {
        $joinlist = $this->_joinlist;
        $joinnames = array();
        foreach ($joinlist as $item) {
            $joinnames[] = $item->name;
        }
        return $joinnames;
    }


    /**
     * Return a join from the joinlist by name
     *
     * @param string $name Join name to get from the join list
     *
     * @return object {@link rb_join} object for the matching join, or false
     */
    function get_joinlist_item($name) {
        $joinlist = $this->_joinlist;
        foreach ($joinlist as $item) {
            if ($item->name == $name) {
                return $item;
            }
        }
        return false;
    }


    /**
     * Given an item, returns an array of {@link rb_join} objects needed by this item
     *
     * @param object $item An object containing a 'joins' property
     * @param string $usage The function is called to obtain joins for various
     *                     different elements of the query. The usage is displayed
     *                     in the error message to help with debugging
     * @return array An array of {@link rb_join} objects used to build the join part of the query
     */
    function get_joins($item, $usage) {
        $output = array();

        // extract the list of joins into an array format
        if (isset($item->joins) && is_array($item->joins)) {
            $joins = $item->joins;
        } else if (isset($item->joins)) {
            $joins = array($item->joins);
        } else {
            $joins = array();
        }

        foreach ($joins as $join) {
            if ($join == 'base') {
                continue;
            }
            $joinobj = $this->get_single_join($join, $usage);
            $output[] = $joinobj;

            $this->get_dependency_joins($output, $joinobj);

        }

        return $output;
    }

    /**
     * Given a join name, look for it in the joinlist and return the join object
     *
     * @param string $join A single join name (should match joinlist item name)
     * @param string $usage The function is called to obtain joins for various
     *                      different elements of the query. The usage is
     *                      displayed in the error message to help with debugging
     * @return string An rb_join object for the specified join, or error
     */
    function get_single_join($join, $usage) {

        if ($match = $this->get_joinlist_item($join)) {
            // return the join object for the item
            return $match;
        } else {
            print_error('joinnotinjoinlist', 'totara_reportbuilder', '', (object)array('join' => $join, 'usage' => $usage));
            return false;
        }
    }

    /**
     * Recursively build an array of {@link rb_join} objects that includes all
     * dependencies
     */
    function get_dependency_joins(&$joins, $joinobj) {

        // get array of dependencies, excluding references to the
        // base table
        if (isset($joinobj->dependencies)
            && is_array($joinobj->dependencies)) {

            $dependencies = array();
            foreach ($joinobj->dependencies as $item) {
                // ignore references to base as a dependency
                if ($item == 'base') {
                    continue;
                }
                $dependencies[] = $item;
            }
        } else if (isset($joinobj->dependencies)
                && $joinobj->dependencies != 'base') {

            $dependencies = array($joinobj->dependencies);
        } else {
            $dependencies = array();
        }

        // loop through dependencies, adding any that aren't already
        // included
        foreach ($dependencies as $dependency) {
            $joinobj = $this->get_single_join($dependency, 'dependencies');
            if (in_array($joinobj, $joins)) {
                // prevents infinite loop if dependencies include
                // circular references
                continue;
            }
            // add to list of current joins
            $joins[] = $joinobj;

            // recursively get dependencies of this dependency
            $this->get_dependency_joins($joins, $joinobj);
        }

    }


    /**
     * Return an array of {@link rb_join} objects containing the joins required by
     * the current enabled content restrictions
     *
     * @return array An array of {@link rb_join} objects containing join information
     */
    function get_content_joins() {
        $reportid = $this->_id;

        if ($this->contentmode == REPORT_BUILDER_CONTENT_MODE_NONE) {
            // no limit on content so no joins necessary
            return array();
        }
        $contentjoins = array();
        foreach ($this->contentoptions as $option) {
            $name = $option->classname;
            $classname = 'rb_' . $name . '_content';
            if (class_exists($classname)) {
                // @TODO take settings form instance, not database, otherwise caching will fail after content settings change
                if (reportbuilder::get_setting($reportid, $name . '_content', 'enable')) {
                    // this content option is enabled
                    // get required joins
                    $contentjoins = array_merge($contentjoins,
                        $this->get_joins($option, 'content'));
                }
            }
        }
        return $contentjoins;
    }

    /**
     * Return an array of strings containing the fields required by
     * the current enabled content restrictions
     *
     * @return array An array for strings conaining SQL snippets for field list
     */
    function get_content_fields() {
        $reportid = $this->_id;

        if ($this->contentmode == REPORT_BUILDER_CONTENT_MODE_NONE) {
            // no limit on content so no joins necessary
            return array();
        }

        $fields = array();
        if (isset($this->contentoptions) && is_array($this->contentoptions)) {
            foreach ($this->contentoptions as $option) {
                $name = $option->classname;
                $classname = 'rb_' . $name . '_content';
                $settingname = $name . '_content';
                if (class_exists($classname)) {
                    if (reportbuilder::get_setting($reportid, $settingname, 'enable')) {
                        foreach ($option->fields as $alias => $field) {
                            $fields[] = $field . ' AS rb_content_option_' . $alias;
                        }
                    }
                }
            }
        }
        return $fields;
    }


    /**
     * Return an array of {@link rb_join} objects containing the joins required by
     * the current column list
     *
     * @return array An array of {@link rb_join} objects containing join information
     */
    function get_column_joins() {
        $coljoins = array();
        foreach ($this->columns as $column) {
            $coljoins = array_merge($coljoins,
                $this->get_joins($column, 'column'));
        }
        return $coljoins;
    }

    /**
     * Return an array of {@link rb_join} objects containing the joins required by
     * the current param list
     *
     * @param bool $all Return all joins even for unused params
     *
     * @return array An array of {@link rb_join} objects containing join information
     */
    function get_param_joins($all = false) {
        $paramjoins = array();
        foreach ($this->_params as $param) {
            $value = $param->value;
            // don't include joins if param not set
            if (!$all && (!isset($value) || $value == '')) {
                continue;
            }
            $paramjoins = array_merge($paramjoins,
                $this->get_joins($param, 'param'));
        }
        return $paramjoins;
    }


    /**
     * Return an array of {@link rb_join} objects containing the joins required by
     * the source joins
     *
     * @return array An array of {@link rb_join} objects containing join information
     */
    function get_source_joins() {
        // no where clause - don't add any joins
        // as they won't be used
        if (empty($this->src->sourcewhere)) {
            return array();
        }

        // no joins specified
        if (empty($this->src->sourcejoins)) {
            return array();
        }

        $item = new stdClass();
        $item->joins = $this->src->sourcejoins;

        return $this->get_joins($item, 'source');

    }

    /**
     * Return an array of {@link rb_join} objects containing the joins of all enabled
     * filters regardless their usage in current request (useful for caching)
     *
     * @return array An array of {@link rb_join} objects containing join information
     */
    function get_all_filter_joins() {
        $filterjoins = array();
        foreach ($this->filters as $filter) {
            $value = $filter->value;
            // Don't include joins if param not set.
            if (!isset($value) || $value == '') {
                continue;
            }
            $filterjoins = array_merge($filterjoins,
                $this->get_joins($filter, 'filter'));
        }
        foreach ($this->searchcolumns as $searchcolumn) {
            $value = $searchcolumn->value;
            // Don't include joins if param not set.
            if (!isset($value) || $value == '') {
                continue;
            }
            $filterjoins = array_merge($filterjoins,
                $this->get_joins($searchcolumn, 'searchcolumn'));
        }
        return $filterjoins;
    }

    /**
     * Check the current session for active filters, and if found
     * collect together join data into a format suitable for {@link get_joins()}
     *
     * @return array An array of arrays containing filter join information
     */
    function get_filter_joins() {
        global $SESSION;
        $filterjoins = array();
        // Check session variable for any active filters.
        // If they exist we need to make sure we have included joins for them too.
        if (isset($SESSION->reportbuilder[$this->_id]) &&
            is_array($SESSION->reportbuilder[$this->_id])) {
            foreach ($SESSION->reportbuilder[$this->_id] as $fname => $unused) {
                if (!array_key_exists($fname, $this->filters)) {
                    continue; // filter not used in this report
                }
                $filter = $this->filters[$fname];

                $filterjoins = array_merge($filterjoins,
                    $this->get_joins($filter, 'filter'));
            }
        }
        // Check session variable for toolbar search text.
        // If it exists we need to make sure we have included joins for it too.
        if (isset($SESSION->reportbuilder[$this->_id]) &&
            isset($SESSION->reportbuilder[$this->_id]['toolbarsearchtext'])) {
            foreach ($this->searchcolumns as $searchcolumn) {
                $columnoption = $this->get_single_item($this->columnoptions, $searchcolumn->type, $searchcolumn->value);
                $filterjoins = array_merge($filterjoins,
                    $this->get_joins($columnoption, 'searchcolumn'));
            }
        }
        return $filterjoins;
    }


    /**
     * Given an array of {@link rb_join} objects, convert them into an SQL snippet
     *
     * @param array $joins Array of {@link rb_join} objects
     *
     * @return string SQL snippet that includes all the joins in the order provided
     */
    function get_join_sql($joins) {
        $out = array();

        foreach ($joins as $join) {
            $name = $join->name;
            $type = $join->type;
            $table = $join->table;
            $conditions = $join->conditions;

            if (array_key_exists($name, $out)) {
                // we've already added this join
                continue;
            }
            // store in associative array so we can tell which
            // joins we've already added
            $sql = "$type JOIN $table $name";
            if (!empty($conditions)) {
                $sql .= "\n        ON $conditions";
            }
            $out[$name] = $sql;
        }
        return implode("\n    ", $out) . " \n";
    }


    /**
     * Sort an array of {@link rb_join} objects
     *
     * Given an array of {@link rb_join} objects, sorts them such that:
     * - any duplicate joins are removed
     * - any joins with dependencies appear after those dependencies
     *
     * This is achieved by repeatedly looping through the list of
     * joins, moving joins to the sorted list only when all their
     * dependencies are already in the sorted list.
     *
     * On the first pass any joins that have no dependencies are
     * saved to the sorted list and removed from the current list.
     *
     * References to the moved items are then removed from the
     * dependencies lists of all the remaining items and the loop
     * is repeated.
     *
     * The loop continues until there is an iteration where no
     * more items are removed. At this point either:
     * - The current list is empty
     * - There are references to joins that don't exist
     * - There are circular references
     *
     * In the later two cases we throw an error, otherwise return
     * the sorted list.
     *
     * @param array Array of {@link rb_join} objects to be sorted
     *
     * @return array Sorted array of {@link rb_join} objects
     */
    function sort_joins($unsortedjoins) {

        // get structured list of dependencies for each join
        $items = $this->get_dependencies_array($unsortedjoins);

        // make an index of the join objects with name as key
        $joinsbyname = array();
        foreach ($unsortedjoins as $join) {
            $joinsbyname[$join->name] = $join;
        }

        // loop through items, storing any that don't have
        // dependencies in the output list

        // safety net to avoid infinite loop if something
        // unexpected happens
        $maxdepth = 50;
        $i = 0;
        $output = array();
        while($i < $maxdepth) {

            // items with empty dependencies array
            $nodeps = $this->get_independent_items($items);

            foreach ($nodeps as $nodep) {
                $output[] = $joinsbyname[$nodep];
                unset($items[$nodep]);
                // remove references to this item from all
                // the other dependency lists
                $this->remove_from_dep_list($items, $nodep);
            }

            // stop when no more items can be removed
            // if all goes well, this will be after all items
            // have been removed
            if (count($nodeps) == 0) {
                break;
            }

            $i++;
        }

        // we shouldn't have any items left once we've left the loop
        if (count($items) != 0) {
            print_error('couldnotsortjoinlist', 'totara_reportbuilder');
        }

        return $output;
    }


    /**
     * Remove joins that have no impact on the results count
     *
     * Given an array of {@link rb_join} objects we want to return a similar list,
     * but with any joins that have no effect on the count removed. This is
     * done for performance reasons when calculating the count.
     *
     * The only joins that can be safely removed match the following criteria:
     * 1- Only LEFT joins are safe to remove
     * 2- Even LEFT joins are unsafe, unless the relationship is either
     *   One-to-one or many-to-one
     * 3- The join can't have any dependencies that don't also match the
     *   criteria above: e.g.:
     *
     *   base LEFT JOIN table_a JOIN table_b
     *
     *   Table_b can't be removed because it fails criteria 1. Table_a
     *   can't be removed, even though it passes criteria 1 and 2, because
     *   table_b is dependent on it.
     *
     * To achieve this result, we use a similar strategy to sort_joins().
     * As a side effect, duplicate joins are removed but note that this
     * method doesn't change the sort order of the joins provided.
     *
     * @param array $unprunedjoins Array of rb_join objects to be pruned
     *
     * @return array Array of {@link rb_join} objects, minus any joins
     *               that don't affect the total record count
     */
    function prune_joins($unprunedjoins) {
        // get structured list of dependencies for each join
        $items = $this->get_dependencies_array($unprunedjoins);

        // make an index of the join objects with name as key
        $joinsbyname = array();
        foreach ($unprunedjoins as $join) {
            $joinsbyname[$join->name] = $join;
        }

        // safety net to avoid infinite loop if something
        // unexpected happens
        $maxdepth = 100;
        $i = 0;
        $output = array();
        while($i < $maxdepth) {
            $prunecount = 0;
            // items with empty dependencies array
            $nodeps = $this->get_nondependent_items($items);
            foreach ($nodeps as $nodep) {
                if ($joinsbyname[$nodep]->pruneable()) {
                    unset($items[$nodep]);
                    $this->remove_from_dep_list($items, $nodep);
                    unset($joinsbyname[$nodep]);
                    $prunecount++;
                }
            }

            // stop when no more items can be removed
            if ($prunecount == 0) {
                break;
            }

            $i++;
        }

        return array_values($joinsbyname);
    }


    /**
     * Reformats an array of {@link rb_join} objects to a structure helpful for managing dependencies
     *
     * Saves the dependency info in the following format:
     *
     * array(
     *    'name1' => array('dep1', 'dep2'),
     *    'name2' => array('dep3'),
     *    'name3' => array(),
     *    'name4' => array(),
     * );
     *
     * This has the effect of:
     * - Removing any duplicate joins (joins with the same name)
     * - Removing any references to 'base' in the dependencies list
     * - Converting null dependencies to array()
     * - Converting string dependencies to array('string')
     *
     * @param array $joins Array of {@link rb_join} objects
     *
     * @return array Array of join dependencies
     */
    private function get_dependencies_array($joins) {
        $items = array();
        foreach ($joins as $join) {

            // group joins in a more consistent way and remove all
            // references to 'base'
            if (is_array($join->dependencies)) {
                $deps = array();
                foreach ($join->dependencies as $dep) {
                    if ($dep == 'base') {
                        continue;
                    }
                    $deps[] = $dep;
                }
                $items[$join->name] = $deps;
            } else if (isset($join->dependencies)
                && $join->dependencies != 'base') {
                $items[$join->name] = array($join->dependencies);
            } else {
                $items[$join->name] = array();
            }
        }
        return $items;
    }


    /**
     * Remove references to a particular join from the
     * join dependencies list
     *
     * Given a list of join dependencies (as generated by
     * get_dependencies_array() ) remove all references to
     * the join named $joinname
     *
     * @param array &$items Array of dependencies. Passed by ref
     * @param string $joinname Name of join to remove from list
     *
     * @return true;
     */
    private function remove_from_dep_list(&$items, $joinname) {
        foreach ($items as $join => $deps) {
            foreach ($deps as $key => $dep) {
                if ($dep == $joinname) {
                    unset($items[$join][$key]);
                }
            }
        }
        return true;
    }


    /**
     * Return a list of items with no dependencies (e.g. the 'tips' of the tree)
     *
     * Given a list of join dependencies (as generated by
     * get_dependencies_array() ) return the names (keys)
     * of elements with no dependencies.
     *
     * @param array $items Array of dependencies
     *
     * @return array Array of names of independent items
     */
    private function get_independent_items($items) {
        $nodeps = array();
        foreach ($items as $join => $deps) {
            if (count($deps) == 0) {
                $nodeps[] = $join;
            }
        }
        return $nodeps;
    }


    /**
     * Return a list of items which no other items depend on (e.g the 'base' of
     * the tree)
     *
     * Given a list of join dependencies (as generated by
     * get_dependencies_array() ) return the names (keys)
     * of elements which are not dependent on any other items
     *
     * @param array $items Array of dependencies
     *
     * @return array Array of names of non-dependent items
     */
    private function get_nondependent_items($items) {
        $alldeps = array();
        // get all the dependencies in one array
        foreach ($items as $join => $deps) {
            foreach ($deps as $dep) {
                $alldeps[] = $dep;
            }
        }
        $nondeps = array();
        foreach (array_keys($items) as $join) {
            if (!in_array($join, $alldeps)) {
                $nondeps[] = $join;
            }
        }
        return $nondeps;
    }


    /**
     * Returns the ORDER BY SQL snippet for the current report
     *
     * @param object $table Flexible table object to use to find the sort parameters (optional)
     *                      If not provided a new object will be created based on the report's
     *                      shortname
     *
     * @return string SQL string to order the report to be appended to the main query
     */
    public function get_report_sort($table = null) {
        global $SESSION;

        // check the sort session var doesn't contain old columns that no
        // longer exist
        $this->check_sort_keys();

        // unless the table object is provided we need to call get_sql_sort() statically
        // and pass in the report's unique id (shortname)
        if (!isset($table)) {
            $shortname = $this->shortname;
            $sort = trim(flexible_table::get_sort_for_table($shortname));
        } else {
            $sort = trim($table->get_sql_sort());
        }

        // always include the base id as a last resort to ensure order is
        // predetermined for pagination
        $baseid = $this->grouped ? 'min(base.id)' : 'base.id';
        $order = ($sort != '') ? " ORDER BY $sort, $baseid" : " ORDER BY $baseid";

        return $order;
    }

    /**
     * Is report caching enabled and cache is ready and not cache is not ignored
     *
     * @return bool
     */
    public function is_cached() {
        if ($this->cacheignore or !$this->cache) {
            return false;
        }

        if ($this->get_cache_table()) {
            return true;
        }
    }

    /**
     * Returns cache status.
     * @return int constants RB_CACHE_FLAG_*
     */
    public function get_cache_status() {
        global $DB;

        if (!$this->cache) {
            return RB_CACHE_FLAG_NONE;
        }

        if (!$this->cacheschedule) {
            return RB_CACHE_FLAG_CHANGED;
        }

        if ($this->cacheschedule->genstart) {
            return RB_CACHE_FLAG_GEN;
        }

        if ($this->cacheschedule->changed) {
            return RB_CACHE_FLAG_CHANGED;
        }

        if (!$this->cacheschedule->cachetable) {
            return RB_CACHE_FLAG_FAIL;
        }

        if ($this->cachetable) {
            // Shortcut.
            return RB_CACHE_FLAG_OK;
        }

        list($query, $params) = $this->build_create_cache_query();
        if (sha1($query.serialize($params)) === $this->cacheschedule->queryhash) {
            $this->cachetable = $this->cacheschedule->cachetable;
            return RB_CACHE_FLAG_OK;
        }

        $this->cachetable = false;
        $DB->set_field('report_builder_cache', 'changed', RB_CACHE_FLAG_CHANGED, array('id' => $this->cacheschedule->id));
        $this->cacheschedule->changed = RB_CACHE_FLAG_CHANGED;
    }

    public function get_cache_table() {
        $status = $this->get_cache_status();
        if ($status !== RB_CACHE_FLAG_OK) {
            return false;
        }

        return $this->cachetable;
    }

    /**
     * This function builds the main SQL query used to generate cache for report
     *
     * @return array containing the full SQL query and SQL params
     */
    function build_create_cache_query() {
        // Save report instance state
        $paramssave = $this->_params;
        $groupedsave = $this->grouped;
        // Prepare instance to generate cache:
        // - Disable grouping
        // - Enable all params (not only used in request)
        $this->cacheignore = true;
        $this->_params = $this->get_current_params(true);
        $this->grouped = false;
        // get the fields required by display, any filter, param, or content option used in report
        $fields = array_merge($this->get_column_fields(rb_column::NOGROUP),
                              $this->get_content_fields(),
                              $this->get_alias_fields($this->filters),
                              $this->get_alias_fields($this->_params));
        // Include all search columns (but not their extrafields).
        foreach ($this->searchcolumns as $searchcolumn) {
            $searchcolumnoption = $this->get_single_item($this->columnoptions, $searchcolumn->type, $searchcolumn->value);
            $fields[] = $searchcolumnoption->field . " AS " . $searchcolumnoption->type . "_" . $searchcolumnoption->value;
        }
        $fields = array_unique($fields);
        $joins = $this->collect_joins(reportbuilder::FILTERALL);

        $where = array();
        if (!empty($this->src->sourcewhere)) {
            $where[] = $this->src->sourcewhere;
        }
        $sql = $this->collect_sql($fields, $this->src->base, $joins, $where);

        // Revert report instance state
        $this->_params = $paramssave;
        $this->cacheignore = false;
        $this->grouped = $groupedsave;
        return array($sql, array());
    }

    /**
     * This function builds main cached SQL query to get the data for page
     *
     * @return array array($sql, $params, $cache). If no cache found array('', array(), array()) will be returned
     */
    public function build_cache_query($countonly = false, $filtered = false) {
        if (!$this->is_cached()) {
            return array('', array(), array());
        }
        $table = $this->get_cache_table();
        $fields = $this->get_column_fields(rb_column::CACHE);

        list($where, $group, $having, $sqlparams, $allgrouped) = $this->collect_restrictions($filtered, true);

        $sql = $this->collect_sql($fields, $table, array(), $where, $group, $having,
                                  $countonly, $allgrouped);

        return array($sql, $sqlparams, (array)$this->cacheschedule);
    }

    /**
     * This function builds the main SQL query used to get the data for the page
     *
     * @param boolean $countonly If true returns SQL to count results, otherwise the
     *                           query requests the fields needed for columns too.
     * @param boolean $filtered If true, includes any active filters in the query,
     *                           otherwise returns results without filtering
     * @param boolean $allowcache If true tries to use cache for query
     * @return array containing the full SQL query, SQL params, and cache meta information
     */
    function build_query($countonly = false, $filtered = false, $allowcache = true) {
        global $CFG;

        if ($allowcache && $CFG->enablereportcaching) {
            $cached = $this->build_cache_query($countonly, $filtered);
            if ($cached[0] != '') {
                return $cached;
            }
        }
        $mode = rb_column::REGULAR;
        if ($this->grouped) {
            $mode = rb_column::REGULARGROUPED;
        }
        $fields = $this->get_column_fields($mode);

        $filter = ($filtered) ? reportbuilder::FILTER : reportbuilder::FILTERNONE;
        $joins = $this->collect_joins($filter, $countonly);

        list($where, $group, $having, $sqlparams, $allgrouped) = $this->collect_restrictions($filtered);

        // apply any SQL specified by the source
        if (!empty($this->src->sourcewhere)) {
            $where[] = $this->src->sourcewhere;
        }
        $sql = $this->collect_sql($fields, $this->src->base, $joins, $where, $group, $having, $countonly, $allgrouped);
        return array($sql, $sqlparams, array());
    }

    /**
     * Add counts indicating how many records match each option in the sidebar.
     * Only filter types which define get_showcount_params will show anything.
     *
     * @param type $mform form to add the counts onto, which already has filters added
     */
    public function add_filter_counts($mform) {
        global $DB;

        // The counts do not make much sense if we aggregate rows,
        // better not show it at all and it also allows us to keep this code as-is,
        // this prevents performance problems too.
        $showcountfilters = array();
        foreach ($this->columns as $column) {
            if ($column->aggregate) {
                return;
            }
        }

        $iscached = $this->is_cached();
        $isgrouped = $this->grouped;
        $filters = $this->get_sidebar_filters();
        $fields = array();
        $extrajoins = array();

        // Find all the showcount filters.
        foreach ($filters as $filter) {
            $showcountparams = $filter->get_showcount_params();
            if ($showcountparams !== false) {
                $showcountfilters[] = $filter;

                if ($iscached) {
                    // Get these extra fields from the base query.
                    $fields[] = $filter->fieldalias;
                } else {
                    // Get any required fields from the base query.
                    if (isset($showcountparams['basefields'])) {
                        $fields = array_merge($fields, $showcountparams['basefields']);
                    }
                    if ($isgrouped) {
                        $fields[] = "{$filter->field} AS {$filter->fieldalias}";
                    }

                    // Compile a list of extra joins (which will supply the fields above) that should be added to the base query.
                    if (isset($showcountparams['dependency']) && $showcountparams['dependency'] != 'base') {
                        $dependency = $this->get_single_join($showcountparams['dependency'], 'filtercount');
                        $this->get_dependency_joins($extrajoins, $dependency);
                        if ($isgrouped) {
                            $extrajoins[] = $dependency;
                        }
                    }
                    if ($isgrouped and $filter->joins != 'base') {
                        $extrajoins[] = $this->get_single_join($filter->joins, 'filtercount');
                    }
                }

                // Temporarily deactivate the filter so that it is not included in the base sql query.
                $filter->save_temp_data(null);
            }
        }

        // If the base query uses grouping then we need to include all column fields (so that each field can be grouped).
        if ($isgrouped && !$iscached) {
            $fields = array_unique(array_merge($fields, $this->get_column_fields(rb_column::REGULARGROUPED)));
        }

        // If there are none then return, because we do not want to generate an empty query.
        if (empty($showcountfilters)) {
            return;
        }

        // Get all joins for required child tables and active filters.
        if (!$iscached) {
            // Grouped reports will include all joins in the base query.
            $basejoins = $this->collect_joins(self::FILTER, !$isgrouped);
            $joins = array_merge($basejoins, $extrajoins);
        } else {
            $joins = array();
        }

        // Get all conditions for active filters (except the ones we deactivated).
        list($where, $group, $having, $sqlparams, $allgrouped) = $this->collect_restrictions(true, $iscached);

        // Apply any SQL specified by the source.
        if (!$iscached && !empty($this->src->sourcewhere)) {
            $where[] = $this->src->sourcewhere;
        }

        // Get the base sql query with all other joins and (active) filters applied.
        if ($iscached) {
            $base = $this->get_cache_table();
        } else {
            $base = $this->src->base;
        }
        $basesql = $this->collect_sql($fields, $base, $joins, $where, $group, $having, false, $allgrouped);

        // Restore all saved filters before we start constructing the main query (must restore ALL filters before the next loop).
        foreach ($showcountfilters as $filter) {
            $filter->restore_temp_data();
        }

        $countscolumns = array();
        $filtersplustotalscolumns = array("filters.*");
        $filterscolumns = array("base.id");
        $showcountjoins = array();

        // Get sql snipets and params for each showcount filter.
        foreach ($showcountfilters as $filter) {
            list($addcountscolumns, $addfiltersplustotalscolumn, $addfilterscolumns, $addshowcountjoins, $addsqlparams) =
                    $filter->get_counts_sql($showcountfilters);
            $countscolumns = array_merge($countscolumns, $addcountscolumns);
            $filtersplustotalscolumns[] = $addfiltersplustotalscolumn;
            $filterscolumns = array_merge($filterscolumns, $addfilterscolumns);
            $showcountjoins = array_merge($showcountjoins, $addshowcountjoins);
            $sqlparams = array_merge($sqlparams, $addsqlparams);
        }

        // Remove duplicate joins.
        $uniqueshowcountjoins = array_unique($showcountjoins);

        // Only run the count sql if there is something to count.
        if (!empty($countscolumns)) {
            // Construct the main query.
            $sql = "SELECT\n" . implode(",\n", $countscolumns) . "\nFROM\n(\n" .
                   "   SELECT " . implode(",\n", $filtersplustotalscolumns) . "\n   FROM\n   (\n" .
                   "      SELECT " . implode(",\n", $filterscolumns) . "\n      FROM (\n\n" . $basesql . "\n      ) base\n" .
                             implode("\n", $uniqueshowcountjoins) . "\n      GROUP BY base.id\n" .
                   "   ) filters\n" . ") filtersplustotals";
            $counts = $DB->get_record_sql($sql, $sqlparams);

            // Put the counts into the form.
            foreach ($showcountfilters as $filter) {
                $filter->set_counts($mform, $counts);
            }
        }
    }

    /**
     * Return SQL snippet for field name depending on report cache settings.
     *
     * This is intended to be used during post_config.
     */
    public function get_field($type, $value, $field) {
        if ($this->is_cached()) {
            return $type . '_' . $value;
        }
        return $field;
    }

    /**
     * Get joins used for query building
     *
     * @param int $filtered reportbuilder::FILTERNONE - for no filter joins,
     *             reportbuilder::FILTER - for enabled filters, reportbuilder::FILTERALL - for all filters
     * @param bool $countonly If true prune joins that don't influent on resulting count
     * @return array of {@link rb_join} objects
     */
    protected function collect_joins($filtered, $countonly = false) {
        // get the joins needed to display requested columns and do filtering and restrictions
        $columnjoins = $this->get_column_joins();

        // if we are only counting, don't need all the column joins. Remove
        // any that don't affect the count
        if ($countonly && !$this->grouped) {
            $columnjoins = $this->prune_joins($columnjoins);
        }
        if ($filtered == reportbuilder::FILTERALL) {
            $filterjoins = $this->get_all_filter_joins();
        } else if ($filtered == reportbuilder::FILTER) {
            $filterjoins = $this->get_filter_joins();
        } else {
            $filterjoins = array();
        }
        $paramjoins = $this->get_param_joins(true);
        $contentjoins = $this->get_content_joins();
        $sourcejoins = $this->get_source_joins();
        $joins = array_merge($columnjoins, $filterjoins, $paramjoins, $contentjoins, $sourcejoins);

        // sort the joins to remove duplicates and resolve any dependencies
        $joins = $this->sort_joins($joins);
        return $joins;
    }

    /**
     * Get all restrictions to filter query
     *
     * @param bool $cache
     * @return array of arrays of strings array(where, group, having, bool allgrouped)
     */
    protected function collect_restrictions($filtered, $cache = false) {
        global $DB;
        $where = array();
        $group = array();
        $having = array();
        $sqlparams = array();
        list($restrictions, $contentparams) = $this->get_content_restrictions($cache);
        if ($restrictions != '') {
            $where[] = $restrictions;
            $sqlparams = array_merge($sqlparams, $contentparams);
        }
        unset($contentparams);

        if ($filtered === true) {
            list($sqls, $filterparams) = $this->fetch_sql_filters();
            if (isset($sqls['where']) && $sqls['where'] != '') {
                $where[] = $sqls['where'];
            }
            if (isset($sqls['having']) && $sqls['having'] != '') {
                $having[] = $sqls['having'];
            }
            $sqlparams = array_merge($sqlparams, $filterparams);
            unset($filterparams);
        }

        list($paramrestrictions, $paramparams) = $this->get_param_restrictions($cache);
        if ($paramrestrictions != '') {
            $where[] = $paramrestrictions;
            $sqlparams = array_merge($sqlparams, $paramparams);
        }
        unset($paramparams);

        list($postconfigrestrictions, $postconfigparams) = $this->get_post_config_restrictions();
        if ($postconfigrestrictions != '') {
            $where[] = $postconfigrestrictions;
            $sqlparams = array_merge($sqlparams, $postconfigparams);
        }
        unset($postconfigparams);

        $allgrouped = true;

        if ($this->grouped) {
            $group = array();
            $groupbymode = ($cache ? rb_column::GROUPBYCACHE : rb_column::GROUPBYREGULAR);

            foreach ($this->columns as $column) {
                if ($column->grouping !== 'none') {
                    // We still need to add extrafields to the GROUP BY if there is a displayfunc.
                    if ($column->extrafields && $column->get_displayfunc()) {
                        $fields = $column->get_extra_fields($groupbymode);
                        foreach ($fields as $field) {
                            if (!in_array($field, $group)) {
                                $group[] = $field;
                                $allgrouped = false;
                            }
                        }
                    }

                } else if ($column->transform) {
                    $allgrouped = false;
                    $group = array_merge($group, $column->get_fields($this->src, $groupbymode, true));

                } else if ($column->aggregate) {
                    // No need to add GROUP BY for extra fields
                    // because the display functions in aggregations do not need extra columns.

                } else { // Column grouping is 'none'.
                    $allgrouped = false;
                    $group = array_merge($group, $column->get_fields($this->src, $groupbymode, true));
                }
            }
        }

        return array($where, $group, $having, $sqlparams, $allgrouped);
    }

    /**
     * Compile SQL query from prepared parts
     *
     * @param array $fields
     * @param string $base
     * @param array $joins
     * @param array $where
     * @param array $group
     * @param array $having
     * @param bool $countonly
     * @param bool $allgrouped
     * @return string
     */
    protected function collect_sql(array $fields, $base, array $joins, array $where = null,
                                    array $group = null, array $having = null, $countonly = false,
                                    $allgrouped = false) {

        if ($countonly && !$this->grouped) {
            $selectsql = "SELECT COUNT(*) ";
        } else {
            $baseid = ($this->grouped) ? "min(base.id) AS id" : "base.id";
            array_unshift($fields, $baseid);
            $selectsql = "SELECT " . implode($fields, ",\n     ") . " \n";

        }
        $joinssql = (count($joins) > 0) ? $this->get_join_sql($joins) : '';

        $fromsql = "FROM {$base} base\n    " . $joinssql;

        $wheresql = (count($where) > 0) ? "WHERE " . implode("\n    AND ", $where) . "\n" : '';

        $groupsql = '';
        if (count($group) > 0 && !$allgrouped) {
            $groupsql = ' GROUP BY ' . implode(', ', $group) . ' ';
        }

        $havingsql = '';
        if (count($having) > 0) {
            $havingsql = ' HAVING ' . implode(' AND ', $having) . "\n";
        }

        if ($countonly && $this->grouped) {
            $sql = "SELECT COUNT(*) FROM ($selectsql $fromsql $wheresql $groupsql $havingsql) AS query";
        } else {
            $sql = "$selectsql $fromsql $wheresql $groupsql $havingsql";
        }
        return $sql;
    }

    /**
     * Return the total number of records in this report (after any
     * restrictions have been applied but before any filters)
     *
     * @return integer Record count
     */
    function get_full_count() {
        global $DB, $CFG;

        // Don't do the calculation if the results are initially hidden.
        if ($this->is_initially_hidden()) {
            return 0;
        }

        // Use cached value if present.
        if (empty($this->_fullcount)) {
            list($sql, $params) = $this->build_query(true);
            try {
                $this->_fullcount = $DB->count_records_sql($sql, $params);
            } catch (dml_read_exception $e) {
                $debuginfo = $CFG->debugdeveloper ? $e->debuginfo : '';
                print_error('error:problemobtainingreportdata', 'totara_reportbuilder', '', $debuginfo);
            }
        }
        return $this->_fullcount;
    }

    /**
     * Return the number of filtered records in this report
     *
     * @param bool $nocache Ignore cache
     * @return integer Filtered record count
     */
    public function get_filtered_count($nocache = false) {
        global $DB, $CFG;

        // Don't do the calculation if the results are initially hidden.
        if ($this->is_initially_hidden()) {
            return 0;
        }

        // Use cached value if present.
        if (empty($this->_filteredcount) || $nocache) {
            list($sql, $params) = $this->build_query(true, true);
            try {
                $this->_filteredcount = $DB->count_records_sql($sql, $params);
            } catch (dml_read_exception $e) {
                $debuginfo = $CFG->debugdeveloper ? $e->debuginfo : '';
                print_error('error:problemobtainingcachedreportdata', 'totara_reportbuilder', '', $debuginfo);
            }
        }
        return $this->_filteredcount;
    }

    /**
     * Exports the data from the current results, maintaining
     * sort order and active filters but removing pagination
     *
     * @param string $format Format for the export ods/csv/xls
     * @return No return but initiates save dialog
     */
    function export_data($format) {
        $columns = $this->columns;
        $count = $this->get_filtered_count();
        list($sql, $params, $cache) = $this->build_query(false, true);
        $order = $this->get_report_sort();


        // array of filters that have been applied
        // for including in report where possible
        $restrictions = $this->get_restriction_descriptions();

        $headings = array();
        foreach ($columns as $column) {
            // check that column should be included
            if ($column->display_column(true)) {
                $headings[] = $column;
            }
        }

        // Log export event.
        \totara_reportbuilder\event\report_exported::create_from_report($this, $format)->trigger();

        switch($format) {
            case REPORT_BUILDER_EXPORT_ODS:
                $this->download_ods($headings, $sql . $order, $params, $count, $restrictions, null, $cache);
            case REPORT_BUILDER_EXPORT_EXCEL:
                $this->download_xls($headings, $sql . $order, $params, $count, $restrictions, null, $cache);
            case REPORT_BUILDER_EXPORT_CSV:
                $this->download_csv($headings, $sql . $order, $params, $count);
            case REPORT_BUILDER_EXPORT_FUSION:
                $this->download_fusion();
            case REPORT_BUILDER_EXPORT_PDF_PORTRAIT:
                $this->download_pdf($headings, $sql . $order, $params, $count, $restrictions, true, null, $cache);
            case REPORT_BUILDER_EXPORT_PDF_LANDSCAPE:
                $this->download_pdf($headings, $sql . $order, $params, $count, $restrictions, false, null, $cache);
        }
        die;
    }

    /**
     * Display the results table
     *
     * @return void No return value but prints the current data table
     */
    function display_table() {
        global $SESSION, $DB, $OUTPUT, $PAGE, $CFG;

        $initiallyhidden = $this->is_initially_hidden();

        define('DEFAULT_PAGE_SIZE', $this->recordsperpage);
        define('SHOW_ALL_PAGE_SIZE', 9999);
        $perpage   = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT);

        $columns = $this->columns;
        $shortname = $this->shortname;
        $countfiltered = $this->get_filtered_count();

        if (count($columns) == 0) {
            echo html_writer::tag('p', get_string('error:nocolumnsdefined', 'totara_reportbuilder'));
            return;
        }

        $graphrecord = $DB->get_record('report_builder_graph', array('reportid' => $this->_id));
        if (!empty($graphrecord->type)) {
            $graph = new \totara_reportbuilder\local\graph($graphrecord, $this, false);
        } else {
            $graph = null;
        }

        list($sql, $params, $cache) = $this->build_query(false, true);

        $tablecolumns = array();
        $tableheaders = array();
        foreach ($columns as $column) {
            $type = $column->type;
            $value = $column->value;
            if ($column->display_column(false)) {
                $tablecolumns[] = "{$type}_{$value}"; // used for sorting
                $tableheaders[] = $this->format_column_heading($column, false);
            }
        }

        // Arrgh, the crazy table outputs each row immediately...
        ob_start();

        // Prevent notifications boxes inside the table.
        echo $OUTPUT->container_start('nobox rb-display-table-container no-overflow', $this->_id);

        // Output cache information if needed.
        if ($cache) {
            $usertz = totara_get_clean_timezone();
            $lastreport = userdate($cache['lastreport'], '', $usertz);
            $nextreport = userdate($cache['nextreport'], '', $usertz);

            $html = html_writer::start_tag('div', array('class' => 'noticebox'));
            $html .= get_string('report:cachelast', 'totara_reportbuilder', $lastreport);
            $html .= html_writer::empty_tag('br');
            $html .= get_string('report:cachenext', 'totara_reportbuilder', $nextreport);
            $html .= html_writer::end_tag('div');
            echo $html;
        }

        // Start the table.
        $table = new totara_table($shortname);
        if ($this->toolbarsearch && $this->has_toolbar_filter()) {
            $toolbarsearchtext = isset($SESSION->reportbuilder[$this->_id]['toolbarsearchtext']) ?
                    $SESSION->reportbuilder[$this->_id]['toolbarsearchtext'] : '';
            $mform = new report_builder_toolbar_search_form($this->report_url(),
                    array('toolbarsearchtext' => $toolbarsearchtext), 'post', '', null, true, 'toolbarsearch');
            $table->add_toolbar_content($mform->render());

            if ($this->embedded && $content = $this->embedobj->get_extrabuttons()) {
                $table->add_toolbar_content($content, 'right');
            }
        }

        $table->define_columns($tablecolumns);
        $table->define_headers($tableheaders);
        $table->define_baseurl($this->get_current_url());
        foreach ($columns as $column) {
            if ($column->display_column()) {
                $ident = "{$column->type}_{$column->value}";
                // Assign $type_$value class to each column.
                $classes = $ident;
                // Apply any column-specific class.
                if (is_array($column->class)) {
                    foreach ($column->class as $class) {
                        $classes .= ' ' . $class;
                    }
                }
                $table->column_class($ident, $classes);
                // Apply any column-specific styling.
                if (is_array($column->style)) {
                    foreach ($column->style as $property => $value) {
                        $table->column_style($ident, $property, $value);
                    }
                }
                // Hide any columns where hidden flag is set.
                if ($column->hidden != 0) {
                    $table->column_style($ident, 'display', 'none');
                }

                // Disable sorting on column where indicated.
                if ($column->nosort) {
                    $table->no_sorting($ident);
                }
            }
        }
        $table->set_attribute('cellspacing', '0');
        $table->set_attribute('id', $shortname);
        $table->set_attribute('class', 'logtable generalbox reportbuilder-table');
        $table->set_control_variables(array(
            TABLE_VAR_SORT    => 'ssort',
            TABLE_VAR_HIDE    => 'shide',
            TABLE_VAR_SHOW    => 'sshow',
            TABLE_VAR_IFIRST  => 'sifirst',
            TABLE_VAR_ILAST   => 'silast',
            TABLE_VAR_PAGE    => 'spage'
        ));
        $table->sortable(true, $this->defaultsortcolumn, $this->defaultsortorder); // sort by name by default
        $table->setup();
        $table->initialbars(true);
        $table->pagesize($perpage, $countfiltered);
        $table->add_toolbar_pagination('right');

        if ($initiallyhidden) {
            $table->set_no_records_message(get_string('initialdisplay_pending', 'totara_reportbuilder'));
        } else {
            if ($this->is_report_filtered()) {
                $table->set_no_records_message(get_string('norecordswithfilter', 'totara_reportbuilder'));
            } else {
                $table->set_no_records_message(get_string('norecordsinreport', 'totara_reportbuilder'));
            }
            // Get the ORDER BY SQL fragment from table.
            $order = $this->get_report_sort($table);
            try {
                $pagestart = $table->get_page_start();
                if ($records = $DB->get_recordset_sql($sql.$order, $params, $pagestart, $perpage)) {
                    $count = $this->get_filtered_count();
                    $location = 0;
                    foreach ($records as $record) {
                        $record_data = $this->src->process_data_row($record, 'html', $this);
                        foreach ($record_data as $k => $v) {
                            if ((string)$v === '') {
                                // We do not want empty cells in HTML table.
                                $record_data[$k] = '&nbsp;';
                            }
                        }
                        if (++$location == $count % $perpage || $location == $perpage) {
                            $table->add_data($record_data, 'last');
                        } else {
                            $table->add_data($record_data);
                        }

                        if ($graph and $pagestart == 0) {
                            $graph->add_record($record);
                        }
                    }
                }
                if ($graph and ($pagestart != 0 or $perpage == $graph->count_records())) {
                    $graph->reset_records();
                    if ($records = $DB->get_recordset_sql($sql.$order, $params, 0, $graph->get_max_records())) {
                        foreach ($records as $record) {
                            $graph->add_record($record);
                        }
                    }
                }
            } catch (dml_read_exception $e) {
                ob_end_flush();

                if ($this->is_cached()) {
                    $debuginfo = $CFG->debugdeveloper ? $e->debuginfo : '';
                    print_error('error:problemobtainingcachedreportdata', 'totara_reportbuilder', '', $debuginfo);
                } else {
                    $debuginfo = $CFG->debugdeveloper ? $e->debuginfo : '';
                    print_error('error:problemobtainingreportdata', 'totara_reportbuilder', '', $debuginfo);
                }
            }
        }

        // The rows are already displayed.
        $table->finish_html();

        // end of .nobox div
        echo $OUTPUT->container_end();

        $tablehmtml = ob_get_clean();

        if ($graph and $graphdata = $graph->fetch_svg()) {
            if (core_useragent::check_browser_version('MSIE', '6.0') and !core_useragent::check_browser_version('MSIE', '9.0')) {
                // See http://partners.adobe.com/public/developer/en/acrobat/PDFOpenParameters.pdf
                $svgurl = new moodle_url('/totara/reportbuilder/ajax/graph.php', array('id' => $this->_id, 'sid' => $this->_sid));
                $svgurl = $svgurl . '#toolbar=0&navpanes=0&scrollbar=0&statusbar=0&viewrect=20,20,400,300';
                $nopdf = get_string('error:nopdf', 'totara_reportbuilder');
                echo "<div class=\"rb-report-pdfgraph\"><object type=\"application/pdf\" data=\"$svgurl\" width=\"100%\" height=\"400\">$nopdf</object>";

            } else {
                // The SVGGraph supports only one SVG per page when embedding directly,
                // it should be fine here because there are no blocks on this page.
                echo '<div class="rb-report-svggraph">';
                echo $graphdata;
                echo '</div>';
            }
        }

        echo $tablehmtml;

        local_js();
        $jsmodule = array(
            'name' => 'totara_reportbuilder_expand',
            'fullpath' => '/totara/reportbuilder/js/expand.js',
            'requires' => array('json'));
        $PAGE->requires->js_init_call('M.totara_reportbuilder_expand.init', array(), true, $jsmodule);

    }

    /**
     * If a redirect url has been specified in the source then output a redirect link.
     */
    public function display_redirect_link() {
        if (isset($this->src->redirecturl)) {
            if (isset($this->src->redirectmessage)) {
                $message = '&laquo; ' . $this->src->redirectmessage;
            } else {
                $message = '&laquo; ' . get_string('selectitem', 'totara_reportbuilder');
            }
            echo html_writer::link($this->src->redirecturl, $message);
        }
    }

    /**
     *
     */
    public function get_expand_content($expandname) {
        $func = 'rb_expand_' . $expandname;
        if (method_exists($this->src, $func)) {
            return $this->src->$func();
        }
    }

    /**
     * Determine if the report should be hidden due to the initialdisplay setting.
     */
    public function is_initially_hidden() {
        if (isset($this->_isinitiallyhidden)) {
            return $this->_isinitiallyhidden;
        }

        $searchedstandard = optional_param_array('submitgroupstandard', array(), PARAM_ALPHANUM);
        $searchedsidebar = optional_param_array('submitgroupstandard', array(), PARAM_ALPHANUM);
        $toolbarsearch = optional_param('toolbarsearchbutton', false, PARAM_TEXT);
        $overrideinitial = isset($searchedstandard['addfilter']) || isset($searchedsidebar['addfilter']) || $toolbarsearch;

        $this->_isinitiallyhidden = ($this->initialdisplay == RB_INITIAL_DISPLAY_HIDE &&
                !$overrideinitial &&
                !$this->is_report_filtered());

        return $this->_isinitiallyhidden;
    }

    /**
     * Get column identifiers of columns that should be hidden on page load
     * The hidden columns are stored in the session
     *
     * @return array of column identifiers, usable by js selectors
     */
    function js_get_hidden_columns() {
        global $SESSION;
        $cols = array();

        $shortname = $this->shortname;
        // javascript to hide columns based on session variable
        if (isset($SESSION->rb_showhide_columns[$shortname])) {
            foreach ($this->columns as $column) {
                $ident = "{$column->type}_{$column->value}";
                if (isset($SESSION->rb_showhide_columns[$shortname][$ident])) {
                    if ($SESSION->rb_showhide_columns[$shortname][$ident] == 0) {
                        $cols[] = "#{$shortname} .{$ident}";
                    }
                }
            }
        }

        return $cols;
    }

    /**
     * Look up the sort keys and make sure they still exist in table
     * (could have been deleted in report builder)
     *
     * @return true May unset flexible table sort keys if they are not
     *              found in the column list
     */
    function check_sort_keys() {
        global $SESSION;
        $shortname = $this->shortname;
        $sortarray = isset($SESSION->flextable[$shortname]->sortby) ? $SESSION->flextable[$shortname]->sortby : null;
        if (is_array($sortarray)) {
            foreach ($sortarray as $sortelement => $unused) {
                // see if sort element is in columns array
                $set = false;
                foreach ($this->columns as $col) {
                    if ($col->type . '_' . $col->value == $sortelement) {
                        $set = true;
                    }
                }
                // if it's not remove it from sort SESSION var
                if ($set === false) {
                    unset($SESSION->flextable[$shortname]->sortby[$sortelement]);
                }
            }
        }
        return true;
    }

    /**
     * Returns a menu that when selected, takes the user to the specified saved search
     *
     * @return string HTML to display a pulldown menu with saved search options
     */
    function view_saved_menu() {
        global $USER, $OUTPUT;
        $id = $this->_id;
        $sid = $this->_sid;

        if ($this->embedded) {
            $common = new moodle_url($this->get_current_url());
        } else {
            $common = new moodle_url('/totara/reportbuilder/report.php', array('id' => $id));
        }

        $savedoptions = $this->get_saved_searches($id, $USER->id);
        if (count($savedoptions) > 0) {
            $select = new single_select($common, 'sid', $savedoptions, $sid);
            $select->label = get_string('viewsavedsearch', 'totara_reportbuilder');
            $select->formid = 'viewsavedsearch';
            return $OUTPUT->render($select);
        } else {
            return '';
        }
    }

    /**
     * Returns an array of available saved seraches for this report and user
     * @param int $reportid look for saved searches for this report
     * @param int $userid Check for saved searches belonging to this user
     * @return array search id => search name
     */
    function get_saved_searches($reportid, $userid) {
        global $DB;
        $savedoptions = array();
        // Are there saved searches for this report and user?
        $saved = $DB->get_records('report_builder_saved', array('reportid' => $reportid, 'userid' => $userid));
        foreach ($saved as $item) {
            $savedoptions[$item->id] = format_string($item->name);
        }
        // Are there public saved searches for this report?
        $saved = $DB->get_records('report_builder_saved', array('reportid' => $reportid, 'ispublic' => 1));
        foreach ($saved as $item) {
            $savedoptions[$item->id] = format_string($item->name);
        }
        return $savedoptions;
    }

    /**
     * Diplays a table containing the save search button and pulldown
     * of existing saved searches (if any)
     *
     * @return string HTML to display the table
     */
    public function display_saved_search_options() {
        global $PAGE;

        if (!isloggedin() or isguestuser()) {
            // No saving for guests, sorry.
            return '';
        }

        $output = $PAGE->get_renderer('totara_reportbuilder');

        $savedbutton = $output->save_button($this);
        $savedmenu = $this->view_saved_menu();

        // no need to print anything
        if (strlen($savedmenu) == 0 && strlen($savedbutton) == 0) {
            return '';
        }

        $controls = html_writer::start_tag('div', array('id' => 'rb-search-controls'));

        if (strlen($savedbutton) != 0) {
            $controls .= $savedbutton;
        }
        if (strlen($savedmenu) != 0) {
            $managesearchbutton = $output->manage_search_button($this);
            $controls .= html_writer::tag('div', $savedmenu, array('id' => 'rb-search-menu'));
            $controls .=  html_writer::tag('div', $managesearchbutton, array('id' => 'manage-saved-search-button'));;
        }

        $controls .= html_writer::end_tag('div');
        return $controls;

    }

    /**
     * Returns HTML for a button that when clicked, takes the user to a page which
     * allows them to edit this report
     *
     * @return string HTML to display the button
     */
    function edit_button() {
        global $OUTPUT;
        $context = context_system::instance();
        // TODO what capability should be required here?
        if (has_capability('totara/reportbuilder:managereports', $context)) {
            return $OUTPUT->single_button(new moodle_url('/totara/reportbuilder/general.php', array('id' => $this->_id)), get_string('editthisreport', 'totara_reportbuilder'), 'get');
        } else {
            return '';
        }
    }


    /** Download current table in ODS format
     * @param array $fields Array of column headings
     * @param string $query SQL query to run to get results
     * @param array $params SQL query params
     * @param integer $count Number of filtered records in query
     * @param array $restrictions Array of strings containing info
     *                            about the content of the report
     * @param string $file path to the directory where the file will be saved
     * @param array $cache report cache information
     * @return Returns the ODS file
     */
    function download_ods($fields, $query, $params, $count, $restrictions = array(), $file = null, $cache = array()) {
        global $CFG, $DB;

        require_once("$CFG->libdir/odslib.class.php");

        // Increasing the execution time to no limit.
        set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $fullname = strtolower(preg_replace(array('/[^a-zA-Z\d\s-_]/', '/[\s-]/'), array('', '_'), $this->fullname));
        $filename = clean_filename($fullname . '_report.ods');

        if (!$file) {
            header("Content-Type: application/download\n");
            header("Content-Disposition: attachment; filename=$filename");
            header("Expires: 0");
            header("Cache-Control: must-revalidate,post-check=0,pre-check=0");
            header("Pragma: public");

            $workbook = new MoodleODSWorkbook($filename);
        } else {
            $workbook = new MoodleODSWorkbook($file, true);
        }

        $worksheet = array();

        $worksheet[0] = $workbook->add_worksheet('');
        $row = 0;
        $col = 0;

        if (is_array($restrictions) && count($restrictions) > 0) {
            $worksheet[0]->write($row, 0, get_string('reportcontents', 'totara_reportbuilder'));
            $row++;
            foreach ($restrictions as $restriction) {
                $worksheet[0]->write($row, 0, $restriction);
                $row++;
            }
        }

        // Add report caching data.
        if ($cache) {
            $usertz = totara_get_clean_timezone();
            $a = userdate($cache['lastreport'], '', $usertz);
            $worksheet[0]->write($row, 0, get_string('report:cachelast', 'totara_reportbuilder', $a));
            $row++;
        }

        // Leave an empty row between any initial info and the header row.
        if ($row != 0) {
            $row++;
        }

        foreach ($fields as $field) {
            $worksheet[0]->write($row, $col, $this->format_column_heading($field, true));
            $col++;
        }
        $row++;

        $numfields = count($fields);

        // Use recordset so we can manage very large datasets.
        if ($records = $DB->get_recordset_sql($query, $params)) {
            foreach ($records as $record) {
                $record_data = $this->src->process_data_row($record, 'ods', $this);
                $col = 0;
                foreach ($record_data as $value) {
                    if (is_array($value)) {
                        if (method_exists($worksheet[0], 'write_' . $value[0])) {
                            $worksheet[0]->{'write_' . $value[0]}($row, $col++, $value[1], $value[2]);
                        } else {
                            $worksheet[0]->write($row, $col++, $value[1]);
                        }
                    } else {
                        $worksheet[0]->write($row, $col++, $value);
                    }
                }
                $row++;
            }
            $records->close();
        } else {
            // This indicates a failed query, not just 0 results.
            return false;
        }

        $workbook->close();
        if (!$file) {
            die;
        }
    }

    /** Download current table in XLS format
     * @param rb_column[] $fields Array of column headings
     * @param string $query SQL query to run to get results
     * @param array $params SQL query params
     * @param integer $count Number of filtered records in query
     * @param array $restrictions Array of strings containing info
     *                            about the content of the report
     * @param string $file path to the directory where the file will be saved
     * @param array $cache Report cache information
     * @return Returns the Excel file
     */
    function download_xls($fields, $query, $params, $count, $restrictions = array(), $file = null, $cache = array()) {
        global $CFG, $DB;

        require_once("$CFG->libdir/excellib.class.php");

        // Increasing the execution time to no limit.
        set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $fullname = strtolower(preg_replace(array('/[^a-zA-Z\d\s-_]/', '/[\s-]/'), array('', '_'), $this->fullname));
        $filename = clean_filename($fullname . '_report.xls');

        if (!$file) {
            $workbook = new MoodleExcelWorkbook($filename);
        } else {
            $workbook = new MoodleExcelWorkbook($file, 'Excel2007', true);
        }

        $worksheet = array();

        $worksheet[0] = $workbook->add_worksheet('');
        $row = 0;
        $col = 0;

        if (is_array($restrictions) && count($restrictions) > 0) {
            $worksheet[0]->write($row, 0, get_string('reportcontents', 'totara_reportbuilder'));
            $row++;
            foreach ($restrictions as $restriction) {
                $worksheet[0]->write($row, 0, $restriction);
                $row++;
            }
        }

        // Add report caching data.
        if ($cache) {
            $usertz = totara_get_clean_timezone();
            $a = userdate($cache['lastreport'], '', $usertz);
            $worksheet[0]->write($row, 0, get_string('report:cachelast', 'totara_reportbuilder', $a));
            $row++;
        }

        // Leave an empty row between any initial info and the header row.
        if ($row != 0) {
            $row++;
        }

        foreach ($fields as $field) {
            $worksheet[0]->write($row, $col, $this->format_column_heading($field, true));
            $col++;
        }
        $row++;

        // User recordset so we can handle large datasets.
        if ($records = $DB->get_recordset_sql($query, $params)) {
            foreach ($records as $record) {
                $record_data = $this->src->process_data_row($record, 'excel', $this);
                $col = 0;
                foreach ($record_data as $value) {
                    if (is_array($value)) {
                        if (method_exists($worksheet[0], 'write_' . $value[0])) {
                            $worksheet[0]->{'write_' . $value[0]}($row, $col++, $value[1], $value[2]);
                        } else {
                            $worksheet[0]->write($row, $col++, $value[1]);
                        }
                    } else {
                        $worksheet[0]->write($row, $col++, $value);
                    }
                }
                $row++;
            }
            $records->close();
        } else {
            // This indicates a failed query, not just 0 results.
            return false;
        }

        $workbook->close();
        if (!$file) {
            die;
        }
    }

     /** Download current table in CSV format
     * @param array $fields Array of column headings
     * @param string $query SQL query to run to get results
     * @param array $params SQL query params
     * @param integer $count Number of filtered records in query
     * @return Returns the CSV file
     */
    function download_csv($fields, $query, $params, $count, $file=null) {
        global $DB, $CFG;

        require_once("{$CFG->libdir}/csvlib.class.php");

        // Increasing the execution time to no limit.
        set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        $fullname = strtolower(preg_replace(array('/[^a-zA-Z\d\s-_]/', '/[\s-]/'), array('', '_'), $this->fullname));
        $filename = clean_filename($fullname . '_report.csv');

        $export = new csv_export_writer();
        $export->filename = $filename;

        $row = array();
        foreach ($fields as $field) {
            $row[] =  $this->format_column_heading($field, true);
        }

        $export->add_data($row);

        if ($records = $DB->get_recordset_sql($query, $params)) {
            foreach ($records as $record) {
                $row = $this->src->process_data_row($record, 'csv', $this);
                $export->add_data($row);
            }
            $records->close();
        } else {
            // this indicates a failed query, not just 0 results
            return false;
        }

        if ($file) {
            $fp = fopen($file, "w");
            fwrite($fp, $export->print_csv_data(true));
            fclose($fp);
        } else {
            $export->download_file();
        }
    }

    /**
     * Download current table in a Pdf format
     * @param array $fields Array of column headings
     * @param string $query SQL query to run to get results
     * @param array $params SQL query params
     * @param integer $count Number of filtered records in query
     * @param array $restrictions Array of strings containing info
     *                            about the content of the report
     * @param boolean $portrait A boolean representing the print layout
     * @param string a path where to save file
     * @param array $cache Report cache information
     * @return Returns the PDF file
     */
    function download_pdf($fields, $query, $params, $count, $restrictions = array(), $portrait = true, $file = null, $cache = array()) {
        global $DB, $CFG;

        require_once $CFG->libdir . '/pdflib.php';

        // Increasing the execution time to no limit.
        set_time_limit(0);

        $fullname = strtolower(preg_replace(array('/[^a-zA-Z\d\s-_]/', '/[\s-]/'), array('', '_'), $this->fullname));
        $filename = clean_filename($fullname . '_report.pdf');

        // Table.
        $html = '';
        $numfields = count($fields);

        if (!$records = $DB->get_recordset_sql($query, $params)) {
            return false;
        }

        $graphrecord = $DB->get_record('report_builder_graph', array('reportid' => $this->_id));
        if (!empty($graphrecord->type)) {
            $graph = new \totara_reportbuilder\local\graph($graphrecord, $this, false);
        } else {
            $graph = null;
        }

        // Layout options.
        if ($portrait) {
            $pdf = new PDF('P', 'mm', 'A4', true, 'UTF-8');
        } else {
            $pdf = new PDF('L', 'mm', 'A4', true, 'UTF-8');
        }

        $pdf->setTitle($filename);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(true);
        $pdf->SetFooterMargin(REPORT_BUILDER_PDF_MARGIN_FOOTER);
        $pdf->SetAutoPageBreak(true, REPORT_BUILDER_PDF_MARGIN_BOTTOM);
        $pdf->AddPage();

        // Get current language to set the font properly.
        $language = current_language();
        $font = $this->get_font($language);
        // Check if language is RTL.
        if (right_to_left()) {
            $pdf->setRTL(true);
        }

        $pdf->SetFont($font, 'B', REPORT_BUILDER_PDF_FONT_SIZE_TITLE);
        $pdf->Write(0, format_string($this->fullname), '', 0, 'L', true, 0, false, false, 0);

        $resultstr = $count == 1 ? 'record' : 'records';
        $recordscount = get_string('x' . $resultstr, 'totara_reportbuilder', $count);
        $pdf->SetFont($font, 'B', REPORT_BUILDER_PDF_FONT_SIZE_RECORD);
        $pdf->Write(0, $recordscount, '', 0, 'L', true, 0, false, false, 0);

        $pdf->SetFont($font, '', REPORT_BUILDER_PDF_FONT_SIZE_DATA);

        if (is_array($restrictions) && count($restrictions) > 0) {
            $pdf->Write(0, get_string('reportcontents', 'totara_reportbuilder'), '', 0, 'L', true, 0, false, false, 0);
            foreach ($restrictions as $restriction) {
                $pdf->Write(0, $restriction, '', 0, 'L', true, 0, false, false, 0);
            }
        }

        // Add report caching data.
        if ($cache) {
            $usertz = totara_get_clean_timezone();
            $a = userdate($cache['lastreport'], '', $usertz);
            $lastcache = get_string('report:cachelast', 'totara_reportbuilder', $a);
            $pdf->Write(0, $lastcache, '', 0, 'L', true, 0, false, false, 0);
        }

        $html .= '<table border="1" cellpadding="2" cellspacing="0">
                        <thead>
                            <tr style="background-color: #CCC;">';
        foreach ($fields as $field) {
            $html .= '<th>' . s($this->format_column_heading($field, true)) . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($records as $record) {
            if ($graph) {
                $graph->add_record($record);
            }

            $record_data = $this->src->process_data_row($record, 'pdf', $this);
            $html .= '<tr>';
            foreach($record_data as $value) {
                $html .= '<td>' . str_replace("\n", '<br />', s($value)) . '</td>';
            }
            $html .= '</tr>';

            // Check memory limit.
            $mramuse = ceil(((memory_get_usage(true)/1024)/1024));
            if (REPORT_BUILDER_EXPORT_PDF_MEMORY_LIMIT <= $mramuse) {
                // Releasing resources.
                $records->close();
                // Notice message.
                print_error('exportpdf_mramlimitexceeded', 'totara_reportbuilder', '', REPORT_BUILDER_EXPORT_PDF_MEMORY_LIMIT);
            }
        }
        $html .= '</tbody></table>';

        $svgdata = $graph->fetch_pdf_svg($portrait);
        if ($svgdata) {
            if ($portrait) {
                $pdf->ImageSVG('@'.$svgdata, 5, 30, 196, 100);
            } else {
                $pdf->ImageSVG('@'.$svgdata, 5, 30, 282, 100);
            }
            $pdf->SetY(130);
        }

        // Closing the pdf.
        $pdf->WriteHTML($html, true, false, false, false, '');

        // Releasing recordset resources.
        $records->close();

        // Returning the complete pdf.
        if (!$file) {
            $pdf->Output($filename, 'D');
        } else {
            $pdf->Output($file, 'F');
        }
    }

    /**
     * Returns the font that must be used based on the language
     *
     * @param string $language Language that is being used
     * @return string The appropriate font based on the language
     */
    function get_font($language) {
        if (in_array($language, array('zh_cn', 'ja'))) {
            return 'droidsansfallback';
        } else if ($language == 'th') {
            return 'cordiaupc';
        } else {
            // NOTE: previously used 'dejavusans' is not compatible with iOS 7 and older devices,
            //       'freesans' on the other hand does not support all languages.
            //       Ideally this should be admin configurable so that multilingual sites
            //       may decide to use Arial Unicode here.
            return 'freeserif';
        }
    }

    /* Download current table to Google Fusion
     * @param array $fields Array of column headings
     * @param string $query SQL query to run to get results
     * @param integer $count Number of filtered records in query
     * @param array $restrictions Array of strings containing info
     *                            about the content of the report
     * @return Returns never
     */
    function download_fusion() {
        $jump = new moodle_url('/totara/reportbuilder/fusionexporter.php', array('id' => $this->_id, 'sid' => $this->_sid));
        redirect($jump->out());
        die;
    }

    /**
     * Returns array of content options allowed for this report's source
     *
     * @return array An array of content option names
     */
    function get_content_options() {

        $contentoptions = array();
        if (isset($this->contentoptions) && is_array($this->contentoptions)) {
            foreach ($this->contentoptions as $option) {
                $contentoptions[] = $option->classname;
            }
        }
        return $contentoptions;
    }


    ///
    /// Functions for Editing Reports
    ///


    /**
     * Parses the filter options data for this source into a data structure
     * suitable for an HTML select pulldown.
     *
     * @return array An Array with $type-$value as key and $label as value
     */
    public function get_filters_select($onlyinstant = false) {
        $ret = array();
        if (!isset($this->filteroptions)) {
            return $ret;
        }

        $filters = $this->filteroptions;

        // Are we handling a 'group' source?
        if (preg_match('/^(.+)_grp_([0-9]+|all)$/', $this->source, $matches)) {
            // Use original source name (minus any suffix).
            $sourcename = $matches[1];
        } else {
            // Standard source.
            $sourcename = $this->source;
        }

        foreach ($filters as $filter) {
            if (!$onlyinstant || in_array($filter->filtertype, array('date', 'select', 'multicheck'))) {
                $langstr = 'type_' . $filter->type;
                if (get_string_manager()->string_exists($langstr, 'rb_source_' . $sourcename)) {
                    // Is there a type string in the source file?
                    $section = get_string($langstr, 'rb_source_' . $sourcename);
                } else if (get_string_manager()->string_exists($langstr, 'totara_reportbuilder')) {
                    // How about in report builder?
                    $section = get_string($langstr, 'totara_reportbuilder');
                } else {
                    // Display in missing string format to make it obvious.
                    $section = get_string_manager()->get_string($langstr, 'rb_source_' . $sourcename);
                }

                $key = $filter->type . '-' . $filter->value;
                $ret[$section][$key] = format_string($filter->label);
            }
        }
        return $ret;
    }

    /**
     * Parses the search columns data for this source into a data structure
     * suitable for an HTML select pulldown.
     *
     * @return array An Array with $type-$value as key and $label as value
     */
    public function get_search_columns_select() {
        $ret = array();
        if (!isset($this->columnoptions)) {
            return $ret;
        }

        $columnoptions = $this->columnoptions;

        // Are we handling a 'group' source?
        if (preg_match('/^(.+)_grp_([0-9]+|all)$/', $this->source, $matches)) {
            // Use original source name (minus any suffix).
            $sourcename = $matches[1];
        } else {
            // Standard source.
            $sourcename = $this->source;
        }

        foreach ($columnoptions as $columnoption) {
            if ($columnoption->is_searchable()) {
                $langstr = 'type_' . $columnoption->type;
                if (get_string_manager()->string_exists($langstr, 'rb_source_' . $sourcename)) {
                    // Is there a type string in the source file?
                    $section = get_string($langstr, 'rb_source_' . $sourcename);
                } else if (get_string_manager()->string_exists($langstr, 'totara_reportbuilder')) {
                    // How about in report builder?
                    $section = get_string($langstr, 'totara_reportbuilder');
                } else {
                    // Display in missing string format to make it obvious.
                    $section = get_string_manager()->get_string($langstr, 'rb_source_' . $sourcename);
                }

                $key = $columnoption->type . '-' . $columnoption->value;
                $ret[$section][$key] = format_string($columnoption->name);
            }
        }
        return $ret;
    }

    public function get_all_filters_select() {
        // Standard filters.
        $allstandardfilters = array_merge(
                array(get_string('new') => array(0 => get_string('addanotherfilter', 'totara_reportbuilder'))),
                $this->get_filters_select());
        $unusedstandardfilters = $allstandardfilters;
        foreach ($allstandardfilters as $okey => $optgroup) {
            foreach ($optgroup as $typeval => $filtername) {
                $typevalarr = explode('-', $typeval);
                foreach ($this->filters as $curfilter) {
                    if (($curfilter->region == rb_filter_type::RB_FILTER_REGION_STANDARD ||
                         $curfilter->region == rb_filter_type::RB_FILTER_REGION_SIDEBAR) &&
                         $curfilter->type == $typevalarr[0] && $curfilter->value == $typevalarr[1]) {
                        unset($unusedstandardfilters[$okey][$typeval]);
                    }
                }
            }
        }

        // Sidebar filters.
        $allsidebarfilters = array_merge(
                array(get_string('new') => array(0 => get_string('addanotherfilter', 'totara_reportbuilder'))),
                $this->get_filters_select(true));
        $unusedsidebarfilters = $allsidebarfilters;
        foreach ($allsidebarfilters as $okey => $optgroup) {
            foreach ($optgroup as $typeval => $filtername) {
                $typevalarr = explode('-', $typeval);
                foreach ($this->filters as $curfilter) {
                    if (($curfilter->region == rb_filter_type::RB_FILTER_REGION_STANDARD ||
                         $curfilter->region == rb_filter_type::RB_FILTER_REGION_SIDEBAR) &&
                         $curfilter->type == $typevalarr[0] && $curfilter->value == $typevalarr[1]) {
                        unset($unusedsidebarfilters[$okey][$typeval]);
                    }
                }
            }
        }

        // Search columns.
        $allsearchcolumns = array_merge(
            array(get_string('new') => array(0 => get_string('addanothersearchcolumn', 'totara_reportbuilder'))),
            $this->get_search_columns_select());
        // Remove already-added search columns from the new search column selectors.
        $unusedsearchcolumns = $allsearchcolumns;
        foreach ($allsearchcolumns as $okey => $optgroup) {
            foreach ($optgroup as $typeval => $searchcolumnname) {
                $typevalarr = explode('-', $typeval);
                foreach ($this->searchcolumns as $cursearchcolumn) {
                    if ($cursearchcolumn->type == $typevalarr[0] && $cursearchcolumn->value == $typevalarr[1]) {
                        unset($unusedsearchcolumns[$okey][$typeval]);
                    }
                }
            }
        }

        return compact('allstandardfilters', 'unusedstandardfilters',
                       'allsidebarfilters', 'unusedsidebarfilters',
                       'allsearchcolumns', 'unusedsearchcolumns');
    }

    /**
     * Parses the column options data for this source into a data structure
     * suitable for an HTML select pulldown
     *
     * @return array An array with $type-$value as key and $name as value
     */
    function get_columns_select() {
        $columns = $this->columnoptions;
        $ret = array();
        if (!isset($this->columnoptions)) {
            return $ret;
        }

        // are we handling a 'group' source?
        if (preg_match('/^(.+)_grp_([0-9]+|all)$/', $this->source, $matches)) {
            // use original source name (minus any suffix)
            $sourcename = $matches[1];
        } else {
            // standard source
            $sourcename = $this->source;
        }

        foreach ($columns as $column) {
            // don't include unselectable columns
            if (!$column->selectable) {
                continue;
            }
            $langstr = 'type_' . $column->type;
            // is there a type string in the source file?
            if (get_string_manager()->string_exists($langstr, 'rb_source_' . $sourcename)) {
                $section = get_string($langstr, 'rb_source_' . $sourcename);
            // how about in report builder?
            } else if (get_string_manager()->string_exists($langstr, 'totara_reportbuilder')) {
                $section = get_string($langstr, 'totara_reportbuilder');
            } else {
                // Display in missing string format to make it obvious.
                $section = get_string_manager()->get_string($langstr, 'rb_source_' . $sourcename);
            }

            $key = $column->type . '-' . $column->value;
            $ret[$section][$key] = format_string($column->name);
        }
        return $ret;
    }

    /**
     * Given a column id, sets the default visibility to show or hide
     * for that column on current report
     *
     * @param integer $cid ID of the column to be changed
     * @param integer $hide 0 to show column, 1 to hide it
     * @return boolean True on success, false otherwise
     */
    function showhide_column($cid, $hide) {
        global $DB;

        $col = $DB->get_record('report_builder_columns', array('id' => $cid));
        if (!$col) {
            return false;
        }

        $todb = new stdClass();
        $todb->id = $cid;
        $todb->hidden = $hide;
        $DB->update_record('report_builder_columns', $todb);

        $this->columns = $this->get_columns();
        return true;
    }

    /**
     * Given a column id, removes that column from the current report
     *
     * @param integer $cid ID of the column to be removed
     * @return boolean True on success, false otherwise
     */
    function delete_column($cid) {
        global $DB;

        $id = $this->_id;
        $sortorder = $DB->get_field('report_builder_columns', 'sortorder', array('id' => $cid));
        if (!$sortorder) {
            return false;
        }
        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('report_builder_columns', array('id' => $cid));
        $allcolumns = $DB->get_records('report_builder_columns', array('reportid' => $id));
        foreach ($allcolumns as $column) {
            if ($column->sortorder > $sortorder) {
                $todb = new stdClass();
                $todb->id = $column->id;
                $todb->sortorder = $column->sortorder - 1;
                $DB->update_record('report_builder_columns', $todb);
            }
        }
        $transaction->allow_commit();

        $this->columns = $this->get_columns();
        return true;
    }

    /**
     * Given a filter id, removes that filter from the current report and
     * updates the sortorder for other filters
     *
     * @param integer $fid ID of the filter to be removed
     * @return boolean True on success, false otherwise
     */
    function delete_filter($fid) {
        global $DB;

        $id = $this->_id;

        $sortorder = $DB->get_field('report_builder_filters', 'sortorder', array('id' => $fid));
        if (!$sortorder) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('report_builder_filters', array('id' => $fid));
        $allfilters = $DB->get_records('report_builder_filters', array('reportid' => $id));
        foreach ($allfilters as $filter) {
            if ($filter->sortorder > $sortorder) {
                $todb = new stdClass();
                $todb->id = $filter->id;
                $todb->sortorder = $filter->sortorder - 1;
                $DB->update_record('report_builder_filters', $todb);
            }
        }

        $transaction->allow_commit();

        $this->filters = $this->get_filters();
        return true;
    }

    /**
     * Given a search column id, removes that search column from the current report
     *
     * @param integer $searchcolumnid ID of the search column to be removed
     * @return boolean True on success, false otherwise
     */
    public function delete_search_column($searchcolumnid) {
        global $DB;

        $DB->delete_records('report_builder_search_cols', array('id' => $searchcolumnid));

        $this->searchcolumns = $this->get_search_columns();
        return true;
    }

    /**
     * Given a column id and a direction, moves a column up or down
     *
     * @param integer $cid ID of the column to be moved
     * @param string $updown String 'up' or 'down'
     * @return boolean True on success, false otherwise
     */
    function move_column($cid, $updown) {
        global $DB;

        $id = $this->_id;

        // assumes sort order is well behaved (no gaps)
        if (!$itemsort = $DB->get_field('report_builder_columns', 'sortorder', array('id' => $cid))) {
            return false;
        }
        if ($updown == 'up') {
            $newsort = $itemsort - 1;
        } else if ($updown == 'down') {
            $newsort = $itemsort + 1;
        } else {
            // invalid updown string
            return false;
        }
        if ($neighbour = $DB->get_record('report_builder_columns', array('reportid' => $id, 'sortorder' => $newsort))) {
            $transaction = $DB->start_delegated_transaction();
            // swap sort orders
            $todb = new stdClass();
            $todb->id = $cid;
            $todb->sortorder = $neighbour->sortorder;
            $todb2 = new stdClass();
            $todb2->id = $neighbour->id;
            $todb2->sortorder = $itemsort;
            $DB->update_record('report_builder_columns', $todb);
            $DB->update_record('report_builder_columns', $todb2);
            $transaction->allow_commit();
        } else {
            // no neighbour
            return false;
        }
        $this->columns = $this->get_columns();
        return true;
    }


    /**
     * Given a filter id and a direction, moves a filter up or down
     *
     * @param integer $fid ID of the filter to be moved
     * @param string $updown String 'up' or 'down'
     * @return boolean True on success, false otherwise
     */
    function move_filter($fid, $updown) {
        global $DB;

        $id = $this->_id;

        // assumes sort order is well behaved (no gaps)
        if (!$itemsort = $DB->get_field('report_builder_filters', 'sortorder', array('id' => $fid))) {
            return false;
        }
        if ($updown == 'up') {
            $newsort = $itemsort - 1;
        } else if ($updown == 'down') {
            $newsort = $itemsort + 1;
        } else {
            // invalid updown string
            return false;
        }
        if ($neighbour = $DB->get_record('report_builder_filters', array('reportid' => $id, 'sortorder' => $newsort))) {
            $transaction = $DB->start_delegated_transaction();
            // swap sort orders
            $todb = new stdClass();
            $todb->id = $fid;
            $todb->sortorder = $neighbour->sortorder;
            $todb2 = new stdClass();
            $todb2->id = $neighbour->id;
            $todb2->sortorder = $itemsort;
            $DB->update_record('report_builder_filters', $todb);
            $DB->update_record('report_builder_filters', $todb2);
            $transaction->allow_commit();
        } else {
            // no neighbour
            return false;
        }
        $this->filters = $this->get_filters();
        return true;
    }

    /**
     * Method for obtaining a report builder setting
     *
     * @param integer $reportid ID for the report to obtain a setting for
     * @param string $type Identifies the class using the setting
     * @param string $name Identifies the particular setting
     * @return mixed The value of the setting $name or null if it doesn't exist
     */
    public static function get_setting($reportid, $type, $name) {
        global $DB;
        return $DB->get_field('report_builder_settings', 'value', array('reportid' => $reportid, 'type' => $type, 'name' => $name));
    }

    /**
     * Return an associative array of all settings of a particular type
     *
     * @param integer $reportid ID of the report to get settings for
     * @param string $type Identifies the class to get settings from
     * @return array Associative array of name|value settings
     */
    static function get_all_settings($reportid, $type) {
        global $DB;

        $settings = array();
        $records = $DB->get_records('report_builder_settings', array('reportid' => $reportid, 'type' => $type));
        foreach ($records as $record) {
            $settings[$record->name] = $record->value;
        }
        return $settings;
    }

    /**
     * Method for updating a setting for a particular report
     *
     * Will create a DB record if no setting is found
     *
     * @param integer $reportid ID of the report to update the settings of
     * @param string $type Identifies the class to be updated
     * @param string $name Identifies the particular setting to update
     * @param string $value The new value of the setting
     * @return boolean True if the setting could be updated or created
     */
    static function update_setting($reportid, $type, $name, $value) {
        global $DB;

        if ($record = $DB->get_record('report_builder_settings', array('reportid' => $reportid, 'type' => $type, 'name' => $name))) {
            // update record
            $todb = new stdClass();
            $todb->id = $record->id;
            $todb->value = $value;
            $DB->update_record('report_builder_settings', $todb);
        } else {
            // insert record
            $todb = new stdClass();
            $todb->reportid = $reportid;
            $todb->type = $type;
            $todb->name = $name;
            $todb->value = $value;
            $DB->insert_record('report_builder_settings', $todb);
        }
        $DB->set_field('report_builder', 'timemodified', time(), array('id' => $reportid));
        return true;
    }


    /**
     * Return HTML to display the results of a feedback activity
     */
    function print_feedback_results() {
        global $DB, $OUTPUT;

        if ($this->is_initially_hidden()) {
            return get_string('initialdisplay_pending', 'totara_reportbuilder');
        }

        // get paging parameters
        define('DEFAULT_PAGE_SIZE', $this->recordsperpage);
        define('SHOW_ALL_PAGE_SIZE', 9999);
        $spage     = optional_param('spage', 0, PARAM_INT);                    // which page to show
        $perpage   = optional_param('perpage', DEFAULT_PAGE_SIZE, PARAM_INT);
        $countfiltered = $this->get_filtered_count();

        $out = '';
        $groupid = $this->src->groupid;
        $out .= $OUTPUT->box_start();

        if (!$groupid) {
            $out .= get_string('activitygroupnotfound', 'totara_reportbuilder');
        }
        $questionstable = "report_builder_fbq_{$groupid}_q";
        $optionstable = "report_builder_fbq_{$groupid}_opt";
        $answerstable = "report_builder_fbq_{$groupid}_a";

        $questions = $DB->get_records($questionstable, null, 'sortorder');
        $options = $DB->get_records($optionstable, null, 'qid, sortorder');
        $grouped_options = array();
        foreach ($options as $option) {
            $grouped_options[$option->qid][] = $option;
        }

        // get first column and use as heading
        $columns = $this->columns;
        if (count($columns) > 0) {
            $primary_field = current($columns);
            if ($primary_field->required == true) {
                $primary_field = null;
            }

            // get any extra (none required) columns
            $additional_fields = array();
            while($col = next($columns)) {
                if ($col->required == false) {
                    $additional_fields[] = $col;
                }
            }
        }

        // get data
        list($sql, $params) = $this->build_query(false, true);

        $baseid = $this->grouped ? 'min(base.id)' : 'base.id';

        // use default sort data if set
        if (isset($this->defaultsortcolumn)) {
            if (isset($this->defaultsortorder) &&
                $this->defaultsortorder == SORT_DESC) {
                $order = 'DESC';
            } else {
                $order = 'ASC';
            }

            // see if sort element is in columns array
            $set = false;
            foreach ($this->columns as $col) {
                if ($col->type . '_' . $col->value == $this->defaultsortcolumn) {
                    $set = true;
                }
            }
            if ($set) {
                $sort = " ORDER BY {$this->defaultsortcolumn} {$order}, {$baseid}";
            } else {
                $sort = " ORDER BY {$baseid}";
            }
        } else {
            $sort = " ORDER BY {$baseid}";
        }
        $data = $DB->get_records_sql($sql . $sort, $params, $spage * $perpage, $perpage);
        $first = true;

        foreach ($data as $item) {
            // dividers between feedback results
            if ($first) {
                $pagingbar = new paging_bar($countfiltered, $spage, $perpage, $this->report_url());
                $pagingbar->pagevar = 'spage';
                $out .= $OUTPUT->render($pagingbar);

                $first = false;
            } else {
                $out .= html_writer::empty_tag('hr', array('class' => 'feedback-separator'));
            }

            if (isset($primary_field)) {
                // Print primary heading.
                $primaryheading = $primary_field->heading;
                $primaryvalue = $this->src->format_column_data($primary_field, 'html', $item, $this);
                $out .= $OUTPUT->heading($primaryheading . ': ' . $primaryvalue, 2);
            }

            if (isset($additional_fields)) {
                // Print secondary details.
                foreach ($additional_fields as $additional_field) {
                    $addheading = $additional_field->heading;
                    $addvalue = $this->src->format_column_data($additional_field, 'html', $item, $this);
                    $out .= html_writer::tag('strong', $addheading . ': '. $addvalue) . html_writer::empty_tag('br');
                }
            }

            // print count of number of results
            $out .= html_writer::tag('p', get_string('resultsfromfeedback', 'totara_reportbuilder', $item->responses_number));

            // display answers
            foreach ($questions as $question) {
                $qnum = $question->sortorder;;
                $qname = $question->name;
                $qid = $question->id;
                $out .= $OUTPUT->heading('Q' . $qnum . ': ' . $qname, 3);

                switch($question->typ) {
                case 'dropdown':
                case 'dropdownrated':
                case 'check':
                case 'radio':
                case 'radiorated':
                    // if it's an option based question, display bar chart if there are options
                    if (!array_key_exists($qid, $grouped_options)) {
                        continue;
                    }
                    $out .= $this->get_feedback_option_answer($qid, $grouped_options[$qid], $item);
                    break;
                case 'textarea':
                case 'textfield':
                    // if it's a text based question, print all answers in a text field
                    $out .= $this->get_feedback_standard_answer($qid, $item);
                    break;
                case 'numeric':
                default:
                }

            }
        }

        $pagingbar = new paging_bar($countfiltered, $spage, $perpage, $this->report_url());
        $pagingbar->pagevar = 'spage';
        $out .= $OUTPUT->render($pagingbar);

        $out .= $OUTPUT->box_end();

        return $out;
    }

    function get_feedback_standard_answer($qid, $item) {
        $out = '';
        $count = 'q' . $qid . '_count';
        $answer = 'q' . $qid . '_list';
        if (isset($item->$count)) {
            $out .= html_writer::tag('p', get_string('numresponses', 'totara_reportbuilder', $item->$count));
        }
        if (isset($item->$answer) && $item->$answer != '') {
            $responses = str_replace(array('<br />'), array("\n"), $item->$answer);
            $out .= html_writer::tag('textarea', $responses, array('rows' => '6', 'cols' => '100'));
        }
        return $out;
    }

    function get_feedback_option_answer($qid, $options, $item) {
        $out = '';
        $count = array();
        $perc = array();
        // group answer counts and percentages
        foreach ($options as $option) {
            $oid = $option->sortorder;
            $countname = 'q' . $qid . '_' . $oid . '_sum';
            $percname = 'q' . $qid . '_' . $oid . '_perc';
            if (isset($item->$countname)) {
                $count[$oid] = $item->$countname;
            } else {
                $count[$oid] = null;
            }
            if (isset($item->$percname)) {
                $perc[$oid] = $item->$percname;
            } else {
                $perc[$oid] = null;
            }
        }
        $maxcount = max($count);
        $maxbarwidth = 100; // percent

        $numresp = 'q' . $qid . '_total';
        if (isset($item->$numresp)) {
            $out .= html_writer::tag('p', get_string('numresponses', 'totara_reportbuilder', $item->$numresp));
        }

        $table =- new html_table();
        $table->attributes['class'] = 'feedback-table';
        foreach ($options as $option) {
            $cells = array();
            $oid = $option->sortorder;
            $cell = new html_table_cell($oid);
            $cell->attributes['class'] = 'feedback-option-number';
            $cells[] = $cell;
            $cell = new html_table_cell($option->name);
            $cell->attributes['class'] = 'feedback-option-name';
            $cells[] = $cell;
            $barwidth = $perc[$oid];
            $spacewidth = 100 - $barwidth;
            $innertable = new html_table();
            $innertable->attributes['class'] = 'feedback-bar-chart';
            $innercells = array();
            $cell = new html_table_cell('');
            $cell->attributes['class'] = 'feedback-bar-color';
            $cell->attributes['width'] = $barwidth.'%';
            $innercells[] = $cell;
            $cell = new html_table_cell('');
            $cell->attributes['class'] = 'feedback-bar-blank';
            $cell->attributes['width'] = $spacewidth.'%';
            $innercells[] = $cell;
            $innertable->data[] = new html_table_row($innercells);
            $cell = new html_table_cell(html_writer::table($innertable));
            $cell->attributes['class'] = 'feedback-option-chart';
            $cells[] = $cell;
            $content = $count[$oid];
            if (isset($perc[$oid])) {
                $content .= ' (' . $perc[$oid] . '%)';
            }
            $cell = new html_table_cell($content);
            $cell->attributes['class'] = 'feedback-option-count';
            $cells[] = $cell;
            $table->data[] = new html_table_row($cells);
        }
        $out .= html_writer::table($table);
        return $out;
    }

    /**
     * Determines if this report currently has any active filters or not
     *
     * This is done by fetching the filtering SQL to see if it is set yet
     *
     * @return boolean True if one or more filters are currently active
     */
    function is_report_filtered() {
        $filters = $this->fetch_sql_filters();
        if (isset($filters[0]['where']) && $filters[0]['where'] != '') {
            return true;
        }
        if (isset($filters[0]['having']) && $filters[0]['having'] != '') {
            return true;
        }
        return false;
    }

    /**
     * Setter for post_config_restrictions property
     *
     * This is an array of the form:
     *
     * $restrictions = array(
     *     "sql_where_snippet",
     *     array('paramkey' => 'paramvalue')
     * );
     *
     * i.e. it provides both a string of SQL and any parameters used by that string.
     *
     * @param array Restrictions to be added to the query WHERE clause.
     */
    public function set_post_config_restrictions($restrictions) {
        $this->_post_config_restrictions = $restrictions;
    }

    /**
     * Getter for post_config_restrictions.
     */
    public function get_post_config_restrictions() {
        if (empty($this->_post_config_restrictions)) {
            return array('', array());
        }
        return $this->_post_config_restrictions;
    }

} // End of reportbuilder class

class ReportBuilderException extends Exception { }



/**
 * Run the reportbuilder cron
 */
function totara_reportbuilder_cron() {
    global $CFG;
    require_once($CFG->dirroot . '/totara/reportbuilder/cron.php');
    reportbuilder_cron();
}

/**
 * Returns the proper SQL to create table based on a query
 * @param string $table
 * @param string $select SQL select statement
 * @param array $params SQL params
 * @return bool success
 */
function sql_table_from_select($table, $select, array $params) {
    global $DB;
    $table = '{' . trim($table, '{}') . '}'; // Make sure this is valid table with correct prefix.
    $hashtablename = substr(md5($table), 0, 15);
    switch ($DB->get_dbfamily()) {
        case 'mysql':
            $columnssql = "SHOW COLUMNS FROM `{$table}`";
            $indexsql = "CREATE INDEX rb_cache_{$hashtablename}_%1\$s ON {$table} (%2\$s)";
            $indexlongsql = "CREATE INDEX rb_cache_{$hashtablename}_%1\$s ON {$table} (%2\$s(%3\$d))";
            $fieldname = 'field';

            // Find out if want some special db engine.
            $enginesql = $DB->get_dbengine() ? " ENGINE = " . $DB->get_dbengine() : '';

            // Do we know collation?
            $collation = $DB->get_dbcollation();
            $collationsql = '';
            if ($collation) {
                if (strpos($collation, 'utf8_') === 0) {
                    $collationsql .= " DEFAULT CHARACTER SET utf8";
                }
                $collationsql .= " DEFAULT COLLATE = {$collation}";
            }

            $sql = "CREATE TABLE `{$table}` $enginesql $collationsql $select";
            $result = $DB->execute($sql, $params);
            break;
        case 'mssql':
            $viewname = 'tmp_'.$hashtablename;
            $viewsql = "CREATE VIEW $viewname AS $select";
            $DB->execute($viewsql, $params);

            $sql = "SELECT * INTO {$table} FROM $viewname";
            $result = $DB->execute($sql);

            $removeviewsql = "DROP VIEW $viewname";
            $DB->execute($removeviewsql);

            $columnssql = "SELECT sc.name, sc.system_type_id, sc.max_length, st.name as field_type FROM sys.columns sc
                    LEFT JOIN sys.types st ON (st.system_type_id = sc.system_type_id
                        AND st.name <> 'sysname' AND st.name <> 'geometry' AND st.name <> 'hierarchyid')
                    WHERE sc.object_id = OBJECT_ID('{$table}')";
            $indexsql = "CREATE INDEX rb_cache_{$hashtablename}_%1\$s ON {$table} (%2\$s)";
            $fieldname = 'name';
            break;
        case 'postgres':
        default:
            $sql = "CREATE TABLE \"{$table}\" AS $select";
            $columnssql = "SELECT column_name, data_type FROM information_schema.columns WHERE table_name ='{$table}'";
            $indexsql = "CREATE INDEX rb_cache_{$hashtablename}_%1\$s ON {$table} (%2\$s)";
            $fieldname = 'column_name';
            $result = $DB->execute($sql, $params);
            break;
    }
    $DB->reset_caches();

    if (!$result) {
        return false;
    }

    // Create indexes
    $fields = $DB->get_records_sql($columnssql);
    foreach ($fields as $field) {
        $hashfieldname = substr(md5($field->$fieldname), 0, 15);
        $sql = sprintf($indexsql, $hashfieldname, $field->$fieldname);

        // db engines specifics
        switch ($DB->get_dbfamily()) {
            case 'mysql':
                // Do not index fields with size 0
                if (strpos($field->type, '(0)') !== false) {
                    continue 2;
                }
                if (strpos($field->type, 'blob') !== false || strpos($field->type, 'text') !== false) {
                    // Index only first 255 symbols (mysql maximum = 767)
                    $sql = sprintf($indexlongsql, $hashfieldname, $field->$fieldname, 255);
                }
            break;
            case 'mssql':
                if ($field->field_type == 'image' || $field->field_type == 'binary') { // image
                    continue;
                }
                if ($field->field_type == 'text' || $field->field_type == 'ntext'
                        || ($field->field_type == 'nvarchar' && ($field->max_length == -1 || $field->max_length > 450))) {
                    $altersql = "ALTER TABLE {$table} ALTER COLUMN {$field->name} NVARCHAR(450)"; //Maximum index size = 900 bytes or 450 unicode chars
                    try {
                        // Attempt to convert field to indexable
                        $DB->execute($altersql);
                    } catch (dml_write_exception $e) {
                        // Recoverable exception
                        // Field has data longer than maximum index, proceed unindexed
                        continue;
                    }
                }
            break;
            case 'postgres':
                if ($field->data_type == 'unknown') {
                    $altersql = "ALTER TABLE {$table} ALTER COLUMN {$field->column_name} type varchar(255)";
                    $DB->execute($altersql);
                }
            break;
        }
        $DB->execute($sql);
    }
    $DB->reset_caches();

    return true;
}

/**
 * Returns the proper SQL to aggregate a field by joining with a specified delimiter
 *
 *
 */
function sql_group_concat($field, $delimiter=', ', $unique=false) {
    global $DB;

    // if not supported, just return single value - use min()
    $sql = " MIN($field) ";

    switch ($DB->get_dbfamily()) {
        case 'mysql':
            // use native function
            $distinct = $unique ? 'DISTINCT' : '';
            $sql = " GROUP_CONCAT($distinct $field SEPARATOR '$delimiter') ";
            break;
        case 'postgres':
            // use custom aggregate function - must have been defined
            // in db/upgrade.php
            $distinct = $unique ? 'TRUE' : 'FALSE';
            $sql = " GROUP_CONCAT($field, '$delimiter', $distinct) ";
            break;
        case 'mssql':
            $distinct = $unique ? 'DISTINCT' : '';
            $sql = " dbo.GROUP_CONCAT_D($distinct $field, '$delimiter') ";
        break;
    }

    return $sql;
}

/**
 * Schedule reporting cache
 *
 * @global object $DB
 * @param int $reportid report id
 * @param array|stdClass $form data from form element
 * @return type
 */
function reportbuilder_schedule_cache($reportid, $form = array()) {
    global $DB;
    if (is_object($form)) {
        $form = (array)$form;
    }
    $cache = $DB->get_record('report_builder_cache', array('reportid' => $reportid), '*', IGNORE_MISSING);
    if (!$cache) {
        $cache = new stdClass();
    }
    $cache->reportid = $reportid;
    $schedule = new scheduler($cache, array('nextevent' => 'nextreport'));
    $schedule->from_array($form);

    if (!isset($cache->id)) {
        $result = $DB->insert_record('report_builder_cache', $cache);
    } else {
        $result = $DB->update_record('report_builder_cache', $cache);
    }
    return $result;
}

/**
 * Shift next scheduled execution if report was generated after scheduled time
 *
 * @param int $reportid Report id
 * @return boolean is operation success
 */
function reportbuilder_fix_schedule($reportid) {
    global $DB;

    $cache = $DB->get_record('report_builder_cache', array('reportid' => $reportid), '*', IGNORE_MISSING);
    if (!$cache) {
        var_dump("cache not found");
        return false;
    }

    $schedule = new scheduler($cache, array('nextevent' => 'nextreport'));
    if ($schedule->get_scheduled_time() < $cache->lastreport) {
        $schedule->next();
    }

    if ($schedule->is_changed()) {
        $DB->update_record('report_builder_cache', $cache);
    }
    return true;
}

/**
 * Returns reports that the current user can view
 *
 * @param boolean showhidden If true include hidden reports
 *
 * @return array Array of report records
 */
function reportbuilder_get_reports($showhidden=false) {
    global $reportbuilder_permittedreports;
    if (!isset($reportbuilder_permittedreports) || !is_array($reportbuilder_permittedreports)) {
        $reportbuilder_permittedreports = reportbuilder::get_permitted_reports(null,$showhidden);
    }
    return $reportbuilder_permittedreports;
}

/**
 * Purge cache to force report update during next load
 *
 * @param int|object $cache either data from rb cache table or report id
 * @param bool $unschedule If true drops scheduling as well
 */
function reportbuilder_purge_cache($cache, $unschedule = false) {
    global $DB;
    if (is_number($cache)) {
        $cache = $DB->get_record('report_builder_cache', array('reportid' => $cache));
    }
    if (!is_object($cache) || !isset($cache->reportid)) {
        error_log(get_string('error:cachenotfound', 'totara_reportbuilder'));
        return false;
    }
    if ($cache->cachetable) {
        sql_drop_table_if_exists($cache->cachetable);
    }
    if ($unschedule) {
        $DB->delete_records('report_builder_cache', array('reportid' => $cache->reportid));
        $DB->set_field('report_builder', 'cache', 0, array('id' => $cache->reportid));
    } else {
        $cache->cachetable = null;
        $cache->queryhash = null;
        $DB->update_record('report_builder_cache', $cache);
    }
}

/**
 * Purge all caches for report builder
 *
 * @param bool $unschedule Turn off caching after purge for all reports
 */
function reportbuilder_purge_all_cache($unschedule = false) {
    global $DB;
    try {
        $caches = $DB->get_records('report_builder_cache');
        foreach ($caches as $cache) {
            reportbuilder_purge_cache($cache, $unschedule);
        }
    } catch (dml_exception $e) {
        // This error is possible during installation process
        return;
    }
}


/**
 * Set flag to report that it is changed and cache settings are out of date or fail
 *
 * @param mixed stdClass|int $report Report id or report_builder_cache record object
 * @param int $flag Change flag - just changed or fail
 * @return bool result
 */
function reportbuilder_set_status($reportcache, $flag = RB_CACHE_FLAG_CHANGED) {
    global $DB;
    $reportid = 0;
    if (is_object($reportcache)) {
        $reportid = $reportcache->reportid;
        $reportcache->changed = $flag;
    } else if (is_numeric($reportcache)) {
        $reportid = $reportcache;
    }
    if (!$reportid) return false;

    $sql = 'UPDATE {report_builder_cache} SET changed = ? WHERE reportid = ?';
    $result = $DB->execute($sql, array($flag, $reportid));
    return $result;
}

/**
 * Report cache (re-)generation
 *
 * @int $reportid Report id
 * @return bool Is cache generated
 */
function reportbuilder_generate_cache($reportid) {
    global $DB;

    $success = false;
    $dbman = $DB->get_manager();

    $rawreport = $DB->get_record('report_builder', array('id' => $reportid), '*', MUST_EXIST);

    // Prepare record for cache
    $rbcache = $DB->get_record('report_builder_cache', array('reportid' => $reportid), '*', IGNORE_MISSING);
    if (!$rbcache) {
        $cache = new stdClass();
        $cache->reportid = $reportid;
        $cache->frequency = 0;
        $cache->schedule = 0;
        $cache->changed = 0;
        $cache->genstart = 0;
        $cache->id = $DB->insert_record('report_builder_cache', $cache);
        $rbcache = $DB->get_record('report_builder_cache', array('reportid' => $reportid), '*', MUST_EXIST);
    }

    $date = date("YmdHis");
    $newtable = "{report_builder_cache_{$reportid}_{$date}}";

    // Purge old data and mark as started.
    $oldtable = $rbcache->cachetable;
    $rbcache->cachetable = $newtable;
    $rbcache->genstart = time();
    $rbcache->queryhash = null;
    $DB->update_record('report_builder_cache', $rbcache);

    if ($oldtable) {
        sql_drop_table_if_exists($oldtable);
    }

    try {
        // Instantiate.
        if ($rawreport->embedded) {
            $report = reportbuilder_get_embedded_report($rawreport->shortname, array(), true, 0);
        } else {
            $report = new reportbuilder($reportid, null, false, null, null, true);
        }

        // Get caching query.
        list($query, $params) = $report->build_create_cache_query();
        $queryhash = sha1($query.serialize($params));

        $result = sql_table_from_select($newtable, $query, $params);

        if ($result) {
            $rbcache->lastreport = time();
            $rbcache->queryhash = $queryhash;
            $rbcache->changed = 0;
            $rbcache->genstart = 0;
            $DB->update_record('report_builder_cache', $rbcache);
            $success = true;
        }
    } catch (dml_exception $e) {
        debugging('Problem creating cache table '.$e->getMessage());
    }

    if (!$success) {
        // Clean up.
        sql_drop_table_if_exists($rbcache->cachetable);

        $rbcache->cachetable = null;
        $rbcache->genstart = 0;
        $rbcache->changed = RB_CACHE_FLAG_FAIL;
        $DB->update_record('report_builder_cache', $rbcache);
    }

    return $success;
}

/**
 *  Send Scheduled report to a user
 *
 *  @param object $sched Object containing data from schedule table
 *
 *  @return boolean True if email was successfully sent
 */
function reportbuilder_send_scheduled_report($sched) {
    global $CFG, $DB, $REPORT_BUILDER_EXPORT_OPTIONS;
    $export_codes = array_flip($REPORT_BUILDER_EXPORT_OPTIONS);

    if (!$user = $DB->get_record('user', array('id' => $sched->userid))) {
        error_log(get_string('error:invaliduserid', 'totara_reportbuilder'));
        return false;
    }

    if (!$report = $DB->get_record('report_builder', array('id' => $sched->reportid))) {
        error_log(get_string('error:invalidreportid', 'totara_reportbuilder'));
        return false;
    }

    // don't send the report if the user doesn't have permission
    // to view it
    if (!reportbuilder::is_capable($sched->reportid, $sched->userid)) {
        error_log(get_string('error:nopermissionsforscheduledreport', 'totara_reportbuilder', $sched));
        return false;
    }

    $attachment = reportbuilder_create_attachment($sched, $user->id);

    switch($sched->format) {
        case REPORT_BUILDER_EXPORT_EXCEL:
            $attachmentfilename = 'report.xlsx';
            break;
        case REPORT_BUILDER_EXPORT_CSV:
            $attachmentfilename = 'report.csv';
            break;
        case REPORT_BUILDER_EXPORT_ODS:
            $attachmentfilename = 'report.ods';
            break;
        case REPORT_BUILDER_EXPORT_PDF_LANDSCAPE:
        case REPORT_BUILDER_EXPORT_PDF_PORTRAIT:
            $attachmentfilename = 'report.pdf';
            break;
    }

    $reporturl = reportbuilder_get_report_url($report);
    if ($sched->savedsearchid != 0) {
        $reporturl .= '&sid=' . $sched->savedsearchid;
    }
    $strmgr = get_string_manager();
    $messagedetails = new stdClass();
    $messagedetails->reportname = $report->fullname;
    $messagedetails->exporttype = $strmgr->get_string($export_codes[$sched->format] . 'format', 'totara_reportbuilder', null, $user->lang);
    $messagedetails->reporturl = $reporturl;
    $messagedetails->scheduledreportsindex = $CFG->wwwroot . '/my/reports.php#scheduled';

    $schedule = new scheduler($sched, array('nextevent' => 'nextreport'));
    $messagedetails->schedule = $schedule->get_formatted($user);

    $subject = $report->fullname . ' ' . $strmgr->get_string('report', 'totara_reportbuilder', null, $user->lang);

    if ($sched->savedsearchid != 0) {
        if (!$savename = $DB->get_field('report_builder_saved', 'name', array('id' => $sched->savedsearchid))) {
            mtrace(get_string('error:invalidsavedsearchid', 'totara_reportbuilder'));
        } else {
            $messagedetails->savedtext = $strmgr->get_string('savedsearchmessage', 'totara_reportbuilder', $savename, $user->lang);
        }
    } else {
        $messagedetails->savedtext = '';
    }

    $message = $strmgr->get_string('scheduledreportmessage', 'totara_reportbuilder', $messagedetails, $user->lang);

    $fromaddress = core_user::get_noreply_user();
    $emailed = false;

    if ($sched->exporttofilesystem != REPORT_BUILDER_EXPORT_SAVE) {
        $emailed = email_to_user($user, $fromaddress, $subject, $message, '', $attachment, $attachmentfilename);
    }

    if (!unlink($CFG->dataroot . DIRECTORY_SEPARATOR . $attachment)) {
        mtrace(get_string('error:failedtoremovetempfile', 'totara_reportbuilder'));
    }

    return $emailed;
}

/**
 * Creates an export of a report in specified format (xls, csv or ods)
 * for adding to email as attachment
 *
 * @param stdClass $sched schedule record
 * @param integer $userid ID of the user the report is for
 *
 * @return string Filename of the created attachment
 */
function reportbuilder_create_attachment($sched, $userid) {
    global $CFG;

    $reportid = $sched->reportid;
    $format = $sched->format;
    $exporttofilesystem = $sched->exporttofilesystem;
    $sid = $sched->savedsearchid;
    $scheduleid = $sched->id;

    $report = new reportbuilder($reportid, null, false, $sid, $userid, false, array('userid' => $userid));
    $columns = $report->columns;
    $count = $report->get_filtered_count();
    list($sql, $params) = $report->build_query(false, true);

    // array of filters that have been applied
    // for including in report where possible
    $restrictions = $report->get_restriction_descriptions();

    $headings = array();
    foreach ($columns as $column) {
        // check that column should be included
        if ($column->display_column(true)) {
            $headings[] = $column;
        }
    }
    $tempfilename = md5(time());
    $tempfilepathname = $CFG->dataroot . DIRECTORY_SEPARATOR . $tempfilename;

    switch ($format) {
        case REPORT_BUILDER_EXPORT_ODS:
            $filename = $report->download_ods($headings, $sql, $params, $count, $restrictions, $tempfilepathname);
            if ($exporttofilesystem != REPORT_BUILDER_EXPORT_EMAIL) {
                $reportfilepathname = reportbuilder_get_export_filename($report, $userid, $scheduleid) . '.ods';
                $filename = $report->download_ods($headings, $sql, $params, $count, $restrictions, $reportfilepathname);
            }
            break;
        case REPORT_BUILDER_EXPORT_EXCEL:
            $filename = $report->download_xls($headings, $sql, $params, $count, $restrictions, $tempfilepathname);
            if ($exporttofilesystem != REPORT_BUILDER_EXPORT_EMAIL) {
                $reportfilepathname = reportbuilder_get_export_filename($report, $userid, $scheduleid) . '.xlsx';
                $filename = $report->download_xls($headings, $sql, $params, $count, $restrictions, $reportfilepathname);
            }
            break;
        case REPORT_BUILDER_EXPORT_CSV:
            $filename = $report->download_csv($headings, $sql, $params, $count, $tempfilepathname);
            if ($exporttofilesystem != REPORT_BUILDER_EXPORT_EMAIL) {
                $reportfilepathname = reportbuilder_get_export_filename($report, $userid, $scheduleid) . '.csv';
                $filename = $report->download_csv($headings, $sql, $params, $count, $reportfilepathname);
            }
            break;
        case REPORT_BUILDER_EXPORT_PDF_PORTRAIT:
            $filename = $report->download_pdf($headings, $sql, $params, $count, $restrictions, true, $tempfilepathname);
            if ($exporttofilesystem != REPORT_BUILDER_EXPORT_EMAIL) {
                $reportfilepathname = reportbuilder_get_export_filename($report, $userid, $scheduleid) . '.pdf';
                $filename = $report->download_pdf($headings, $sql, $params, $count, $restrictions, true, $reportfilepathname);
            }
            break;
        case REPORT_BUILDER_EXPORT_PDF_LANDSCAPE:
            $filename = $report->download_pdf($headings, $sql, $params, $count, $restrictions, false, $tempfilepathname);
            if ($exporttofilesystem != REPORT_BUILDER_EXPORT_EMAIL) {
                $reportfilepathname = reportbuilder_get_export_filename($report, $userid, $scheduleid) . '.pdf';
                $filename = $report->download_pdf($headings, $sql, $params, $count, $restrictions, false, $reportfilepathname);
            }
            break;
    }

    return $tempfilename;
}

/**
 * Checks if username directory under given path exists
 * If it does not it creates it and returns fullpath with filename
 * userdir + report fullname + time created + schedule id
 *
 * @param record $report
 * @param int $userid
 * @param int $scheduleid
 *
 * @return string reportfullpathname
 */
function reportbuilder_get_export_filename($report, $userid, $scheduleid) {
    global $DB;
    $reportfilename = format_string($report->fullname) . '_' .
            userdate(time(), get_string('datepickerlongyearphpuserdate', 'totara_core')) . '_' . $scheduleid;
    $reportfilename = clean_param($reportfilename, PARAM_FILE);
    $username = $DB->get_field('user', 'username', array('id' => $userid));

    // Validate directory.
    $path = get_config('reportbuilder', 'exporttofilesystempath');
    if (!empty($path)) {
        // Check path format.
        if (DIRECTORY_SEPARATOR == '\\') {
            $pattern = '/[^a-zA-Z0-9\/_\\\\\\:-]/i';
        } else {
            $pattern = '/[^a-zA-Z0-9\/_-]/i';
        }
        if (preg_match($pattern, $path)) {
            mtrace(get_string('error:notapathexportfilesystempath', 'totara_reportbuilder'));
        } else if (!is_dir($path)) {
            mtrace(get_string('error:notdirexportfilesystempath', 'totara_reportbuilder'));
        } else if (!is_writable($path)) {
            mtrace(get_string('error:notwriteableexportfilesystempath', 'totara_reportbuilder'));
        }
    }

    $dir = $path . DIRECTORY_SEPARATOR . $username;
    if (!is_directory_a_preset($dir) && !file_exists($dir)) {
        mkdir($dir);
    }
    $reportfilepathname = $dir . DIRECTORY_SEPARATOR . $reportfilename;

    return $reportfilepathname;
}

/**
 * Given a report database record, return the URL to the report
 *
 * For use when a reportbuilder object is not available. If a reportbuilder
 * object is being used, call {@link reportbuilder->report_url()} instead
 *
 * @param object $report Report builder database object. Must contain id, shortname and embedded parameters
 *
 * @return string URL of the report provided or false
 */
function reportbuilder_get_report_url($report) {
    global $CFG;
    if ($report->embedded == 0) {
        return $CFG->wwwroot . '/totara/reportbuilder/report.php?id=' . $report->id;
    } else {
        // use report shortname to find appropriate embedded report object
        if ($embed = reportbuilder_get_embedded_report_object($report->shortname)) {
            return $CFG->wwwroot . $embed->url;
        } else {
            return $CFG->wwwroot;
        }
    }

}

/**
 * Generate object used to describe an embedded report
 *
 * This method returns a new instance of an embedded report object
 * Given an embedded report name, it finds the class, includes it then
 * calls the class passing in any data provided. The object created
 * by that call is returned, or false if something went wrong.
 *
 * @param string $embedname Shortname of embedded report
 *                          e.g. X from rb_X_embedded.php
 * @param array $data Associative array of data needed by source (optional)
 *
 * @return object Embedded report object
 */
function reportbuilder_get_embedded_report_object($embedname, $data=array()) {
    global $CFG;

    $sourcepaths = reportbuilder::find_source_dirs();
    $sourcepaths[] = $CFG->dirroot . '/totara/reportbuilder/embedded/';

    foreach ($sourcepaths as $sourcepath) {
        $classfile = $sourcepath . 'rb_' . $embedname . '_embedded.php';
        if (is_readable($classfile)) {
            include_once($classfile);
            $classname = 'rb_' . $embedname . '_embedded';
            if (class_exists($classname)) {
                return new $classname($data);
            }
        }
    }
    // file or class not found
    return false;
}


/**
 * Generate actual embedded report
 *
 * This function is an alias to "new reportbuilder()", for use within embedded report pages. The embedded object
 * will be created within the reportbuilder constructor.
 *
 * @param string $embedname Shortname of embedded report
 *                          e.g. X from rb_X_embedded.php
 * @param array $data Associative array of data needed by source (optional)
 * @param bool $nocache Disable cache
 * @param int $sid saved search id
 *
 * @return reportbuilder Embedded report
 */
function reportbuilder_get_embedded_report($embedname, $data = array(), $nocache = false, $sid = 'nosidsupplied') {
    if ($sid === 'nosidsupplied') {
        debugging('Call to reportbuilder_get_embedded_report without supplying $sid is probably an error - if you
            want to save searches on your embedded report then you must pass in $sid here, otherwise pass 0 to remove
            this warning', DEBUG_DEVELOPER);
        $sid = 0;
    }
    return new reportbuilder(null, $embedname, false, $sid, null, $nocache, $data);
}


/**
 * Returns an array of all embedded reports found in the filesystem, sorted by name
 *
 * Looks in the totara/reportbuilder/embedded/ directory and creates a new
 * object for each embedded report definition found. These are returned
 * as an array, sorted by the report fullname
 *
 * @return array Array of embedded report objects
 */
function reportbuilder_get_all_embedded_reports() {
    global $CFG;

    $embedded = array();
    $sourcepaths = reportbuilder::find_source_dirs();
    $sourcepaths[] = $CFG->dirroot . '/totara/reportbuilder/embedded/';
    foreach ($sourcepaths as $sourcepath) {
        if ($dh = opendir($sourcepath)) {
            while(($file = readdir($dh)) !== false) {
                if (is_dir($file) ||
                    !preg_match('|^rb_(.*)_embedded\.php$|', $file, $matches)) {
                        continue;
                    }
                $name = $matches[1];
                $embed = false;
                if ($embed = reportbuilder_get_embedded_report_object($name)) {
                    $embedded[] = $embed;
                }
            }
        }
    }
    // sort by fullname before returning
    usort($embedded, 'reportbuilder_sortbyfullname');
    return $embedded;
}

/**
 * Return object with cached record for report or false if not found
 *
 * @param int $reportid
 */
function reportbuilder_get_cached($reportid) {
    global $DB;
    $sql = "SELECT rbc.*, rb.cache, rb.fullname, rb.shortname, rb.embedded
            FROM {report_builder} rb
            LEFT JOIN {report_builder_cache} rbc ON rbc.reportid = rb.id
            WHERE rb.cache = 1
              AND rb.id = ?";
    return $DB->get_record_sql($sql, array($reportid));
}

/**
 * Get all reports with enabled caching
 *
 * @return array of stdClass
 */
function reportbuilder_get_all_cached() {
    global $DB, $CFG;
    if (!$CFG->enablereportcaching) {
        return array();
    }
    $sql = "SELECT rbc.*, rb.cache, rb.fullname, rb.shortname, rb.embedded
            FROM {report_builder} rb
            LEFT JOIN {report_builder_cache} rbc
                ON rb.id = rbc.reportid
            WHERE rb.cache = 1";
    $caches = $DB->get_records_sql($sql);
    $result = array();
    foreach ($caches as $c) {
        $result[$c->reportid] = $c;
    }
    return $result;
}
/**
 * Function for sorting by report fullname, used in usort as callback
 *
 * @param object $a The first array element
 * @param object $a The second array element
 *
 * @return integer 1, 0, or -1 depending on sort order
 */
function reportbuilder_sortbyfullname($a, $b) {
    return strcmp($a->fullname, $b->fullname);
}


/**
 * Returns the ID of an embedded report from its shortname, creating if necessary
 *
 * To save on db calls, you need to pass an array of the existing embedded
 * reports to this method, in the format key=id, value=shortname.
 *
 * If the shortname doesn't exist in the array provided this method will
 * create a new embedded report and return the new ID generated or false
 * on failure
 *
 * @param string $shortname The shortname you need the ID of
 * @param array $embedded_ids Array of embedded report IDs and shortnames
 *
 * @return integer ID of the requested embedded report
 */
function reportbuilder_get_embedded_id_from_shortname($shortname, $embedded_ids) {
    // return existing ID if a database record exists already
    if (is_array($embedded_ids)) {
        foreach ($embedded_ids as $id => $embed_shortname) {
            if ($shortname == $embed_shortname) {
                return $id;
            }
        }
    }
    // otherwise, create a new embedded report and return the new ID
    // returns false if creation fails
    $embed = reportbuilder_get_embedded_report_object($shortname);
    $error = null;
    return reportbuilder_create_embedded_record($shortname, $embed, $error);
}


/**
 * Creates a database entry for an embedded report when it is first viewed
 * so the settings can be edited
 *
 * @param string $shortname The unique name for this embedded report
 * @param object $embed An object containing the embedded reports settings
 * @param string &$error Error string to return on failure
 *
 * @return boolean ID of new database record, or false on failure
 */
function reportbuilder_create_embedded_record($shortname, $embed, &$error) {
    global $DB;
    $error = null;

    // check input
    if (!isset($shortname)) {
        $error = 'Bad shortname';
        return false;
    }
    if (!isset($embed->source)) {
        $error = 'Bad source';
        return false;
    }
    if (!isset($embed->filters) || !is_array($embed->filters)) {
        $embed->filters = array();
    }
    if (!isset($embed->columns) || !is_array($embed->columns)) {
        $error = 'Bad columns';
        return false;
    }
    if (!isset($embed->toolbarsearchcolumns) || !is_array($embed->toolbarsearchcolumns)) {
        $embed->toolbarsearchcolumns = array();
    }
    // hide embedded reports from report manager by default
    $embed->hidden = isset($embed->hidden) ? $embed->hidden : 1;
    $embed->accessmode = isset($embed->accessmode) ? $embed->accessmode : 0;
    $embed->contentmode = isset($embed->contentmode) ? $embed->contentmode : 0;

    $embed->accesssettings = isset($embed->accesssettings) ? $embed->accesssettings : array();
    $embed->contentsettings = isset($embed->contentsettings) ? $embed->contentsettings : array();

    $embed->defaultsortcolumn = isset($embed->defaultsortcolumn) ? $embed->defaultsortcolumn : '';
    $embed->defaultsortorder = isset($embed->defaultsortorder) ? $embed->defaultsortorder : SORT_ASC;

    $todb = new stdClass();
    $todb->shortname = $shortname;
    $todb->fullname = $embed->fullname;
    $todb->source = $embed->source;
    $todb->hidden = 1; // hide embedded reports by default
    $todb->accessmode = $embed->accessmode;
    $todb->contentmode = $embed->contentmode;
    $todb->embedded = 1;
    $todb->defaultsortcolumn = $embed->defaultsortcolumn;
    $todb->defaultsortorder = $embed->defaultsortorder;

    try {
        $transaction = $DB->start_delegated_transaction();

        $newid = $DB->insert_record('report_builder', $todb);
        // Add columns.
        $so = 1;
        foreach ($embed->columns as $column) {
            $todb = new stdClass();
            $todb->reportid = $newid;
            $todb->type = $column['type'];
            $todb->value = $column['value'];
            $todb->heading = $column['heading'];
            $todb->sortorder = $so;
            $todb->customheading = 0; // Initially no columns are customised.
            $todb->hidden = isset($column['hidden']) ? $column['hidden'] : 0;
            $DB->insert_record('report_builder_columns', $todb);
            $so++;
        }
        // Add filters.
        $so = 1;
        foreach ($embed->filters as $filter) {
            $todb = new stdClass();
            $todb->reportid = $newid;
            $todb->type = $filter['type'];
            $todb->value = $filter['value'];
            $todb->advanced = isset($filter['advanced']) ? $filter['advanced'] : 0;
            if (isset($filter['fieldname'])) {
                $todb->filtername = $filter['fieldname'];
                $todb->customname =  1;
            } else {
                $todb->filtername = '';
                $todb->customname =  0;
            }
            $todb->sortorder = $so;
            $todb->region = isset($filter['region']) ? $filter['region'] : rb_filter_type::RB_FILTER_REGION_STANDARD;
            $DB->insert_record('report_builder_filters', $todb);
            $so++;
        }
        // Add toolbar search columns.
        foreach ($embed->toolbarsearchcolumns as $toolbarsearchcolumn) {
            $todb = new stdClass();
            $todb->reportid = $newid;
            $todb->type = $toolbarsearchcolumn['type'];
            $todb->value = $toolbarsearchcolumn['value'];
            $DB->insert_record('report_builder_search_cols', $todb);
        }
        // Add content restrictions.
        foreach ($embed->contentsettings as $option => $settings) {
            $classname = $option . '_content';
            if (class_exists('rb_' . $classname)) {
                foreach ($settings as $name => $value) {
                    if (!reportbuilder::update_setting($newid, $classname, $name,
                        $value)) {
                            throw new moodle_exception('Error inserting content restrictions');
                        }
                }
            }
        }
        // add access restrictions
        foreach ($embed->accesssettings as $option => $settings) {
            $classname = $option . '_access';
            if (class_exists($classname)) {
                foreach ($settings as $name => $value) {
                    if (!reportbuilder::update_setting($newid, $classname, $name,
                        $value)) {
                            throw new moodle_exception('Error inserting access restrictions');
                        }
                }
            }
        }
        $report = new reportbuilder($newid);
        \totara_reportbuilder\event\report_created::create_from_report($report, true)->trigger();

        $transaction->allow_commit();
    } catch (Exception $e) {
        $transaction->rollback($e);
        $error = $e->getMessage();
        return false;
    }

    return $newid;
}


/**
 * Attempt to ensure an SQL named param is unique by appending a random number value
 * and keeping records of other param names
 *
 * @param string $name the param name to make unique
 * @return string the unique string
 */
function rb_unique_param($name) {
    static $UNIQUE_PARAMS = array();

    $param = $name . uniqid();

    while (in_array($param, $UNIQUE_PARAMS)) {
        $param = $name . uniqid();
    }

    $UNIQUE_PARAMS[] = $param;

    return $param;
}

/**
 * Helper function for renaming the data in the columns/filters table
 *
 * Useful when a field is renamed and the report data needs to be updated
 *
 * @param string $table Table to update, either 'filters' or 'columns'
 * @param string $source Name of the source or '*' to update all sources
 * @param string $oldtype The type of the item to change
 * @param string $oldvalue The value of the item to change
 * @param string $newtype The new type of the item
 * @param string $newvalue The new value of the item
 *
 * @return boolean Result from the update query or true if no data to update
 */
function reportbuilder_rename_data($table, $source, $oldtype, $oldvalue, $newtype, $newvalue) {
    global $DB;

    if ($source == '*') {
        $sourcesql = '';
        $params = array();
    } else {
        $sourcesql = ' AND rb.source = :source';
        $params = array('source' => $source);
    }

    $sql = "SELECT rbt.id FROM {report_builder_{$table}} rbt
        JOIN {report_builder} rb
        ON rbt.reportid = rb.id
        WHERE rbt.type = :oldtype AND rbt.value = :oldvalue
        $sourcesql";
    $params['oldtype'] = $oldtype;
    $params['oldvalue'] = $oldvalue;

    $items = $DB->get_fieldset_sql($sql, $params);

    if (!empty($items)) {
        list($insql, $params) = $DB->get_in_or_equal($items, SQL_PARAMS_NAMED);
        $sql = "UPDATE {report_builder_{$table}}
            SET type = :newtype, value = :newvalue
            WHERE id $insql";
        $params['newtype'] = $newtype;
        $params['newvalue'] = $newvalue;
        $DB->execute($sql, $params);
    }
    return true;
}

/**
 * Returns available export options for reportbuilder.
 *
 * @return array (option => string name)
 */
function reportbuilder_get_export_options() {
    global $REPORT_BUILDER_EXPORT_OPTIONS;
    $exportoptions = get_config('reportbuilder', 'exportoptions');
    $options = !empty($exportoptions) ? explode(',', $exportoptions) : array();

    $alloptions = array_flip($REPORT_BUILDER_EXPORT_OPTIONS);

    $select = array();
    foreach ($options as $key => $value) {
        $select[$value] = get_string('export' . $alloptions[$value], 'totara_reportbuilder');
    }

    return $select;
}

/**
* Serves reportbuilder file type files. Required for M2 File API
*
* @param object $course
* @param object $cm
* @param object $context
* @param string $filearea
* @param array $args
* @param bool $forcedownload
* @param array $options
* @return bool false if file not found, does not return if found - just send the file
*/
function totara_reportbuilder_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options=array()) {
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/totara_reportbuilder/$filearea/$args[0]/$args[1]";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }
    // finally send the file
    send_stored_file($file, 86400, 0, true, $options); // download MUST be forced - security!
}

/**
 * Get extrafield alias.
 * Hash type and value so it works when caching reports in MySQL
 * (current restriction in MySQL: fieldname cannot be longer than 64 chars)
 *
 * @param string $type column type of this option in the report
 * @param string $value column value of this option in the report
 * @param string $name the field name
 * @return string $extrafieldalias
 */
function reportbuilder_get_extrafield_alias($type, $value, $name) {
    $typevalue = "{$type}_{$value}";
    $hashtypevalue = substr(md5($typevalue), 0, 10);
    $extrafieldalias = "ef_{$hashtypevalue}_{$name}";

    return $extrafieldalias;
}

/**
 * Day/month picker admin setting for report builder settings.
 *
 */
class admin_setting_configdaymonthpicker extends admin_setting {
    /**
     * Constructor
     * @param string $name unique ascii name, either 'mysetting' for settings that in config,
     *                     or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised name
     * @param string $description localised long description
     * @param mixed $defaultsetting string or array depending on implementation
     */
    public function __construct($name, $visiblename, $description, $defaultsetting) {
        parent::__construct($name, $visiblename, $description, $defaultsetting);
    }

    /**
     * Gets the current settings as an array
     *
     * @return mixed Null if none, else array of settings
     */
    public function get_setting() {
        $result = $this->config_read($this->name);
        if (is_null($result)) {
            return null;
        }

        return $result;
    }

    /**
     * Store the data as ddmm string.
     *
     * @param string $data
     * @return bool true if success, false if not
     */
    public function write_setting($data) {
        if (!is_array($data)) {
            return '';
        }
        $result = $this->config_write($this->name, date("dm", mktime(0, 0, 0, $data['m'], $data['d'], 0)));

        return ($result ? '' : get_string('errorsetting', 'admin'));
    }

    /**
     * Returns day/month select+select fields.
     *
     * @param string $data
     * @param string $query
     * @return string html select+select fields and wrapping div(s)
     */
    public function output_html($data, $query='') {
        // Default settings.
        $default = $this->get_defaultsetting();

        if (is_array($default)) {
            $defaultday = $default['d'];
            $defaultmonth = $default['m'];
            $defaultinfo = date('j F', mktime(0, 0, 0, $defaultmonth, $defaultday, 0));
        } else {
            $defaultinfo = null;
        }

        // Saved settings.
        $day = substr($data, 0, 2);
        $month = substr($data, 2, 2);

        $days = array_combine(range(1,31), range(1,31));
        $months = array();
        for ($i = 1; $i <= 12; $i++) {
            $mname = date("F", mktime(0, 0, 0, $i, 10));
            $months[$i] = $mname;
        }

        $return = html_writer::start_tag('div', array('class' => 'form-daymonth defaultsnext'));
        $return .= html_writer::select($days, $this->get_full_name() . '[d]' , (int)$day);
        $return .= html_writer::select($months, $this->get_full_name() . '[m]', (int)$month);
        $return .= html_writer::end_tag('div');

        return format_admin_setting($this, $this->visiblename, $return, $this->description, false, '', $defaultinfo, $query);
    }
}

