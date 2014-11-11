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
 * @author Ben Lobo <ben.lobo@kineo.com>
 * @package totara
 * @subpackage program
 */

require_once($CFG->libdir . '/coursecatlib.php');
require_once($CFG->libdir . '/datalib.php');
require_once($CFG->libdir . '/ddllib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/totara/program/program.class.php');
require_once($CFG->dirroot . '/totara/certification/lib.php'); // For the constants

/**
 * Can logged in user view user's required learning
 *
 * @access  public
 * @param   int     $learnerid   Learner's id
 * @return  boolean
 */
function prog_can_view_users_required_learning($learnerid) {
    global $USER;

    if (!isloggedin()) {
        return false;
    }

    $systemcontext = context_system::instance();

    // If the user can view any programs
    if (has_capability('totara/program:accessanyprogram', $systemcontext)) {
        return true;
    }

    // If the user cannot view any programs
    if (!has_capability('totara/program:viewprogram', $systemcontext)) {
        return false;
    }

    // If this is the current user's own required learning
    if ($learnerid == $USER->id) {
        return true;
    }

    // If this user is their manager
    if (totara_is_manager($learnerid)) {
        return true;
    }

    return false;
}

/**
 * Return a list of a user's programs or a count
 *
 * @global object $DB
 * @param int $userid
 * @param string $sort The order in which to sort the programs
 * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set).
 * @param int $limitnum return a subset comprising this many records (optional, required if $limitfrom is set).
 * @param bool $returncount Whether to return a count of the number of records found or the records themselves
 * @param bool $showhidden Whether to include hidden programs in records returned
 * @param bool $onlyrequiredlearning Only return required learning programs
 * @return array|int
 */
function prog_get_all_programs($userid, $sort = '', $limitfrom = '', $limitnum = '', $returncount = false, $showhidden = false,
        $onlyrequiredlearning = false) {
    global $DB;

    // Construct sql query.
    $count = 'SELECT COUNT(*) ';
    $select = 'SELECT p.*, p.fullname AS progname, pc.timedue AS duedate ';
    list($insql, $params) = $DB->get_in_or_equal(array(PROGRAM_EXCEPTION_RAISED, PROGRAM_EXCEPTION_DISMISSED),
            SQL_PARAMS_QM, 'param', false);
    $from = "FROM {prog} p
            INNER JOIN {prog_completion} pc ON p.id = pc.programid AND pc.coursesetid = 0
            INNER JOIN (SELECT DISTINCT userid, programid FROM {prog_user_assignment}
            WHERE exceptionstatus {$insql}) pua
            ON (pc.programid = pua.programid AND pc.userid = pua.userid)";

    $where = "WHERE pc.userid = ?
            AND pc.status <> ?";
    if ($onlyrequiredlearning) {
        $where .= " AND p.certifid IS NULL";
    }

    $params[] = $userid;
    $params[] = STATUS_PROGRAM_COMPLETE;
    if (!$showhidden) {
        $where .= " AND p.visible = ?";
        $params[] = 1;
    }

    if ($returncount) {
        return $DB->count_records_sql($count.$from.$where, $params);
    } else {
        return $DB->get_records_sql($select.$from.$where.$sort, $params, $limitfrom, $limitnum);
    }
}

/**
 * Return a list of a user's required learning programs or a count
 *
 * @global object $DB
 * @param int $userid
 * @param string $sort The order in which to sort the programs
 * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set).
 * @param int $limitnum return a subset comprising this many records (optional, required if $limitfrom is set).
 * @param bool $returncount Whether to return a count of the number of records found or the records themselves
 * @param bool $showhidden Whether to include hidden programs in records returned
 * @return array|int
 */
function prog_get_required_programs($userid, $sort='', $limitfrom='', $limitnum='', $returncount=false, $showhidden=false) {
    return prog_get_all_programs($userid, $sort, $limitfrom, $limitnum, $returncount, $showhidden, true);
}

/**
 * Return a list of a user's certification programs or a count
 *
 * @global object $DB
 * @param int $userid
 * @param string $sort SQL fragment to order the programs
 * @param int $limitfrom return a subset of records, starting at this point (optional, required if $limitnum is set).
 * @param int $limitnum return a subset comprising this many records (optional, required if $limitfrom is set).
 * @param bool $returncount Whether to return a count of the number of records found or the records themselves
 * @param bool $showhidden Whether to include hidden programs in records returned
 * @param bool $activeonly Whether to restrict to only active programs (programs where "Progress" is not "Complete")
 * @return array|int
 */
function prog_get_certification_programs($userid, $sort='', $limitfrom='', $limitnum='', $returncount=false, $showhidden=false,
        $activeonly=false) {
    global $DB;

    $params = array();
    $params['contextlevel'] = CONTEXT_PROGRAM;
    $params['userid'] = $userid;
    $params['comptype'] = CERTIFTYPE_PROGRAM;

    list($exceptionsql, $exceptionparams) = $DB->get_in_or_equal(array(PROGRAM_EXCEPTION_RAISED, PROGRAM_EXCEPTION_DISMISSED),
                                                                                        SQL_PARAMS_NAMED, 'exception', false);
    $params = array_merge($params, $exceptionparams);

    // Construct sql query
    $count = 'SELECT COUNT(*) ';
    $select = 'SELECT p.id, p.fullname, p.fullname AS progname, pc.timedue AS duedate, cfc.certifpath, cfc.status, cfc.timeexpires ';
    $from = "FROM {prog} p
            INNER JOIN {context} ctx ON (p.id = ctx.instanceid AND ctx.contextlevel =:contextlevel)
            INNER JOIN {prog_completion} pc ON p.id = pc.programid
                    AND pc.coursesetid = 0
                    AND pc.userid = :userid
            INNER JOIN {certif} cf ON cf.id = p.certifid
            INNER JOIN {certif_completion} cfc ON cfc.certifid = cf.id
                    AND cfc.userid = pc.userid ";

    $where = 'WHERE 1=1 ';
    // Is the user assigned? Exists is more efficient than using distinct.
    $where .= "AND EXISTS (SELECT userid
                            FROM {prog_user_assignment} pua
                            WHERE pua.programid = pc.programid
                            AND pua.userid = pc.userid
                            AND pua.exceptionstatus {$exceptionsql})";

    list($visibilitysql, $visibilityparams) = totara_visibility_where($userid,
                                                                      'p.id',
                                                                      'p.visible',
                                                                      'p.audiencevisible',
                                                                      'p',
                                                                      'certification');

    $params = array_merge($params, $visibilityparams);

    $where .= " AND {$visibilitysql} ";

    if (!$showhidden) {
        $where .= "AND p.visible = :visible ";
        $params['visible'] = 1;
    }

    if ($activeonly) {
        // This should only show non complete certifications and due/expired recertifications.
        $where .= "AND (cfc.status <> :cstatus OR cfc.renewalstatus <> :rstatus) ";
        $params['cstatus'] = CERTIFSTATUS_COMPLETED;
        $params['rstatus'] = CERTIFRENEWALSTATUS_NOTDUE;
    }

    if ($returncount) {
        return $DB->count_records_sql($count.$from.$where, $params);
    } else {
        return $DB->get_records_sql($select.$from.$where.$sort, $params, $limitfrom, $limitnum);
    }
}

/**
 * Return markup for displaying a table of a specified user's required programs
 * (i.e. programs that have been automatically assigned to the user)
 *
 * This includes hidden programs but excludes unavailable programs
 *
 * @access  public
 * @param   int     $userid     Program assignee
 * @return  string
 */
