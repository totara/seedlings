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
 * @subpackage reportbuilder
 */

require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * Abstract base class to be extended to create report builder sources
 */
abstract class rb_base_source {

    /*
     * Used in default pre_display_actions function.
     */
    public $needsredirect, $redirecturl, $redirectmessage;


/**
 * Class constructor
 *
 * Call from the constructor of all child classes with:
 *
 *  parent::__construct()
 *
 * to ensure child class has implemented everything necessary to work.
 *
 */
    function __construct() {
        // check that child classes implement required properties
        $properties = array(
            'base',
            'joinlist',
            'columnoptions',
            'filteroptions',
        );
        foreach ($properties as $property) {
            if (!property_exists($this, $property)) {
                $a = new stdClass();
                $a->property = $property;
                $a->class = get_class($this);
                throw new ReportBuilderException(get_string('error:propertyxmustbesetiny', 'totara_reportbuilder', $a));
            }
        }

        // set sensible defaults for optional properties
        $defaults = array(
            'paramoptions' => array(),
            'requiredcolumns' => array(),
            'contentoptions' => array(),
            'preproc' => null,
            'grouptype' => 'none',
            'groupid' => null,
            'selectable' => true,
            'scheduleable' => true,
            'cacheable' => true,
            'hierarchymap' => array()
        );
        foreach ($defaults as $property => $default) {
            if (!property_exists($this, $property)) {
                $this->$property = $default;
            } else if ($this->$property === null) {
                $this->$property = $default;
            }
        }

        // basic sanity checking of joinlist
        $this->validate_joinlist();
        //create array to store the join functions and join table
        $joindata = array();
        $base = $this->base;
        //if any of the join tables are customfield-related, ensure the customfields are added
        foreach ($this->joinlist as $join) {
            //tables can be joined multiple times so we set elements of an associative array as joinfunction => jointable
            $table = $join->table;
            switch ($table) {
                case '{user}':
                    $joindata['add_custom_user_fields'] = 'auser';
                    break;
                case '{course}':
                    $joindata['add_custom_course_fields'] = 'course';
                    break;
                case '{org}':
                    $joindata['add_custom_organisation_fields'] = 'org';
                    break;
                case '{pos}':
                    $joindata['add_custom_position_fields'] = 'pos';
                    break;
                case '{comp}':
                    $joindata['add_custom_competency_fields'] = 'comp';
                    break;
                case '{goal}':
                    $joindata['add_custom_goal_fields'] = 'goal';
                    break;
            }
        }
        //now ensure customfields fields are added if there are no joins but the base table is customfield-related
        switch ($base) {
            case '{user}':
                $joindata['add_custom_user_fields'] = 'base';
                break;
            case '{course}':
                $joindata['add_custom_course_fields'] = 'base';
                break;
            case '{prog}':
                $joindata['add_custom_prog_fields'] = 'base';
                break;
            case '{org}':
                $joindata['add_custom_organisation_fields'] = 'base';
                break;
            case '{pos}':
                $joindata['add_custom_position_fields'] = 'base';
                break;
            case '{comp}':
                $joindata['add_custom_competency_fields'] = 'base';
                break;
            case '{goal}':
                $joindata['add_custom_goal_fields'] = 'base';
        }
        //and then use the flags to call the appropriate add functions
        foreach ($joindata as $joinfunction => $jointable) {
            $this->$joinfunction($this->joinlist,
                                 $this->columnoptions,
                                 $this->filteroptions,
                                 $jointable
                                );

        }

        //validate column extrafields don't have alias named 'id'
        foreach ($this->columnoptions as $columnoption) {
            if (isset($columnoption->extrafields) && is_array($columnoption->extrafields)) {
                foreach ($columnoption->extrafields as $extrakey => $extravalue) {
                    if ($extrakey == 'id') {
                        throw new ReportBuilderException(get_string('error:columnextranameid', 'totara_reportbuilder', $extravalue), 101);
                    }
                }
            }
        }
    }


    /**
     * Set redirect url and (optionally) message for use in default pre_display_actions function.
     *
     * When pre_display_actions is call it will redirect to the specified url (unless pre_display_actions
     * is overridden, in which case it performs those actions instead).
     *
     * @param mixed $url moodle_url or url string
     * @param string $message
     */
    protected function set_redirect($url, $message = null) {
        $this->redirecturl = $url;
        $this->redirectmessage = $message;
    }


    /**
     * Set whether redirect needs to happen in pre_display_actions.
     *
     * @param bool $truth true if redirect is needed
     */
    protected function needs_redirect($truth = true) {
        $this->needsredirect = $truth;
    }


    /**
     * Default pre_display_actions - if needsredirect is true then redirect to the specified
     * page, otherwise do nothing.
     *
     * This function is called after post_config and before report data is generated. This function is
     * not called when report data is not generated, such as on report setup pages.
     * If you want to perform a different action after post_config then override this function and
     * set your own private variables (e.g. to signal a result from post_config) in your report source.
     */
    public function pre_display_actions() {
        if ($this->needsredirect && isset($this->redirecturl)) {
            if (isset($this->redirectmessage)) {
                totara_set_notification($this->redirectmessage, $this->redirecturl, array('class' => 'notifymessage'));
            } else {
                redirect($this->redirecturl);
            }
        }
    }


    /**
     * Create a link that when clicked will display additional information inserted in a box below the clicked row.
     *
     * @param string|stringable $columnvalue the value to display in the column
     * @param string $expandname the name of the function (prepended with 'rb_expand_') that will generate the contents
     * @param array $params any parameters that the content generator needs
     * @param string|moodle_url $alternateurl url to link to in case js is not available
     * @param array $attributes
     * @return type
     */
    protected function create_expand_link($columnvalue, $expandname, $params, $alternateurl = '', $attributes = array()) {
        global $OUTPUT;

        // Serialize the data so that it can be passed as a single value.
        $paramstring = http_build_query($params);

        $attributes['class'] = 'rb-display-expand';
        $attributes['data-name'] = $expandname;
        $attributes['data-param'] = $paramstring;
        $attributes['style'] = 'background-image:url(' . $OUTPUT->pix_url('i/info') . ')';

        // Create the result.
        $link = html_writer::link($alternateurl, format_string($columnvalue), array('class' => 'rb-display-expand-link'));
        return html_writer::div($link, 'rb-display-expand', $attributes);
    }


    /**
     * Check the joinlist for invalid dependencies and duplicate names
     *
     * @return True or throws exception if problem found
     */
    private function validate_joinlist() {
        $joinlist = $this->joinlist;
        $joins_used = array();

        // don't let source define join with same name as an SQL
        // reserved word
        // from http://docs.moodle.org/en/XMLDB_reserved_words
        $reserved_words = explode(', ', 'access, accessible, add, all, alter, analyse, analyze, and, any, array, as, asc, asensitive, asymmetric, audit, authorization, autoincrement, avg, backup, before, begin, between, bigint, binary, blob, both, break, browse, bulk, by, call, cascade, case, cast, change, char, character, check, checkpoint, close, cluster, clustered, coalesce, collate, column, comment, commit, committed, compress, compute, condition, confirm, connect, connection, constraint, contains, containstable, continue, controlrow, convert, count, create, cross, current, current_date, current_role, current_time, current_timestamp, current_user, cursor, database, databases, date, day_hour, day_microsecond, day_minute, day_second, dbcc, deallocate, dec, decimal, declare, default, deferrable, delayed, delete, deny, desc, describe, deterministic, disk, distinct, distinctrow, distributed, div, do, double, drop, dual, dummy, dump, each, else, elseif, enclosed, end, errlvl, errorexit, escape, escaped, except, exclusive, exec, execute, exists, exit, explain, external, false, fetch, file, fillfactor, float, float4, float8, floppy, for, force, foreign, freetext, freetexttable, freeze, from, full, fulltext, function, goto, grant, group, having, high_priority, holdlock, hour_microsecond, hour_minute, hour_second, identified, identity, identity_insert, identitycol, if, ignore, ilike, immediate, in, increment, index, infile, initial, initially, inner, inout, insensitive, insert, int, int1, int2, int3, int4, int8, integer, intersect, interval, into, is, isnull, isolation, iterate, join, key, keys, kill, leading, leave, left, level, like, limit, linear, lineno, lines, load, localtime, localtimestamp, lock, long, longblob, longtext, loop, low_priority, master_heartbeat_period, master_ssl_verify_server_cert, match, max, maxextents, mediumblob, mediumint, mediumtext, middleint, min, minus, minute_microsecond, minute_second, mirrorexit, mlslabel, mod, mode, modifies, modify, national, natural, new,' .
            ' no_write_to_binlog, noaudit, nocheck, nocompress, nonclustered, not, notnull, nowait, null, nullif, number, numeric, of, off, offline, offset, offsets, old, on, once, online, only, open, opendatasource, openquery, openrowset, openxml, optimize, option, optionally, or, order, out, outer, outfile, over, overlaps, overwrite, pctfree, percent, perm, permanent, pipe, pivot, placing, plan, precision, prepare, primary, print, prior, privileges, proc, procedure, processexit, public, purge, raid0, raiserror, range, raw, read, read_only, read_write, reads, readtext, real, reconfigure, references, regexp, release, rename, repeat, repeatable, replace, replication, require, resource, restore, restrict, return, returning, revoke, right, rlike, rollback, row, rowcount, rowguidcol, rowid, rownum, rows, rule, save, schema, schemas, second_microsecond, select, sensitive, separator, serializable, session, session_user, set, setuser, share, show, shutdown, similar, size, smallint, some, soname, spatial, specific, sql, sql_big_result, sql_calc_found_rows, sql_small_result, sqlexception, sqlstate, sqlwarning, ssl, start, starting, statistics, straight_join, successful, sum, symmetric, synonym, sysdate, system_user, table, tape, temp, temporary, terminated, textsize, then, tinyblob, tinyint, tinytext, to, top, trailing, tran, transaction, trigger, true, truncate, tsequal, uid, uncommitted, undo, union, unique, unlock, unsigned, update, updatetext, upgrade, usage, use, user, using, utc_date, utc_time, utc_timestamp, validate, values, varbinary, varchar, varchar2, varcharacter, varying, verbose, view, waitfor, when, whenever, where, while, with, work, write, writetext, x509, xor, year_month, zerofill');

        foreach ($joinlist as $item) {
            // check join list for duplicate names
            if (in_array($item->name, $joins_used)) {
                $a = new stdClass();
                $a->join = $item->name;
                $a->source = get_class($this);
                throw new ReportBuilderException(get_string('error:joinxusedmorethanonceiny', 'totara_reportbuilder', $a));
            } else {
                $joins_used[] = $item->name;
            }

            if (in_array($item->name, $reserved_words)) {
                $a = new stdClass();
                $a->join = $item->name;
                $a->source = get_class($this);
                throw new ReportBuilderException(get_string('error:joinxisreservediny', 'totara_reportbuilder', $a));
            }
        }

        foreach ($joinlist as $item) {
            // check that dependencies exist
            if (isset($item->dependencies) &&
                is_array($item->dependencies)) {

                foreach ($item->dependencies as $dep) {
                    if ($dep == 'base') {
                        continue;
                    }
                    if (!in_array($dep, $joins_used)) {
                        $a = new stdClass();
                        $a->join = $item->name;
                        $a->source = get_class($this);
                        $a->dependency = $dep;
                        throw new ReportBuilderException(get_string('error:joinxhasdependencyyinz', 'totara_reportbuilder', $a));
                    }
                }
            } else if (isset($item->dependencies) &&
                $item->dependencies != 'base') {

                if (!in_array($item->dependencies, $joins_used)) {
                    $a = new stdClass();
                    $a->join = $item->name;
                    $a->source = get_class($this);
                    $a->dependency = $item->dependencies;
                    throw new ReportBuilderException(get_string('error:joinxhasdependencyyinz', 'totara_reportbuilder', $a));
                }
            }
        }
        return true;
    }


    //
    //
    // General purpose source specific methods
    //
    //

    /**
     * Returns a new rb_column object based on a column option from this source
     *
     * If $heading is given use it for the heading property, otherwise use
     * the default heading property from the column option
     *
     * @param string $type The type of the column option to use
     * @param string $value The value of the column option to use
     * @param string $heading Heading for the new column
     * @param boolean $customheading True if the heading has been customised
     * @return object A new rb_column object with details copied from this
     *                rb_column_option
     */
    function new_column_from_option($type, $value, $heading=null, $customheading = true, $hidden=0) {
        $columnoptions = $this->columnoptions;
        $joinlist = $this->joinlist;
        if ($coloption =
            reportbuilder::get_single_item($columnoptions, $type, $value)) {

            // make sure joins are defined before adding column
            if (!reportbuilder::check_joins($joinlist, $coloption->joins)) {
                $a = new stdClass();
                $a->type = $coloption->type;
                $a->value = $coloption->value;
                $a->source = get_class($this);
                throw new ReportBuilderException(get_string('error:joinsfortypexandvalueynotfoundinz', 'totara_reportbuilder', $a));
            }

            if ($heading === null) {
                $heading = ($coloption->defaultheading !== null) ?
                    $coloption->defaultheading : $coloption->name;
            }

            return new rb_column(
                $type,
                $value,
                $heading,
                $coloption->field,
                array(
                    'joins' => $coloption->joins,
                    'displayfunc' => $coloption->displayfunc,
                    'extrafields' => $coloption->extrafields,
                    'required' => false,
                    'capability' => $coloption->capability,
                    'noexport' => $coloption->noexport,
                    'grouping' => $coloption->grouping,
                    'nosort' => $coloption->nosort,
                    'style' => $coloption->style,
                    'class' => $coloption->class,
                    'hidden' => $hidden,
                    'customheading' => $customheading,
                )
            );
        } else {
            $a = new stdClass();
            $a->type = $type;
            $a->value = $value;
            $a->source = get_class($this);
            throw new ReportBuilderException(get_string('error:columnoptiontypexandvalueynotfoundinz', 'totara_reportbuilder', $a));
        }
    }


