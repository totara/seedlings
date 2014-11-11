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
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package enrol
 * @subpackage totara_program
 */

$string['pluginname_desc'] = 'The Program enrolment plugin is used to provide access to courses that form part of a program. Courses added to programs will have this plugin enabled automatically, and users will automatically get enrolled into the appropriate courses via this plugin.';
$string['guestnoenrol'] = 'You are unable to enrol in this course. You need to be a real (not guest) user to be allowed to enrol in this course.Click \'logout\' to stop being a guest, and log in using individual username and password, then try enroling again.';
$string['guestaccess'] = 'Without enrolment, guests are allowed <a href="{$a}">limited access to view the course</a>.<br />';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid (in seconds). If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user enrols themselves. If disabled, the enrolment duration will be unlimited.';
$string['nowenrolled'] = 'You have been enrolled in course {$a->course} via required learning program {$a->program}.';
$string['nowenrolledcontinue'] = 'You have been enrolled in course {$a->course} via required learning program {$a->program}.<br/>
        <br/><a href="{$a->url}">Continue</a>';
$string['redirectedsoon'] = 'You are about to be redirected.';
$string['program'] = 'Program';
$string['pluginname'] = 'Program';
$string['totara_program:unenrol'] = 'Unenrol users from course';
