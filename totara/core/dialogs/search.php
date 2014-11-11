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
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @package totara
 * @subpackage totara_core/dialogs
 */

defined('TOTARA_DIALOG_SEARCH') || die();

require_once("{$CFG->dirroot}/totara/core/dialogs/search_form.php");
require_once("{$CFG->dirroot}/totara/core/dialogs/dialog_content_hierarchy.class.php");
require_once($CFG->dirroot . '/totara/core/searchlib.php');

global $DB, $OUTPUT, $USER;

// Get parameter values
$query      = optional_param('query', null, PARAM_TEXT); // search query
$page       = optional_param('page', 0, PARAM_INT); // results page number
$searchtype = $this->searchtype;

// Trim whitespace off search query
$query = trim($query);

// This url
$data = array(
    'search'        => true,
    'query'         => $query,
    'searchtype'    => $searchtype,
    'page'          => $page
);
$thisurl = new moodle_url(strip_querystring(qualified_me()), array_merge($data, $this->urlparams));

// Extra form data
$formdata = array(
    'hidden'        => $this->urlparams,
    'query'         => $query,
    'searchtype'    => $searchtype
);


// Generate SQL
// Search SQL information
$search_info = new stdClass();
$search_info->id = 'id';
$search_info->fullname = 'fullname';
$search_info->sql = null;
$search_info->params = null;

// Check if user has capability to view emails.
$canviewemail = in_array('email', get_extra_user_fields(context_system::instance()));

/**
 * Use whitelist for table to prevent people messing with the query
 * Required variables from each case statement:
 *  + $search_info->id: Title of id field (defaults to 'id')
 *  + $search_info->fullname: Title of fullname field (defaults to 'fullname')
 *  + $search_info->sql: SQL after "SELECT .." fragment (e,g, 'FROM ... etc'), without the ORDER BY
 *  + $search_info->order: The "ORDER BY" SQL fragment (should contain the ORDER BY text also)
 *
 *  Remember to generate and include the query SQL in your WHERE clause with:
 *     totara_dialog_get_search_clause()
 */