    //
    //
    // Generic column display methods
    //
    //


    /**
     * Reformat a timestamp into a date, showing nothing if invalid or null
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row
     *
     * @return string Date in a nice format
     */
    function rb_display_nice_date($date, $row) {
        if ($date && is_numeric($date)) {
            return userdate($date, get_string('strfdateshortmonth', 'langconfig'));
        } else {
            return '';
        }
    }

    /**
     * Reformat a timestamp into a time, showing nothing if invalid or null
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row
     *
     * @return string Time in a nice format
     */
    function rb_display_nice_time($date, $row) {
        if ($date && is_numeric($date)) {
            return userdate($date, get_string('strftimeshort', 'langconfig'));
        } else {
            return '';
        }
    }

    /**
     * Reformat a timestamp and timezone into a time, showing nothing if invalid or null
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row (which should include a timezone field)
     *
     * @return string Time in a nice format
     */
    function rb_display_nice_time_in_timezone($date, $row) {
        if ($date && is_numeric($date)) {
            if (empty($row->timezone)) {
                $targetTZ = totara_get_clean_timezone();
                $tzstring = get_string('nice_time_unknown_timezone', 'totara_reportbuilder');
            } else {
                $targetTZ = $row->timezone;
                $tzstring = get_string(strtolower($targetTZ), 'timezones');
            }
            $date = userdate($date, get_string('nice_time_in_timezone_format', 'totara_reportbuilder'), $targetTZ) . ' ';
            return $date . $tzstring;
        } else {
            return '';
        }
    }

    /**
     * Reformat a timestamp and timezone into a date, showing nothing if invalid or null
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row (which should include a timezone field)
     *
     * @return string Date in a nice format
     */
    function rb_display_nice_date_in_timezone($date, $row) {
        if ($date && is_numeric($date)) {
            if (empty($row->timezone)) {
                $targetTZ = totara_get_clean_timezone();
            } else {
                $targetTZ = $row->timezone;
            }
            $date = userdate($date, get_string('nice_date_in_timezone_format', 'totara_reportbuilder'), $targetTZ) . ' ';
            return $date;
        } else {
            return '';
        }
    }

    /**
     * Reformat a timestamp into a date and time, showing nothing if invalid or null
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row
     *
     * @return string Date and time in a nice format
     */
    function rb_display_nice_datetime($date, $row) {
        if ($date && is_numeric($date)) {
            return userdate($date, get_string('strfdateattime', 'langconfig'));
        } else {
            return '';
        }
    }

    /**
     * Reformat a timestamp into a date and time (including seconds), showing nothing if invalid or null
     *
     * @param integer $date Unix timestamp
     * @param object $row Object containing all other fields for this row
     *
     * @return string Date and time (including seconds) in a nice format
     */
    function rb_display_nice_datetime_seconds($date, $row) {
        if ($date && is_numeric($date)) {
            return userdate($date, get_string('strftimedateseconds', 'langconfig'));
        } else {
            return '';
        }
    }

    /**
     * Convert 1 and 0 to 'Yes' and 'No'
     *
     * @param integer $value input value
     *
     * @return string yes or no, or null for values other than 1 or 0
     */
    function rb_display_yes_or_no($value, $row) {
        if ($value == 1) {
            return get_string('yes');
        } else if ($value == 0) {
            return get_string('no');
        } else {
            return '';
        }
    }


    /**
     * Convert first letters of each word to uppercase
     *
     * @param string $item A string to convert
     * @param object $row Object containing all other fields for this row
     *
     * @return string The string with words capitialized
     */
    function rb_display_ucfirst($item, $row) {
        return ucfirst($item);
    }

    // convert floats to 2 decimal places
    function rb_display_round2($item, $row) {
        return $item === null ? null : sprintf('%.2f', $item);
    }

    // converts number to percentage with 1 decimal place
    function rb_display_percent($item, $row) {
        return $item === null ? null : sprintf('%.1f%%', $item);
    }

    /**
     * Display correct course grade via grade or RPL as a percentage string
     *
     * @param string $item A number to convert
     * @param object $row Object containing all other fields for this row
     *
     * @return string The percentage with 1 decimal place
     */
    function rb_display_course_grade_percent($item, $row) {
        global $CFG;
        require_once($CFG->dirroot.'/completion/completion_completion.php');
        if ($row->course_completion_status == COMPLETION_STATUS_COMPLETEVIARPL && !empty($row->rplgrade)) {
            $item = $row->rplgrade;
        }
        return $item === null ? null : sprintf('%.1f%%', $item);
    }
    // link user's name to profile page
    // requires the user_id extra field
    // in column definition
    function rb_display_link_user($user, $row, $isexport = false) {
        if ($isexport) {
            return $user;
        }

        $userid = $row->user_id;
        $url = new moodle_url('/user/view.php', array('id' => $userid));
        return html_writer::link($url, $user);
    }

    /**
     * Properly format tinymce textarea data for display
     *
     * @param string $field fieldname from SQL query
     * @param integer $data contents of field from database
     * @param object $row Object containing all other fields for this row
     * @param boolean $isexport
     * @return string Textarea contents
     */
    public function rb_display_tinymce_textarea($field = '', $data = '', $row = '', $isexport = false) {
        if (empty($field) || empty($data) || empty($row)) {
            return '';
        }

        if (empty($row->context) && empty($row->recordid)) {
            $context = context_system::instance();
        } else {
            $rowcontext = $row->context;
            if ($rowcontext == 'context_module') {
                $component = str_replace('mod_', '', $row->component);
                $cm = get_coursemodule_from_instance($component, $row->recordid);
                $row->recordid = $cm->id;
            }
            $context = $rowcontext::instance($row->recordid);
        }

        if (!isset($row->fileid)) {
            $row->fileid = null;
        }

        $data = file_rewrite_pluginfile_urls($data, 'pluginfile.php', $context->id, $row->component, $row->filearea, $row->fileid);

        if ($isexport) {
            $displaytext = format_text($data, FORMAT_MOODLE);
        } else {
            $displaytext = format_text($data, FORMAT_HTML);
        }

        return $displaytext;
    }

    /**
     * Properly format user customfield textarea data for display
     *
     * @param integer $data contents of field from database
     * @param object $row Object containing all other fields for this row
     * @param boolean $isexport
     * @return string Textarea contents with images etc processed properly
     */
    function rb_display_userfield_textarea($data, $row, $isexport = false) {
        global $CFG;
        if (empty($data)) {
            return '';
        }

        if ($isexport) {
            $displaytext = format_text($data, FORMAT_MOODLE);
        } else {
            $displaytext = format_text($data, FORMAT_HTML);
        }

        return $displaytext;
    }

    /**
     * Properly format totara customfield textarea data for display
     *
     * @param string $field fieldname from SQL query
     * @param integer $data contents of field from database
     * @param object $row Object containing all other fields for this row
     * @param boolean $isexport
     * @return string Textarea contents with images etc processed properly
     */
    function rb_display_customfield_textarea($field, $data, $row, $isexport = false) {
        global $CFG;
        if (empty($data)) {
            return '';
        }

        if ($isexport) {
            $displaytext = format_text($data, FORMAT_MOODLE);
        } else {
            //hierarchy custom fields are stored in the FileAPI fileareas using the longform of the prefix
            //extract prefix from field name
            $pattern = '/(?P<prefix>(.*?))_custom_field_(\d+)$/';
            $matches = array();
            preg_match($pattern, $field, $matches);
            if (!empty($matches)) {
                $cf_prefix = $matches['prefix'];
                switch ($cf_prefix) {
                    case 'org_type':
                        $prefix = 'organisation';
                        break;
                    case 'pos_type':
                        $prefix = 'position';
                        break;
                    case 'comp_type':
                        $prefix = 'competency';
                        break;
                    case 'goal_type':
                        $prefix = 'goal';
                        break;
                    case 'course':
                        $prefix = 'course';
                        break;
                    default:
                        //unknown prefix
                        return '';
                }
            } else {
                //unknown prefix
                return '';
            }

            $itemidfield = "{$field}_itemid";
            require_once($CFG->dirroot.'/totara/customfield/field/textarea/field.class.php');
            $extradata = array('prefix' => $prefix, 'itemid' => $row->$itemidfield);
            $displaytext = call_user_func(array('customfield_textarea', 'display_item_data'), $data, $extradata);
        }

        return $displaytext;
    }

    /**
     * Properly format totara customfield file data for display
     * @param string $field fieldname from SQL query
     * @param integer $data contents of field from database
     * @param object $row Object containing all other fields for this row
     * @param boolean $isexport
     * @return string Filename action link or just the name if $isexport
     */
    function rb_display_customfield_file($field, $data, $row, $isexport = false) {
        global $CFG;
        if (empty($data)) {
            return '';
        }
        //hierarchy custom fields are stored in the FileAPI fileareas using the longform of the prefix
        //extract prefix from field name
        $pattern = '/(?P<prefix>(.*?))_custom_field_(\d?)$/';
        $matches = array();
        preg_match($pattern, $field, $matches);
        if (!empty($matches)) {
            $cf_prefix = $matches['prefix'];
            switch ($cf_prefix) {
                case 'org_type':
                    $prefix = 'organisation';
                    break;
                case 'pos_type':
                    $prefix = 'position';
                    break;
                case 'comp_type':
                    $prefix = 'competency';
                    break;
                case 'goal_type':
                    $prefix = 'goal';
                    break;
                case 'course':
                    $prefix = 'course';
                    break;
                default:
                    //unknown prefix
                    return '';
            }
        } else {
            //unknown prefix
            return '';
        }
        $itemidfield = "{$field}_itemid";
        require_once($CFG->dirroot.'/totara/customfield/field/file/field.class.php');
        $extradata = array('prefix' => $prefix, 'itemid' => $row->$itemidfield, 'isexport' => $isexport);
        $displaytext = call_user_func(array('customfield_file', 'display_item_data'), $data, $extradata);

        return $displaytext;
    }

    /**
     * Properly format totara customfield multi-select data for display as icons
     * @param string $field fieldname from SQL query
     * @param integer $data contents of field from database
     * @param object $row Object containing all other fields for this row
     * @param boolean $isexport
     * @return string icon of selected options
     */
    public function rb_display_customfield_multiselect_icon($field, $data, $row, $isexport = false) {
        global $CFG;

        if ($isexport) {
            return $this->rb_display_customfield_multiselect_text($field, $data, $row, $isexport);
        }

        require_once($CFG->dirroot . '/totara/customfield/field/multiselect/field.class.php');

        return customfield_multiselect::display_item_data($row->{$field . '_json'}, array('display' => 'list-icons'));
    }

    /**
     * Properly format totara customfield multi-select data for display as text titles
     * @param string $field fieldname from SQL query
     * @param integer $data contents of field from database
     * @param object $row Object containing all other fields for this row
     * @param boolean $isexport
     * @return string icon of selected options
     */
    public function rb_display_customfield_multiselect_text($field, $data, $row, $isexport = false) {
        global $CFG;

        require_once($CFG->dirroot . '/totara/customfield/field/multiselect/field.class.php');

        return customfield_multiselect::display_item_data($row->{$field . '_json'}, array('display' => 'list-text'));
    }

    function rb_display_link_user_icon($user, $row, $isexport = false) {
        global $OUTPUT;

        if ($isexport) {
            return $user;
        }

        if ($row->user_id == 0) {
            return '';
        }

        $userid = $row->user_id;
        $url = new moodle_url('/user/view.php', array('id' => $userid));

        $picuser = new stdClass();
        $picuser->id = $userid;
        $picuser->picture = $row->userpic_picture;
        $picuser->imagealt = $row->userpic_imagealt;
        $picuser->firstname = $row->userpic_firstname;
        $picuser->firstnamephonetic = $row->userpic_firstnamephonetic;
        $picuser->middlename = $row->userpic_middlename;
        $picuser->lastname = $row->userpic_lastname;
        $picuser->lastnamephonetic = $row->userpic_lastnamephonetic;
        $picuser->alternatename = $row->userpic_alternatename;
        $picuser->email = $row->userpic_email;

        return $OUTPUT->user_picture($picuser, array('courseid' => 1)) . "&nbsp;" . html_writer::link($url, $user);
    }

    /**
     * A rb_column_options->displayfunc helper function for showing a user's
     * profile picture
     * @param integer $itemid ID of the user
     * @param object $row The rest of the data for the row
     * @param boolean $isexport If the report is being exported or viewed
     * @return string
     */
    function rb_display_user_picture($itemid, $row, $isexport = false) {
        global $OUTPUT;

        $picuser = new stdClass();
        $picuser->id = $itemid;
        $picuser->picture = $row->userpic_picture;
        $picuser->imagealt = $row->userpic_imagealt;
        $picuser->firstname = $row->userpic_firstname;
        $picuser->firstnamephonetic = $row->userpic_firstnamephonetic;
        $picuser->middlename = $row->userpic_middlename;
        $picuser->lastname = $row->userpic_lastname;
        $picuser->lastnamephonetic = $row->userpic_lastnamephonetic;
        $picuser->alternatename = $row->userpic_alternatename;
        $picuser->email = $row->userpic_email;

        // don't show picture in spreadsheet
        if ($isexport) {
            return '';
        } else {
            return $OUTPUT->user_picture($picuser, array('courseid' => 1));
        }
    }

    /**
     * Convert a course name into an expanding link.
     *
     * @param string $course
     * @param array $row
     * @param bool $isexport
     * @return html|string
     */
    public function rb_display_course_expand($course, $row, $isexport = false) {
        if ($isexport) {
            return format_string($course);
        }

        $attr = (isset($row->course_visible) && $row->course_visible == 0) ? array('class' => 'dimmed') : array();
        $alturl = new moodle_url('/course/view.php', array('id' => $row->course_id));
        return $this->create_expand_link($course, 'course_details', array('expandcourseid' => $row->course_id), $alturl, $attr);
    }