function prog_display_required_programs($userid) {
    global $CFG, $OUTPUT;

    $count = prog_get_required_programs($userid, '', '', '', true, true);

    // Set up table
    $tablename = 'progs-list';
    $tableheaders = array(get_string('programname', 'totara_program'));
    $tablecols = array('progname');

    // Due date
    $tableheaders[] = get_string('duedate', 'totara_program');
    $tablecols[] = 'duedate';

    // Progress
    $tableheaders[] = get_string('progress', 'totara_program');
    $tablecols[] = 'progress';

    $baseurl = $CFG->wwwroot . '/totara/program/required.php?userid='.$userid;

    $table = new flexible_table($tablename);
    $table->define_headers($tableheaders);
    $table->define_columns($tablecols);
    $table->define_baseurl($baseurl);
    $table->set_attribute('class', 'fullwidth generalbox');
    $table->set_control_variables(array(
        TABLE_VAR_SORT    => 'tsort',
    ));
    $table->sortable(true);
    $table->no_sorting('progress');

    $table->setup();
    $table->pagesize(15, $count);
    $sort = $table->get_sql_sort();
    $sort = empty($sort) ? '' : ' ORDER BY '.$sort;

    // Add table data
    $programs = prog_get_required_programs($userid, $sort, $table->get_page_start(), $table->get_page_size(), false, true);

    if (!$programs) {
        return '';
    }
    $rowcount = 0;
    foreach ($programs as $p) {
        $program = new program($p->id);
        if (!$program->is_accessible()) {
            continue;
        }
        $row = array();
        $row[] = $program->display_summary_widget($userid);
        $row[] = $program->display_duedate($p->duedate, $userid);
        $row[] = $program->display_progress($userid);
        $table->add_data($row);
        $rowcount++;
    }

    unset($programs);

    if ($rowcount > 0) {
        //2.2 flexible_table class no longer supports $table->data and echos directly on each call to add_data
        ob_start();
        $table->finish_html();
        $out = ob_get_contents();
        ob_end_clean();
        return $out;
    } else {
        return '';
    }
}

/**
 * Return markup for displaying a table of a specified user's certification programs
 * This includes hidden programs but excludes unavailable programs
 *
 * @param   int $userid     Program assignee
 * @return  string
 */
function prog_display_certification_programs($userid) {

    $count = prog_get_certification_programs($userid, '', '', '', true, true, true);

    // Set up table
    $tablename = 'progs-list';
    $tableheaders = array(get_string('certificationname', 'totara_program'));
    $tablecols = array('progname');

    // Due date
    $tableheaders[] = get_string('duedate', 'totara_program');
    $tablecols[] = 'duedate';

    // Progress
    $tableheaders[] = get_string('progress', 'totara_program');
    $tablecols[] = 'progress';

    $baseurl = new moodle_url('/totara/program/required.php', array('userid' => $userid));

    $table = new flexible_table($tablename);
    $table->define_headers($tableheaders);
    $table->define_columns($tablecols);
    $table->define_baseurl($baseurl);
    $table->set_attribute('class', 'fullwidth generalbox');
    $table->set_control_variables(array(
        TABLE_VAR_SORT    => 'tsort',
    ));
    $table->sortable(true);
    $table->no_sorting('progress');

    $table->setup();
    $table->pagesize(15, $count);
    $sort = $table->get_sql_sort();
    $sort = empty($sort) ? '' : ' ORDER BY '.$sort;

    // Add table data
    $cprograms = prog_get_certification_programs($userid, $sort, $table->get_page_start(), $table->get_page_size(),
            false, true, true);

    if (!$cprograms) {
        return '';
    }

    $rowcount = 0;
    foreach ($cprograms as $cp) {
        $program = new program($cp->id);
        if (!$program->is_accessible()) {
            continue;
        }
        $row = array();
        $row[] = $program->display_summary_widget($userid);
        if (!empty($cp->timeexpires)) {
            $row[] = $program->display_duedate($cp->timeexpires, $userid, $cp->certifpath, $cp->status);
        } else {
            $row[] = $program->display_duedate($cp->duedate, $userid, $cp->certifpath, $cp->status);
        }
        $row[] = $program->display_progress($userid);
        $table->add_data($row);
        $rowcount++;
    }

    unset($cprograms);

    if ($rowcount > 0) {
        //2.2 flexible_table class no longer supports $table->data and echos directly on each call to add_data
        ob_start();
        $table->finish_html();
        $out = ob_get_contents();
        ob_end_clean();
        return $out;
    } else {
        return '';
    }
}

/**
 * Display the user message box
 *
 * @access public
 * @param  int    $programuser the id of the user
 * @return string $out      the display code
 */
function prog_display_user_message_box($programuser) {
    global $CFG, $PAGE, $DB;
    $user = $DB->get_record('user', array('id' => $programuser));
    if (!$user) {
        return false;
    }
    $user->courseid = 1;

    $a = new stdClass();
    $a->name = fullname($user);
    $a->userid = $programuser;
    $a->site = $CFG->wwwroot;

    $renderer = $PAGE->get_renderer('totara_program');
    $out = $renderer->display_user_message_box($user, $a);
    return $out;
}

/**
 * Add lowest levels of breadcrumbs to program
 *
 * @return void
 */
function prog_add_base_navlinks() {
    global $PAGE;

    $PAGE->navbar->add(get_string('browsecategories', 'totara_program'), new moodle_url('/totara/program/index.php'));
}

/**
 * Add lowest levels of breadcrumbs to required learning
 *
 * Exact links added depends on if the require learning being viewed belongs
 * to the current user or not.
 *
 * @param array &$navlinks The navlinks array to update (passed by reference)
 * @param integer $userid ID of the required learning's owner
 *
 * @return boolean True if it is the user's own required learning
 */
function prog_add_required_learning_base_navlinks($userid) {
    global $USER, $PAGE, $DB;

    // the user is viewing their own learning
    if ($userid == $USER->id) {
        $PAGE->navbar->add(get_string('mylearning', 'totara_core'), '/my/');
        $PAGE->navbar->add(get_string('requiredlearning', 'totara_program'), new moodle_url('/totara/program/required.php'));
        return true;
    }

    // the user is viewing someone else's learning
    $user = $DB->get_record('user', array('id' => $userid));
    if ($user) {
        $PAGE->navbar->add(get_string('myteam', 'totara_core'), new moodle_url('/my/teammembers.php'));
        $PAGE->navbar->add(get_string('xsrequiredlearning', 'totara_program', fullname($user)), new moodle_url('/totara/program/required.php', array('userid' => $userid)));
    } else {
        $PAGE->navbar->add(get_string('unknownusersrequiredlearning', 'totara_program'), new moodle_url('/totara/program/required.php', array('userid' => $userid)));
    }

    return true;
}

/**
 * Returns list of programs, for whole site, or category
 *
 * Note: Cannot use p.* in $fields because MSSQL does not handle DISTINCT text fields.
 * See T-11732
 */
