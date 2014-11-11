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
 * @author Aaron Wells <aaronw@catalyst.net.nz>
 * @package totara
 * @subpackage cohort
 */
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); //  It must be included from a Moodle page
}

global $CFG;
require_once($CFG->dirroot.'/totara/reportbuilder/rb_sources/rb_source_user.php');

/**
 * A report builder source for users that aren't in any cohort table.
 */
class rb_source_cohort_orphaned_users extends rb_source_user {

    public $base, $joinlist, $columnoptions, $filteroptions;
    public $contentoptions, $paramoptions, $defaultcolumns;
    public $defaultfilters, $requiredcolumns, $sourcetitle;

    /** @var bool do not cache because current time is used in query */
    public $cacheable = false;

    /**
     * Constructor
     * @global object $CFG
     */
    public function __construct() {
        global $CFG;
        require_once($CFG->dirroot.'/cohort/lib.php');
        parent::__construct();
        $this->base = "(
            select *
            from {user} u
            where
                not exists (
                    select 1
                    from {cohort_members} cm
                        inner join {cohort} c
                        on cm.cohortid=c.id
                    where
                        cm.userid=u.id
                        and " . totara_cohort_date_where_clause( 'c' ) . '
                )
                AND u.id <> 1
                AND u.deleted = 0
                AND u.confirmed = 1
            )';
        $this->sourcetitle = get_string('sourcetitle', 'rb_source_cohort_orphaned_users');

    }

}