switch ($searchtype) {
    /**
     * User search
     */
    case 'user':
        // Grab data from dialog object
        if (isset($this->customdata['current_user'])) {
            $userid = $this->customdata['current_user'];
            $formdata['hidden']['userid'] = $userid;
        }

        // Generate search SQL
        $keywords = totara_search_parse_keywords($query);
        $fields = array('firstname', 'lastname', 'email');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields);

        $search_info->fullname = $DB->sql_fullname('firstname', 'lastname');
        if ($canviewemail) {
            $search_info->email = 'email';
        }

        // exclude deleted, guest users and self
        $guest = guest_user();

        $search_info->sql = "
            FROM
                {user}
            WHERE
                {$searchsql}
                AND deleted = 0
                AND suspended = 0
                AND id != ?
        ";
        $params[] = $guest->id;

        if (isset($this->customdata['current_user'])) {
            $search_info->sql .= " AND id <> ?";
            $params[] = $userid;
        }

        $search_info->order = " ORDER BY firstname, lastname, email";
        $search_info->params = $params;
        break;


    /**
     * Hierarchy search
     */
    case 'hierarchy':
        if (method_exists($this, 'put_search_params')) {
            $this->put_search_params($formdata);
        }
        // Grab data from dialog object
        $prefix = $this->hierarchy->prefix;
        $frameworkid = $this->frameworkid;
        $requireevidence = $this->requireevidence;
        $shortprefix = hierarchy::get_short_prefix($this->hierarchy->prefix);
        $formdata['hierarchy'] = $this->hierarchy;
        $formdata['hidden']['prefix'] = $prefix;
        $formdata['showpicker'] = !$this->disable_picker;
        $formdata['showhidden'] = $showhidden = $this->showhidden;
        $formdata['frameworkid'] = $frameworkid;

        // Generate search SQL
        $keywords = totara_search_parse_keywords($query);
        $fields = array('i.fullname', 'i.shortname', 'i.description', 'i.idnumber');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields);

        $search_info->id = 'i.id';
        $search_info->fullname = 'CASE WHEN (i.idnumber IS NULL OR i.idnumber = \'\' OR i.idnumber = \'0\') THEN i.fullname ELSE '. $DB->sql_concat('i.fullname', "' ('", 'i.idnumber', "')'").' END';

        $search_info->sql = "
            FROM
                {{$shortprefix}} i
            JOIN
                {{$shortprefix}_framework} f
             ON i.frameworkid = f.id
            WHERE
                {$searchsql}
            AND i.visible = 1
        ";

        // Restrict by framework if required
        if ($frameworkid) {
            $search_info->sql .= " AND i.frameworkid = ? ";
            $params[] = $frameworkid;
        }

        // Don't show hidden frameworks
        if (!$showhidden) {
            $search_info->sql .= ' AND f.visible = 1 ';
        }

        // Only show hierarchy items with evidence
        if ($requireevidence) {
            $search_info->sql .= ' AND i.evidencecount > 0 ';
        }

        if (isset($this->customdata['current_item_id'])) {
            $search_info->sql .= "
                AND i.id <> ?
                ";
            $params[] = $this->customdata['current_item_id'];
        }

        $search_info->order = " ORDER BY i.frameworkid, i.sortthread";
        $search_info->params = $params;
        break;


    /**
     * Course (with completion enabled) search
     */
    case 'coursecompletion':
        // Generate search SQL
        $keywords = totara_search_parse_keywords($query);
        $fields = array('c.fullname', 'c.shortname');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields, SQL_PARAMS_NAMED);

        $search_info->id = 'c.id';
        $search_info->fullname = 'c.fullname';

        $search_info->sql = "
            FROM
                {course} c
            LEFT JOIN
                {context} ctx
              ON c.id = ctx.instanceid AND contextlevel = ". CONTEXT_COURSE . " ";

        if ($this->requirecompletioncriteria) {
            $search_info->sql .= "
                LEFT JOIN
                    {course_completion_criteria} ccc
                 ON ccc.course = c.id
            ";
        }

        $search_info->sql .= " WHERE {$searchsql} ";
        list($visibilitysql, $visibilityparams) = totara_visibility_where($USER->id, 'c.id', 'c.visible', 'c.audiencevisible');
        $search_info->sql .= " AND {$visibilitysql}";
        $params = array_merge($params, $visibilityparams);

        if ($this->requirecompletion || $this->requirecompletioncriteria) {
            $search_info->sql .= "
                AND c.enablecompletion = :enablecompletion
            ";
            $params['enablecompletion'] = COMPLETION_ENABLED;

            if ($this->requirecompletioncriteria) {
                $search_info->sql .= "
                    AND ccc.id IS NOT NULL
                ";
            }
        }
        //always exclude site course
        $search_info->sql .= " AND c.id <> :siteid";
        $params['siteid'] = SITEID;
        $search_info->order = " ORDER BY c.sortorder ASC";
        $search_info->params = $params;
        break;


    /**
     * Program search
     */
    case 'program':
        // Generate search SQL
        $search_info->id = 'p.id';
        $keywords = totara_search_parse_keywords($query);
        $fields = array('p.fullname', 'p.shortname');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields, SQL_PARAMS_NAMED);
        list($visibilitysql, $visibilityparams) = totara_visibility_where(null,
                                                                          'p.id',
                                                                          'p.visible',
                                                                          'p.audiencevisible',
                                                                          'p',
                                                                          'program');
        $search_info->sql = "
            FROM
                {prog} p
            LEFT JOIN
                {context} ctx
              ON p.id = ctx.instanceid AND contextlevel = " . CONTEXT_PROGRAM . "
            WHERE
                  {$searchsql}
              AND {$visibilitysql}
        ";
        $params = array_merge($params, $visibilityparams);

        $search_info->order = " ORDER BY p.sortorder ASC";
        $search_info->params = $params;
        break;

    /**
     * Cohort search
     */
    case 'cohort':
        if (!empty($this->customdata['courseid'])) {
            $formdata['hidden']['courseid'] = $this->customdata['courseid'];
        }
        if (!empty($this->customdata['categoryid'])) {
            $formdata['hidden']['categoryid'] = $this->customdata['categoryid'];
        }
        // Generate search SQL.
        $keywords = totara_search_parse_keywords($query);
        $fields = array('idnumber', 'name');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields);

        $search_info->fullname = "(
            CASE WHEN {cohort}.idnumber IS NULL
                OR {cohort}.idnumber = ''
                OR {cohort}.idnumber = '0'
            THEN
                {cohort}.name
            ELSE " .
                $DB->sql_concat("{cohort}.name", "' ('", "{cohort}.idnumber", "')'") .
            "END)";
        $search_info->sql = "
            FROM
                {cohort}
            WHERE
                {$searchsql}
        ";
        if (!empty($this->customdata['current_cohort_id'])) {
            $search_info->sql .= ' AND {cohort}.id != ? ';
            $params[] = $this->customdata['current_cohort_id'];
        }
        $search_info->order = ' ORDER BY name ASC';
        $search_info->params = $params;
        break;

    /**
     * Manager search
     */
    case 'manager':
        $keywords = totara_search_parse_keywords($query);
        $fields = array('u.firstname', 'u.lastname', 'u.email');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields);

        $search_info->id = 'pa.managerid';
        $search_info->fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        if ($canviewemail) {
            $search_info->email = 'email';
        }
        $search_info->sql = "
            FROM {pos_assignment} pa
            INNER JOIN {user} u
            ON pa.managerid = u.id
            WHERE
                pa.type = " . POSITION_TYPE_PRIMARY . "
                AND {$searchsql}
        ";
        $search_info->order = " GROUP BY pa.managerid, u.firstname, u.lastname, u.email ORDER BY u.firstname, u.lastname";
        $search_info->params = $params;
        break;

    /**
     * Evidence search
     */
    case 'dp_plan_evidence':
        // Generate search SQL
        $keywords = totara_search_parse_keywords($query);
        $fields = array('e.name', 'e.description');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields);

        $search_info->id = 'e.id';
        $search_info->fullname = 'e.name';
        $search_info->sql = "
            FROM
                {dp_plan_evidence} e
            WHERE
                {$searchsql}
                AND e.userid = ?
        ";

        $search_info->order = " ORDER BY e.name";
        if (!empty($this->customdata['userid'])) {
            $params[] = $this->customdata['userid'];
        } else {
            $params[] = $USER->id;
        }

        $search_info->params = $params;
        break;

    /**
     * Facetoface room search
     */
    case 'facetoface_room':
        $formdata['hidden']['sessionid'] = $this->customdata['sessionid'];
        $formdata['hidden']['timeslots'] = $this->customdata['timeslots'];

        // Generate search SQL
        $keywords = totara_search_parse_keywords($query);
        $fields = array('r.name', 'r.building', 'r.address');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields);

        $search_info->fullname = $DB->sql_concat('r.name', "', '",
                'r.building', "', '",
                'r.address', "', '",
                'r.description',
                "' (".get_string('capacity', 'facetoface').": '", 'r.capacity', "')'");

        $search_info->sql = "
            FROM
                {facetoface_room} r
            WHERE
                {$searchsql}
                AND r.custom = 0
        ";

        $search_info->order = " ORDER BY r.name ASC";
        $search_info->params = $params;
        break;

    case 'temporary_manager':
        // Generate search SQL.
        $keywords = totara_search_parse_keywords($query);
        $fields = array('u.firstname', 'u.lastname', 'u.email');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields);

        // Generate manager sql.
        $managersql = '';
        if ($CFG->tempmanagerrestrictselection) {
            // Current managers.
            $managersql = "AND u.id IN (SELECT DISTINCT pa.managerid
                                      FROM {pos_assignment} pa
                                     WHERE pa.type = ?)";
            $params[] = POSITION_TYPE_PRIMARY;
        }

        $search_info->id = 'u.id';
        $search_info->fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        if ($canviewemail) {
            $search_info->email = 'email';
        }
        $search_info->sql = "FROM {user} u
                            WHERE {$searchsql} {$managersql}
                              AND u.deleted = 0
                              AND u.suspended = 0
                              AND u.id NOT IN (?, ?)";
        $params[] = $this->customdata['current_user'];
        $params[] = $this->customdata['current_manager'];
        $search_info->order = " ORDER BY u.firstname, u.lastname";
        $search_info->params = $params;
        break;

    /**
     * Badge search
     */
    case 'badge':
        // Generate search SQL
        $keywords = totara_search_parse_keywords($query);
        $fields = array('name', 'description', 'issuername');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields);

        $search_info->fullname = "{badge}.name";
        $search_info->sql = "
            FROM
                {badge}
            WHERE
                {$searchsql}
        ";
        $search_info->order = ' ORDER BY name ASC';
        $search_info->params = $params;
        break;

    /*
     * Category search.
     */
    case 'category':
        // Generate search SQL.
        $keywords = totara_search_parse_keywords($query);
        $fields = array('name');
        list($searchsql, $params) = totara_search_get_keyword_where_clause($keywords, $fields);

        $search_info->fullname = 'c.name';
        $search_info->sql = "
            FROM
                {course_categories} c
            WHERE
                {$searchsql}
        ";
        $search_info->order = ' ORDER BY name ASC';
        $search_info->params = $params;
        break;

    case 'this':
        $keywords = totara_search_parse_keywords($query);
        $this->put_search_info($search_info, $formdata, $keywords);
        break;

    default:
        print_error('invalidsearchtable', 'totara_core');
}