    /**
     * Convert a program/certification name into an expanding link.
     *
     * @param string $program
     * @param array $row
     * @param bool $isexport
     * @return html|string
     */
    public function rb_display_program_expand($program, $row, $isexport = false) {
        if ($isexport) {
            return format_string($program);
        }

        $attr = (isset($row->prog_visible) && $row->prog_visible == 0) ? array('class' => 'dimmed')
                : array();
        $alturl = new moodle_url('/totara/program/view.php', array('id' => $row->prog_id));
        return $this->create_expand_link($program, 'prog_details',
                array('expandprogid' => $row->prog_id), $alturl, $attr);
    }

    /**
     * Enpanding content to display when clicking a course.
     * Will be placed inside a table cell which is the width of the table.
     * Call required_param to get any param data that is needed.
     *
     * @return string
     */
    public function rb_expand_course_details() {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');

        $formdata = array();

        $courseid = required_param('expandcourseid', PARAM_INT);
        $userid = $USER->id;

        $course = $DB->get_record('course', array('id' => $courseid));
        $formdata['summary'] = $course->summary;

        $coursecontext = context_course::instance($course->id, MUST_EXIST);
        $enrolled = is_enrolled($coursecontext);
        $formdata['url'] = new moodle_url('/course/view.php', array('id' => $courseid));

        if ($enrolled) {
            $ccompl = new completion_completion(array('userid' => $userid, 'course' => $courseid));
            $complete = $ccompl->is_complete();
            if ($complete) {
                $sql = 'SELECT gg.*
                          FROM {grade_grades} gg
                          JOIN {grade_items} gi
                            ON gg.itemid = gi.id
                         WHERE gg.userid = ?
                           AND gi.courseid = ?';
                $grade = $DB->get_record_sql($sql, array($userid, $courseid));
                $coursecompletion = $DB->get_record('course_completions', array('userid' => $userid, 'course' => $courseid));
                $coursecompletedon = userdate($coursecompletion->timecompleted, get_string('strfdateshortmonth', 'langconfig'));

                $formdata['status'] = get_string('coursestatuscomplete', 'totara_reportbuilder');
                $formdata['progress'] = get_string('coursecompletedon', 'totara_reportbuilder', $coursecompletedon);
                if ($grade) {
                    if (!isset($grade->finalgrade)) {
                        $formdata['grade'] = '-';
                    } else {
                        $formdata['grade'] = get_string('xpercent', 'totara_core', $grade->finalgrade);
                    }
                }
            } else {
                $formdata['status'] = get_string('coursestatusenrolled', 'totara_reportbuilder');

                list($statusdpsql, $statusdpparams) = $this->get_dp_status_sql($userid, $courseid);
                $statusdp = $DB->get_record_sql($statusdpsql, $statusdpparams);
                $progress = totara_display_course_progress_icon($userid, $courseid,
                    $statusdp->course_completion_statusandapproval);
                // Highlight if the item has not yet been approved.
                if ($statusdp->approved == DP_APPROVAL_UNAPPROVED
                        || $statusdp->approved == DP_APPROVAL_REQUESTED) {
                    $progress .= $this->rb_display_plan_item_status($statusdp->approved);
                }
                $formdata['progress'] = $progress;

                // Course not finished, so no end date for course.
                $formdata['enddate'] = '';
            }
            $formdata['action'] =  get_string('launchcourse', 'totara_program');
        } else {
            $formdata['status'] = get_string('coursestatusnotenrolled', 'totara_reportbuilder');

            $instances = enrol_get_instances($courseid, true);
            $plugins = enrol_get_plugins(true);

            $cansignup = false;
            $enrolmethodlist = array();
            foreach ($instances as $instance) {
                if (!isset($plugins[$instance->enrol])) {
                    continue;
                }
                $plugin = $plugins[$instance->enrol];
                if (enrol_is_enabled($instance->enrol)) {
                    $enrolmethodlist[] = $plugin->get_instance_name($instance);
                    if (in_array($instance->enrol, array('self', 'guest'))) {
                        if ($plugin->show_enrolme_link($instance)) {
                            $cansignup = true;
                        }
                    }
                }
            }
            $enrolmethodstr = implode(', ', $enrolmethodlist);
            $realuser = \core\session\manager::get_realuser();

            // Enrolling methods.

            if ($cansignup) {
                $formdata['enroltype'] = get_string('courseenrolavailable', 'totara_reportbuilder');
                $formdata['action'] = get_string('enrol', 'enrol');
                $formdata['url'] = new moodle_url('/enrol/index.php', array('id' => $courseid));
            } else if (is_viewing($coursecontext, $realuser->id) || is_siteadmin($realuser->id)) {
                $formdata['enroltype'] = $enrolmethodstr;
                $formdata['action'] = get_string('viewcourse', 'totara_program');
                $formdata['url'] = new moodle_url('/course/view.php', array('id' => $courseid));
            } else {
                $formdata['enroltype'] = $enrolmethodstr;
                $formdata['action'] = get_string('notenrollable', 'enrol');
                $formdata['url'] = '';
            }
        }

        $mform = new report_builder_course_expand_form(null, $formdata);

        return $mform->render();
    }

    /**
     * Enpanding content to display when clicking a course.
     * Will be placed inside a table cell which is the width of the table.
     * Call required_param to get any param data that is needed.
     *
     * @return string
     */
    public function rb_expand_prog_details() {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/totara/reportbuilder/report_forms.php');

        $progid = required_param('expandprogid', PARAM_INT);
        $userid = $USER->id;

        $formdata = (array)$DB->get_record('prog', array('id' => $progid));

        $formdata['assigned'] = $DB->record_exists('prog_user_assignment', array('userid' => $userid, 'programid' => $progid));

        $mform = new report_builder_program_expand_form(null, $formdata);

        return $mform->render();
    }

    /**
     * Get course progress status for user according his record of learning
     *
     * @param int $userid
     * @param int $courseid
     * @return array
     */
    public function get_dp_status_sql($userid, $courseid) {
        global $CFG;
        require_once($CFG->dirroot.'/totara/plan/rb_sources/rb_source_dp_course.php');
        // Use base query from rb_source_dp_course, and column/joins of statusandapproval.
        $base_sql = rb_source_dp_course::get_base_sql();
        $sql = "SELECT CASE WHEN dp_course.planstatus = " . DP_PLAN_STATUS_COMPLETE . "
                            THEN dp_course.completionstatus
                            ELSE course_completion.status
                            END AS course_completion_statusandapproval,
                       dp_course.approved AS approved
                 FROM ".$base_sql. " base
                 LEFT JOIN {course_completions} course_completion
                   ON (base.courseid = course_completion.course
                  AND base.userid = course_completion.userid)
                 LEFT JOIN (SELECT p.userid AS userid, p.status AS planstatus,
                                   pc.courseid AS courseid, pc.approved AS approved,
                                   pc.completionstatus AS completionstatus
                              FROM {dp_plan} p
                             INNER JOIN {dp_plan_course_assign} pc ON p.id = pc.planid) dp_course
                   ON dp_course.userid = base.userid AND dp_course.courseid = base.courseid
                WHERE base.userid = ? AND base.courseid = ?";
        return array($sql, array($userid, $courseid));
    }
    // convert a course name into a link to that course
    function rb_display_link_course($course, $row, $isexport = false) {
        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        if ($isexport) {
            return format_string($course);
        }

        $courseid = $row->course_id;
        if (empty($CFG->audiencevisibility)) {
            $attr = (isset($row->course_visible) && $row->course_visible == 0) ? array('class' => 'dimmed') : array();
        } else {
            $attr = (isset($row->course_audiencevisible) && $row->course_audiencevisible == COHORT_VISIBLE_NONE) ?
                    array('class' => 'dimmed') : array();
        }
        $url = new moodle_url('/course/view.php', array('id' => $courseid));
        return html_writer::link($url, $course, $attr);
    }

    // convert a course name into a link to that course and shows
    // the course icon next to it
    function rb_display_link_course_icon($course, $row, $isexport = false) {
        global $CFG, $OUTPUT;
        require_once($CFG->dirroot . '/cohort/lib.php');

        if ($isexport) {
            return format_string($course);
        }

        $courseid = $row->course_id;
        $courseicon = !empty($row->course_icon) ? $row->course_icon : 'default';
        if (empty($CFG->audiencevisibility)) {
            $cssclass = (isset($row->course_visible) && $row->course_visible == 0) ? 'dimmed' : '';
        } else {
            $cssclass = (isset($row->course_audiencevisible) && $row->course_audiencevisible == COHORT_VISIBLE_NONE) ?
                    'dimmed' : '';
        }
        $icon = html_writer::empty_tag('img', array('src' => totara_get_icon($courseid, TOTARA_ICON_TYPE_COURSE),
            'class' => 'course_icon'));
        $link = $OUTPUT->action_link(
            new moodle_url('/course/view.php', array('id' => $courseid)),
            $icon . $course, null, array('class' => $cssclass)
        );
        return $link;
    }

    // display an icon based on the course icon field
    function rb_display_course_icon($icon, $row, $isexport = false) {
        if ($isexport) {
            return format_string($row->course_name);
        }

        $coursename = format_string($row->course_name);
        $courseicon = html_writer::empty_tag('img', array('src' => totara_get_icon($row->course_id, TOTARA_ICON_TYPE_COURSE),
            'class' => 'course_icon', 'alt' => $coursename));
        return $courseicon;
    }

    // display an icon for the course type
    function rb_display_course_type_icon($type, $row, $isexport = false) {
        global $OUTPUT;

        if ($isexport) {
            switch ($type) {
                case TOTARA_COURSE_TYPE_ELEARNING:
                    return get_string('elearning', 'rb_source_dp_course');
                case TOTARA_COURSE_TYPE_BLENDED:
                    return get_string('blended', 'rb_source_dp_course');
                case TOTARA_COURSE_TYPE_FACETOFACE:
                    return get_string('facetoface', 'rb_source_dp_course');
            }
            return '';
        }

        switch ($type) {
        case null:
            return null;
            break;
        case 0:
            $image = 'elearning';
            break;
        case 1:
            $image = 'blended';
            break;
        case 2:
            $image = 'facetoface';
            break;
        }
        $alt = get_string($image, 'rb_source_dp_course');
        $icon = $OUTPUT->pix_icon('/msgicons/' . $image . '-regular', $alt, 'totara_core', array('title' => $alt));

        return $icon;
    }

    /**
     * Display course type text
     * @param string $type
     * @param array $row
     * @param bool $isexport
     * @return string
     */
    public function rb_display_course_type($type, $row, $isexport = false) {
        $types = $this->rb_filter_course_types();
        if (isset($types[$type])) {
            return $types[$type];
        }
        return '';
    }

    // convert a course category name into a link to that category's page
    function rb_display_link_course_category($category, $row, $isexport = false) {
        if ($isexport) {
            return format_string($category);
        }

        $catid = $row->cat_id;
        $category = format_string($category);
        if ($catid == 0 || !$catid) {
            return '';
        }
        $attr = (isset($row->cat_visible) && $row->cat_visible == 0) ? array('class' => 'dimmed') : array();
        $url = new moodle_url('/course/index.php', array('categoryid' => $catid));
        return html_writer::link($url, $category, $attr);
    }


    public function rb_display_audience_visibility($visibility, $row, $isexport = false) {
        global $COHORT_VISIBILITY;

        return $COHORT_VISIBILITY[$visibility];
    }


    /**
     * Generate the plan title with a link to the plan
     * @param string $planname
     * @param object $row
     * @param boolean $isexport If the report is being exported or viewed
     * @return string
     */
    public function rb_display_planlink($planname, $row, $isexport = false) {

        // no text
        if (strlen($planname) == 0) {
            return '';
        }

        // invalid id - show without a link
        if (empty($row->plan_id)) {
            return $planname;
        }

        if ($isexport) {
            return $planname;
        }
        $url = new moodle_url('/totara/plan/view.php', array('id' => $row->plan_id));
        return html_writer::link($url, $planname);
    }


    /**
     * Display the plan's status (for use as a column displayfunc)
     *
     * @global object $CFG
     * @param int $status
     * @param object $row
     * @return string
     */
    public function rb_display_plan_status($status, $row) {
        global $CFG;
        require_once($CFG->dirroot . '/totara/plan/lib.php');

        switch ($status) {
            case DP_PLAN_STATUS_UNAPPROVED:
                return get_string('unapproved', 'totara_plan');
                break;
            case DP_PLAN_STATUS_APPROVED:
                return get_string('approved', 'totara_plan');
                break;
            case DP_PLAN_STATUS_COMPLETE:
                return get_string('complete', 'totara_plan');
                break;
        }
    }


    /**
     * Column displayfunc to convert a plan item's status to a
     * human-readable string
     *
     * @param int $status
     * @return string
     */
    public function rb_display_plan_item_status($status) {
        global $CFG;
        require_once($CFG->dirroot . '/totara/plan/lib.php');

        switch($status) {
        case DP_APPROVAL_DECLINED:
            return get_string('declined', 'totara_plan');
        case DP_APPROVAL_UNAPPROVED:
            return get_string('unapproved', 'totara_plan');
        case DP_APPROVAL_REQUESTED:
            return get_string('pendingapproval', 'totara_plan');
        case DP_APPROVAL_APPROVED:
            return get_string('approved', 'totara_plan');
        default:
            return '';
        }
    }


    function rb_display_yes_no($item, $row) {
        if ($item === null) {
            return '';
        } else if ($item) {
            return get_string('yes');
        } else {
            return get_string('no');
        }
    }

