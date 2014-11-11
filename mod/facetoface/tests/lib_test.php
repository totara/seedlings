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
 * @author Chris Wharton <chrisw@catalyst.net.nz>
 * @author Aaron Barnes <aaronb@catalyst.net.nz>
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage facetoface
 */

/*
 * Unit tests for mod/facetoface/lib.php
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}

global $CFG;
require_once($CFG->dirroot . '/mod/facetoface/lib.php');

class facetoface_lib_testcase extends advanced_testcase {
    // Test database data.
    protected $facetoface_data = array(
        array('id',                     'course',           'name',                     'thirdparty',
            'thirdpartywaitlist',       'display',          'confirmationsubject',      'confirmationinstrmngr',
            'confirmationmessage',      'waitlistedsubject','waitlistedmessage',        'cancellationsubject',
            'cancellationinstrmngr',    'cancellationmessage','remindersubject',        'reminderinstrmngr',
            'remindermessage',          'reminderperiod',   'requestsubject',           'requestinstrmngr',
            'requestmessage',           'timecreated',      'timemodified',             'shortname',
            'description',              'showoncalendar',   'approvalreqd'
            ),
        array(1,                        1,                  'name1',                    'thirdparty1',
            0,                          0,                  'consub1',                  'coninst1',
            'conmsg1',                  'waitsub1',         'waitmsg1',                 'cansub1',
            'caninst1',                 'canmsg1',          'remsub1',                  'reminst1',
            'remmsg1',                  0,                  'reqsub1',                  'reqinst1',
            'reqmsg1',                  0,                  0,                          'short1',
            'desc1',                    1,                  0
            ),
        array(2,                        2,                  'name2',                    'thirdparty2',
            0,                          0,                  'consub2',                  'coninst2',
            'conmsg2',                  'waitsub2',         'waitmsg2',                 'cansub2',
            'caninst2',                 'canmsg2',          'remsub2',                  'reminst2',
            'remmsg2',                 0,                  'reqsub2',                  'reqinst2',
            'reqmsg2',                  0,                  0,                          'short2',
            'desc2',                    1,                  0
            ),
        array(3,                        3,                  'name3',                    'thirdparty3',
            0,                          0,                  'consub3',                  'coninst3',
            'conmsg3',                  'waitsub3',         'waitmsg3',                 'cansub3',
            'caninst3',                 'canmsg3',          'remsub3',                  'reminst3',
            'remmsg3',                  0,                  'reqsub3',                  'reqinst3',
            'reqmsg3',                  0,                  0,                          'short3',
            'desc3',                    1,                  0
            ),
        array(4,                        4,                  'name4',                    'thirdparty4',
            0,                          0,                  'consub4',                  'coninst4',
            'conmsg4',                  'waitsub4',         'waitmsg4',                 'cansub4',
            'caninst4',                 'canmsg4',          'remsub4',                  'reminst4',
            'remmsg4',                  0,                  'reqsub4',                  'reqinst4',
            'reqmsg4',                  0,                  0,                          'short4',
            'desc4',                    1,                  0
            ),
    );

    protected $facetoface_sessions_data = array(
        array('id', 'facetoface', 'capacity', 'allowoverbook', 'details', 'datetimeknown',
              'duration', 'normalcost', 'discountcost', 'timecreated', 'timemodified', 'usermodified'),
        array(1,    1,   100,    1,  'dtl1',     1,     4,    '$75',     '$60',     1500,   1600, 2),
        array(2,    2,    50,    0,  'dtl2',     0,     1,    '$90',     '$0',     1400,   1500, 2),
        array(3,    3,    10,    1,  'dtl3',     1,     7,    '$100',    '$80',     1500,   1500, 2),
        array(4,    4,    1,     0,  'dtl4',     0,     7,    '$10',     '$8',      500,   1900, 2),
        );

    protected $facetoface_sessions_field_data = array(
        array('id',     'name',     'shortname',    'type',     'possiblevalues',
            'required',     'defaultvalue',   'showinsummary'),
        array(1,    'name1',    'shortname1',   0,  'possible1',    0,  'defaultvalue1',    1),
        array(2,    'name2',    'shortname2',   2,  'possible2',    0,  'defaultvalue2',    1),
        array(3,    'name3',    'shortname3',   3,  'possible3',    1,  'defaultvalue3',    1),
        array(4,    'name4',    'shortname4',   4,  'possible4',    1,  'defaultvalue4',    1),
    );

    protected $facetoface_sessions_data_data = array(
        array('id', 'fieldid', 'sessionid', 'data'),
        array(1,    0,  0,  'test data1'),
        array(2,    1,  1,  'test data2'),
        array(3,    2,  2,  'test data3'),
        array(4,    3,  3,  'test data4'),
    );

    protected $facetoface_sessions_dates_data = array(
        array('id',     'sessionid',    'timestart',    'timefinish'),
        array(1,        1,              1100,           1300),
        array(2,        2,              1900,           2100),
        array(3,        3,               900,           1100),
        array(4,        3,              1200,           1400),
    );

    protected $facetoface_signups_data = array(
        array('id', 'sessionid', 'userid', 'mailedreminder', 'discountcode', 'notificationtype'),
        array(1,    1,  1,  1,  'disc1',    7),
        array(2,    2,  2,  0,  NULL,       6),
        array(3,    2,  3,  0,  NULL,       5),
        array(4,    2,  4,  0,  'disc4',   11),
    );

    protected $facetoface_signups_status_data = array(
        array('id',     'signupid',     'statuscode',   'superceded',   'grade',
            'note',     'advice',       'createdby',    'timecreated'),
        array(1,        1,              70,             0,              99.12345,
            'note1',    'advice1',      '1',      1600),
        array(2,        2,              70,             0,              32.5,
            'note2',    'advice2',      '2',      1700),
        array(3,        3,              70,             0,              88,
            'note3',    'advice3',      '3',       700),
        array(4,        4,              70,             0,              12.5,
            'note4',    'advice4',      '4',      1100),
    );

    protected $course_data = array(
        array('id',         'category',     'sortorder',    'password',
            'fullname',    'shortname',    'idnumber',     'summary',
            'format',      'showgrades',   'modinfo',      'newsitems',
            'teacher',     'teachers',     'student',      'students',
            'guest',       'startdate',    'enrolperiod',  'numsections',
            'marker',      'maxbytes',     'showreports',  'visible',
            'hiddensections','groupmode',  'groupmodeforce','defaultgroupid',
            'lang',        'theme',        'cost',         'currency',
            'timecreated', 'timemodified', 'metacourse',   'requested',
            'restrictmodules','expirynotify','expirythreshold','notifystudents',
            'enrollable',  'enrolstartdate','enrolenddate','enrol',
            'defaultrole', 'enablecompletion','completionstartenrol',  'icon'
            ),
        array(1,            0,              0,              'pw1',
            'name1',        'sn1',          '101',          'summary1',
            'format1',      1,              'mod1',         1,
            'teacher1',     'teachers1',    'student1',     'students1',
            0,              0,              0,              1,
            0,              0,              0,              1,
            0,              0,              0,              0,
            'lang1',        'theme1',       'cost1',        'cu1',
            0,              0,              0,              0,
            0,              0,              0,              0,
            1,              0,              0,              'enrol1',
            0,              0,              0,              'icon1'
            ),
        array(2,            0,              0,              'pw2',
            'name2',        'sn2',          '102',          'summary2',
            'format2',      1,              'mod2',         1,
            'teacher2',     'teachers2',    'student2',     'students2',
            0,              0,              0,              1,
            0,              0,              0,              1,
            0,              0,              0,              0,
            'lang2',        'theme2',       'cost2',        'cu2',
            0,              0,              0,              0,
            0,              0,              0,              0,
            1,              0,              0,              'enrol2',
            0,              0,              0,              'icon2'
            ),
        array(3,            0,              0,              'pw3',
            'name3',        'sn3',          '103',          'summary3',
            'format3',      1,              'mod3',         1,
            'teacher3',     'teachers3',    'student3',     'students3',
            0,              0,              0,              1,
            0,              0,              0,              1,
            0,              0,              0,              0,
            'lang3',        'theme3',       'cost3',        'cu3',
            0,              0,              0,              0,
            0,              0,              0,              0,
            1,              0,              0,              'enrol3',
            0,              0,              0,              'icon3'
            ),
        array(4,            0,              0,              'pw4',
            'name4',        'sn4',          '104',          'summary4',
            'format4',      1,              'mod4',         1,
            'teacher4',     'teachers4',    'student4',     'students4',
            0,              0,              0,              1,
            0,              0,              0,              1,
            0,              0,              0,              0,
            'lang4',        'theme4',       'cost4',        'cu4',
            0,              0,              0,              0,
            0,              0,              0,              0,
            1,              0,              0,              'enrol4',
            0,              0,              0,              'icon4'
            ),
    );

    protected $event_data = array(
        array('id',         'name',     'description',      'format',
            'courseid',     'groupid',  'userid',           'repeatid',
            'modulename',   'instance', 'eventtype',        'timestart',
            'timeduration', 'visible',  'uuid',             'sequence',
            'timemodified'),
        array(1,            'name1',    'desc1',            0,
            1,              1,          1,                  0,
            'facetoface',   1,          'facetofacesession',1300,
            3,             1,          'uuid1',            1,
            0),
        array(2,            'name2',    'desc2',            0,
            2,              2,          2,                  0,
            'facetoface',   2,          'facetofacesession',2300,
            3,              2,          'uuid2',            2,
            0),
        array(3,            'name3',    'desc3',            0,
            3,              3,          3,                  0,
            'facetoface',   3,          'facetofacesession',3300,
            3,              3,          'uuid3',            3,
            0),
        array(4,            'name4',    'desc4',            0,
            4,              4,          4,                  0,
            'facetoface',   4,          'facetofacesession',4300,
            3,              4,          'uuid4',            4,
            0),
    );

    protected $role_assignments_data = array(
        array('id', 'roleid', 'contextid', 'userid', 'hidden',
            'timestart', 'timeend'),
        array(1,  1,  1,  1,  0,  0,  0),
        array(2,  4,  2,  2,  1,  0,  0),
        array(3,  5,  3,  3,  0,  0,  0),
        array(4,  4,  3,  2,  0,  0,  0),
    );

    protected $pos_assignment_data = array(
        array('id', 'fullname', 'shortname', 'idnumber', 'description',
            'timevalidfrom', 'timevalidto', 'timecreated', 'timemodified',
            'usermodified', 'organisationid', 'userid', 'positionid',
            'reportstoid', 'type', 'managerid'),
        array(1, 'fullname1', 'shortname1', 'idnumber1', 'desc1',
             900, 1000,  800, 1300,
            1, 1122, 1, 2,
            1, 1, 2),
        array(2, 'fullname2', 'shortname2', 'idnumber2', 'desc2',
             900, 2000,  800, 2300,
            2, 2222, 2, 2,
            2, 2, 1),
        array(3, 'fullname3', 'shortname3', 'idnumber3', 'desc3',
             900, 3000,  800, 3300,
            3, 3322, 3, 2,
            3, 3, 1),
        array(4, 'fullname4', 'shortname4', 'idnumber4', 'desc4',
             900, 4000,  800, 4300,
            4, 4422, 4, 2,
            4, 4, 1),
    );

    protected $course_modules_data =array(
        array('id', 'course', 'module', 'instance', 'section', 'idnumber',
            'added', 'score', 'indent', 'visible', 'visibleold', 'groupmode',
            'groupingid', 'groupmembersonly', 'completion', 'completiongradeitemnumber',
            'completionview', 'completionview', 'completionexpected', 'availablefrom',
            'availableuntil', 'showavailability'),
        array(1, 2, 3, 4, 5, '1001',
            6, 1, 7, 1, 1, 0,
            8, 0, 0, 10,
            0, 11, 12, 13,
            14, 1),
        array(2, 2, 3, 4, 5, '1002',
            6, 1, 7, 1, 1, 0,
            8, 0, 0, 10,
            0, 11, 12, 13,
            14, 1),
        array(3, 2, 3, 4, 5, '1003',
            6, 1, 7, 1, 1, 0,
            8, 0, 0, 10,
            0, 11, 12, 13,
            14, 1),
        array(4, 2, 3, 4, 5, '1004',
            6, 1, 7, 1, 1, 0,
            8, 0, 0, 10,
            0, 11, 12, 13,
            14, 1),
    );

    protected $grade_items_data = array(
        array('id', 'courseid', 'categoryid', 'itemname', 'itemtype',
            'itemmodule', 'iteminstance', 'itemnumber', 'iteminfo', 'idnumber',
            'calculation', 'gradetype', 'grademax', 'grademin', 'scaleid',
            'outcomeid', 'gradepass', 'multfactor', 'plusfactor', 'aggregationcoef',
            'sortorder', 'display', 'decimals', 'hidden', 'locked',
            'locktime', 'needsupdate', 'timecreated', 'timemodified'),
        array(1, 1, 1, 'itemname1', 'type1',
            'module1', 1, 100, 'info1', '10012',
            'calc1', 1, 100, 0, 70,
            80, 0, 1.0, 0, 0,
            0, 0, 1, 0, 0,
            0, 0, 0, 0),
        array(2, 1, 1, 'itemname1', 'type1',
            'module1', 1, 100, 'info1', '10012',
            'calc1', 1, 100, 0, 70,
            80, 0, 1.0, 0, 0,
            0, 0, 1, 0, 0,
            0, 0, 0, 0),
        array(3, 1, 1, 'itemname1', 'type1',
            'module1', 1, 100, 'info1', '10012',
            'calc1', 1, 100, 0, 70,
            80, 0, 1.0, 0, 0,
            0, 0, 1, 0, 0,
            0, 0, 0, 0),
        array(4, 1, 1, 'itemname1', 'type1',
            'module1', 1, 100, 'info1', '10012',
            'calc1', 1, 100, 0, 70,
            80, 0, 1.0, 0, 0,
            0, 0, 1, 0, 0,
            0, 0, 0, 0),
    );

    protected $grade_categories_data = array(
        array('id', 'courseid', 'parent', 'depth', 'path',
            'fullname', 'aggregation', 'keephigh', 'droplow',
            'aggregateonlygraded', 'aggregateoutcomes', 'aggregatesubcats',
            'timecreated', 'timemodified'),
        array(1, 1, 1, 1, 'path1',
            'fullname1', 0, 0, 0,
            0, 0, 0,
            1300, 1400),
        array(2, 1, 1, 1, 'path1',
            'fullname1', 0, 0, 0,
            0, 0, 0,
            1300, 1400),
        array(3, 1, 1, 1, 'path1',
            'fullname1', 0, 0, 0,
            0, 0, 0,
            1300, 1400),
        array(4, 1, 1, 1, 'path1',
            'fullname1', 0, 0, 0,
            0, 0, 0,
            1300, 1400),
    );

    protected $user_data = array(
        array('id',                 'auth',             'confirmed',
            'policyagreed',         'deleted',          'mnethostid',
            'username',             'password',         'idnumber',
            'firstname',            'lastname',         'email',
            'emailstop',            'icq',              'skype',
            'yahoo',                'aim',              'msn',
            'phone1',               'phone2',           'institution',
            'department',           'address',          'city',
            'country',              'lang',             'theme',
            'timezone',             'firstaccess',      'lastaccess',
            'lastlogin',            'currentlogin',     'lastip',
            'secret',               'picture',          'url',
            'description',          'mailformat',       'maildigest',
            'maildisplay',          'htmleditor',       'ajax',
            'autosubscribe',        'trackforums',      'timemodified',
            'trustbitmask',         'imagealt',         'screenreader',
            ),
        array(1,                    'auth1',            0,
            0,                      0,                  1,
            'user1',                'test',             '10011',
            'fname1',               'lname1',           'user1@example.com',
            1,                      0,                  'test',
            'test',                 'test',             'test',
            'test',                 'test',             'test',
            'test',                 'test',             'test',
            'NZ',                   'en_utf8',          'default',
            'default',              1,                  2,
            2,                      1,                  1,
            0,                      2,                  1,
            'desc1',                1,                  0,
            0,                      0,                  0,
            0,                      0,                  0,
            0,                      'imagealt1',        0
            ),
        array(2,                    'auth2',            0,
            0,                      0,                  1,
            'user2',                'test',             '20022',
            'fname2',               'lname2',           'user2@example.com',
            1,                      0,                  'test',
            'test',                 'test',             'test',
            'test',                 'test',             'test',
            'test',                 'test',             'test',
            'NZ',                   'en_utf8',          'default',
            'default',              '22',               0,
            0,                      1,                  2,
            0,                      2,                  2,
            'desc2',                2,                  0,
            0,                      0,                  0,
            0,                      0,                  0,
            0,                      'imagealt2',        0
            ),
        array(3,                    'auth3',            0,
            0,                      0,                  1,
            'user3',                'test',             '30033',
            'fname3',               'lname3',           'user3@example.com',
            1,                      0,                  'test',
            'test',                 'test',             'test',
            'test',                 'test',             'test',
            'test',                 'test',             'test',
            'NZ',                   'en_utf8',          'default',
            'default',              '32',               0,
            0,                      1,                  3,
            0,                      2,                  3,
            'desc3',                3,                  0,
            0,                      0,                  0,
            0,                      0,                  0,
            0,                      'imagealt3',        0
            ),
        array(4,                    'auth4',            0,
            0,                      0,                  1,
            'user4',                'test',             '40044',
            'fname4',               'lname4',           'user4@example.com',
            1,                      0,                  'test',
            'test',                 'test',             'test',
            'test',                 'test',             'test',
            'test',                 'test',             'test',
            'NZ',                   'en_utf8',          'default',
            'default',              '42',               0,
            0,                      1,                  4,
            0,                      2,                  4,
            'desc4',                4,                  0,
            0,                      0,                  0,
            0,                      0,                  0,
            0,                      'imagealt4',        0
            ),
    );

    protected $grade_grades_data = array(
        array('id',                 'itemid',           'userid',
            'rawgrade',             'rawgrademax',      'rawgrademin',
            'rawscaleid',           'usermodified',     'finalgrade',
            'hidden',               'locked',           'locktime',
            'exported',             'overridden',       'excluded',
            'feedback',             'feedbackformat',   'information',
            'informationformat',    'timecreated',      'timemodified'
            ),
        array(1,                    1,                  3,
            50,                     100,                0,
            30,                     1 ,                 80.2,
            0,                      0,                  0,
            0,                      0,                  0,
            'feedback1',            0,                  'info1',
            0,                      1300,               1400
        ),
        array(2,                    2,                  3,
            50,                     200,                0,
            30,                     2 ,                 80.2,
            0,                      0,                  0,
            0,                      0,                  0,
            'feedback2',            0,                  'info2',
            0,                      2300,               2400
        ),
        array(3,                    3,                  3,
            50,                     300,                0,
            30,                     3 ,                 80.2,
            0,                      0,                  0,
            0,                      0,                  0,
            'feedback3',            0,                  'info3',
            0,                      3300,               3400
        ),
        array(4,                    2,                  1,
            50,                     400,                0,
            30,                     4 ,                 80.2,
            0,                      0,                  0,
            0,                      0,                  0,
            'feedback4',            0,                  'info4',
            0,                      4300,               4400
        ),
    );

    protected $user_info_field_data = array(
        array('id',                 'shortname',         'name',
            'datatype',             'description',      'categoryid',
            'sortorder',            'required',         'locked',
            'visible',              'forceunique',      'signup',
            'defaultdata',          'param1',           'param2',
            'param3',               'param4',           'param5'
            ),
        array(1,                    'shortname1',       'name1',
            'datatype1',            'desc1',            0,
            0,                      0,                  0,
            0,                      0,                  0,
            0,                      'param1',           'param2',
            'param3',               'param4',           'param5'
            ),
        array(2,                    'shortname2',       'name2',
            'datatype2',            'desc2',            0,
            0,                      0,                  0,
            0,                      0,                  0,
            0,                      'param1',           'param2',
            'param3',               'param4',           'param5'
            ),
        array(3,                    'shortname3',       'name3',
            'datatype3',            'desc3',            0,
            0,                      0,                  0,
            0,                      0,                  0,
            0,                      'param1',           'param2',
            'param3',               'param4',           'param5'
            ),
        array(4,                    'shortname4',       'name4',
            'datatype4',            'desc4',            0,
            0,                      0,                  0,
            0,                      0,                  0,
            0,                      'param1',           'param2',
            'param4',               'param4',           'param5'
            ),
    );

    protected $user_info_data_data = array(
        array('id',    'userid',   'fieldid',  'data'),
        array(1,    1,  1,  'data1'),
        array(2,    2,  2,  'data2'),
        array(3,    3,  3,  'data3'),
        array(4,    4,  4,  'data4'),
    );

    protected $user_info_category_data = array(
        array('id', 'name', 'sortorder'),
        array(1,    'name1',          0),
        array(2,    'name2',          0),
        array(3,    'name3',          0),
        array(4,    'name4',          0),
    );

    protected $course_categories_data = array(
        array('id',     'name', 'description',  'parent',   'sortorder',
            'coursecount',  'visible',  'timemodified', 'depth',
            'path', 'theme',    'icon'),
        array(2,    'name2',    'desc2',    0,  0,
            0,    2,          0,          0,
            'path2',    'theme2',   'icon2'),
        array(3,    'name3',    'desc3',    0,  0,
            0,    3,          0,          0,
            'path3',    'theme3',   'icon3'),
        array(4,    'name4',    'desc4',    0,  0,
            0,    4,          0,          0,
            'path4',    'theme4',   'icon4'),
    );

    protected $facetoface_session_roles_data = array (
        array('id', 'sessionid', 'roleid', 'userid'),
        array(1,    1,  1,  1),
        array(2,    2,  4,  2),
        array(3,    3,  1,  3),
        array(4,    4,  4,  4),
    );

    protected $facetoface_notice_data = array (
        array('id', 'name',     'text'),
        array(1,    'name1',    'text1'),
        array(2,    'name2',    'text2'),
        array(3,    'name3',    'text3'),
        array(4,    'name4',    'text4'),
    );

    protected $user_preferences_data = array (
        array('id',     'userid',   'name',     'value'),
        array(1,        1,          'name1',    'val1'),
        array(2,        2,          'name2',    'val2'),
        array(3,        3,          'name3',    'val3'),
        array(4,        4,          'name4',    'val4'),
    );

    protected $config_email = false;

    // Constant variables!

    protected $facetoface = array(
        'f2f0' => array(
            'id' => 1,
            'instance' => 1,
            'course' => 4,
            'name' => 'name1',
            'thirdparty' => 'thirdparty1',
            'thirdpartywaitlist' => 0,
            'display' => 1,
            'confirmationsubject' => 'consub1',
            'confirmationinstrmngr' => '',
            'confirmationmessage' => 'conmsg1',
            'reminderinstrmngr' => '',
            'reminderperiod' => 0,
            'waitlistedsubject' => 'waitsub1',
            'cancellationinstrmngr' => '',
            'showoncalendar' => 1,
            'shortname' => 'shortname1',
            'description' => 'description1',
            'timestart' => 1300,
            'timefinish' => 1500,
            'emailmanagerconfirmation' => 'test1',
            'emailmanagerreminder' => 'test2',
            'emailmanagercancellation' => 'test3',
            'showcalendar' => 1,
            'approvalreqd' => 0,
            'requestsubject' => 'reqsub1',
            'requestmessage' => 'reqmsg1',
            'requestinstrmngr' => '',
            'usercalentry' => false,
            'multiplesessions' => 0,
            'managerreserve' => 0,
            'maxmanagerreserves' => 1,
            'reservecanceldays' => 1,
            'reservedays' => 2
        ),
        'f2f1' => array(
            'id' => 2,
            'instance' => 2,
            'course' => 3,
            'name' => 'name2',
            'thirdparty' => 'thirdparty2',
            'thirdpartywaitlist' => 0,
            'display' => 0,
            'confirmationsubject' => 'consub2',
            'confirmationinstrmngr' => 'conins2',
            'confirmationmessage' => 'conmsg2',
            'reminderinstrmngr' => 'remmngr2',
            'reminderperiod' => 1,
            'waitlistedsubject' => 'waitsub2',
            'cancellationinstrmngr' => 'canintmngr2',
            'showoncalendar' => 1,
            'shortname' => 'shortname2',
            'description' => 'description2',
            'timestart' => 2300,
            'timefinish' => 2330,
            'emailmanagerconfirmation' => 'test2',
            'emailmanagerreminder' => 'test2',
            'emailmanagercancellation' => 'test3',
            'showcalendar' => 1,
            'approvalreqd' => 1,
            'requestsubject' => 'reqsub2',
            'requestmessage' => 'reqmsg2',
            'requestinstrmngr' => 'reqinstmngr2',
            'usercalentry' => true,
            'multiplesessions' => 0,
            'managerreserve' => 0,
            'maxmanagerreserves' => 1,
            'reservecanceldays' => 1,
            'reservedays' => 2
        ),
    );

    protected $sessions = array(
        'sess0' => array(
            'id' => 1,
            'facetoface' => 1,
            'capacity' => 0,
            'allowoverbook' => 1,
            'details' => 'details1',
            'datetimeknown' => 1,
            'sessiondates' => array(
                'id' => 20,
                'timestart' => 0,
                'timefinish' => 0,
            ),
            'duration' => 3,
            'normalcost' => '$100',
            'discountcost' => '$75',
            'timecreated' => 1300,
            'timemodified' => 1400,
        ),
        'sess1' => array(
            'id' => 2,
            'facetoface' => 2,
            'capacity' => 3,
            'allowoverbook' => 0,
            'details' => 'details2',
            'datetimeknown' => 0,
            'sessiondates' => array(
                'id' => 20,
                'timestart' => 0,
                'timefinish' => 0,
            ),
            'duration' => 6,
            'normalcost' => '$100',
            'discountcost' => '$75',
            'timecreated' => 1300,
            'timemodified' => 1400,
        ),
    );

    protected $sessiondata = array(
        'sess0' => array(
            'id' => 1,
            'fieldid' => 1,
            'sessionid' => 1,
            'data' => 'testdata1',
            'discountcost' => '$60',
            'normalcost' => '$75',
        ),
        'sess1' => array(
            'id' => 2,
            'fieldid' => 2,
            'sessionid' => 2,
            'data' => 'testdata2',
            'discountcost' => '',
            'normalcost' => '$90',
        ),
    );

    // message string 1
    protected $msgtrue = 'should be true';

    // message string 2
    protected $msgfalse = 'should be false';

    function array_to_object(array $arr) {
        $obj = new stdClass();

        foreach ($arr as $key => $value) {
            $obj->$key = $value;
        }

        return $obj;
    }

    function setup() {
        // function to load test tables
        global $DB, $CFG;

        isset($CFG->noemailever) ? $this->config_email = $CFG->noemailever : false;
        $CFG->noemailever = true;

        parent::setUp();
        $this->loadDataSet(
            $this->createArrayDataset(
                array(
                    'facetoface_signups'        => $this->facetoface_signups_data,
                    'facetoface_sessions'       => $this->facetoface_sessions_data,
                    'facetoface_session_field'  => $this->facetoface_sessions_field_data,
                    'facetoface_session_data'   => $this->facetoface_sessions_data_data,
                    'facetoface'                => $this->facetoface_data,
                    'facetoface_sessions_dates' => $this->facetoface_sessions_dates_data,
                    'facetoface_signups_status' => $this->facetoface_signups_status_data,
                    'event'                     => $this->event_data,
                    'role_assignments'          => $this->role_assignments_data,
                    'pos_assignment'            => $this->pos_assignment_data,
                    'course_modules'            => $this->course_modules_data,
                    'grade_items'               => $this->grade_items_data,
                    'grade_categories'          => $this->grade_categories_data,
                    'grade_grades'              => $this->grade_grades_data,
                    'user_info_field'           => $this->user_info_field_data,
                    'user_info_data'            => $this->user_info_data_data,
                    'user_info_category'        => $this->user_info_category_data,
                    'course_categories'         => $this->course_categories_data,
                    'facetoface_session_roles'  => $this->facetoface_session_roles_data,
                    'facetoface_notice'         => $this->facetoface_notice_data,
                    'user_preferences'          => $this->user_preferences_data,
                )
            )
        );

        $this->user1 = $this->getDataGenerator()->create_user();
        $this->user2 = $this->getDataGenerator()->create_user();
        $this->user3 = $this->getDataGenerator()->create_user();
        $this->user4 = $this->getDataGenerator()->create_user();

        $this->course1 = $this->getDataGenerator()->create_course(array('fullname'=> 'Into'));
        $this->course2 = $this->getDataGenerator()->create_course(array('fullname'=> 'Basics'));
        $this->course3 = $this->getDataGenerator()->create_course(array('fullname'=> 'Advanced'));
        $this->course4 = $this->getDataGenerator()->create_course(array('fullname'=> 'Pro'));

    }

    function test_facetoface_get_status() {
        // check for valid status codes
        $this->assertEquals(facetoface_get_status(10), 'user_cancelled');
        //$this->assertEquals(facetoface_get_status(20), 'session_cancelled'); //not yet implemented
        $this->assertEquals(facetoface_get_status(30), 'declined');
        $this->assertEquals(facetoface_get_status(40), 'requested');
        $this->assertEquals(facetoface_get_status(50), 'approved');
        $this->assertEquals(facetoface_get_status(60), 'waitlisted');
        $this->assertEquals(facetoface_get_status(70), 'booked');
        $this->assertEquals(facetoface_get_status(80), 'no_show');
        $this->assertEquals(facetoface_get_status(90), 'partially_attended');
        $this->assertEquals(facetoface_get_status(100), 'fully_attended');

        $this->resetAfterTest(true);
    }

    function test_facetoface_cost() {
        // Test variables - case WITH discount.
        $sessiondata = $this->sessiondata['sess0'];
        $sess0 = $this->array_to_object($sessiondata);

        $userid1 = 1;
        $sessionid1 = 1;

        // Variable for test case NO discount.
        $sessiondata1 = $this->sessiondata['sess1'];
        $sess1 = $this->array_to_object($sessiondata1);

        $userid2 = 2;
        $sessionid2 = 2;

        // Test WITH discount.
        $this->assertEquals(facetoface_cost($userid1, $sessionid1, $sess0), '$60');

        // Test NO discount case.
        $this->assertEquals(facetoface_cost($userid2, $sessionid2, $sess1), '$90');

        $this->resetAfterTest(true);
    }

    function test_format_duration() {
        /* ISSUES:
         * expects a space after hour/s but not minute/s
         * minutes > 59 are not being converted to hour values
         * negative values are not interpreted correctly
         */

        // Test - for positive single hour value.
        $this->assertEquals(format_duration('1:00'), '1 hour ');
        $this->assertEquals(format_duration('1.00'), '1 hour ');

        // Test - for positive multiple hours value.
        $this->assertEquals(format_duration('3:00'), '3 hour(s) ');
        $this->assertEquals(format_duration('3.00'), '3 hour(s) ');

        // Test - for positive single minute value.
        $this->assertEquals(format_duration('0:01'), '1 minute');
        $this->assertEquals(format_duration('0.1'), '6 minute(s)');

        // Test - for positive minutes value.
        $this->assertEquals(format_duration('0:30'), '30 minute(s)');
        $this->assertEquals(format_duration('0.50'), '30 minute(s)');

        // Test - for out of range minutes value.
        $this->assertEquals(format_duration('9:70'), '');

        // Test - for zero value.
        $this->assertEquals(format_duration('0:00'), '');
        $this->assertEquals(format_duration('0.00'), '');

        // Test - for negative hour value.
        $this->assertEquals(format_duration('-1:00'), '');
        $this->assertEquals(format_duration('-1.00'), '');

        // Test - for negative multiple hours value.
        $this->assertEquals(format_duration('-7:00'), '');
        $this->assertEquals(format_duration('-7.00'), '');

        // Test - for negative single minute value.
        $this->assertEquals(format_duration('-0:01'), '');
        $this->assertEquals(format_duration('-0.01'), '');

        // Test - for negative multiple minutes value.
        $this->assertEquals(format_duration('-0:33'), '');
        $this->assertEquals(format_duration('-0.33'), '');

        // Test - for negative hours & minutes value.
        $this->assertEquals(format_duration('-5:42'), '');
        $this->assertEquals(format_duration('-5.42'), '');

        // Test - for invalid characters value.
        $this->assertEquals(format_duration('invalid_string'), '');

        $this->resetAfterTest(true);
    }

    function test_facetoface_minutes_to_hours() {
        // Test - for positive minutes value.
        $this->assertEquals(facetoface_minutes_to_hours('11'), '0:11');

        // Test - for positive hours & minutes value.
        $this->assertEquals(facetoface_minutes_to_hours('67'), '1:7');

        // Test - for negative minutes value.
        $this->assertEquals(facetoface_minutes_to_hours('-42'), '-42');

        // Test - for negative hours and minutes value.
        $this->assertEquals(facetoface_minutes_to_hours('-7:19'), '-7:19');

        // Test - for invalid characters value.
        $this->assertEquals(facetoface_minutes_to_hours('invalid_string'), '0');

        $this->resetAfterTest(true);
    }

    function test_facetoface_hours_to_minutes() {
        // Test - for positive hours value.
        $this->assertEquals(facetoface_hours_to_minutes('10'), '600');

        // Test - for positive minutes and hours value.
        $this->assertEquals(facetoface_hours_to_minutes('11:17'), '677');

        // Test - for negative hours value.
        $this->assertEquals(facetoface_hours_to_minutes('-3'), '-180');

        // Test - for negative hours & minutes value.
        $this->assertEquals(facetoface_hours_to_minutes('-2:1'), '-119');

        // Test - for invalid characters value.
        $this->assertEquals(facetoface_hours_to_minutes('invalid_string'), 0.0);

        $this->resetAfterTest(true);
    }

    function test_facetoface_fix_settings() {
        // test for facetoface object
        $facetoface1 = $this->facetoface['f2f0'];
        $f2f = $this->array_to_object($facetoface1);

        // Test - for empty values.
        $this->assertEquals(facetoface_fix_settings($f2f), null);

        $this->resetAfterTest(true);
    }

    function test_facetoface_add_instance() {
        // Define test variables.
        $facetoface1 = $this->facetoface['f2f0'];
        $f2f = $this->array_to_object($facetoface1);

        $this->assertEquals(facetoface_add_instance($f2f), 5);

        $this->resetAfterTest(true);
    }

    function test_facetoface_update_instance() {
        // Define test variables.
        $facetoface1 = $this->facetoface['f2f0'];
        $f2f = $this->array_to_object($facetoface1);

        // Test.
        $this->assertTrue((bool)facetoface_update_instance($f2f));

        $this->resetAfterTest(true);
    }

    function test_facetoface_delete_instance() {
        // Test variables.
        $id = 1;

        // Test.
        $this->assertTrue((bool)facetoface_delete_instance($id));

        $this->resetAfterTest(true);
    }

    function test_cleanup_session_data() {
        //define session object for test
        //valid values
        $sessionValid = new stdClass();
        $sessionValid->duration = '1.5';
        $sessionValid->capacity = '250';
        $sessionValid->normalcost = '70';
        $sessionValid->discountcost = '50';

        //invalid values
        $sessionInvalid = new stdClass();
        $sessionInvalid->duration = '0';
        $sessionInvalid->capacity = '100999';
        $sessionInvalid->normalcost = '-7';
        $sessionInvalid->discountcost = 'b';

        // Test - for valid values.
        $this->assertEquals(cleanup_session_data($sessionValid), $sessionValid);

        // Test - for invalid values.
        $this->assertEquals(cleanup_session_data($sessionInvalid), $sessionInvalid);

        $this->resetAfterTest(true);
    }

    function test_facetoface_add_session() {
        // Test. method - returns false or session id number
        $this->markTestSkipped('TODO - this test hasn\'t been working since 1.1');

        //variable for test
        $session1 = $this->sessions['sess0'];
        $sess0 = $this->array_to_object($session1);

        $sessiondates1 = new stdClass();

        // Test.
        $this->assertEquals(facetoface_add_session($session1, $sessiondates1), 4);
        $this->resetAfterTest(true);
    }

    function test_facetoface_update_session() {
        // test method - returns boolean
        $this->markTestSkipped('TODO - this test hasn\'t been working since 1.1');

        // Test variables.
        $session1 = $this->sessions['sess0'];
        $sess0 = $this->array_to_object($session1);

        $sessiondates = new stdClass();
        $sessiondates->sessionid = 1;
        $sessiondates->timestart = 1300;
        $sessiondates->timefinish = 1400;
        $sessiondates->sessionid = 1;

        // Test.
        $this->assertTrue((bool)facetoface_update_session($session1, $sessiondates), $this->msgtrue);
        $this->resetAfterTest(true);
    }

    function test_facetoface_update_attendees() {
        // Test variables.
        $session1 = $this->sessions['sess0'];
        $sess0 = $this->array_to_object($session1);

        $this->assertTrue((bool)facetoface_update_attendees($sess0), $this->msgtrue);
        $this->resetAfterTest(true);
    }

    function test_facetoface_get_facetoface_menu() {
        // positive test
        $menu = facetoface_get_facetoface_menu();
        $this->assertEquals('array', gettype($menu));
        $this->resetAfterTest(true);
    }

    function test_facetoface_delete_session() {
        // Test. method - returns boolean
        $this->markTestSkipped('TODO - this test hasn\'t been working since 1.1');

        //TODO invalid test
        // Test variables.
        $session1 = $this->sessions['sess0'];
        $sess0 = $this->array_to_object($session1);
        $this->assertTrue((bool)facetoface_delete_session($session1));
        $this->resetAfterTest(true);
    }

    function test_facetoface_cron() {
        // Test for valid case.
        $this->assertTrue((bool)facetoface_cron(true), $this->msgtrue);

        $this->resetAfterTest(true);
    }

    function test_facetoface_has_session_started() {
        // Define test variables.
        $session1 = $this->sessions['sess0'];
        $sess0 = $this->array_to_object($session1);
        $sess0->sessiondates = array(0 => new stdClass());
        $sess0->sessiondates[0]->timestart = time() - 100;
        $sess0->sessiondates[0]->timefinish = time() + 100;

        $session2 = $this->sessions['sess1'];
        $sess1 = $this->array_to_object($session2);

        $timenow = time();

        // Test for Valid case.
        $this->assertTrue((bool)facetoface_has_session_started($sess0, $timenow), $this->msgtrue);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_has_session_started($sess1, $timenow), $this->msgfalse);

        $this->resetAfterTest(true);
    }

    function test_facetoface_is_session_in_progress() {
        // Define test variables.
        $session1 = $this->sessions['sess0'];
        $sess0 = $this->array_to_object($session1);
        $sess0->sessiondates = array(0 => new stdClass());
        $sess0->sessiondates[0]->timestart = time() - 100;
        $sess0->sessiondates[0]->timefinish = time() + 100;

        $session2 = $this->sessions['sess1'];
        $sess1 = $this->array_to_object($session2);

        $timenow = time();

        // Test for valid case.
        $this->assertTrue((bool)facetoface_is_session_in_progress($sess0, $timenow), $this->msgtrue);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_is_session_in_progress($sess1, $timenow), $this->msgfalse);
        $this->resetAfterTest(true);
    }

    function test_facetoface_get_session_dates() {
        // Test variables.
        $sessionid1 = 1;
        $sessionid2 = 10;

        // Test for valid case.
        $this->assertTrue((bool)facetoface_get_session_dates($sessionid1), $this->msgtrue);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_get_session_dates($sessionid2), $this->msgfalse);
        $this->resetAfterTest(true);
    }

    function test_facetoface_get_session() {
        // Test variables.
        $sessionid1 = 1;
        $sessionid2 = 10;

        // test for valid case
        $this->assertTrue((bool)facetoface_get_session($sessionid1), $this->msgtrue);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_get_session($sessionid2), $this->msgfalse);
        $this->resetAfterTest(true);
    }

    function test_facetoface_get_sessions() {
        // Test variables.
        $facetofaceid1 = 1;
        $facetofaceid2 = 42;

        // Test for valid case.
        $this->assertTrue((bool)facetoface_get_sessions($facetofaceid1), $this->msgtrue);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_get_sessions($facetofaceid2), $this->msgfalse);
        $this->resetAfterTest(true);
    }

    function test_facetoface_get_attendees() {
        // Test variables.
        $sessionid1 = 1;
        $sessionid2 = 42;

        // Test - for valid sessionid.
        $this->assertTrue((bool)count(facetoface_get_attendees($sessionid1)));

        // Test - for invalid sessionid.
        $this->assertEquals(facetoface_get_attendees($sessionid2), array());
        $this->resetAfterTest(true);

    }

    function test_facetoface_get_attendee() {
        // Test variables.
        $sessionid1 = 1;
        $sessionid2 = 42;
        $userid1 = 1;
        $userid2 = 14;

        // Test for valid case.
        $this->assertTrue((bool)is_object(facetoface_get_attendee($sessionid1, $userid1)), $this->msgtrue);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_get_attendee($sessionid2, $userid2), $this->msgfalse);
        $this->resetAfterTest(true);
    }

    function test_facetoface_get_userfields() {
        $this->assertTrue((bool)facetoface_get_userfields(), $this->msgtrue);
        $this->resetAfterTest(true);
    }

    function test_facetoface_get_user_custom_fields() {
        // Test variables.
        $userid1 = 1;
        $userid2 = 42;
        $fieldstoinclude1 = TRUE;

        // Test for valid case.
        $this->assertTrue((bool)facetoface_get_user_customfields($userid1, $fieldstoinclude1), $this->msgtrue);
        $this->assertTrue((bool)facetoface_get_user_customfields($userid1), $this->msgtrue);
        //TODO invalid case
        // Test for invalid case.
        $this->resetAfterTest(true);
    }

    function test_facetoface_user_signup() {
        global $DB;

        $teacher1 = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();

        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        $this->getDataGenerator()->enrol_user($teacher1->id, $course1->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($student1->id, $course1->id, $studentrole->id);

        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');

        $facetofacedata = array(
            'name' => 'facetoface1',
            'course' => $course1->id
        );
        $facetoface1 = $facetofacegenerator->create_instance($facetofacedata);

        // Session that starts in 24hrs time.
        // This session should trigger a mincapacity warning now as cutoff is 24:01 hrs before start time.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + DAYSECS;
        $sessiondate->timefinish = time() + DAYSECS + 60;
        $sessiondate->sessiontimezone = 'Pacific/Auckland';

        $sessiondata = array(
            'facetoface' => $facetoface1->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1',
            'mincapacity' => '1',
            'cutoff' => DAYSECS - 60
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);

        $session = $DB->get_record('facetoface_sessions', array('id' => $sessionid));
        $session->sessiondates = facetoface_get_session_dates($session->id);

        $discountcode1 = 'disc1';
        $notificationtype1 = 1;
        $statuscode1 = 1;

        // Test for valid case.
        $this->assertTrue((bool)facetoface_user_signup($session, $facetoface1, $course1, $discountcode1, $notificationtype1, $statuscode1), $this->msgtrue);

        $this->resetAfterTest(true);
    }

    public function test_facetoface_user_signup_select_manager_message_manager() {
        global $DB, $CFG;

        set_config('facetoface_selectpositiononsignupglobal', true);

        $this->resetAfterTest();
        $this->preventResetByRollback();

        // Set up three users, one learner, a primary mgr and a secondary mgr.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $assignmentprim = new position_assignment(
            array('userid' => $user1->id, 'type' => POSITION_TYPE_PRIMARY, 'managerid' => $user2->id)
        );
        $assignmentsec = new position_assignment(
            array('userid' => $user1->id, 'type' => POSITION_TYPE_SECONDARY, 'managerid' => $user3->id)
        );
        assign_user_position($assignmentprim, true);
        assign_user_position($assignmentsec, true);

        // Get position assignment records.
        $posassprim = $DB->get_record('pos_assignment', array('userid' => $user1->id, 'type' => POSITION_TYPE_PRIMARY));
        $posassprim->positiontype = $posassprim->type;
        $posasssec = $DB->get_record('pos_assignment', array('userid' => $user1->id, 'type' => POSITION_TYPE_SECONDARY));
        $posasssec->positiontype = $posasssec->type;

        // Set up a face to face session that requires you to get manager approval and select a position.
        $facetofacedata = array(
            'course' => $this->course1->id,
            'multiplesessions' => 1,
            'selectpositiononsignup' => 1,
            'approvalreqd' => 1
        );
        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');
        $facetoface = $facetofacegenerator->create_instance($facetofacedata);
        $facetofaces[$facetoface->id] = $facetoface;

        // Create session with capacity and date in 2 years.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (DAYSECS * 365 * 2);
        $sessiondate->timefinish = time() + (DAYSECS * 365 * 2 + 60);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);

        // Grab any messages that get sent.
        $sink = $this->redirectMessages();

        // Sign the user up to the session with the secondary position.
        facetoface_user_signup(
            $session,
            $facetoface,
            $this->course1,
            'discountcode1',
            MDL_F2F_INVITE,
            MDL_F2F_STATUS_REQUESTED,
            $user1->id,
            true,
            '',
            $posasssec
        );

        // Grab the messages that got sent.
        $messages = $sink->get_messages();

        // Check the expected number of messages got sent.
        $this->assertCount(2, $messages);

        $foundstudent = false;
        $foundmanager = false;

        // Look for user1 and user 3 email addresses.
        foreach ($messages as $message) {
            if ($message->useridto == $user1->id) {
                $foundstudent = true;
            } else if ($message->useridto == $user3->id) {
                $foundmanager = true;
            }
        }
        $this->assertTrue($foundstudent);
        $this->assertTrue($foundmanager);
    }

    function test_facetoface_send_request_notice() {
        // Set managerroleid to make sure that it
        // matches the role id defined in the unit test
        // role table, as the local install may have a different
        // manager role id
        set_config('managerroleid', 1);

        // Test variables.
        $session1 = $this->sessions['sess0'];
        $sess0 = $this->array_to_object($session1);
        $facetoface1 = $this->facetoface['f2f0'];
        $f2f = $this->array_to_object($facetoface1);

        $userid1 = 1;
        $userid2 = 25;

        // Test for valid case. -- need to set manager
        //$this->assertEquals(facetoface_send_request_notice($f2f, $sess0, $userid1), '');

        // Test for invalid case.
        $this->assertEquals(get_string(facetoface_send_request_notice($f2f, $sess0, $userid2), 'facetoface'), 'No manager email is set');
        $this->resetAfterTest(true);
    }

    function test_facetoface_update_signup_status() {

        global $DB;

        $teacher1 = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();

        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        $this->getDataGenerator()->enrol_user($teacher1->id, $course1->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($student1->id, $course1->id, $studentrole->id);

        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');

        $facetofacedata = array(
            'name' => 'facetoface1',
            'course' => $course1->id
        );
        $facetoface1 = $facetofacegenerator->create_instance($facetofacedata);

        // Session that starts in 24hrs time.
        // This session should trigger a mincapacity warning now as cutoff is 24:01 hrs before start time.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + DAYSECS;
        $sessiondate->timefinish = time() + DAYSECS + 60;
        $sessiondate->sessiontimezone = 'Pacific/Auckland';

        $sessiondata = array(
            'facetoface' => $facetoface1->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1',
            'mincapacity' => '1',
            'cutoff' => DAYSECS - 60
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);

        $session = $DB->get_record('facetoface_sessions', array('id' => $sessionid));
        $session->sessiondates = facetoface_get_session_dates($session->id);

        $discountcode1 = 'disc1';
        $notificationtype1 = 1;
        $statuscode1 = 1;

        // Test for valid case.
        facetoface_user_signup($session, $facetoface1, $course1, $discountcode1, $notificationtype1, $statuscode1, $student1->id);

        $params = array('sessionid' => $sessionid, 'userid' => $student1->id);
        $signup = $DB->get_record('facetoface_signups', $params);
        // Test for valid case.
        $this->assertEquals(facetoface_update_signup_status($signup->id, $statuscode1, $teacher1->id, 'testnote'), 6);

        // Test for invalid case.
        // TODO invlaid case - how to cause sql error from here?
        //$this->assertFalse((bool)facetoface_update_signup_status($signupid2, $statuscode2, $createdby2, $note2), $this->msgfalse);
        $this->resetAfterTest(true);
    }

    function test_facetoface_user_cancel() {
        // test method - returns boolean
        $this->markTestSkipped('TODO - this test hasn\'t been working since 1.1');

        // Test variables.
        $session1 = $this->sessions['sess0'];
        $sess0 = $this->array_to_object($session1);
        $facetoface1 = $this->facetoface['f2f0'];
        $f2f = $this->array_to_object($facetoface1);

        $userid1 = 1;
        $forcecancel1 = TRUE;
        $errorstr1 = 'error1';
        $cancelreason1 = 'cancelreason1';

        $session2 = $this->session[1];

        $userid2 = 42;

        // Test for valid case.
        //$this->assertTrue((bool)facetoface_user_cancel($session1, $userid1, $forcecancel1, $errorstr1, $cancelreason1), $this->msgtrue);

        // Test for invalid case.
        //TODO invalid case?
        //$this->assertFalse((bool)facetoface_user_cancel($session2, $userid2), $this->msgfalse);
        $this->resetAfterTest(true);
    }

    // Test sending an adhoc notice using message substitution to the users signed for a session.
    function test_facetoface_send_notice() {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        $fields = array('username', 'email', 'institution', 'department', 'city', 'idnumber', 'icq', 'skype',
            'yahoo', 'aim', 'msn', 'phone1', 'phone2', 'address', 'url', 'description');

        $usernamefields = get_all_user_name_fields();
        $fields = array_merge($fields, array_values($usernamefields));

        $noticebody = '';
        foreach ($fields as $field) {
            $noticebody .= get_string('placeholder:'.$field, 'mod_facetoface') . ' ';
        }

        $noticebody .= get_string('placeholder:fullname', 'mod_facetoface') . ' ';

        $userdata = array();
        foreach ($fields as $field) {
            $userdata[$field] = 'display_' . $field;
        }

        // Set up three users, one learner, a primary mgr and a secondary mgr.
        $user1 = $this->getDataGenerator()->create_user($userdata);
        $course1 = $this->getDataGenerator()->create_course();

        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');
        $facetoface = $facetofacegenerator->create_instance(array('course' => $course1->id, 'multiplesessions' => 1));
        $facetofaces[$facetoface->id] = $facetoface;

        // Create session with capacity and date in 2 years.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (YEARSECS * 2);
        $sessiondate->timefinish = time() + (YEARSECS * 2 + 60);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);

        facetoface_user_signup($session, $facetoface, $this->course1, 'discountcode1', MDL_F2F_INVITE, MDL_F2F_STATUS_BOOKED, $user1->id, false);

        $notification = new facetoface_notification();
        $notification->booked = 0;
        $notification->courseid = $course1->id;
        $notification->facetofaceid = $facetoface->id;
        $notification->ccmanager = 0;
        $notification->status = 1;
        $notification->title = 'hello';
        $notification->body = $noticebody;
        $notification->managerprefix = '';
        $notification->type = MDL_F2F_NOTIFICATION_MANUAL;
        $notification->save();

        // Grab any messages that get sent.
        $sink = $this->redirectMessages();

        $notification->send_to_users($sessionid);

        // Grab the messages that got sent.
        $messages = $sink->get_messages();

        // Check the expected number of messages got sent.
        $this->assertCount(1, $messages);
        $this->assertEquals($user1->id, $messages[0]->useridto);

        foreach ($fields as $field) {
            $uservalue = 'display_' . $field;
            $this->assertTrue(strpos($messages[0]->fullmessage, $uservalue) !== false, $uservalue);
        }

        $this->assertTrue(strpos($messages[0]->fullmessage, fullname($user1)) !== false, fullname($user1));
    }

    function test_facetoface_send_confirmation_notice() {
        $this->resetAfterTest();
        $this->preventResetByRollback();

        // Set up three users, one learner, a primary mgr and a secondary mgr.
        $user1 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();

        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');
        $facetoface = $facetofacegenerator->create_instance(array('course' => $course1->id, 'multiplesessions' => 1));
        $facetofaces[$facetoface->id] = $facetoface;

        // Create session with capacity and date in 2 years.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (YEARSECS * 2);
        $sessiondate->timefinish = time() + (YEARSECS * 2 + 60);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);

        // Grab any messages that get sent.
        $sink = $this->redirectMessages();

        facetoface_user_signup($session, $facetoface, $this->course1, 'discountcode1', MDL_F2F_INVITE, MDL_F2F_STATUS_BOOKED, $user1->id, true);

        // Grab the messages that got sent.
        $messages = $sink->get_messages();

        // Check the expected number of messages got sent.
        $this->assertCount(1, $messages);
        $this->assertEquals($user1->id, $messages[0]->useridto);
    }

    function test_facetoface_send_cancellation_notice() {
        // Test. method - returns string
        $this->markTestSkipped('TODO - this test hasn\'t been working since 1.1');

        // Test variables.
        $facetoface1 = $this->facetoface[0];

        $session1 = $this->session[0];

        $userid1 = 1;

        // Test for valid case.
        //$this->assertEquals(facetoface_send_cancellation_notice($facetoface1, $session1, $userid1), '');
        $this->resetAfterTest(true);
    }

    function test_facetoface_get_manageremailformat() {
        //TODO how to run negative test?
        // Define test variables.
        //$addressformat = '';

        // test for no address format
        $this->assertEquals(facetoface_get_manageremailformat(), '');
        $this->resetAfterTest(true);
    }

    function test_facetoface_check_manageremail() {
        set_config('facetoface_manageraddressformat', 'example.com');

        // Define test variables.
        $validEmail = 'user@example.com';
        $invalidEmail = NULL;

        // Test for valid case.
        $this->assertTrue((bool)facetoface_check_manageremail($validEmail), $this->msgtrue);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_check_manageremail($invalidEmail), $this->msgfalse);
        $this->resetAfterTest(true);
    }

    function test_facetoface_take_attendance() {
        // Test variables.
        $data1 = new stdClass();
        $data1->s = 1;
        $data1->submissionid = 1;

        // Test for valid case.
        $this->assertTrue((bool)facetoface_take_attendance($data1), $this->msgtrue);
        //TODO invalid case
        // Test for invalid case.
        $this->resetAfterTest(true);
    }

    function test_facetoface_approve_requests() {
        // Test variables.
        $data1 = new stdClass();
        $data1->s = 1;
        $data1->submissionid = 1;
        $data1->requests = array(0 => new stdClass());
        $data1->requests[0]->request = 1;

        // Test for valid case.
        $this->assertTrue((bool)facetoface_approve_requests($data1), $this->msgtrue);

        // TODO test for invalid case
        $this->resetAfterTest(true);
    }

    function test_facetoface_ical_generate_timestamp() {
        // Test variables.
        $timenow = time();
        $return = gmdate("Ymd\THis\Z", $timenow);
        //TODO check if this is the correct return value to compare
        // Test for valid case.
        $this->assertEquals(facetoface_ical_generate_timestamp($timenow), $return);

        $this->resetAfterTest(true);
    }

    function test_facetoface_ical_escape() {
        // Define test variables.
        $text1 = "this is a test!&nbsp";
        $text2 = NULL;
        $text3 = "This string should start repeating at 75 charaters for three repetitions. "
            . "This string should start repeating at 75 charaters for three repetitions. "
            . "This string should start repeating at 75 charaters for three repetitions.";
        $text4 = "/'s ; \" ' \n , . & &nbsp;";

        $converthtml1 = FALSE;
        $converthtml2 = TRUE;

        // Tests.
        $this->assertEquals(facetoface_ical_escape($text1, $converthtml1), $text1);
        $this->assertEquals(facetoface_ical_escape($text1, $converthtml2), $text1);

        $this->assertEquals(facetoface_ical_escape($text2, $converthtml1), $text2);
        $this->assertEquals(facetoface_ical_escape($text2, $converthtml2), $text2);

        $this->assertEquals(facetoface_ical_escape($text3, $converthtml1),
            "This string should start repeating at 75 charaters for three repetitions.\r\n\t"
            . "This string should start repeating at 75 charaters for three repetitions.\r\n\t"
            . "This string should start repeating at 75 charaters for three repetitions.");
        $this->assertEquals(facetoface_ical_escape($text3, $converthtml2),
            "This string should start repeating at 75 charaters for three repetitions.\r\n\t"
            . "This string should start repeating at 75 charaters for three repetitions.\r\n\t"
            . "This string should start repeating at 75 charaters for three repetitions.");

        $this->assertEquals(facetoface_ical_escape($text4, $converthtml1), "/'s \; \\\" ' \\n \, . & &nbsp\;");
        $this->assertEquals(facetoface_ical_escape($text4, $converthtml2), "/'s \; \\\" ' \, . & ");

        $this->resetAfterTest(true);
    }

    function test_facetoface_update_grades() {
        // Variables.
        $facetoface1 = $this->facetoface['f2f0'];
        $f2f = $this->array_to_object($facetoface1);

        $userid = 0;

        $this->assertTrue((bool)facetoface_update_grades($f2f, $userid), $this->msgtrue);

        $this->resetAfterTest(true);
    }

    function test_facetoface_grade_item_update() {
        // Test variables.
        $facetoface1 = $this->facetoface['f2f0'];
        $f2f = $this->array_to_object($facetoface1);

        $grades = NULL;

        // Test.
        $this->assertTrue((bool)facetoface_grade_item_update($f2f), $this->msgtrue);

        $this->resetAfterTest(true);
    }

    function test_facetoface_grade_item_delete() {
        // Test variables.
        $facetoface1 = $this->facetoface['f2f0'];
        $f2f = $this->array_to_object($facetoface1);

        // Test for valid case.
        $this->assertTrue((bool)facetoface_grade_item_delete($f2f), $this->msgtrue);

        $this->resetAfterTest(true);
    }

    function test_facetoface_get_num_attendees() {
        // Test variables.
        $sessionid1 = 2;
        $sessionid2 = 42;

        // Test for valid case.
        $this->assertEquals(facetoface_get_num_attendees($sessionid1), 3);

        // Test for invalid case.
        $this->assertEquals(facetoface_get_num_attendees($sessionid2), 0);

        $this->resetAfterTest(true);
    }

    function test_facetoface_get_user_submissions() {
        // Test variables.
        $facetofaceid1 = 1;
        $userid1 = 1;
        $includecancellations1 = TRUE;

        $facetofaceid2 = 11;
        $userid2 = 11;
        $includecancellations2 = TRUE;

        // Test for valid case.
        $this->assertTrue((bool)facetoface_get_user_submissions($facetofaceid1, $userid1, $includecancellations1), $this->msgtrue);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_get_user_submissions($facetofaceid2, $userid2, $includecancellations2), $this->msgfalse);

        $this->resetAfterTest(true);
    }

    function test_facetoface_get_view_actions() {
        // Define test variables.
        $testArray = array('view', 'view all');

        // Test.
        $this->assertEquals(facetoface_get_view_actions(), $testArray);
        $this->resetAfterTest(true);
    }

    function test_facetoface_get_post_actions() {
        // Test method - returns an array.

        // Define test variables.
        $testArray = array('cancel booking', 'signup');

        // Test.
        $this->assertEquals(facetoface_get_post_actions(), $testArray);

        $this->resetAfterTest(true);
    }


    function test_facetoface_session_has_capacity() {
        // Test method - returns boolean.

        // Test variables.
        $session1 = $this->sessions['sess0'];
        $sess0 = $this->array_to_object($session1);

        $session2 = $this->sessions['sess1'];
        $sess1 = $this->array_to_object($session2);

        // Test for valid case.
        $this->assertFalse((bool)facetoface_session_has_capacity($sess0), $this->msgfalse);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_session_has_capacity($sess1), $this->msgfalse);

        $this->resetAfterTest(true);
    }

    function test_facetoface_get_trainer_roles() {
        global $CFG;
        // Test method - returns array.

        $context = context_course::instance(4);

        // No session roles.
        $this->assertFalse((bool)facetoface_get_trainer_roles($context), $this->msgfalse);

        // Add some roles.
        set_config('facetoface_session_roles', "4");

        $result = facetoface_get_trainer_roles($context);
        $this->assertEquals($result[4]->localname, 'Trainer');

        $this->resetAfterTest(true);
    }


    function test_facetoface_get_trainers() {
        // Test variables.
        $sessionid1 = 1;
        $roleid1 = 1;

        // Test for valid case.
        $this->assertTrue((bool)facetoface_get_trainers($sessionid1, $roleid1), $this->msgtrue);

        $this->assertTrue((bool)facetoface_get_trainers($sessionid1), $this->msgtrue);

        $this->resetAfterTest(true);
    }

    function test_facetoface_supports() {
        // Test variables.
        $feature1 = 'grade_has_grade';
        $feature2 = 'UNSUPPORTED_FEATURE';

        // Test for valid case.
        $this->assertTrue((bool)facetoface_supports($feature1), $this->msgtrue);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_supports($feature2), $this->msgfalse);

        $this->resetAfterTest(true);
    }

    function test_facetoface_manager_needed() {
        // Test variables.
        $facetoface1 = $this->facetoface['f2f1'];
        $f2f1 = $this->array_to_object($facetoface1);

        $facetoface2 = $this->facetoface['f2f0'];
        $f2f2 = $this->array_to_object($facetoface2);

        // Test for valid case.
        $this->assertTrue((bool)facetoface_manager_needed($f2f1), $this->msgtrue);

        // Test for invalid case.
        $this->assertFalse((bool)facetoface_manager_needed($f2f2), $this->msgfalse);

        $this->resetAfterTest(true);
    }

    function test_facetoface_notify_under_capacity() {
        global $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();

        $teacher1 = $this->getDataGenerator()->create_user();
        $student1 = $this->getDataGenerator()->create_user();
        $course1 = $this->getDataGenerator()->create_course();

        $teacherrole = $DB->get_record('role', array('shortname' => 'editingteacher'));
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        $this->getDataGenerator()->enrol_user($teacher1->id, $course1->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($student1->id, $course1->id, $studentrole->id);


        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');

        $facetofacedata = array(
            'name' => 'facetoface1',
            'course' => $course1->id
        );
        $facetoface1 = $facetofacegenerator->create_instance($facetofacedata);

        // Session that starts in 24hrs time.
        // This session should trigger a mincapacity warning now as cutoff is 24:01 hrs before start time.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + DAYSECS;
        $sessiondate->timefinish = time() + DAYSECS + 60;
        $sessiondate->sessiontimezone = 'Pacific/Auckland';

        $sessiondate2 = new stdClass();
        $sessiondate2->timestart = time() + (DAYSECS * 2);
        $sessiondate2->timefinish = time() + (DAYSECS * 2) + 60;
        $sessiondate2->sessiontimezone = 'Pacific/Auckland';

        $sessiondata = array(
            'facetoface' => $facetoface1->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate, $sessiondate2),
            'datetimeknown' => '1',
            'mincapacity' => '1',
            'cutoff' => DAYSECS - 60
        );
        $facetofacegenerator->add_session($sessiondata);

        // Session that starts in 24hrs time.
        // This session should not trigger a mincapacity warning now as cutoff is 23:59 hrs before start time.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + DAYSECS;
        $sessiondate->timefinish = time() + DAYSECS + 60;
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface1->id,
            'capacity' => 3,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1',
            'mincapacity' => '1',
            'cutoff' => DAYSECS + 60
        );
        $facetofacegenerator->add_session($sessiondata);

        $sink = $this->redirectMessages();
        ob_start();
        facetoface_notify_under_capacity();
        $mtrace = ob_get_clean();
        $this->assertContains('is under capacity', $mtrace);
        $messages = $sink->get_messages();

        // Only the teacher should get a message.
        $this->assertCount(1, $messages);
        $this->assertEquals($messages[0]->useridto, $teacher1->id);

        // Check they got the right message.
        $this->assertEquals(get_string('sessionundercapacity', 'facetoface', format_string($facetoface1->name)), $messages[0]->subject);
    }

    public function test_facetoface_waitlist() {
        $this->resetAfterTest();

        // Set two users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        // Set up a face to face session with a capacity of 1 and overbook enabled.
        $facetofacegenerator = $this->getDataGenerator()->get_plugin_generator('mod_facetoface');
        $facetoface = $facetofacegenerator->create_instance(array('course' => $this->course1->id));

        // Create session with capacity and date in 2 years.
        $sessiondate = new stdClass();
        $sessiondate->timestart = time() + (YEARSECS * 2);
        $sessiondate->timefinish = time() + (YEARSECS * 2 + 60);
        $sessiondate->sessiontimezone = 'Pacific/Auckland';
        $sessiondata = array(
            'facetoface' => $facetoface->id,
            'capacity' => 1,
            'allowoverbook' => 1,
            'sessiondates' => array($sessiondate),
            'datetimeknown' => '1'
        );
        $sessionid = $facetofacegenerator->add_session($sessiondata);
        $session = facetoface_get_session($sessionid);

        $sink = $this->redirectMessages();
        // Sign the user up user 2.
        facetoface_user_signup(
            $session,
            $facetoface,
            $this->course1,
            'discountcode1',
            MDL_F2F_INVITE,
            MDL_F2F_STATUS_BOOKED,
            $user1->id,
            true,
            ''
        );

        // Sign the user up user 1.
        facetoface_user_signup(
            $session,
            $facetoface,
            $this->course1,
            'discountcode1',
            MDL_F2F_INVITE,
            MDL_F2F_STATUS_WAITLISTED,
            $user2->id,
            true,
            ''
        );
        $messages = $sink->get_messages();
        // User 1 and 2 should have received confirmation messages.
        $this->assertCount(2, $messages);

        $founduser1 = false;
        $founduser2 = false;

        // Look for user1 and user 2 email addresses.
        foreach ($messages as $message) {
            if ($message->useridto == $user1->id) {
                $founduser1 = true;
            } else if ($message->useridto == $user2->id) {
                $founduser2 = true;
            }
        }
        $this->assertTrue($founduser1);
        $this->assertTrue($founduser2);

        $sink->clear();

        // User 1 should be booked, user 2 waitlisted.
        $booked = facetoface_get_attendees($session->id, MDL_F2F_STATUS_BOOKED);
        $waitlisted = facetoface_get_attendees($session->id, MDL_F2F_STATUS_WAITLISTED);
        $this->assertCount(1, $booked);
        $this->assertCount(1, $waitlisted);
        $booked = reset($booked);
        $waitlisted = reset($waitlisted);
        $this->assertEquals($user1->id, $booked->id);
        $this->assertEquals($user2->id, $waitlisted->id);

        $sink->clear();

        // Cancel user1's booking.
        facetoface_user_cancel($session, $user1->id);

        $cancelled = facetoface_get_attendees($session->id, MDL_F2F_STATUS_USER_CANCELLED);
        $booked = facetoface_get_attendees($session->id, MDL_F2F_STATUS_BOOKED);
        $waitlisted = facetoface_get_attendees($session->id, MDL_F2F_STATUS_WAITLISTED);

        // User 1 should be cancelled, user 2 should be booked.
        $this->assertCount(1, $cancelled);
        $this->assertCount(1, $booked);
        $this->assertCount(0, $waitlisted);
        $cancelled = reset($cancelled);
        $booked = reset($booked);
        $this->assertEquals($user1->id, $cancelled->id);
        $this->assertEquals($user2->id, $booked->id);

        // User 2 should have had a message from admin.
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertEquals($user2->id, $message->useridto);
        $this->assertEquals(0, $message->useridfrom);
    }
}