function prog_get_programs($categoryid="all", $sort="p.sortorder ASC",
                           $fields="p.id, p.category, p.sortorder, p.shortname, p.fullname, p.visible, p.icon, p.audiencevisible",
                           $type = 'program', $options = array()) {
    global $USER, $DB, $CFG;
    require_once($CFG->dirroot . '/totara/cohort/lib.php');

    $offset = !empty($options['offset']) ? $options['offset'] : 0;
    $limit = !empty($options['limit']) ? $options['limit'] : null;

    $params = array('contextlevel' => CONTEXT_PROGRAM);
    if ((int)$categoryid > 0) {
        $certifsql = ($type == 'program') ? " AND p.certifid IS NULL" : " AND p.certifid IS NOT NULL";
        $categoryselect = "WHERE p.category = :category {$certifsql}";
        $params['category'] = (int)$categoryid;
    } else if ($categoryid === "all") {
        // Returns all programs for Program Overview reportbuilder.
        $categoryselect = "";
    } else {
        return array();
    }

    if (empty($sort)) {
        $sortstatement = "";
    } else {
        $sortstatement = "ORDER BY $sort";
    }

    // Manage visibility.
    list($visibilitysql, $visibilityparams) = totara_visibility_where($USER->id,
                                                                        'p.id',
                                                                        'p.visible',
                                                                        'p.audiencevisible',
                                                                        'p',
                                                                        $type);
    $params = array_merge($params, $visibilityparams);

    // Pull out all programs matching the category.
    $programs = $DB->get_records_sql("SELECT DISTINCT {$fields},
                                    ctx.id AS ctxid, ctx.path AS ctxpath,
                                    ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel
                                    FROM {prog} p
                                    JOIN {context} ctx
                                      ON (p.id = ctx.instanceid
                                          AND ctx.contextlevel = :contextlevel)
                                    {$categoryselect} AND {$visibilitysql}
                                    {$sortstatement}", $params, $offset, $limit);

    return $programs;
}

/**
 * Gets the path of breadcrumbs for a category path matching $categoryid
 *
 * @param integer $categoryid The id of the current category
 * @param string $viewtype Type of the page
 * @return array Multidimensional array containing name, link, and type of breadcrumbs
 *
 */
function prog_get_category_breadcrumbs($categoryid, $viewtype = 'program') {
    global $CFG, $DB;

    $category = $DB->get_record('course_categories', array('id' => $categoryid));

    if (strpos($category->path, '/') === false) {
        return array();
    }

    $bread = explode('/', substr($category->path, 1));
    list($breadinsql, $params) = $DB->get_in_or_equal($bread);
    $sql = "SELECT id, name FROM {course_categories} WHERE id {$breadinsql} ORDER BY depth";
    $cat_bread = array();

    if ($bread_info = $DB->get_records_sql($sql, $params)) {
        foreach ($bread_info as $crumb) {
            $cat_bread[] = array('name' => format_string($crumb->name),
                                 'link' => new moodle_url("/totara/program/index.php",
                                                 array('categoryid' => $crumb->id,
                                                       'viewtype' => $viewtype)),
                                 'type' => 'misc');

        }
    }
    return $cat_bread;
}

/**
 * Returns list of courses and programs, for whole site, or category
 * (This is the counterpart to get_courses_page in /lib/datalib.php)
 *
 * Similar to prog_get_programs, but allows paging
 *
 */
function prog_get_programs_page($categoryid="all", $sort="sortorder ASC",
                          $fields="p.id,p.sortorder,p.shortname,p.fullname,p.summary,p.visible",
                          &$totalcount, $limitfrom="", $limitnum="", $type = 'program') {

    global $DB;

    $params = array(CONTEXT_PROGRAM);
    $categoryselect = "";
    if ($categoryid != "all" && is_numeric($categoryid)) {
        $categoryselect = " AND p.category = ? ";
        $params[] = $categoryid;
    }

    $typesql = '';
    if ($type == 'program') {
        $typesql = " p.certifid IS NULL"; // Filter out certifications.
    } else {
        $typesql = " p.certifid IS NOT NULL";
    }

    // Visibility.
    list($visibilitysql, $visibilityparams) = totara_visibility_where(null, 'p.id', 'p.visible', 'p.audiencevisible', 'p', $type);
    $params = array_merge($params, $visibilityparams);

    // Pull out all programs matching the cat.
    $visibleprograms = array();

    $progselect = "SELECT $fields, 'program' AS listtype,
                          ctx.id AS ctxid, ctx.path AS ctxpath,
                          ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel
                   FROM {prog} p
                   JOIN {context} ctx ON (p.id = ctx.instanceid AND ctx.contextlevel = ?)
                   WHERE {$typesql} AND {$visibilitysql}";

    $select = $progselect.$categoryselect.' ORDER BY '.$sort;

    $rs = $DB->get_recordset_sql($select, $params);

    $totalcount = 0;

    if (!$limitfrom) {
        $limitfrom = 0;
    }

    // Iteration will have to be done inside loop to keep track of the limitfrom and limitnum.
    foreach ($rs as $program) {
        $totalcount++;
        if ($totalcount > $limitfrom && (!$limitnum or count($visibleprograms) < $limitnum)) {
            $visibleprograms [] = $program;
        }
    }

    $rs->close();

    return $visibleprograms;
}

/**
 * Efficiently moves many programs around while maintaining
 * sortorder in order.
 * (This is the counterpart to move_courses in /course/lib.php)
 *
 * $programids is an array of program ids
 *
 **/
function prog_move_programs($programids, $categoryid) {
    global $DB, $OUTPUT;

    if (!empty($programids)) {

            $programids = array_reverse($programids);

            foreach ($programids as $programid) {

                if (!$program  = $DB->get_record("prog", array("id" => $programid))) {
                    echo $OUTPUT->notification(get_string('error:findingprogram', 'totara_program'));
                } else {
                    // figure out a sortorder that we can use in the destination category
                    $sortorder = $DB->get_field_sql('SELECT MIN(sortorder)-1 AS min
                                                     FROM {prog}
                                                     WHERE category = ?', array($categoryid));
                    if (is_null($sortorder) || $sortorder === false) {
                        // the category is empty
                        // rather than let the db default to 0
                        // set it to > 100 and avoid extra work in fix_program_sortorder()
                        $sortorder = 200;
                    } else if ($sortorder < 10) {
                        prog_fix_program_sortorder($categoryid);
                    }

                    $program->category  = $categoryid;
                    $program->sortorder = $sortorder;

                    if (!$DB->update_record('prog', $program)) {
                        echo $OUTPUT->notification(get_string('error:prognotmoved', 'totara_program'));
                    }

                    $context   = context_program::instance($program->id);
                    $newparent = context_coursecat::instance($program->category);
                    $context->update_moved($newparent);
                }
            }
            prog_fix_program_sortorder();
        }
    return true;
}

/**
 * This recursive function makes sure that the program order is consecutive
 * (This is the counterpart to fix_course_sortorder in /lib/datalib.php)
 *
 * $n is the starting point, offered only for compatilibity -- will be ignored!
 * $safe (bool) prevents it from assuming category-sortorder is unique, used to upgrade
 * safely from 1.4 to 1.5
 *
 * @global <type> $CFG
 * @param <type> $categoryid
 * @param <type> $n
 * @param <type> $safe
 * @param <type> $depth
 * @param <type> $path
 * @return <type>
 */
function prog_fix_program_sortorder($categoryid=0, $n=0, $safe=0, $depth=0, $path='') {

    global $DB;

    $counters = new stdClass();
    $counters->programcount = 0;
    $counters->certifcount = 0;
    $count = 0;

    $catgap    = 1000; // "standard" category gap
    $tolerance = 200;  // how "close" categories can get

    if ($categoryid > 0){
        // update depth and path
        $cat   = $DB->get_record('course_categories', array('id' => $categoryid));
        if ($cat->parent == 0) {
            $depth = 0;
            $path  = '';
        } else if ($depth == 0 ) { // doesn't make sense; get from DB
            // this is only called if the $depth parameter looks dodgy
            $parent = $DB->get_record('course_categories', array('id' => $cat->parent));
            $path  = $parent->path;
            $depth = $parent->depth;
        }
        $path  = $path . '/' . $categoryid;
        $depth = $depth + 1;

        if ($cat->path !== $path) {
            $DB->set_field('course_categories', 'path', $path, array('id' => $categoryid));
        }
        if ($cat->depth != $depth) {
            $DB->set_field('course_categories', 'depth', $depth, array('id' => $categoryid));
        }
    }

    // get some basic info about programs in the category
    $info = $DB->get_record_sql('SELECT MIN(sortorder) AS min,
                                        MAX(sortorder) AS max,
                                        COUNT(sortorder) AS count,
                                        COALESCE(SUM(CASE WHEN certifid IS NULL THEN 1 ELSE 0 END),0) AS programcount,
                                        COALESCE(SUM(CASE WHEN certifid IS NULL THEN 0 ELSE 1 END),0) AS certifcount
                                   FROM {prog}
                                  WHERE category = ?', array($categoryid));
    if (is_object($info)) { // no courses?
        $max   = $info->max;
        $counters->programcount = $info->programcount;
        $counters->certifcount = $info->certifcount;
        $count = $info->count;
        $min   = $info->min;
        unset($info);
    }

    if ($categoryid > 0 && $n == 0) { // only passed category so don't shift it
        $n = $min;
    }

    // $hasgap flag indicates whether there's a gap in the sequence
    $hasgap    = false;
    if ($max-$min+1 != $count) {
        $hasgap = true;
    }

    // $mustshift indicates whether the sequence must be shifted to
    // meet its range
    $mustshift = false;
    if ($min < $n-$tolerance || $min > $n+$tolerance+$catgap ) {
        $mustshift = true;
    }

    // actually sort only if there are programs,
    // and we meet one ofthe triggers:
    //  - safe flag
    //  - they are not in a continuos block
    //  - they are too close to the 'bottom'
    if ($count && ( $safe || $hasgap || $mustshift ) ) {
        // special, optimized case where all we need is to shift
        if ($mustshift && !$safe && !$hasgap) {
            $shift = $n + $catgap - $min;
            if ($shift < $count) {
                $shift = $count + $catgap;
            }

            $DB->execute("UPDATE {prog}
                          SET sortorder = sortorder + ?
                          WHERE category = ?", array($shift, $categoryid));
            $n = $n + $catgap + $count;

        } else { // do it slowly
            $n = $n + $catgap;
            // if the new sequence overlaps the current sequence, lack of transactions
            // will stop us -- shift things aside for a moment...
            if ($safe || ($n >= $min && $n+$count+1 < $min && $DB->get_dbfamily() === 'mysql')) {
                $shift = $max + $n + 1000;
                $DB->execute("UPDATE {prog}
                              SET sortorder = sortorder+$shift
                              WHERE category = ?". array($categoryid));
            }

            $programs = prog_get_programs($categoryid, 'p.sortorder ASC', 'p.id,p.sortorder');

            $transaction = $DB->start_delegated_transaction();

            $tx = true; // transaction sanity
            foreach ($programs as $program) {
                if ($tx && $program->sortorder != $n ) { // save db traffic
                    $tx = $tx && $DB->set_field('prog', 'sortorder', $n, array('id' => $program->id));
                }
                $n++;
            }
            if ($tx) {
                $transaction->allow_commit();
            } else {
                if (!$safe) {
                    // if we failed when called with !safe, try
                    // to recover calling self with safe=true
                    return prog_fix_program_sortorder($categoryid, $n, true, $depth, $path);
                }
            }
        }
    }
    if ($categoryid) {
        $counters->id = $categoryid;
        $DB->update_record('course_categories', $counters);
    }

    // $n could need updating
    $max = $DB->get_field_sql("SELECT MAX(sortorder)
                               FROM {prog}
                               WHERE category = ?", array($categoryid));
    if ($max > $n) {
        $n = $max;
    }

    if ($categories = coursecat::get($categoryid)->get_children()) {
        foreach ($categories as $category) {
            $n = prog_fix_program_sortorder($category->id, $n, $safe, $depth, $path);
        }
    }

    return $n+1;
}

/**
 * Checks whether or not a user should have access to a course that belongs to a
 * program in the user's required learning. If so, the user will be automatically
 * enrolled onto the course as a student.
 *
 * @global object $CFG
 * @param object $user
 * @param object $course
 * @return object $result containing properties:
 *         'enroled' (boolean: whether user is enroled on the course)
 *         'notify' (boolean: whether a new enrolment has been made so notify user)
 *         'program' (string: name of program they have obtained access through)
 */
function prog_can_enter_course($user, $course) {
    global $DB;

    $result = new stdClass();
    $result->enroled = false;
    $result->notify = false;
    $result->program = null;

    $studentrole = get_archetype_roles('student');
    if (empty($studentrole)) {
        return $result;
    }
    $studentrole = reset($studentrole);

    // Get programs containing this course that this user is assigned to, either via learning plans or required learning
    $get_programs = "
        SELECT p.id, p.fullname, p.available
          FROM {prog} p
          WHERE p.available = ?
          AND (
              p.id IN
              (
                SELECT DISTINCT pc.programid
                  FROM {dp_plan_program_assign} pc
            INNER JOIN {dp_plan} pln ON pln.id = pc.planid
             LEFT JOIN {prog_courseset} pcs ON pc.programid = pcs.programid
             LEFT JOIN {prog_courseset_course} pcsc ON pcs.id = pcsc.coursesetid AND pcsc.courseid = ?
                 WHERE pc.approved >= ?
                   AND pln.userid = ?
                   AND pln.status = ?
             )
            OR p.id IN
             (
                SELECT DISTINCT pua.programid
                  FROM {prog_user_assignment} pua
             LEFT JOIN {prog_completion} pc
                    ON pua.programid = pc.programid AND pua.userid = pc.userid
             LEFT JOIN {prog_courseset} pcs ON pua.programid = pcs.programid
             LEFT JOIN {prog_courseset_course} pcsc ON pcs.id = pcsc.coursesetid AND pcsc.courseid = ?
                 WHERE pua.userid = ?
                   AND pc.coursesetid = ?
                   AND (pc.timedue = ?
                        OR pc.status <> ? )
             ))
    ";
    $params = array(AVAILABILITY_TO_STUDENTS, $course->id, DP_APPROVAL_APPROVED, $user->id, DP_PLAN_STATUS_APPROVED, $course->id, $user->id, 0, COMPLETION_TIME_NOT_SET, STATUS_PROGRAM_COMPLETE);
    $program_records = $DB->get_records_sql($get_programs, $params);

    if (!empty($program_records)) {
        //get program enrolment plugin class
        $program_plugin = enrol_get_plugin('totara_program');
        foreach ($program_records as $program_record) {
            $program = new program($program_record->id);
            if ($program->is_accessible() && $program->can_enter_course($user->id, $course->id)) {
                //check if program enrolment plugin is enabled on this course
                //should be added when coursesets are created but just in case we'll double-check
                $instance = $program_plugin->get_instance_for_course($course->id);
                if (!$instance) {
                    //add it
                    $instanceid = $program_plugin->add_instance($course);
                    $instance = $DB->get_record('enrol', array('id' => $instanceid));
                }
                //check if user is already enroled under the program plugin
                if (!$ue = $DB->get_record('user_enrolments', array('enrolid' => $instance->id, 'userid' => $user->id))) {
                    //enrol them
                    $program_plugin->enrol_user($instance, $user->id, $studentrole->id);
                    $result->enroled = true;
                    $result->notify = true;
                    $result->program = $program->fullname;
                } else {
                    //already enroled
                    $result->enroled = true;
                }
                return $result;
            }
        }
    }
    return $result;
}


/**
 * A list of programs that match a search
 *
 * @uses $DB, $USER
 * @param array $searchterms Terms to search
 * @param string $sort Sort order of the records
 * @param int $page
 * @param int $recordsperpage
 * @param int $totalcount Passed in by reference.
 * @param string $type Are we looking for programs or certifications
 * @return object {@link $COURSE} records
 */
function prog_get_programs_search($searchterms, $sort='fullname ASC', $page=0, $recordsperpage=50, &$totalcount, $type = 'program') {
    global $DB;

    $REGEXP    = $DB->sql_regex(true);
    $NOTREGEXP = $DB->sql_regex(false);

    $fullnamesearch = '';
    $summarysearch = '';
    $idnumbersearch = '';
    $shortnamesearch = '';

    $fullnamesearchparams = array();
    $summarysearchparams = array();
    $idnumbersearchparams = array();
    $shortnamesearchparams = array();
    $params = array();

    foreach ($searchterms as $searchterm) {
        if ($fullnamesearch) {
            $fullnamesearch .= ' AND ';
        }
        if ($summarysearch) {
            $summarysearch .= ' AND ';
        }
        if ($idnumbersearch) {
            $idnumbersearch .= ' AND ';
        }
        if ($shortnamesearch) {
            $shortnamesearch .= ' AND ';
        }

        if (substr($searchterm,0,1) == '+') {
            $searchterm      = substr($searchterm,1);
            $summarysearch  .= " p.summary $REGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $fullnamesearch .= " p.fullname $REGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $idnumbersearch  .= " p.idnumber $REGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $shortnamesearch  .= " p.shortname $REGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
        } else if (substr($searchterm,0,1) == "-") {
            $searchterm      = substr($searchterm,1);
            $summarysearch  .= " p.summary $NOTREGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $fullnamesearch .= " p.fullname $NOTREGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $idnumbersearch .= " p.idnumber $NOTREGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
            $shortnamesearch .= " p.shortname $NOTREGEXP '(^|[^a-zA-Z0-9])$searchterm([^a-zA-Z0-9]|$)' ";
        } else {
            $summaryparam = rb_unique_param('summary');
            $summarysearch .= $DB->sql_like('summary', ":{$summaryparam}", false, true, false) . ' ';
            $summarysearchparams[$summaryparam] = '%' . $searchterm . '%';

            $fullnameparam = rb_unique_param('fullname');
            $fullnamesearch .= $DB->sql_like('fullname', ":{$fullnameparam}", false, true, false) . ' ';
            $fullnamesearchparams[$fullnameparam] = '%' . $searchterm . '%';

            $idnumberparam = rb_unique_param('idnumber');
            $idnumbersearch .= $DB->sql_like('idnumber', ":{$idnumberparam}", false, true, false) . ' ';
            $idnumbersearchparams[$idnumberparam] = '%' . $searchterm . '%';

            $shortnameparam = rb_unique_param('shortname');
            $shortnamesearch .= $DB->sql_like('shortname', ":{$shortnameparam}", false, true, false) . ' ';
            $shortnamesearchparams[$shortnameparam] = '%' . $searchterm . '%';
        }
    }

    // If search terms supplied, include in where.
    if (count($searchterms)) {
        $where = "
            WHERE (( $fullnamesearch ) OR ( $summarysearch ) OR ( $idnumbersearch ) OR ( $shortnamesearch ))
            AND category > 0
        ";
        $params = array_merge($params, $fullnamesearchparams, $summarysearchparams, $idnumbersearchparams, $shortnamesearchparams);
    } else {
        // Otherwise return everything.
        $where = " WHERE category > 0 ";
    }

    if ($type == 'program') {
        $where .= " AND p.certifid IS NULL"; // Filter out certifications.
    } else {
        $where .= " AND p.certifid IS NOT NULL";
    }

    // Add visibility query.
    list($visibilitysql, $visibilityparams) = totara_visibility_where(null, 'p.id', 'p.visible', 'p.audiencevisible', 'p', $type);
    $params = array_merge($params, $visibilityparams);
    $sql = "SELECT p.*,
                   ctx.id AS ctxid, ctx.path AS ctxpath,
                   ctx.depth AS ctxdepth, ctx.contextlevel AS ctxlevel
            FROM {prog} p
            JOIN {context} ctx ON (p.id = ctx.instanceid AND ctx.contextlevel = ".CONTEXT_PROGRAM.")
            {$where} AND {$visibilitysql}
            ORDER BY {$sort}";

    $programs = array();

    $limitfrom = $page * $recordsperpage;
    $limitto   = $limitfrom + $recordsperpage;
    $c = 0; // Counts how many visible programs we've seen.

    $rs = $DB->get_recordset_sql($sql, $params);

    foreach ($rs as $program) {
        // Don't exit this loop till the end we need to count all the visible programs to update $totalcount.
        if ($c >= $limitfrom && $c < $limitto) {
            $programs[] = $program;
        }
        $c++;
    }

    $rs->close();

    // Our caller expects 2 bits of data - our return array, and an updated $totalcount.
    $totalcount = $c;
    return $programs;
}

function prog_store_position_assignment($assignment) {
    global $DB;

    // Do not save prog_pos_assignment if we don't really select any position.
    if (!isset($assignment->positionid) || empty($assignment->positionid)) {
        return;
    }

    // Need to check this since this is not necessarily set now.
    $currentpositionid = $assignment->positionid;

    $position_assignment_history = $DB->get_record('prog_pos_assignment', array('userid' => $assignment->userid, 'type' => $assignment->type));
    if (!$position_assignment_history) {
        $position_assignment_history = new stdClass();
        $position_assignment_history->userid = $assignment->userid;
        $position_assignment_history->positionid = $currentpositionid;
        $position_assignment_history->type = $assignment->type;
        $position_assignment_history->timeassigned = time();
        $DB->insert_record('prog_pos_assignment', $position_assignment_history);
    } else if ($position_assignment_history->positionid != $currentpositionid) {
        $position_assignment_history->positionid = $currentpositionid;
        $position_assignment_history->timeassigned = time();
        $DB->update_record('prog_pos_assignment', $position_assignment_history);
    }
}

/**
 * Retrieves any recurring programs and returns them in an array or an empty
 * array
 *
 * @return array
 */
function prog_get_recurring_programs() {
    global $DB;
    $recurring_programs = array();

    // get all programs
    $program_records = $DB->get_records('prog');
    foreach ($program_records as $program_record) {
        $program = new program($program_record->id);
        $content = $program->get_content();
        $coursesets = $content->get_course_sets();

        if ((count($coursesets) == 1) && ($coursesets[0]->is_recurring())) {
            $recurring_programs[] = $program;
        }
    }

    return $recurring_programs;
}


function prog_get_tab_link($userid) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();
    $progtable = new xmldb_table('prog');
    if ($dbman->table_exists($progtable)) {
        $programcount = prog_get_required_programs($userid, '', '', '', true, true);
        $certificationcount = prog_get_certification_programs($userid, '', '', '', true, true, true);
        $requiredlearningcount = $programcount + $certificationcount;
        if ($requiredlearningcount == 1) {
            if ($programcount == 1) {
                $program = prog_get_required_programs($userid, '', '', '', false, true);
            } else {
                $program = prog_get_certification_programs($userid, '', '', '', false, true, true);
            }
            $program = reset($program); // resets array pointer and returns value of first element
            $prog = new program($program->id);
            if (!$prog->is_accessible()) {
                return false;
            }
            return $CFG->wwwroot . '/totara/program/required.php?id=' . $program->id;
        } else if ($requiredlearningcount > 1) {
            return $CFG->wwwroot . '/totara/program/required.php';
        }
    }

    return false;
}


/*


/**
 * Processes extension request to grant or deny them given
 * an array of exceptions and the action to take
 *
 * @param array $extensions list of extension ids and actions in the form array(id => action)
 * @param array $reasonfordecision Reason for granting or denying the extension
 * @return array Contains count of extensions processed and number of failures
 */
function prog_process_extensions($extensions, $reasonfordecision = array()) {
    global $CFG, $DB, $USER;

    if (!empty($extensions)) {
        $update_fail_count = 0;
        $update_extension_count = 0;

        foreach ($extensions as $id => $action) {
            if ($action == 0) {
                continue;
            }

            $update_extension_count++;

            if (!$extension = $DB->get_record('prog_extension', array('id' => $id))) {
                print_error('error:couldnotloadextension', 'totara_program');
            }

            if (!totara_is_manager($extension->userid)) {
                print_error('error:notusersmanager', 'totara_program');
            }

            if ($action == PROG_EXTENSION_DENY) {

                $userto = $DB->get_record('user', array('id' => $extension->userid));
                $stringmanager = get_string_manager();
                //ensure the message is actually coming from $user's manager, default to support
                $userfrom = totara_is_manager($extension->userid, $USER->id) ? $USER : core_user::get_support_user();

                $program = $DB->get_record('prog', array('id' => $extension->programid), 'fullname');

                $messagedata = new stdClass();
                $messagedata->userto           = $userto;
                $messagedata->userfrom         = $userfrom;
                $messagedata->subject          = $stringmanager->get_string('extensiondenied', 'totara_program', null, $userto->lang);
                $messagedata->contexturl       = $CFG->wwwroot.'/totara/program/required.php?id='.$extension->programid;
                $messagedata->contexturlname   = $stringmanager->get_string('launchprogram', 'totara_program', null, $userto->lang);
                $messagedata->fullmessage      = $stringmanager->get_string('extensiondeniedmessage', 'totara_program', $program->fullname, $userto->lang);
                $messagedata->icon             = 'program-decline';
                $messagedata->msgtype          = TOTARA_MSG_TYPE_PROGRAM;

                if (!empty($reasonfordecision[$id])) {
                    // Add reason to the message.
                    $messagedata->fullmessage  .= html_writer::empty_tag('br') . html_writer::empty_tag('br');
                    $messagedata->fullmessage  .= $stringmanager->get_string('reasondeniedmessage', 'totara_program', $reasonfordecision[$id], $userto->lang);
                }

                $eventdata = new stdClass();
                $eventdata->message = $messagedata;

                if ($result = tm_alert_send($messagedata)) {

                    $extension_todb = new stdClass();
                    $extension_todb->id = $extension->id;
                    $extension_todb->status = PROG_EXTENSION_DENY;
                    $extension_todb->reasonfordecision = $reasonfordecision[$id];

                    if (!$DB->update_record('prog_extension', $extension_todb)) {
                        $update_fail_count++;
                    }
                } else {
                    print_error('error:failedsendextensiondenyalert', 'totara_program');
                }
            } elseif ($action == PROG_EXTENSION_GRANT) {
                // Load the program for this extension
                $extension_program = new program($extension->programid);

                if ($prog_completion = $DB->get_record('prog_completion', array('programid' => $extension_program->id, 'userid' => $extension->userid, 'coursesetid' => 0))) {
                    $duedate = empty($prog_completion->timedue) ? 0 : $prog_completion->timedue;

                    if ($extension->extensiondate < $duedate) {
                        $update_fail_count++;
                        continue;
                    }
                }

                $now = time();
                if ($extension->extensiondate < $now) {
                    $update_fail_count++;
                    continue;
                }

                // Try to update due date for program using extension date
                if (!$extension_program->set_timedue($extension->userid, $extension->extensiondate)) {
                    $update_fail_count++;
                    continue;
                } else {
                    $userto = $DB->get_record('user', array('id' => $extension->userid));
                    if (!$userto) {
                        print_error('error:failedtofinduser', 'totara_program', $extension->userid);
                    }

                    // Ensure the message is actually coming from $user's manager, default to support.
                    $userfrom = totara_is_manager($extension->userid, $USER->id) ? $USER : core_user::get_support_user();
                    $stringmanager = get_string_manager();
                    $messagedata = new stdClass();
                    $messagedata->userto           = $userto;
                    $messagedata->userfrom         = $userfrom;
                    $messagedata->subject          = $stringmanager->get_string('extensiongranted', 'totara_program', null, $userto->lang);
                    $messagedata->contexturl       = $CFG->wwwroot.'/totara/program/required.php?id='.$extension->programid;
                    $messagedata->contexturlname   = $stringmanager->get_string('launchprogram', 'totara_program', null, $userto->lang);
                    $messagedata->fullmessage      = $stringmanager->get_string('extensiongrantedmessage', 'totara_program', userdate($extension->extensiondate, get_string('strftimedate', 'langconfig'), $CFG->timezone), null, $userto->lang);
                    $messagedata->icon             = 'program-approve';
                    $messagedata->msgtype          = TOTARA_MSG_TYPE_PROGRAM;

                    if (!empty($reasonfordecision[$id])) {
                        // Add reason to the message.
                        $messagedata->fullmessage  .= html_writer::empty_tag('br') . html_writer::empty_tag('br');
                        $messagedata->fullmessage  .= $stringmanager->get_string('reasonapprovedmessage', 'totara_program', $reasonfordecision[$id], $userto->lang);
                    }

                    if ($result = tm_alert_send($messagedata)) {

                        $extension_todb = new stdClass();
                        $extension_todb->id = $extension->id;
                        $extension_todb->status = PROG_EXTENSION_GRANT;
                        $extension_todb->reasonfordecision = $reasonfordecision[$id];

                        if (!$DB->update_record('prog_extension', $extension_todb)) {
                            $update_fail_count++;
                        }
                    } else {
                        print_error('error:failedsendextensiongrantalert','totara_program');
                    }
                 }
            }
        }
        return array('total' => $update_extension_count, 'failcount' => $update_fail_count, 'updatefailcount' => $update_fail_count);
    }
    return array();
}

/**
 * Update program completion status for particular user
 *
 * @param int $userid
 * @param program $program if not set - all programs will be updated
 */
function prog_update_completion($userid, program $program = null) {
    global $DB;

    if (!$program) {
        $proglist = prog_get_all_programs($userid);
        $programs = array();
        foreach ($proglist as $progrow) {
            $programs[] = new program($progrow->id);
        }
    } else {
        $programs = array($program);
    }

    foreach ($programs as $program) {
        // Get the program content.
        $program_content = $program->get_content();

        if ($program->certifid) {
            // If this is a certification program get course sets for groups on the path the user is on.
            $certificationpath = get_certification_path_user($program->certifid, $userid);
            $courseset_groups = $program_content->get_courseset_groups($certificationpath);
        } else {
            // If standard program get the courseset groups (just one path).
            $courseset_groups = $program_content->get_courseset_groups(CERTIFPATH_STD);
        }

        // First check if the program is already marked as complete for this user and do nothing if it is.
        if ($program->is_program_complete($userid)) {
            continue;
        }

        $courseset_group_completed = false;
        $previous_courseset_group_completed = false;

        // Go through the course set groups to determine the user's completion status.
        foreach ($courseset_groups as $courseset_group) {

            $courseset_group_completed = false;

            // Check if the user has completed any of the course sets in the group - this constitutes completion of the group.
            foreach ($courseset_group as $courseset) {

                // First check if the course set is already marked as complete.
                if ($courseset->is_courseset_complete($userid)) {
                    $courseset_group_completed = true;
                    $previous_courseset_group_completed = true;
                    break;
                }

                // Otherwise carry out a check to see if the course set should be marked as complete and mark it as complete if so.
                if ($courseset->check_courseset_complete($userid)) {
                    $courseset_group_completed = true;
                    $previous_courseset_group_completed = true;
                    break;
                }
            }

            // If the user has not completed the course group the program is not complete.
            if (!$courseset_group_completed) {
                // Set the timedue for the course set in this group with the shortest
                // time allowance so that course set due reminders will be triggered
                // at the appropriate time.
                if ($previous_courseset_group_completed) {
                    $program_content->set_courseset_group_timedue($courseset_group, $userid);
                    $previous_courseset_group_completed = false;
                }
                break;
            }
        }

        // Courseset_group_completed will be true if all the course groups in the program have been completed.
        if ($courseset_group_completed) {
            //Get the completion date of the last courseset to use in program completion
            $sql = "SELECT MAX(timecompleted) as timecompleted
                    FROM {prog_completion}
                    WHERE coursesetid != 0 AND programid = ? AND userid = ?";
            $params = array($program->id, $userid);
            $coursesetcompletion = $DB->get_record_sql($sql, $params);

            $completionsettings = array(
                'status'        => STATUS_PROGRAM_COMPLETE,
                'timecompleted' => $coursesetcompletion->timecompleted
                );
            $program->update_program_complete($userid, $completionsettings);
        }
    }
}

/**
 * This function is to cope with program assignments set up
 * with completion deadlines 'from first login' where the
 * user had not yet logged in.
 *
 * Used by program_hourly_cron and user_firstlogin events
 *
 * @param int $user User object to check first firstlogin for
 * @return boolean True if all the update_learner_assignments() succeeded or there was nothing to do
 */
function prog_assignments_firstlogin($user) {
    global $DB;

    $status = true;

    /* Future assignments for this user that can now be processed
     * (because this user has logged in)
     * we are looking for:
     * - future assignments for this user
     * - that relate to a "first login" assignment
     */
    $rs = $DB->get_recordset_sql(
        "SELECT pfua.* FROM
            {prog_future_user_assignment} pfua
        LEFT JOIN
            {prog_assignment} pa
            ON pfua.assignmentid = pa.id
        WHERE
            pfua.userid = ?
            AND pa.completionevent = ?"
    , array($user->id, COMPLETION_EVENT_FIRST_LOGIN));
    // Group the future assignments by 'programid'.
    $pending_by_program = totara_group_records($rs, 'programid');

    if ($pending_by_program) {
        foreach ($pending_by_program as $programid => $assignments) {

            // Update each program.
            $program = new program($programid);
            if ($program->update_learner_assignments()) {
                // If the update succeeded, delete the future assignments related to this program.
                $future_assignments_to_delete = array();
                foreach ($assignments as $assignment) {
                    $future_assignments_to_delete[] = $assignment->id;
                }
                if (!empty($future_assignments_to_delete)) {
                    list($deleteids_sql, $deleteids_params) = $DB->get_in_or_equal($future_assignments_to_delete);
                    $DB->delete_records_select('prog_future_user_assignment', "id {$deleteids_sql}", $deleteids_params);
                }
            } else {
                $status = false;
            }
        }
    }

    return $status;
}

/**
 * Run the program cron
 */
function totara_program_cron() {
    global $CFG;
    require_once($CFG->dirroot . '/totara/program/cron.php');
    program_cron();
}

/**
 * Returns an array of course objects for all the courses which
 * are part of any program.
 *
 * If an array of courseids are provided, the query is restricted
 * to only check for those courses
 *
 * @param array $courses Array of courseids to check for (optional) Defaults to all courses
 * @return array Array of course objects
 */
function prog_get_courses_associated_with_programs($courses = null) {
    global $DB;

    $limitcourses = (isset($courses) && is_array($courses) && count($courses) > 0);

    // restrict by list of courses provided
    if ($limitcourses) {
        list($insql, $inparams) = $DB->get_in_or_equal($courses);
        $insql = " AND c.id $insql";
    } else {
        $insql = '';
        $inparams = array();
    }

    // get courses mentioned in the courseset_course tab, and also any courses
    // linked to competencies used in any courseset
    // always exclude the site course and optionally restrict to a selected list of courses

    //mssql fails because of the 'ntext not comparable' issue
    //so we have to use a subquery to perform union
    $subquery = "SELECT c.id FROM {prog_courseset_course} pcc
                INNER JOIN {course} c ON c.id = pcc.courseid
                WHERE c.id <> ? $insql
            UNION
                SELECT c.id FROM {course} c
                JOIN {comp_criteria} cc ON c.id = cc.iteminstance
                AND cc.itemtype = ?
                WHERE cc.competencyid IN
                    (SELECT DISTINCT competencyid FROM {prog_courseset} WHERE competencyid <> 0)
                AND c.id <> ? $insql";
    $sql = "SELECT * FROM {course} WHERE id IN ($subquery)";

    // build up the params array
    $params = array(SITEID);
    $params = array_merge($params, $inparams);
    $params[] = 'coursecompletion';
    $params[] = SITEID;
    $params = array_merge($params, $inparams);

    return $DB->get_records_sql($sql, $params);
}

function totara_program_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options=array()) {
    $component = 'totara_program';
    $itemid = $args[0];
    $filename = $args[1];
    $fs = get_file_storage();

    $file = $fs->get_file($context->id, $component, $filearea, $itemid, '/', $filename);

    if (empty($file)) {
        send_file_not_found();
    }

    send_stored_file($file, 60*60*24, 0, false, $options); //enable long cache and disable forcedownload
}

/**
 * Returns options to use in program overviewfiles filemanager
 *
 * @param null|stdClass|course_in_list|int $program either object that has 'id' property or just the course id;
 *     may be empty if course does not exist yet (course create form)
 * @return array|null array of options such as maxfiles, maxbytes, accepted_types, etc.
 *     or null if overviewfiles are disabled
 */
function prog_program_overviewfiles_options($program) {
    global $CFG;
    if (empty($CFG->courseoverviewfileslimit)) {
        return null;
    }
    $accepted_types = preg_split('/\s*,\s*/', trim($CFG->courseoverviewfilesext), -1, PREG_SPLIT_NO_EMPTY);
    if (in_array('*', $accepted_types) || empty($accepted_types)) {
        $accepted_types = '*';
    } else {
        // Since config for $CFG->courseoverviewfilesext is a text box, human factor must be considered.
        // Make sure extensions are prefixed with dot unless they are valid typegroups
        foreach ($accepted_types as $i => $type) {
            if (substr($type, 0, 1) !== '.') {
                require_once($CFG->libdir. '/filelib.php');
                if (!count(file_get_typegroup('extension', $type))) {
                    // It does not start with dot and is not a valid typegroup, this is most likely extension.
                    $accepted_types[$i] = '.'. $type;
                    $corrected = true;
                }
            }
        }
        if (!empty($corrected)) {
            set_config('courseoverviewfilesext', join(',', $accepted_types));
        }
    }
    $options = array(
                    'maxfiles' => $CFG->courseoverviewfileslimit,
                    'maxbytes' => $CFG->maxbytes,
                    'subdirs' => 0,
                    'accepted_types' => $accepted_types
    );
    if (!empty($program->id)) {
        $options['context'] = context_program::instance($program->id);
    } else if (is_int($program) && $program > 0) {
        $options['context'] = context_program::instance($program);
    }
    return $options;
}

/**
 * Returns true if the category has programs in it (count does not include programs
 * in child categories)
 *
 * @param coursecat $category
 * @return bool
 */
function prog_has_programs($category) {
    global $DB;
    return $DB->record_exists_sql("SELECT 1 FROM {prog} WHERE category = :category AND certifid IS NULL",
            array('category' => $category->id));
}

/** Returns number of programs visible to the user
 *
 * @param coursecat $category
 * @param string $type Program or certification
 * @return int
 */
function prog_get_programs_count($category, $type = 'program') {
    // We have no programs at site level.
    if ($category->id == 0) {
        return 0;
    }
    $programs = prog_get_programs($category->id, '', 'p.id', $type);
    return count($programs);
}

/**
 * Can the current user delete programs in this category?
 *
 * @param int $categoryid
 * @return boolean
 */
function prog_can_delete_programs($categoryid) {
    global $DB;

    $context = context_coursecat::instance($categoryid);
    $sql = context_helper::get_preload_record_columns_sql('ctx');
    $programcontexts = $DB->get_records_sql('SELECT ctx.instanceid AS progid, '.
                    $sql. ' FROM {context} ctx '.
                    'WHERE ctx.path like :pathmask and ctx.contextlevel = :programlevel',
                    array('pathmask' => $context->path. '/%',
                          'programlevel' => CONTEXT_PROGRAM));
    foreach ($programcontexts as $ctxrecord) {
        context_helper::preload_from_record($ctxrecord);
        $programcontext = context_program::instance($ctxrecord->progid);
        if (!has_capability('totara/program:deleteprogram', $programcontext)) {
            return false;
        }
    }

    return true;
}

/**
 * Class to store information about one program in a list of programs
 *
 * Written to resemble {@link course_in_list} class in coursecatlib.php
 */
class program_in_list implements IteratorAggregate {

    /** @var stdClass record retrieved from DB, may have additional calculated property such as managers and hassummary */
    protected $record;

    /**
     * Creates an instance of the class from record
     *
     * @param stdClass $record except fields from prog table it may contain
     *     field hassummary indicating that summary field is not empty.
     *     Also it is recommended to have context fields here ready for
     *     context preloading
     */
    public function __construct(stdClass $record) {
        context_helper::preload_from_record($record);
        $this->record = new stdClass();
        foreach ($record as $key => $value) {
            $this->record->$key = $value;
        }
    }

    /**
     * Indicates if the program has non-empty summary field
     *
     * @return bool
     */
    public function has_summary() {
        if (isset($this->record->hassummary)) {
            return $this->record->hassummary;
        }
        if (!isset($this->record->summary)) {
            // We need to retrieve summary.
            $this->__get('summary');
        }
        $this->record->hassummary = !empty($this->record->summary);
        return $this->record->hassummary;
    }

    /**
     * Checks if program has any associated overview files
     *
     * @return bool
     */
    public function has_program_overviewfiles() {
        global $CFG;
        if (empty($CFG->courseoverviewfileslimit)) {
            return 0;
        }
        require_once($CFG->libdir. '/filestorage/file_storage.php');
        $fs = get_file_storage();
        $context = context_program::instance($this->id);
        return $fs->is_area_empty($context->id, 'program', 'overviewfiles');
    }

    /**
     * Returns all program overview files
     *
     * @return array array of stored_file objects
     */
    public function get_program_overviewfiles() {
        global $CFG;
        if (empty($CFG->courseoverviewfileslimit)) {
            return array();
        }
        require_once($CFG->libdir . '/filestorage/file_storage.php');
        $fs = get_file_storage();
        $context = context_program::instance($this->id);
        $files = $fs->get_area_files($context->id, 'totara_program', 'overviewfiles', false, 'filename', false);
        if (count($files)) {
            $overviewfilesoptions = prog_program_overviewfiles_options($this->id);
            $acceptedtypes = $overviewfilesoptions['accepted_types'];
            if ($acceptedtypes !== '*') {
                // Filter only files with allowed extensions.
                require_once($CFG->libdir . '/filelib.php');
                foreach ($files as $key => $file) {
                    if (!file_extension_in_typegroup($file->get_filename(), $acceptedtypes)) {
                        unset($files[$key]);
                    }
                }
            }
            if (count($files) > $CFG->courseoverviewfileslimit) {
                $files = array_slice($files, 0, $CFG->courseoverviewfileslimit, true);
            }
        }
        return $files;
    }

    public function __isset($name) {
        return isset($this->record->$name);
    }

    /**
     * Magic method to get a program property
     *
     * Returns any field from table prog (from cache or from DB) and/or special field 'hassummary'
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        global $DB;
        if (property_exists($this->record, $name)) {
            return $this->record->$name;
        } else if ($name === 'summary') {
            // retrieve fields summary and summaryformat together because they are most likely to be used together
            $record = $DB->get_record('prog', array('id' => $this->record->id), 'summary', MUST_EXIST);
            $this->record->summary = $record->summary;
            return $this->record->$name;
        } else if (array_key_exists($name, $DB->get_columns('prog'))) {
            // another field from table 'prog' that was not retrieved
            $this->record->$name = $DB->get_field('prog', $name, array('id' => $this->record->id), MUST_EXIST);
            return $this->record->$name;
        }
        debugging('Invalid program property accessed! ' . $name, DEBUG_DEVELOPER);
        return null;
    }

    /**
     * ALl properties are read only, sorry.
     * @param string $name
     */
    public function __unset($name) {
        debugging('Can not unset ' . get_class($this) . ' instance properties!', DEBUG_DEVELOPER);
    }

    /**
     * Magic setter method, we do not want anybody to modify properties from the outside
     * @param string $name
     * @param mixed $value
     */
    public function __set($name, $value) {
        debugging('Can not change ' . get_class($this) . ' instance properties!', DEBUG_DEVELOPER);
    }

    /**
     * Create an iterator because magic vars can't be seen by 'foreach'.
     * Exclude context fields
     */
    public function getIterator() {
        $ret = array('id' => $this->record->id);
        foreach ($this->record as $property => $value) {
            $ret[$property] = $value;
        }
        return new ArrayIterator($ret);
    }
}

/**
 * Returns the minimum time for the program as html or returns the time string only
 *
 * @param int $seconds
 * @param boolean $timeonly false = output html, true = time string only
 * @return string
 */
function prog_format_seconds($seconds, $timeonly = false) {

    $years = floor($seconds / DURATION_YEAR);
    $str_years = get_string('xyears', 'totara_program', $years);
    $seconds = $seconds % DURATION_YEAR;

    $months = floor($seconds / DURATION_MONTH);
    $str_months = get_string('xmonths', 'totara_program', $months);
    $seconds = $seconds % DURATION_MONTH;

    $weeks = floor($seconds / DURATION_WEEK);
    $str_weeks = get_string('xweeks', 'totara_program', $weeks);
    $seconds = $seconds % DURATION_WEEK;

    $days = floor($seconds / DURATION_DAY);
    $str_days = get_string('xdays', 'totara_program', $days);

    $timestring = !empty($years) ? ' ' . $str_years : '';
    $timestring .= !empty($months) ? ' ' . $str_months : '';
    $timestring .= !empty($weeks) ? ' ' . $str_weeks : '';
    $timestring .= !empty($days) ? ' ' . $str_days : '';

    if ($timeonly) {
        return $timestring;
    }

    $output = '';
    $output .= html_writer::start_tag('div', array('id' => 'programtimerequired'));
    $output .= html_writer::start_tag('p');
    $output .= get_string('minprogramtimerequired', 'totara_program');
    $output .= $timestring;
    $output .= html_writer::end_tag('p');
    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * Returns list of programs user is assigned to.
 *
 * @param int $userid ID of a user
 * @param string|array $fields Fields to return
 * @param string $sort
 * @return array
 */
function prog_get_all_users_programs($userid, $fields = NULL, $sort = 'visible DESC,sortorder ASC') {
    global $DB;

    // Guest account does not have any programs.
    if (isguestuser($userid) || !isloggedin()) {
        return(array());
    }

    $basefields = array('id', 'category', 'sortorder', 'shortname', 'fullname', 'idnumber', 'visible');

    if (empty($fields)) {
        $fields = $basefields;
    } else if (is_string($fields)) {
        $fields = explode(',', $fields);
        $fields = array_map('trim', $fields);
        $fields = array_unique(array_merge($basefields, $fields));
    } else if (is_array($fields)) {
        $fields = array_unique(array_merge($basefields, $fields));
    } else {
        throw new coding_exception('Invalid $fileds parameter in prog_get_all_users_programs()');
    }
    if (in_array('*', $fields)) {
        $fields = array('*');
    }

    $orderby = "";
    $sort = trim($sort);
    if (!empty($sort)) {
        $rawsorts = explode(',', $sort);
        $sorts = array();
        foreach ($rawsorts as $rawsort) {
            $rawsort = trim($rawsort);
            if (strpos($rawsort, 'p.') === 0) {
                $rawsort = substr($rawsort, 2);
            }
            $sorts[] = trim($rawsort);
        }
        $sort = 'p.' . implode(',p.', $sorts);
        $orderby = "ORDER BY $sort";
    }

    $progfields = 'pua.id AS pgaupuniqueid, p.' . join(',p.', $fields);
    $sql = "SELECT $progfields
                FROM {prog} p
            JOIN {prog_user_assignment} pua ON p.id = pua.programid
            WHERE pua.userid = :userid
            $orderby";
    $params['userid']  = $userid;

    $programs = $DB->get_records_sql($sql, $params);
    return $programs;
}

/**
 * updates the course enrolments for a program enrolment plugin, unenrolling students if the program is unavailable.
 *
 * @param enrol_totara_program_plugin $program_plugin
 * @param int $programid
 * @param boolean debugging
 */
function prog_update_available_enrolments(enrol_totara_program_plugin $program_plugin, $programid, $debugging = false) {
    global $DB;

    // Get all the courses in all the coursesets of the program.
    $coursesql = "SELECT c.*
                    FROM {course} c
                   WHERE c.id IN (SELECT DISTINCT(pcc.courseid)
                                    FROM {prog_courseset_course} pcc
                                    JOIN {prog_courseset} pc
                                      ON pcc.coursesetid = pc.id
                                   WHERE pc.programid = :pid
                                 )";
    $courseparams = array('pid' => $programid);
    $courses = $DB->get_records_sql($coursesql, $courseparams);

    foreach ($courses as $course) {
        if (CLI_SCRIPT && $debugging) {
            mtrace("Checking enrolments for Course-{$course->id}...");
        }

        // Get all the users enrolled in the course through the program enrolment plugin.
        $enrolsql = "SELECT ue.*
                      FROM {user_enrolments} ue
                      JOIN {enrol} e
                        ON ue.enrolid = e.id
                     WHERE e.courseid = :cid
                       AND e.enrol = 'totara_program'";
        $enrolparams = array('cid' => $course->id);
        $enrolments = $DB->get_records_sql($enrolsql, $enrolparams);
        $instance = $program_plugin->get_instance_for_course($course->id);

        foreach ($enrolments as $enrolment) {
            // Check to see if they user should still be able to access the course.
            if (CLI_SCRIPT && $debugging) {
                mtrace("Checking enrolment-{$enrolment->id}");
            }

            $user = $DB->get_record('user', array('id' => $enrolment->userid));
            $access = prog_can_enter_course($user, $course);
            if (!$access->enroled) {
                // If they can't, then remove the enrolment.
                if (CLI_SCRIPT && $debugging) {
                    mtrace("unenrolling user-{$enrolment->userid}");
                }
                $program_plugin->unenrol_user($instance, $enrolment->userid);
            } else if (CLI_SCRIPT && $debugging) {
                mtrace("user-{$enrolment->userid} can still access the course");
            }
        }
    }
}

/**
 * Checks the programs availability based off the available from/untill dates.
 *
 * @param int $availablefrom    - A time stamp of the time a program becomes available
 * @param int $availableuntil   - A time stamp of the time a program becomes unavailable
 * @return int                  - Either AVAILABILITY_NOT_TO_STUDENTS or AVAILABILITY_TO_STUDENTS
 */
function prog_check_availability($availablefrom, $availableuntil) {
    $now = time();

    if (!empty($availablefrom) && $availablefrom > $now) {
        return AVAILABILITY_NOT_TO_STUDENTS;
    }
    if (!empty($availableuntil) && $availableuntil < $now) {
        return AVAILABILITY_NOT_TO_STUDENTS;
    }

    return AVAILABILITY_TO_STUDENTS;
}

/**
 * Prints an error if Program is not enabled
 *
 */
function check_program_enabled() {
    if (totara_feature_disabled('programs')) {
        print_error('programsdisabled', 'totara_program');
    }
}

/**
 * Prints an error if Certification is not enabled
 *
 */
function check_certification_enabled() {
    if (totara_feature_disabled('certifications')) {
        print_error('certificationsdisabled', 'totara_certification');
    }
}

/**
 * Snippet to determine if a program is available based on the available fields.
 *
 * @param $fieldalias Alias for the program table used in the query
 * @param $separator Character separator between the alias and the field name
 * @param int|null $userid The user ID that wants to see the program
 * @return array
 */
function get_programs_availability_sql($fieldalias, $separator, $userid = null) {
    global $DB, $USER;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $user = $DB->get_record('user', array('id' => $userid));
    $now = isset($user->timezone) ? usertime(time(), $user->timezone) : usertime(time());

    $availabilitysql = " (({$fieldalias}{$separator}available = :available) AND
                          ({$fieldalias}{$separator}availablefrom = 0 OR {$fieldalias}{$separator}availablefrom < :timefrom) AND
                          ({$fieldalias}{$separator}availableuntil = 0 OR {$fieldalias}{$separator}availableuntil > :timeuntil))";
    $availabilityparams = array('available' => AVAILABILITY_TO_STUDENTS, 'timefrom' => $now, 'timeuntil' => $now);

    return array($availabilitysql, $availabilityparams);
}