    // convert an integer number of minutes into a
    // formatted duration (e.g. 90 mins => 1h 30m)
    function rb_display_hours_minutes($mins, $row) {
        if ($mins === null) {
            return '';
        } else {
            $minutes = abs((int) $mins);
            $hours = floor($minutes / 60);
            $decimalMinutes = $minutes - floor($minutes/60) * 60;
            return sprintf("%dh %02.0fm", $hours, $decimalMinutes);
        }
    }

    // convert a 2 digit country code into the country name
    function rb_display_country_code($code, $row) {
        $countries = get_string_manager()->get_list_of_countries();

        if (isset($countries[$code])) {
            return $countries[$code];
        }
        return $code;
    }

    // indicates if the user is deleted or not
    function rb_display_deleted_status($status, $row) {
        switch($status) {
            case 1:
                return get_string('deleteduser', 'totara_reportbuilder');
            case 2:
                return get_string('suspendeduser', 'totara_reportbuilder');
            default:
                return get_string('activeuser', 'totara_reportbuilder');
        }
    }

    /**
     * Column displayfunc to show a hierarchy path as a human-readable string
     * @param $path the path string of delimited ids e.g. 1/3/7
     * @param $row data row
     */
    function rb_display_nice_hierarchy_path($path, $row) {
        global $DB;
        if (empty($path)) {
            return '';
        }
        $displaypath = '';
        $parentid = 0;
        // Make sure we know what we are looking for, and that the private var is populated (in source constructor).
        if (isset($row->hierarchytype) && isset($this->hierarchymap[$row->hierarchytype])) {
            $paths = explode('/', substr($path, 1));
            $map = $this->hierarchymap[$row->hierarchytype];
            foreach ($paths as $path) {
                if ($parentid !== 0) {
                    // Include ' > ' before name except on top element.
                    $displaypath .= ' &gt; ';
                }
                if (isset($map[$path])) {
                    $displaypath .= $map[$path];
                } else {
                    // Should not happen if paths are correct!
                    $displaypath .= get_string('unknown', 'totara_reportbuilder');
                }
                $parentid = $path;
            }
        }

        return $displaypath;
    }

    /**
     * Column displayfunc to convert a language code to a human-readable string
     * @param $code Language code
     * @param $row data row - unused in this function
     * @return string
     */
    function rb_display_language_code($code, $row) {
            global $CFG;
        static $languages = array();
        $strmgr = get_string_manager();
        // Populate the static variable if empty
        if (count($languages) == 0) {
            // Return all languages available in system (adapted from stringmanager->get_list_of_translations()).
            $langdirs = get_list_of_plugins('', '', $CFG->langotherroot);
            $langdirs = array_merge($langdirs, array("{$CFG->dirroot}/lang/en"=>'en'));
            $curlang = current_language();
            // Loop through all langs and get info.
            foreach ($langdirs as $lang) {
                if (isset($languages[$lang])){
                    continue;
                }
                if (strstr($lang, '_local') !== false) {
                    continue;
                }
                if (strstr($lang, '_utf8') !== false) {
                    continue;
                }
                $string = $strmgr->load_component_strings('langconfig', $lang);
                if (!empty($string['thislanguage'])) {
                    $languages[$lang] = $string['thislanguage'];
                    // If not the current language, provide the English translation also.
                    if(strpos($lang, $curlang) === false) {
                        $languages[$lang] .= ' ('. $string['thislanguageint'] .')';
                    }
                }
                unset($string);
            }
        }

        if (empty($code)) {
            return get_string('notspecified', 'totara_reportbuilder');
        }
        if (strpos($code, '_') !== false) {
            list($langcode, $langvariant) = explode('_', $code);
        } else {
            $langcode = $code;
        }

        // Now see if we have a match in "localname (English)" format.
        if (isset($languages[$code])) {
            return $languages[$code];
        } else {
            // Not an installed language - may have been uninstalled, as last resort try the get_list_of_languages silly function.
            $langcodes = $strmgr->get_list_of_languages();
            if (isset($langcodes[$langcode])) {
                $a = new stdClass();
                $a->code = $langcode;
                $a->name = $langcodes[$langcode];
                return get_string('uninstalledlanguage', 'totara_reportbuilder', $a);
            } else {
                return get_string('unknownlanguage', 'totara_reportbuilder', $code);
            }
        }
    }

    function rb_display_user_email($email, $row, $isexport = false) {
        if (empty($email)) {
            return '';
        }
        $maildisplay = $row->maildisplay;
        $emaildisabled = $row->emailstop;

        // respect users email privacy setting
        // at some point we may want to allow admins to view anyway
        if ($maildisplay != 1) {
            return get_string('useremailprivate', 'totara_reportbuilder');
        }

        if ($isexport) {
            return $email;
        } else {
            // obfuscate email to avoid spam if printing to page
            return obfuscate_mailto($email, '', (bool) $emaildisabled);
        }
    }

    public function rb_display_user_email_unobscured($email, $row, $isexport = false) {
        if ($isexport) {
            return $email;
        } else {
            // Obfuscate email to avoid spam if printing to page.
            return obfuscate_mailto($email);
        }
    }

    function rb_display_link_program_icon($program, $row) {
        global $OUTPUT;
        $programid = $row->program_id;
        $programicon = !empty($row->program_icon) ? $row->program_icon : 'default';
        $icon = html_writer::empty_tag('img', array('src' => totara_get_icon($programid, TOTARA_ICON_TYPE_PROGRAM),
            'class' => 'course_icon'));
        $link = $OUTPUT->action_link(
            new moodle_url('/totara/program/view.php', array('id' => $programid)),
            $icon . $program, null, array('class' => 'course_icon')
        );
        return $link;
    }


    // display grade along with passing grade if it is known
    function rb_display_grade_string($item, $row) {
        $passgrade = isset($row->gradepass) ? sprintf('%d', $row->gradepass) : null;
        $usergrade = sprintf('%d', $item);

        if ($item === null) {
            return '';
        } else if ($passgrade === null) {
            return "{$usergrade}%";
        } else {
            $a = new stdClass();
            $a->grade = $usergrade;
            $a->pass = $passgrade;
            return get_string('gradeandgradetocomplete', 'totara_reportbuilder', $a);
        }
    }

    //
    //
    // Generic select filter methods
    //
    //

    function rb_filter_yesno_list() {
        $yn = array();
        $yn[1] = get_string('yes');
        $yn[0] = get_string('no');
        return $yn;
    }

    function rb_filter_modules_list() {
        global $DB, $OUTPUT, $CFG;

        $out = array();
        $mods = $DB->get_records('modules', array('visible' => 1), 'id', 'id, name');
        foreach ($mods as $mod) {
            if (get_string_manager()->string_exists('pluginname', $mod->name)) {
                $modname = get_string('pluginname', $mod->name);
            } else {
                continue;
            }
            if (file_exists($CFG->dirroot . '/mod/' . $mod->name . '/pix/icon.gif') ||
                file_exists($CFG->dirroot . '/mod/' . $mod->name . '/pix/icon.png')) {
                $icon = $OUTPUT->pix_icon('icon', $modname, $mod->name) . '&nbsp;';
            } else {
                $icon = '';
            }

            $out[$mod->name] = $icon . $modname;
        }
        return $out;
    }

    function rb_filter_tags_list() {
        global $DB, $OUTPUT, $CFG;

        return $DB->get_records_menu('tag', array('tagtype' => 'official'), 'name', 'id, name');
    }

    function rb_filter_organisations_list($report) {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/organisation/lib.php');

        $contentmode = $report->contentmode;
        $contentoptions = $report->contentoptions;
        $reportid = $report->_id;

        // show all options if no content restrictions set
        if ($contentmode == REPORT_BUILDER_CONTENT_MODE_NONE) {
            $hierarchy = new organisation();
            $hierarchy->make_hierarchy_list($orgs, null, true, false);
            return $orgs;
        }

        $baseorg = null; // default to top of tree

        $localset = false;
        $nonlocal = false;
        // are enabled content restrictions local or not?
        if (isset($contentoptions) && is_array($contentoptions)) {
            foreach ($contentoptions as $option) {
                $name = $option->classname;
                $classname = 'rb_' . $name . '_content';
                $settingname = $name . '_content';
                if (class_exists($classname)) {
                    if ($name == 'completed_org' || $name == 'current_org') {
                        if (reportbuilder::get_setting($reportid, $settingname,
                            'enable', $report->is_cached())) {
                            $localset = true;
                        }
                    } else {
                        if (reportbuilder::get_setting($reportid, $settingname,
                            'enable', $report->is_cached())) {
                        $nonlocal = true;
                        }
                    }
                }
            }
        }

        if ($contentmode == REPORT_BUILDER_CONTENT_MODE_ANY) {
            if ($localset && !$nonlocal) {
                // only restrict the org list if all content restrictions are local ones
                if ($orgid = $DB->get_field('pos_assignment', 'organisationid', array('userid' => $USER->id))) {
                    $baseorg = $orgid;
                }
            }
        } else if ($contentmode == REPORT_BUILDER_CONTENT_MODE_ALL) {
            if ($localset) {
                // restrict the org list if any content restrictions are local ones
                if ($orgid = $DB->get_field('pos_assignment', 'organisationid', array('userid' => $USER->id))) {
                    $baseorg = $orgid;
                }
            }
        }

        $hierarchy = new organisation();
        $hierarchy->make_hierarchy_list($orgs, $baseorg, true, false);

        return $orgs;

    }

    function rb_filter_positions_list() {
        global $CFG;
        require_once($CFG->dirroot . '/totara/hierarchy/lib.php');
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

        $hierarchy = new position();
        $hierarchy->make_hierarchy_list($positions, null, true, false);

        return $positions;

    }

    function rb_filter_course_categories_list() {
        global $CFG;
        require_once($CFG->libdir . '/coursecatlib.php');
        $cats = coursecat::make_categories_list();

        return $cats;
    }


    function rb_filter_competency_type_list() {
        global $CFG;
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/competency/lib.php');

        $competencyhierarchy = new competency();
        $unclassified_option = array(0 => get_string('unclassified', 'totara_hierarchy'));
        $typelist = $unclassified_option + $competencyhierarchy->get_types_list();

        return $typelist;
    }


    function rb_filter_position_type_list() {
        global $CFG;
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

        $positionhierarchy = new position();
        $unclassified_option = array(0 => get_string('unclassified', 'totara_hierarchy'));
        $typelist = $unclassified_option + $positionhierarchy->get_types_list();

        return $typelist;
    }


    function rb_filter_organisation_type_list() {
        global $CFG;
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/organisation/lib.php');

        $organisationhierarchy = new organisation();
        $unclassified_option = array(0 => get_string('unclassified', 'totara_hierarchy'));
        $typelist = $unclassified_option + $organisationhierarchy->get_types_list();

        return $typelist;
    }