// Generate forn markup
// Create form
$mform = new dialog_search_form(null, $formdata);

// Display form
$mform->display();


// Generate results
if (strlen($query)) {

    $strsearch = get_string('search');
    $strqueryerror = get_string('queryerror', 'totara_core');
    $start = $page * DIALOG_SEARCH_NUM_PER_PAGE;

    $select = "SELECT {$search_info->id} AS id, {$search_info->fullname} AS fullname ";
    if (isset($search_info->email)) {
        $select .= ", {$search_info->email} AS email ";
    }
    $count  = "SELECT COUNT({$search_info->id}) ";

    $total = $DB->count_records_sql($count.$search_info->sql, $search_info->params);
    if ($total) {
        $results = $DB->get_records_sql(
            $select.$search_info->sql.$search_info->order,
            $search_info->params,
            $start,
            DIALOG_SEARCH_NUM_PER_PAGE
        );
    }

    if ($total) {
        if ($results) {
            $pagingbar = new paging_bar($total, $page, DIALOG_SEARCH_NUM_PER_PAGE, $thisurl);
            $pagingbar->pagevar = 'page';
            $output = $OUTPUT->render($pagingbar);
            echo html_writer::tag('div',$output, array('class' => "search-paging"));

            // Generate some treeview data
            $dialog = new totara_dialog_content();
            $dialog->items = array();
            $dialog->parent_items = array();
            $dialog->disabled_items = $this->disabled_items;

            foreach ($results as $result) {
                $item = new stdClass();

                if (method_exists($this, 'search_can_display_result') && !$this->search_can_display_result($result->id)) {
                   continue;
                }

                $item->id = $result->id;
                if (isset($result->email)) {
                    $username = new stdClass();
                    $username->fullname = $result->fullname;
                    $username->email = $result->email;
                    $item->fullname = get_string('assignindividual', 'totara_program', $username);
                } else {
                    $item->fullname = format_string($result->fullname);
                }

                if (method_exists($this, 'search_get_item_hover_data')) {
                    $item->hover = $this->search_get_item_hover_data($item->id);
                }

                $dialog->items[$item->id] = $item;
            }

            echo $dialog->generate_treeview();

        } else {
            // if count succeeds, query shouldn't fail
            // must be something wrong with query
            print $strqueryerror;
        }
    } else {
        $params = new stdClass();
        $params->query = $query;

        $message = get_string('noresultsfor', 'totara_core', $params);

        if (!empty($frameworkid)) {
            $params->framework = $DB->get_field($shortprefix.'_framework', 'fullname', array('id' => $frameworkid));
            $message = get_string('noresultsforinframework', 'totara_hierarchy', $params);
        }

        echo html_writer::tag('p', $message, array('class' => 'message'));
    }
}