    function rb_filter_course_languages() {
        global $DB;
        $out = array();
        $langs = $DB->get_records_sql("SELECT DISTINCT lang
            FROM {course} ORDER BY lang");
        foreach ($langs as $row) {
            $out[$row->lang] = $this->rb_display_language_code($row->lang, array());
        }

        return $out;
    }

    /**
     *
     * @return array possible course types
     */
    public function rb_filter_course_types() {
        global $TOTARA_COURSE_TYPES;
        $coursetypeoptions = array();
        foreach ($TOTARA_COURSE_TYPES as $k => $v) {
            $coursetypeoptions[$v] = get_string($k, 'totara_core');
        }
        return $coursetypeoptions;
    }

    //
    //
    // Generic grouping methods for aggregation
    //
    //

    function rb_group_count($field) {
        return "COUNT($field)";
    }

    function rb_group_unique_count($field) {
        return "COUNT(DISTINCT $field)";
    }

    function rb_group_sum($field) {
        return "SUM($field)";
    }

    function rb_group_average($field) {
        return "AVG($field)";
    }

    function rb_group_max($field) {
        return "MAX($field)";
    }

    function rb_group_min($field) {
        return "MIN($field)";
    }

    function rb_group_stddev($field) {
        return "STDDEV($field)";
    }

    // can be used to 'fake' a percentage, if matching values return 1 and
    // all other values return 0 or null
    function rb_group_percent($field) {
        global $DB;

        return $DB->sql_cast_char2int("AVG($field)*100.0");
    }

    // return list as single field, separated by commas
    function rb_group_comma_list($field) {
        return sql_group_concat($field);
    }

    // return unique list items as single field, separated by commas
    function rb_group_comma_list_unique($field) {
        return sql_group_concat($field, ', ', true);
    }

    // return list as single field, one per line
    function rb_group_list($field) {
        return sql_group_concat($field, html_writer::empty_tag('br'));
    }

    // return unique list items as single field, one per line
    function rb_group_list_unique($field) {
        return sql_group_concat($field, html_writer::empty_tag('br'), true);
    }

    // return list as single field, separated by a line with - on (in HTML)
    function rb_group_list_dash($field) {
        return sql_group_concat($field, html_writer::empty_tag('br') . '-' . html_writer::empty_tag('br'));
    }

    //
    //
    // Methods for adding commonly used data to source definitions
    //
    //

    //
    // Wrapper functions to add columns/fields/joins in one go
    //
    //

    /**
     * Populate the hierarchymap private variable to look up Hierarchy names from ids
     * e.g. when converting a hierarchy path from ids to human-readable form
     *
     * @param array $hierarchies array of all the hierarchy types we want to populate (pos, org, comp, goal etc)
     *
     * @return boolean True
     */
    function populate_hierarchy_name_map($hierarchies) {
        global $DB;
        foreach ($hierarchies as $hierarchy) {
            $this->hierarchymap["{$hierarchy}"] = $DB->get_records_menu($hierarchy, null, 'id', 'id, fullname');
        }
        return true;
    }

    /**
     * Adds the user table to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'user id' field
     * @param string $field Name of user id field to join on
     * @return boolean True
     */
    protected function add_user_table_to_joinlist(&$joinlist, $join, $field) {

        // join uses 'auser' as name because 'user' is a reserved keyword
        $joinlist[] = new rb_join(
            'auser',
            'LEFT',
            '{user}',
            "auser.id = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );
    }


    /**
     * Adds some common user field to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $join Name of the join that provides the 'user' table
     * @param string $groupname The group to add fields to. If you are defining
     *                          a custom group name, you must define a language
     *                          string with the key "type_{$groupname}" in your
     *                          report source language file.
     *
     * @return True
     */
    protected function add_user_fields_to_columns(&$columnoptions,
        $join='auser', $groupname = 'user') {
        global $DB, $CFG;

        $columnoptions[] = new rb_column_option(
            $groupname,
            'fullname',
            get_string('userfullname', 'totara_reportbuilder'),
            $DB->sql_fullname("$join.firstname", "$join.lastname"),
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'namelink',
            get_string('usernamelink', 'totara_reportbuilder'),
            $DB->sql_fullname("$join.firstname", "$join.lastname"),
            array(
                'joins' => $join,
                'displayfunc' => 'link_user',
                'defaultheading' => get_string('userfullname', 'totara_reportbuilder'),
                'extrafields' => array('user_id' => "$join.id"),
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'namelinkicon',
            get_string('usernamelinkicon', 'totara_reportbuilder'),
            $DB->sql_fullname("$join.firstname", "$join.lastname"),
            array(
                'joins' => $join,
                'displayfunc' => 'link_user_icon',
                'defaultheading' => get_string('userfullname', 'totara_reportbuilder'),
                'extrafields' => array(
                    'user_id' => "$join.id",
                    'userpic_picture' => "$join.picture",
                    'userpic_firstname' => "$join.firstname",
                    'userpic_firstnamephonetic' => "$join.firstnamephonetic",
                    'userpic_middlename' => "$join.middlename",
                    'userpic_lastname' => "$join.lastname",
                    'userpic_lastnamephonetic' => "$join.lastnamephonetic",
                    'userpic_alternatename' => "$join.alternatename",
                    'userpic_email' => "$join.email",
                    'userpic_imagealt' => "$join.imagealt"
                ),
                'style' => array('white-space' => 'nowrap'),
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'email',
            get_string('useremail', 'totara_reportbuilder'),
            // use CASE to include/exclude email in SQL
            // so search won't reveal hidden results
            "CASE WHEN $join.maildisplay <> 1 THEN '-' ELSE $join.email END",
            array(
                'joins' => $join,
                'displayfunc' => 'user_email',
                'extrafields' => array(
                    'emailstop' => "$join.emailstop",
                    'maildisplay' => "$join.maildisplay",
                ),
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )
        );
        // Only include this column if email is among fields allowed
        // by showuseridentity setting.
        if (!empty($CFG->showuseridentity) &&
            in_array('email', explode(',', $CFG->showuseridentity))) {
            $columnoptions[] = new rb_column_option(
                $groupname,
                'emailunobscured',
                get_string('useremailunobscured', 'totara_reportbuilder'),
                "$join.email",
                array(
                    'joins' => $join,
                    'displayfunc' => 'user_email_unobscured',
                    'defaultheading' => get_string('useremail', 'totara_reportbuilder'),
                    // Users must have viewuseridentity to see the
                    // unobscured email address.
                    'capability' => 'moodle/site:viewuseridentity',
                    'dbdatatype' => 'char',
                    'outputformat' => 'text'
                )
            );
        }
        $columnoptions[] = new rb_column_option(
            $groupname,
            'lastlogin',
            get_string('userlastlogin', 'totara_reportbuilder'),
            // See MDL-22481 for why currentlogin is used instead of lastlogin
            "$join.currentlogin",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_date',
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'firstaccess',
            get_string('userfirstaccess', 'totara_reportbuilder'),
            "$join.firstaccess",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_datetime',
            )
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'lang',
            get_string('userlang', 'totara_reportbuilder'),
            "$join.lang",
            array(
                'joins' => $join,
                'displayfunc' => 'language_code',
            )
        );
        // auto-generate columns for user fields
        $fields = array(
            'firstname' => get_string('userfirstname', 'totara_reportbuilder'),
            'firstnamephonetic' => get_string('userfirstnamephonetic', 'totara_reportbuilder'),
            'lastname' => get_string('userlastname', 'totara_reportbuilder'),
            'lastnamephonetic' => get_string('userlastnamephonetic', 'totara_reportbuilder'),
            'username' => get_string('username', 'totara_reportbuilder'),
            'idnumber' => get_string('useridnumber', 'totara_reportbuilder'),
            'phone1' => get_string('userphone', 'totara_reportbuilder'),
            'institution' => get_string('userinstitution', 'totara_reportbuilder'),
            'department' => get_string('userdepartment', 'totara_reportbuilder'),
            'address' => get_string('useraddress', 'totara_reportbuilder'),
            'city' => get_string('usercity', 'totara_reportbuilder'),
        );
        foreach ($fields as $field => $name) {
            $columnoptions[] = new rb_column_option(
                $groupname,
                $field,
                $name,
                "$join.$field",
                array('joins' => $join,
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
            );
        }
        $columnoptions[] = new rb_column_option(
            'user',
            'id',
            get_string('userid', 'totara_reportbuilder'),
            "$join.id",
            array('joins' => $join)
        );

        // add country option
        $columnoptions[] = new rb_column_option(
            $groupname,
            'country',
            get_string('usercountry', 'totara_reportbuilder'),
            "$join.country",
            array(
                'joins' => $join,
                'displayfunc' => 'country_code'
            )
        );

        // add deleted option
        $columnoptions[] = new rb_column_option(
            $groupname,
            'deleted',
            get_string('userstatus', 'totara_reportbuilder'),
            "CASE WHEN $join.deleted = 0 and $join.suspended = 1 THEN 2 ELSE $join.deleted END",
            array(
                'joins' => $join,
                'displayfunc' => 'deleted_status'
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'timecreated',
            get_string('usertimecreated', 'totara_reportbuilder'),
            "$join.timecreated",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_datetime'
            )
        );
        $columnoptions[] = new rb_column_option(
            $groupname,
            'timemodified',
            get_string('usertimemodified', 'totara_reportbuilder'),
            "$join.timemodified",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_datetime'
            )
        );

        return true;
    }


    /**
     * Adds some common user field to the $filteroptions array
     *
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $groupname Name of group to filter. If you are defining
     *                          a custom group name, you must define a language
     *                          string with the key "type_{$groupname}" in your
     *                          report source language file.
     * @return True
     */
    protected function add_user_fields_to_filters(&$filteroptions, $groupname = 'user') {
        // auto-generate filters for user fields
        $fields = array(
            'fullname' => get_string('userfullname', 'totara_reportbuilder'),
            'firstname' => get_string('firstname'),
            'lastname' => get_string('lastname'),
            'username' => get_string('username'),
            'idnumber' => get_string('useridnumber', 'totara_reportbuilder'),
            'phone1' => get_string('userphone', 'totara_reportbuilder'),
            'institution' => get_string('userinstitution', 'totara_reportbuilder'),
            'department' => get_string('userdepartment', 'totara_reportbuilder'),
            'address' => get_string('useraddress', 'totara_reportbuilder'),
            'city' => get_string('usercity', 'totara_reportbuilder'),
            'email' => get_string('useremail', 'totara_reportbuilder'),
        );
        foreach ($fields as $field => $name) {
            $filteroptions[] = new rb_filter_option(
                $groupname,
                $field,
                $name,
                'text'
            );
        }

        // pulldown with list of countries
        $select_width_options = rb_filter_option::select_width_limiter();
        $filteroptions[] = new rb_filter_option(
            $groupname,
            'country',
            get_string('usercountry', 'totara_reportbuilder'),
            'select',
            array(
                'selectchoices' => get_string_manager()->get_list_of_countries(),
                'attributes' => $select_width_options,
                'simplemode' => true,
            )
        );
        $filteroptions[] = new rb_filter_option(
            $groupname,
            'deleted',
            get_string('userstatus', 'totara_reportbuilder'),
            'select',
            array(
                'selectchoices' => array(0 => get_string('activeonly', 'totara_reportbuilder'),
                                         1 => get_string('deletedonly', 'totara_reportbuilder'),
                                         2 => get_string('suspendedonly', 'totara_reportbuilder')),
                'attributes' => $select_width_options,
                'simplemode' => true,
            )
        );

        $filteroptions[] = new rb_filter_option(
            $groupname,
            'lastlogin',
            get_string('userlastlogin', 'totara_reportbuilder'),
            'date',
            array(
                'includetime' => true
            )
        );

        $filteroptions[] = new rb_filter_option(
            $groupname,
            'firstaccess',
            get_string('userfirstaccess', 'totara_reportbuilder'),
            'date',
            array(
                'includetime' => true
            )
        );

        $filteroptions[] = new rb_filter_option(
            $groupname,
            'timecreated',
            get_string('usertimecreated', 'totara_reportbuilder'),
            'date',
            array(
                'includetime' => true
            )
        );

        $filteroptions[] = new rb_filter_option(
            $groupname,
            'timemodified',
            get_string('usertimemodified', 'totara_reportbuilder'),
            'date',
            array(
                'includetime' => true
            )
        );

        return true;
    }


    /**
     * Adds the course table to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'course id' field
     * @param string $field Name of course id field to join on
     * @return boolean True
     */
    protected function add_course_table_to_joinlist(&$joinlist, $join, $field) {

        $joinlist[] = new rb_join(
            'course',
            'LEFT',
            '{course}',
            "course.id = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );
    }


    /**
     * Adds some common course info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $join Name of the join that provides the 'course' table
     *
     * @return True
     */
    protected function add_course_fields_to_columns(&$columnoptions, $join='course') {
        global $DB;

        $columnoptions[] = new rb_column_option(
            'course',
            'fullname',
            get_string('coursename', 'totara_reportbuilder'),
            "$join.fullname",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'courselink',
            get_string('coursenamelinked', 'totara_reportbuilder'),
            "$join.fullname",
            array(
                'joins' => $join,
                'displayfunc' => 'link_course',
                'defaultheading' => get_string('coursename', 'totara_reportbuilder'),
                'extrafields' => array('course_id' => "$join.id",
                                       'course_visible' => "$join.visible",
                                       'course_audiencevisible' => "$join.audiencevisible")
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'courseexpandlink',
            get_string('courseexpandlink', 'totara_reportbuilder'),
            "$join.fullname",
            array(
                'joins' => $join,
                'displayfunc' => 'course_expand',
                'defaultheading' => get_string('coursename', 'totara_reportbuilder'),
                'extrafields' => array('course_id' => "$join.id", 'course_visible' => "$join.visible")
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'courselinkicon',
            get_string('coursenamelinkedicon', 'totara_reportbuilder'),
            "$join.fullname",
            array(
                'joins' => $join,
                'displayfunc' => 'link_course_icon',
                'defaultheading' => get_string('coursename', 'totara_reportbuilder'),
                'extrafields' => array(
                    'course_id' => "$join.id",
                    'course_icon' => "$join.icon",
                    'course_visible' => "$join.visible",
                    'course_audiencevisible' => "$join.audiencevisible"
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'visible',
            get_string('coursevisible', 'totara_reportbuilder'),
            "$join.visible",
            array(
                'joins' => $join,
                'displayfunc' => 'yes_no'
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'audvis',
            get_string('audiencevisibility', 'totara_reportbuilder'),
            "$join.audiencevisible",
            array(
                'joins' => $join,
                'displayfunc' => 'audience_visibility'
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'icon',
            get_string('courseicon', 'totara_reportbuilder'),
            "$join.icon",
            array(
                'joins' => $join,
                'displayfunc' => 'course_icon',
                'defaultheading' => get_string('courseicon', 'totara_reportbuilder'),
                'extrafields' => array(
                    'course_name' => "$join.fullname",
                    'course_id' => "$join.id",
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'shortname',
            get_string('courseshortname', 'totara_reportbuilder'),
            "$join.shortname",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'idnumber',
            get_string('courseidnumber', 'totara_reportbuilder'),
            "$join.idnumber",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'id',
            get_string('courseid', 'totara_reportbuilder'),
            "$join.id",
            array('joins' => $join)
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'startdate',
            get_string('coursestartdate', 'totara_reportbuilder'),
            "$join.startdate",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_date',
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'name_and_summary',
            get_string('coursenameandsummary', 'totara_reportbuilder'),
            // case used to merge even if one value is null
            "CASE WHEN $join.fullname IS NULL THEN " . $DB->sql_compare_text("$join.summary", 1024) . "
                WHEN $join.summary IS NULL THEN $join.fullname
                ELSE " . $DB->sql_concat("$join.fullname", "'" . html_writer::empty_tag('br') . "'",
                    $DB->sql_compare_text("$join.summary", 1024)) . ' END',
            array(
                'joins' => $join,
                'displayfunc' => 'tinymce_textarea',
                'extrafields' => array(
                    'filearea' => '\'summary\'',
                    'component' => '\'course\'',
                    'context' => '\'context_course\'',
                    'recordid' => "$join.id"
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'summary',
            get_string('coursesummary', 'totara_reportbuilder'),
            $DB->sql_compare_text("$join.summary", 1024),
            array(
                'joins' => $join,
                'displayfunc' => 'tinymce_textarea',
                'extrafields' => array(
                    'filearea' => '\'summary\'',
                    'component' => '\'course\'',
                    'context' => '\'context_course\'',
                    'recordid' => "$join.id"
                ),
                'dbdatatype' => 'text',
                'outputformat' => 'text'
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'coursetypeicon',
            get_string('coursetypeicon', 'totara_reportbuilder'),
            "$join.coursetype",
            array(
                'joins' => $join,
                'displayfunc' => 'course_type_icon',
                'defaultheading' => get_string('coursetypeicon', 'totara_reportbuilder'),
            )
        );
        $columnoptions[] = new rb_column_option(
            'course',
            'coursetype',
            get_string('coursetype', 'totara_reportbuilder'),
            "$join.coursetype",
            array(
                'joins' => $join,
                'displayfunc' => 'course_type',
                'defaultheading' => get_string('coursetype', 'totara_reportbuilder'),
            )
        );
        // add language option
        $columnoptions[] = new rb_column_option(
            'course',
            'language',
            get_string('courselanguage', 'totara_reportbuilder'),
            "$join.lang",
            array(
                'joins' => $join,
                'displayfunc' => 'language_code'
            )
        );

        return true;
    }


    /**
     * Adds some common course filters to the $filteroptions array
     *
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_course_fields_to_filters(&$filteroptions) {
        $filteroptions[] = new rb_filter_option(
            'course',
            'fullname',
            get_string('coursename', 'totara_reportbuilder'),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'shortname',
            get_string('courseshortname', 'totara_reportbuilder'),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'idnumber',
            get_string('courseidnumber', 'totara_reportbuilder'),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'visible',
            get_string('coursevisible', 'totara_reportbuilder'),
            'select',
            array(
                'selectchoices' => array(0 => get_string('no'), 1 => get_string('yes')),
                'simplemode' => true
            )
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'startdate',
            get_string('coursestartdate', 'totara_reportbuilder'),
            'date'
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'name_and_summary',
            get_string('coursenameandsummary', 'totara_reportbuilder'),
            'textarea'
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'coursetype',
            get_string('coursetype', 'totara_reportbuilder'),
            'multicheck',
            array(
                'selectfunc' => 'course_types',
                'simplemode' => true,
                'showcounts' => array(
                        'joins' => array("LEFT JOIN {course} coursetype_filter ON base.id = coursetype_filter.id"),
                        'dataalias' => 'coursetype_filter',
                        'datafield' => 'coursetype')
            )
        );
        $filteroptions[] = new rb_filter_option(
            'course',
            'language',
            get_string('courselanguage', 'totara_reportbuilder'),
            'select',
            array(
                'selectfunc' => 'course_languages',
                'attributes' => rb_filter_option::select_width_limiter(),
            )
        );
        return true;
    }

    /**
     * Adds the program table to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'program id' field
     * @param string $field Name of table containing program id field to join on
     * @return boolean True
     */
    protected function add_program_table_to_joinlist(&$joinlist, $join, $field) {

        $joinlist[] = new rb_join(
            'program',
            'LEFT',
            '{prog}',
            "program.id = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );
    }


    /**
     * Adds some common program info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $join Name of the join that provides the 'program' table
     * @param string $langfile Source for translation, totara_program or totara_certification
     *
     * @return True
     */
    protected function add_program_fields_to_columns(&$columnoptions, $join = 'program', $langfile = 'totara_program') {
        global $DB;

        $columnoptions[] = new rb_column_option(
            'prog',
            'fullname',
            get_string('programname', $langfile),
            "$join.fullname",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'shortname',
            get_string('programshortname', $langfile),
            "$join.shortname",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'idnumber',
            get_string('programidnumber', $langfile),
            "$join.idnumber",
            array('joins' => $join,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'id',
            get_string('programid', $langfile),
            "$join.id",
            array('joins' => $join)
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'summary',
            get_string('programsummary', $langfile),
            $DB->sql_compare_text("$join.summary", 1024),
            array(
                'joins' => $join,
                'displayfunc' => 'tinymce_textarea',
                'extrafields' => array(
                    'filearea' => '\'summary\'',
                    'component' => '\'totara_program\'',
                    'context' => '\'context_program\'',
                    'recordid' => "$join.id",
                    'fileid' => 0
                ),
                'dbdatatype' => 'text',
                'outputformat' => 'text'
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'availablefrom',
            get_string('availablefrom', 'totara_program'),
            "$join.availablefrom",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_date'
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'availableuntil',
            get_string('availableuntil', 'totara_program'),
            "$join.availableuntil",
            array(
                'joins' => $join,
                'displayfunc' => 'nice_date'
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'proglinkicon',
            get_string('prognamelinkedicon', $langfile),
            "$join.fullname",
            array(
                'joins' => $join,
                'displayfunc' => 'link_program_icon',
                'defaultheading' => get_string('programname', $langfile),
                'extrafields' => array(
                    'program_id' => "$join.id",
                    'program_icon' => "$join.icon"
                )
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'progexpandlink',
            get_string('programexpandlink', $langfile),
            "$join.fullname",
            array(
                'joins' => $join,
                'displayfunc' => 'program_expand',
                'defaultheading' => get_string('programname', $langfile),
                'extrafields' => array('prog_id' => "$join.id", 'prog_visible' => "$join.visible",
                    'prog_certifid' => "$join.certifid")
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'visible',
            get_string('programvisible', 'totara_program'),
            "$join.visible",
            array(
                'joins' => $join,
                'displayfunc' => 'yes_no'
            )
        );
        $columnoptions[] = new rb_column_option(
            'prog',
            'audvis',
            get_string('audiencevisibility', 'totara_reportbuilder'),
            "$join.audiencevisible",
            array(
                'joins' => $join,
                'displayfunc' => 'audience_visibility'
            )
        );
        return true;
    }

    /**
     * Adds some common program filters to the $filteroptions array
     *
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $langfile Source for translation, totara_program or totara_certification
     * @return True
     */
    protected function add_program_fields_to_filters(&$filteroptions, $langfile = 'totara_program') {
        $filteroptions[] = new rb_filter_option(
            'prog',
            'fullname',
            get_string('programname', $langfile),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'prog',
            'shortname',
            get_string('programshortname', $langfile),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'prog',
            'idnumber',
            get_string('programidnumber', $langfile),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'prog',
            'summary',
            get_string('programsummary', $langfile),
            'textarea'
        );
        $filteroptions[] = new rb_filter_option(
            'prog',
            'availablefrom',
            get_string('availablefrom', 'totara_program'),
            'date'
        );
        $filteroptions[] = new rb_filter_option(
            'prog',
            'availableuntil',
            get_string('availableuntil', 'totara_program'),
            'date'
        );
        return true;
    }


    /**
     * Adds the course_category table to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include course_category
     * @param string $join Name of the join that provides the 'course' table
     * @param string $field Name of category id field to join on
     * @return boolean True
     */
    protected function add_course_category_table_to_joinlist(&$joinlist,
        $join, $field) {

        $joinlist[] = new rb_join(
            'course_category',
            'LEFT',
            '{course_categories}',
            "course_category.id = $join.$field",
            REPORT_BUILDER_RELATION_MANY_TO_ONE,
            $join
        );

        return true;
    }


    /**
     * Adds some common course category info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $catjoin Name of the join that provides the
     *                        'course_categories' table
     * @param string $coursejoin Name of the join that provides the
     *                           'course' table
     * @return True
     */
    protected function add_course_category_fields_to_columns(&$columnoptions,
        $catjoin='course_category', $coursejoin='course') {
        $columnoptions[] = new rb_column_option(
                'course_category',
                'name',
                get_string('coursecategory', 'totara_reportbuilder'),
                "$catjoin.name",
                array('joins' => $catjoin,
                      'dbdatatype' => 'char',
                      'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
                'course_category',
                'namelink',
                get_string('coursecategorylinked', 'totara_reportbuilder'),
                "$catjoin.name",
                array(
                    'joins' => $catjoin,
                    'displayfunc' => 'link_course_category',
                    'defaultheading' => get_string('category', 'totara_reportbuilder'),
                    'extrafields' => array('cat_id' => "$catjoin.id", 'cat_visible' => "$catjoin.visible")
                )
        );
        $columnoptions[] = new rb_column_option(
                'course_category',
                'id',
                get_string('coursecategoryid', 'totara_reportbuilder'),
                "$coursejoin.category",
                array('joins' => $coursejoin)
        );
        return true;
    }


    /**
     * Adds some common course category filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_course_category_fields_to_filters(&$filteroptions) {
        $filteroptions[] = new rb_filter_option(
            'course_category',
            'id',
            get_string('coursecategory', 'totara_reportbuilder'),
            'select',
            array(
                'selectfunc' => 'course_categories_list',
                'attributes' => rb_filter_option::select_width_limiter(),
            )
        );
        return true;
    }


    /**
     * Adds the pos_assignment, pos and org tables to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the 'user' table
     * @param string $field Name of user id field to join on
     * @return boolean True
     */
    protected function add_position_tables_to_joinlist(&$joinlist,
        $join, $field) {

        global $CFG;

        // to get access to position type constants
        require_once($CFG->dirroot . '/totara/hierarchy/prefix/position/lib.php');

        $joinlist[] =new rb_join(
            'position_assignment',
            'LEFT',
            '{pos_assignment}',
            "(position_assignment.userid = $join.$field AND " .
            'position_assignment.type = ' . POSITION_TYPE_PRIMARY . ')',
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        $joinlist[] = new rb_join(
            'organisation',
            'LEFT',
            '{org}',
            'organisation.id = position_assignment.organisationid',
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'position_assignment'
        );

        $joinlist[] = new rb_join(
            'position',
            'LEFT',
            '{pos}',
            'position.id = position_assignment.positionid',
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            'position_assignment'
        );

        $joinlist[] = new rb_join(
                'pos_type',
                'LEFT',
                '{pos_type}',
                'position.typeid = pos_type.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'position'
        );

        $joinlist[] = new rb_join(
                'org_type',
                'LEFT',
                '{org_type}',
                'organisation.typeid = org_type.id',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'organisation'
        );

        return true;
    }


    /**
     * Adds some common user position info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $posassign Name of the join that provides the
     *                          'pos_assignment' table.
     * @param string $org Name of the join that provides the 'org' table.
     * @param string $pos Name of the join that provides the 'pos' table.
     *
     * @return True
     */
    protected function add_position_fields_to_columns(&$columnoptions,
        $posassign='position_assignment',
        $org='organisation', $pos='position') {

        $columnoptions[] = new rb_column_option(
            'user',
            'organisationid',
            get_string('usersorgid', 'totara_reportbuilder'),
            "$posassign.organisationid",
            array('joins' => $posassign, 'selectable' => false)
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'organisationidnumber',
            get_string('usersorgidnumber', 'totara_reportbuilder'),
            "$org.idnumber",
            array('joins' => $org,
                  'selectable' => true,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'organisationpath',
            get_string('usersorgpathids', 'totara_reportbuilder'),
            "$org.path",
            array('joins' => $org, 'selectable' => false)
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'organisation',
            get_string('usersorgname', 'totara_reportbuilder'),
            "$org.fullname",
            array('joins' => $org,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'org_type',
            get_string('organisationtype', 'totara_reportbuilder'),
            'org_type.fullname',
            array(
                'joins' => 'org_type',
                'dbdatatype' => 'char',
                'outputformat' => 'text'
            )
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'org_type_id',
            get_string('organisationtypeid', 'totara_reportbuilder'),
            'organisation.typeid',
            array('joins' => $org, 'selectable' => false)
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'positionid',
            get_string('usersposid', 'totara_reportbuilder'),
            "$posassign.positionid",
            array('joins' => $posassign, 'selectable' => false)
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'positionidnumber',
            get_string('usersposidnumber', 'totara_reportbuilder'),
            "$pos.idnumber",
            array('joins' => $pos,
                  'selectable' => true,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'positionpath',
            get_string('userspospathids', 'totara_reportbuilder'),
            "$pos.path",
            array('joins' => $pos, 'selectable' => false)
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'position',
            get_string('userspos', 'totara_reportbuilder'),
            "$pos.fullname",
            array('joins' => $pos,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'pos_type',
            get_string('positiontype', 'totara_reportbuilder'),
            'pos_type.fullname',
            array(
                'joins' => 'pos_type',
                 'dbdatatype' => 'char',
                'outputformat' => 'text'
            )
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'pos_type_id',
            get_string('positiontypeid', 'totara_reportbuilder'),
            'position.typeid',
            array('joins' => $pos, 'selectable' => false)
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'title',
            get_string('usersjobtitle', 'totara_reportbuilder'),
            "$posassign.fullname",
            array('joins' => $posassign,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'posstartdate',
            get_string('posstartdate', 'totara_reportbuilder'),
            "$posassign.timevalidfrom",
            array('joins' => $posassign, 'displayfunc' => 'nice_date')
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'posenddate',
            get_string('posenddate', 'totara_reportbuilder'),
            "$posassign.timevalidto",
            array('joins' => $posassign, 'displayfunc' => 'nice_date')
        );
        return true;
    }


    /**
     * Adds some common user position filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_position_fields_to_filters(&$filteroptions) {
        $filteroptions[] = new rb_filter_option(
            'user',
            'title',
            get_string('usersjobtitle', 'totara_reportbuilder'),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'user',
            'organisationid',
            get_string('participantscurrentorgbasic', 'totara_reportbuilder'),
            'select',
            array(
                'selectfunc' => 'organisations_list',
                'attributes' => rb_filter_option::select_width_limiter(),
            )
        );
        $filteroptions[] = new rb_filter_option(
            'user',
            'organisationpath',
            get_string('participantscurrentorg', 'totara_reportbuilder'),
            'hierarchy',
            array(
                'hierarchytype' => 'org',
            )
        );
        $filteroptions[] = new rb_filter_option(
            'user',
            'positionid',
            get_string('participantscurrentposbasic', 'totara_reportbuilder'),
            'select',
            array(
                'selectfunc' => 'positions_list',
                'attributes' => rb_filter_option::select_width_limiter(),
            )
        );
        $filteroptions[] = new rb_filter_option(
            'user',
            'positionpath',
            get_string('participantscurrentpos', 'totara_reportbuilder'),
            'hierarchy',
            array(
                'hierarchytype' => 'pos',
            )
        );
        $filteroptions[] = new rb_filter_option(
                'user',
                'pos_type_id',
                get_string('positiontype', 'totara_reportbuilder'),
                'select',
                array(
                    'selectfunc' => 'position_type_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
        );
        $filteroptions[] = new rb_filter_option(
                'user',
                'posstartdate',
                get_string('posstartdate', 'totara_reportbuilder'),
                'date'
        );
        $filteroptions[] = new rb_filter_option(
                'user',
                'posenddate',
                get_string('posenddate', 'totara_reportbuilder'),
                'date'
        );
        $filteroptions[] = new rb_filter_option(
                'user',
                'org_type_id',
                get_string('organisationtype', 'totara_reportbuilder'),
                'select',
                array(
                    'selectfunc' => 'organisation_type_list',
                    'attributes' => rb_filter_option::select_width_limiter(),
                )
        );

        return true;
    }

    /**
     * Converts a list to an array given a list and a separator
     * duplicate values are ignored
     *
     * Example;
     * list_to_array('some-thing-some', '-'); =>
     * array('some' => 'some', 'thing' => 'thing');
     *
     * @param string $list List of items
     * @param string $sep Symbol or string that separates list items
     * @return array $result array of list items
     */
    function list_to_array($list, $sep) {
        $result = array();
        $base = explode($sep, $list);
        if (!empty($base)) {
            $result = array_combine($base, $base);
        }
        return $result;
    }

    /**
     * Generic function for adding custom fields to the reports
     * Intentionally optimized into one function to reduce number of db queries
     *
     * @param string $cf_prefix - prefix for custom field table e.g. everything before '_info_field' or '_info_data'
     * @param string $join - join table in joinlist used as a link to main query
     * @param string $joinfield - joinfield in data table used to link with main table
     * @param array $joinlist - array of joins passed by reference
     * @param array $columnoptions - array of columnoptions, passed by reference
     * @param array $filteroptions - array of filters, passed by reference
     */
    protected function add_custom_fields_for($cf_prefix, $join, $joinfield,
        array &$joinlist, array &$columnoptions, array &$filteroptions) {

        global $CFG, $DB;

        $seek = false;
        foreach ($joinlist as $object) {
            $seek = ($object->name == $join);
            if ($seek) {
                break;
            }
        }

        if ($join == 'base') {
            $seek = 'base';
        }

        if (!$seek) {
            $a = new stdClass();
            $a->join = $join;
            $a->source = get_class($this);
            throw new ReportBuilderException(get_string('error:missingdependencytable', 'totara_reportbuilder', $a));
        }

        // Build the table names for this sort of custom field data.
        $fieldtable = $cf_prefix.'_info_field';
        $datatable = $cf_prefix.'_info_data';

        // Check if there are any visible custom fields of this type.
        if ($cf_prefix == 'user') {
            // For user fields include them all - below we require moodle/user:update to actually display the column.
            $items = $DB->get_recordset($fieldtable);
        } else {
            $items = $DB->get_recordset($fieldtable, array('hidden' => '0'));
        }

        if (empty($items)) {
            $items->close();
            return false;
        }

        foreach ($items as $record) {
            $id   = $record->id;
            $joinname = "{$cf_prefix}_{$id}";
            $value = "custom_field_{$id}";
            $name = isset($record->fullname) ? $record->fullname : $record->name;
            $column_options = array('joins' => $joinname);
            // If profile field isn't available to everyone require a capability to display the column.
            if ($cf_prefix == 'user' && $record->visible != PROFILE_VISIBLE_ALL) {
                $column_options['capability'] = 'moodle/user:update';
            }
            $filtertype = 'text'; // default filter type
            $filter_options = array();

            $columnsql = $DB->sql_compare_text("{$joinname}.data", 1024);

            if ($record->datatype == 'multiselect') {
                $filtertype = 'multicheck';

                require_once($CFG->dirroot . '/totara/customfield/definelib.php');
                require_once($CFG->dirroot . '/totara/customfield/field/multiselect/field.class.php');
                require_once($CFG->dirroot . '/totara/customfield/field/multiselect/define.class.php');

                $cfield = new customfield_define_multiselect();
                $cfield->define_load_preprocess($record);
                $filter_options['concat'] = true;
                $filter_options['simplemode'] = true;

                $joinlist[] = new rb_join(
                        $joinname,
                        'LEFT',
                        '(SELECT '.sql_group_concat(sql_cast2char('cfidp.value'), '|', true).' AS data,
                                 cfid.'.$joinfield.' AS joinid, '.sql_cast2char('cfid.data').' AS jsondata
                            FROM {'.$datatable.'} cfid
                            LEFT JOIN {'.$datatable.'_param} cfidp ON (cfidp.dataid = cfid.id)
                           WHERE cfid.fieldid = '.$id.'
                           GROUP BY cfid.'.$joinfield.', '.sql_cast2char('cfid.data').')',
                        "$joinname.joinid = {$join}.id ",
                        REPORT_BUILDER_RELATION_ONE_TO_ONE,
                        $join
                    );

                $columnoptions[] = new rb_column_option(
                        $cf_prefix,
                        $value.'_icon',
                        get_string('multiselectcolumnicon', 'totara_customfield', $name),
                        "$joinname.data",
                        array('joins' => $joinname,
                              'displayfunc' => 'customfield_multiselect_icon',
                              'extrafields' => array(
                                  "{$cf_prefix}_{$value}_icon_json" => "{$joinname}.jsondata"
                              ),
                              'defaultheading' => $name
                        )
                    );

                $columnoptions[] = new rb_column_option(
                        $cf_prefix,
                        $value.'_text',
                        get_string('multiselectcolumntext', 'totara_customfield', $name),
                        "$joinname.data",
                        array('joins' => $joinname,
                              'displayfunc' => 'customfield_multiselect_text',
                              'extrafields' => array(
                                  "{$cf_prefix}_{$value}_text_json" => "{$joinname}.jsondata"
                              ),
                              'defaultheading' => $name
                        )
                    );

                $selectchoices = array();
                foreach ($record->multiselectitem as $selectchoice) {
                    $selectchoices[md5($selectchoice['option'])] = $selectchoice['option'];
                }
                $filter_options['selectchoices'] = $selectchoices;
                $filter_options['showcounts'] = array(
                        'joins' => array(
                                "LEFT JOIN (SELECT id, {$joinfield} FROM {{$cf_prefix}_info_data} " .
                                            "WHERE fieldid = {$id}) {$cf_prefix}_idt_{$id} " .
                                       "ON base_{$cf_prefix}_idt_{$id} = {$cf_prefix}_idt_{$id}.{$joinfield}",
                                "LEFT JOIN {{$cf_prefix}_info_data_param} {$cf_prefix}_idpt_{$id} " .
                                       "ON {$cf_prefix}_idt_{$id}.id = {$cf_prefix}_idpt_{$id}.dataid"),
                        'basefields' => array("{$join}.id AS base_{$cf_prefix}_idt_{$id}"),
                        'dependency' => $join,
                        'dataalias' => "{$cf_prefix}_idpt_{$id}",
                        'datafield' => "value");
                $filteroptions[] = new rb_filter_option(
                        $cf_prefix,
                        $value.'_text',
                        get_string('multiselectcolumntext', 'totara_customfield', $name),
                        $filtertype,
                        $filter_options
                    );

                $iconselectchoices = array();
                foreach ($record->multiselectitem as $selectchoice) {
                    $iconselectchoices[md5($selectchoice['option'])] =
                            customfield_multiselect::get_item_string($selectchoice['option'], $selectchoice['icon'], 'list-icon');
                }
                $filter_options['selectchoices'] = $iconselectchoices;
                $filter_options['showcounts'] = array(
                        'joins' => array(
                                "LEFT JOIN (SELECT id, {$joinfield} FROM {{$cf_prefix}_info_data} " .
                                            "WHERE fieldid = {$id}) {$cf_prefix}_idi_{$id} " .
                                       "ON base_{$cf_prefix}_idi_{$id} = {$cf_prefix}_idi_{$id}.{$joinfield}",
                                "LEFT JOIN {{$cf_prefix}_info_data_param} {$cf_prefix}_idpi_{$id} " .
                                       "ON {$cf_prefix}_idi_{$id}.id = {$cf_prefix}_idpi_{$id}.dataid"),
                        'basefields' => array("{$join}.id AS base_{$cf_prefix}_idi_{$id}"),
                        'dependency' => $join,
                        'dataalias' => "{$cf_prefix}_idpi_{$id}",
                        'datafield' => "value");
                $filteroptions[] = new rb_filter_option(
                        $cf_prefix,
                        $value.'_icon',
                        get_string('multiselectcolumnicon', 'totara_customfield', $name),
                        $filtertype,
                        $filter_options
                    );
                continue;
            }

            switch ($record->datatype) {
                case 'file':
                    $column_options['displayfunc'] = 'customfield_file';
                    $column_options['extrafields'] = array(
                            "{$cf_prefix}_custom_field_{$id}_itemid" => "{$joinname}.id"
                    );
                    break;

                case 'textarea':
                    $filtertype = 'textarea';
                    if ($cf_prefix == 'user') {
                        $column_options['displayfunc'] = 'userfield_textarea';
                    } else {
                        $column_options['displayfunc'] = 'customfield_textarea';
                    }
                    $column_options['extrafields'] = array(
                            "{$cf_prefix}_custom_field_{$id}_itemid" => "{$joinname}.id"
                    );
                    $column_options['dbdatatype'] = 'text';
                    $column_options['outputformat'] = 'text';
                    break;

                case 'menu':
                    $filtertype = 'select';
                    $filter_options['selectchoices'] = $this->list_to_array($record->param1,"\n");
                    $filter_options['simplemode'] = true;
                    $column_options['dbdatatype'] = 'text';
                    $column_options['outputformat'] = 'text';
                    break;

                case 'checkbox':
                    $default = $record->defaultdata;
                    $columnsql = $DB->sql_cast_char2int($columnsql, true);
                    $columnsql = "CASE WHEN {$columnsql} IS NULL THEN {$default} ELSE {$columnsql} END";
                    $filtertype = 'select';
                    $filter_options['selectchoices'] = array(0 => get_string('no'), 1 => get_string('yes'));
                    $filter_options['simplemode'] = true;
                    $column_options['displayfunc'] = 'yes_no';
                    break;

                case 'datetime':
                    $filtertype = 'date';
                    $columnsql = $DB->sql_cast_char2int($columnsql, true);
                    if ($record->param3) {
                        $column_options['displayfunc'] = 'nice_datetime';
                        $filter_options['includetime'] = true;
                    } else {
                        $column_options['displayfunc'] = 'nice_date';
                    }
                    break;

                case 'text':
                    $column_options['dbdatatype'] = 'text';
                    $column_options['outputformat'] = 'text';
                    break;

                default:
                    // Unsupported customfields.
                    continue 2;
            }

            $joinlist[] = new rb_join(
                    $joinname,
                    'LEFT',
                    "{{$datatable}}",
                    "{$joinname}.{$joinfield} = {$join}.id AND {$joinname}.fieldid = {$id}",
                    REPORT_BUILDER_RELATION_ONE_TO_ONE,
                    $join
                );
            $columnoptions[] = new rb_column_option(
                    $cf_prefix,
                    $value,
                    $name,
                    $columnsql,
                    $column_options
                );

            if ($record->datatype == 'file') {
                // No filter options for files yet.
                continue;
            } else {
                $filteroptions[] = new rb_filter_option(
                        $cf_prefix,
                        $value,
                        $name,
                        $filtertype,
                        $filter_options
                    );
            }

        }

        $items->close();

        return true;

    }

    /**
     * Adds user custom fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @param string $basetable
     * @return boolean
     */
    protected function add_custom_user_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions, $basetable = 'auser') {
        return $this->add_custom_fields_for('user',
                                            $basetable,
                                            'userid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }


    /**
     * Adds course custom fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @param string $basetable
     * @return boolean
     */
    protected function add_custom_course_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions, $basetable = 'course') {
        return $this->add_custom_fields_for('course',
                                            $basetable,
                                            'courseid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }

    /**
     * Adds course custom fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @param string $basetable
     * @return boolean
     */
    protected function add_custom_prog_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions, $basetable = 'prog') {
        return $this->add_custom_fields_for('prog',
                                            $basetable,
                                            'programid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }

    /**
     * Adds custom organisation fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @return boolean
     */
    protected function add_custom_organisation_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions) {
        return $this->add_custom_fields_for('org_type',
                                            'organisation',
                                            'organisationid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }

    /**
     * Adds custom goal fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @return boolean
     */
    protected function add_custom_goal_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions) {
        return $this->add_custom_fields_for('goal_type',
                                            'goal',
                                            'goalid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);
    }


    /**
     * Adds custom position fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @return boolean
     */
    protected function add_custom_position_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions) {
        return $this->add_custom_fields_for('pos_type',
                                            'position',
                                            'positionid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);

    }


    /**
     * Adds custom competency fields to the report
     *
     * @param array $joinlist
     * @param array $columnoptions
     * @param array $filteroptions
     * @return boolean
     */
    protected function add_custom_competency_fields(array &$joinlist, array &$columnoptions,
        array &$filteroptions) {
        return $this->add_custom_fields_for('comp_type',
                                            'competency',
                                            'competencyid',
                                            $joinlist,
                                            $columnoptions,
                                            $filteroptions);

    }

    /**
     * Adds the manager_role_assignment and manager tables to the $joinlist
     * array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'position_assignment' table
     * @param string $field Name of reportstoid field to join on
     * @return boolean True
     */
    protected function add_manager_tables_to_joinlist(&$joinlist,
        $join, $field) {

        global $CFG;

        // only include these joins if the manager role is defined
        if ($managerroleid = $CFG->managerroleid) {
            $joinlist[] = new rb_join(
                'manager_role_assignment',
                'LEFT',
                '{role_assignments}',
                "(manager_role_assignment.id = $join.$field" .
                    ' AND manager_role_assignment.roleid = ' .
                    $managerroleid . ')',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'position_assignment'
            );
            $joinlist[] = new rb_join(
                'manager',
                'LEFT',
                '{user}',
                'manager.id = manager_role_assignment.userid',
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                'manager_role_assignment'
            );
        }

        return true;
    }


    /**
     * Adds some common user manager info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $manager Name of the join that provides the
     *                          'manager' table.
     * @param string $org Name of the join that provides the 'org' table.
     * @param string $pos Name of the join that provides the 'pos' table.
     *
     * @return True
     */
    protected function add_manager_fields_to_columns(&$columnoptions,
        $manager='manager') {
        global $DB;

        $columnoptions[] = new rb_column_option(
            'user',
            'managername',
            get_string('usersmanagername', 'totara_reportbuilder'),
            $DB->sql_fullname("$manager.firstname", "$manager.lastname"),
            array('joins' => $manager,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'managerfirstname',
            get_string('usersmanagerfirstname', 'totara_reportbuilder'),
            "$manager.firstname",
            array('joins' => $manager,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'managerlastname',
            get_string('usersmanagerlastname', 'totara_reportbuilder'),
            "$manager.lastname",
            array('joins' => $manager,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'managerid',
            get_string('usersmanagerid', 'totara_reportbuilder'),
            "$manager.id",
            array('joins' => $manager)
        );
        $columnoptions[] = new rb_column_option(
            'user',
            'manageridnumber',
            get_string('usersmanageridnumber', 'totara_reportbuilder'),
            "$manager.idnumber",
            array('joins' => $manager,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );
        return true;
    }


    /**
     * Adds some common manager filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_manager_fields_to_filters(&$filteroptions) {
        $filteroptions[] = new rb_filter_option(
            'user',
            'managername',
            get_string('managername', 'totara_reportbuilder'),
            'text'
        );
        $filteroptions[] = new rb_filter_option(
            'user',
            'managerid',
            get_string('usersmanagerid', 'totara_reportbuilder'),
            'number'
        );
        $filteroptions[] = new rb_filter_option(
            'user',
            'manageridnumber',
            get_string('usersmanageridnumber', 'totara_reportbuilder'),
            'text'
        );
        return true;
    }


    /**
     * Adds the tag tables to the $joinlist array
     *
     * @param string $type tag itemtype
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     $type table
     * @param string $field Name of course id field to join on
     * @return boolean True
     */
    protected function add_tag_tables_to_joinlist($type, &$joinlist, $join, $field) {

        global $DB;

        $joinlist[] = new rb_join(
            'tagids',
            'LEFT',
            // subquery as table name
            "(SELECT til.id AS tilid, " .
                sql_group_concat(sql_cast2char('t.id'), '|') .
                " AS idlist FROM {{$type}} til
                LEFT JOIN {tag_instance} ti
                    ON til.id = ti.itemid AND ti.itemtype = '{$type}'
                LEFT JOIN {tag} t
                    ON ti.tagid = t.id AND t.tagtype = 'official'
                GROUP BY til.id)",
            "tagids.tilid = {$join}.{$field}",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        $joinlist[] = new rb_join(
            'tagnames',
            'LEFT',
            // subquery as table name
            "(SELECT tnl.id AS tnlid, " .
                sql_group_concat(sql_cast2char('t.name'), ', ') .
                " AS namelist FROM {{$type}} tnl
                LEFT JOIN {tag_instance} ti
                    ON tnl.id = ti.itemid AND ti.itemtype = '{$type}'
                LEFT JOIN {tag} t
                    ON ti.tagid = t.id AND t.tagtype = 'official'
                GROUP BY tnl.id)",
            "tagnames.tnlid = {$join}.{$field}",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        // create a join for each official tag
        $tags = $DB->get_records('tag', array('tagtype' => 'official'));
        foreach ($tags as $tag) {
            $tagid = $tag->id;
            $name = "{$type}_tag_$tagid";
            $joinlist[] = new rb_join(
                $name,
                'LEFT',
                '{tag_instance}',
                "($name.itemid = $join.$field AND $name.tagid = $tagid " .
                    "AND $name.itemtype = '{$type}')",
                REPORT_BUILDER_RELATION_ONE_TO_ONE,
                $join
            );
        }

        return true;
    }


    /**
     * Adds some common tag info to the $columnoptions array
     *
     * @param string $type tag itemtype
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $tagids name of the join that provides the 'tagids' table.
     * @param string $tagnames name of the join that provides the 'tagnames' table.
     *
     * @return True
     */
    protected function add_tag_fields_to_columns($type, &$columnoptions, $tagids='tagids', $tagnames='tagnames') {
        global $DB;

        $columnoptions[] = new rb_column_option(
            'tags',
            'tagids',
            get_string('tagids', 'totara_reportbuilder'),
            "$tagids.idlist",
            array('joins' => $tagids, 'selectable' => false)
        );
        $columnoptions[] = new rb_column_option(
            'tags',
            'tagnames',
            get_string('tags', 'totara_reportbuilder'),
            "$tagnames.namelist",
            array('joins' => $tagnames,
                  'dbdatatype' => 'char',
                  'outputformat' => 'text')
        );

        // create a on/off field for every official tag
        $tags = $DB->get_records('tag', array('tagtype' => 'official'));
        foreach ($tags as $tag) {
            $tagid = $tag->id;
            $name = $tag->name;
            $join = "{$type}_tag_$tagid";
            $columnoptions[] = new rb_column_option(
                'tags',
                $join,
                get_string('taggedx', 'totara_reportbuilder', $name),
                "CASE WHEN $join.id IS NOT NULL THEN 1 ELSE 0 END",
                array(
                    'joins' => $join,
                    'displayfunc' => 'yes_no',
                )
            );
        }
        return true;
    }


    /**
     * Adds some common tag filters to the $filteroptions array
     *
     * @param string $type tag itemtype
     * @param array &$filteroptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_tag_fields_to_filters($type, &$filteroptions) {
        global $DB;

        // create a yes/no filter for every official tag
        $tags = $DB->get_records('tag', array('tagtype' => 'official'));
        foreach ($tags as $tag) {
            $tagid = $tag->id;
            $name = $tag->name;
            $join = "{$type}_tag_{$tagid}";
            $filteroptions[] = new rb_filter_option(
                'tags',
                $join,
                get_string('taggedx', 'totara_reportbuilder', $name),
                'select',
                array(
                    'selectchoices' => array(1 => get_string('yes'), 0 => get_string('no')),
                    'simplemode' => true,
                )
            );
        }

        // create a tag list selection filter
        $filteroptions[] = new rb_filter_option(
            'tags',         // type
            'tagids',           // value
            get_string('tags', 'totara_reportbuilder'), // label
            'multicheck',     // filtertype
            array(            // options
                'selectchoices' => $this->rb_filter_tags_list(),
                'concat' => true, // Multicheck filter needs to know that we are working with concatenated values
                'showcounts' => array(
                        'joins' => array("LEFT JOIN (SELECT ti.itemid, ti.tagid FROM {{$type}} base " .
                                                      "LEFT JOIN {tag_instance} ti ON base.id = ti.itemid " .
                                                            "AND ti.itemtype = '{$type}'" .
                                                      "LEFT JOIN {tag} tag ON ti.tagid = tag.id " .
                                                            "AND tag.tagtype = 'official')\n {$type}_tagids_filter " .
                                                "ON base.id = {$type}_tagids_filter.itemid"),
                        'dataalias' => $type.'_tagids_filter',
                        'datafield' => 'tagid')
            )
        );
        return true;
    }


    /**
     * Adds the cohort user tables to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'user' table
     * @param string $field Name of user id field to join on
     * @return boolean True
     */
    protected function add_cohort_user_tables_to_joinlist(&$joinlist,
                                                          $join, $field) {

        $joinlist[] = new rb_join(
            'cohortuser',
            'LEFT',
            // subquery as table name
            "(SELECT cm.userid AS userid, " .
                sql_group_concat(sql_cast2char('cm.cohortid'),'|', true) .
                " AS idlist FROM {cohort_members} cm
                GROUP BY cm.userid)",
            "cohortuser.userid = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        return true;
    }

    /**
     * Adds the cohort course tables to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     'course' table
     * @param string $field Name of course id field to join on
     * @return boolean True
     */
    protected function add_cohort_course_tables_to_joinlist(&$joinlist,
                                                            $join, $field) {

        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $joinlist[] = new rb_join(
            'cohortenrolledcourse',
            'LEFT',
            // subquery as table name
            "(SELECT courseid AS course, " .
                sql_group_concat(sql_cast2char('customint1'), '|', true) .
                " AS idlist FROM {enrol} e
                WHERE e.enrol = 'cohort'
                GROUP BY courseid)",
            "cohortenrolledcourse.course = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        return true;
    }


    /**
     * Adds the cohort program tables to the $joinlist array
     *
     * @param array &$joinlist Array of current join options
     *                         Passed by reference and updated to
     *                         include new table joins
     * @param string $join Name of the join that provides the
     *                     table containing the program id
     * @param string $field Name of program id field to join on
     * @return boolean True
     */
    protected function add_cohort_program_tables_to_joinlist(&$joinlist,
                                                             $join, $field) {

        global $CFG;
        require_once($CFG->dirroot . '/cohort/lib.php');

        $joinlist[] = new rb_join(
            'cohortenrolledprogram',
            'LEFT',
            // subquery as table name
            "(SELECT programid AS program, " .
                sql_group_concat(sql_cast2char('assignmenttypeid'), '|', true) .
                " AS idlist FROM {prog_assignment} pa
                WHERE assignmenttype = " . ASSIGNTYPE_COHORT . "
                GROUP BY programid)",
            "cohortenrolledprogram.program = $join.$field",
            REPORT_BUILDER_RELATION_ONE_TO_ONE,
            $join
        );

        return true;
    }


    /**
     * Adds some common cohort user info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $cohortids Name of the join that provides the
     *                          'cohortuser' table.
     *
     * @return True
     */
    protected function add_cohort_user_fields_to_columns(&$columnoptions,
                                                         $cohortids='cohortuser') {

        $columnoptions[] = new rb_column_option(
            'cohort',
            'usercohortids',
            get_string('usercohortids', 'totara_reportbuilder'),
            "$cohortids.idlist",
            array('joins' => $cohortids, 'selectable' => false)
        );

        return true;
    }


    /**
     * Adds some common cohort course info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $cohortenrolledids Name of the join that provides the
     *                          'cohortenrolledcourse' table.
     *
     * @return True
     */
    protected function add_cohort_course_fields_to_columns(&$columnoptions, $cohortenrolledids='cohortenrolledcourse') {
        $columnoptions[] = new rb_column_option(
            'cohort',
            'enrolledcoursecohortids',
            get_string('enrolledcoursecohortids', 'totara_reportbuilder'),
            "$cohortenrolledids.idlist",
            array('joins' => $cohortenrolledids, 'selectable' => false)
        );

        return true;
    }


    /**
     * Adds some common cohort program info to the $columnoptions array
     *
     * @param array &$columnoptions Array of current column options
     *                              Passed by reference and updated by
     *                              this method
     * @param string $cohortenrolledids Name of the join that provides the
     *                          'cohortenrolledprogram' table.
     *
     * @return True
     */
    protected function add_cohort_program_fields_to_columns(&$columnoptions, $cohortenrolledids='cohortenrolledprogram') {
        $columnoptions[] = new rb_column_option(
            'cohort',
            'enrolledprogramcohortids',
            get_string('enrolledprogramcohortids', 'totara_reportbuilder'),
            "$cohortenrolledids.idlist",
            array('joins' => $cohortenrolledids, 'selectable' => false)
        );

        return true;
    }

    /**
     * Adds some common user cohort filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_cohort_user_fields_to_filters(&$filteroptions) {

        $filteroptions[] = new rb_filter_option(
            'cohort',
            'usercohortids',
            get_string('userincohort', 'totara_reportbuilder'),
            'cohort'
        );
        return true;
    }

    /**
     * Adds some common course cohort filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_cohort_course_fields_to_filters(&$filteroptions) {

        $filteroptions[] = new rb_filter_option(
            'cohort',
            'enrolledcoursecohortids',
            get_string('courseenrolledincohort', 'totara_reportbuilder'),
            'cohort'
        );

        return true;
    }


    /**
     * Adds some common program cohort filters to the $filteroptions array
     *
     * @param array &$columnoptions Array of current filter options
     *                              Passed by reference and updated by
     *                              this method
     * @return True
     */
    protected function add_cohort_program_fields_to_filters(&$filteroptions) {

        $filteroptions[] = new rb_filter_option(
            'cohort',
            'enrolledprogramcohortids',
            get_string('programenrolledincohort', 'totara_reportbuilder'),
            'cohort'
        );

        return true;
    }

    /**
     * @return array
     */
    protected function define_columnoptions() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_filteroptions() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_defaultcolumns() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_defaultfilters() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_contentoptions() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_paramoptions() {
        return array();
    }

    /**
     * @return array
     */
    protected function define_requiredcolumns() {
        return array();
    }

    /**
     * Called after parameters have been read, allows the source to configure source title, additional tables, etc.
     *
     * post_config() can add extra data to the query by adding to joinlist and columnoptions
     * or by calling the following method on the $report object:
     * {@link $report->set_post_config_restrictions()}    Extra WHERE clause
     *
     * If post_config fails and needs to redirect or execute some other code then return an object.
     *
     * @param array $params
     * @return object
     */
    public function post_config(reportbuilder $report) {
    }

    /**
     * Returns an array of js objects that need to be included with this report.
     *
     * @return array(object)
     */
    public function get_required_jss() {
        return array();
    }

} // end of rb_base_source class
