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
 * @author Yuliya Bozhko <yuliya.bozhko@totaralms.com>
 * @package totara
 * @subpackage totara_coursecatalog
 */

$string['addcertification'] = 'Create Certification';
$string['addcourse'] = 'Create Course';
$string['addprogram'] = 'Create Program';
$string['backtoallcategory'] = 'Back to all Categories';
$string['backtoallcourse'] = 'Back to all courses';
$string['backtoallprogram'] = 'Back to all programs';
$string['backtocategoryx'] = 'Back to {$a}';
$string['backtocourselisting'] = 'Back to course listing';
$string['backtoparent'] = '&laquo; Back to parent';
$string['browsebycategory'] = 'Browse by category';
$string['businessdays'] = 'Business days';
$string['catalogcertifications'] = 'Enhanced catalog: certifications';
$string['catalogcourses'] = 'Enhanced catalog: courses';
$string['catalogprograms'] = 'Enhanced catalog: programs';
$string['certifications'] = 'Certifications';
$string['completiontotrack'] = 'Completion to track';
$string['coursecatalog:manageaudiencevisibility'] = 'Manage audience-based visibility';
$string['courses'] = 'Courses';
$string['deletedreminder'] = 'Successfully deleted reminder "{$a}"';
$string['deletereminder'] = 'Delete reminder "{$a}"';
$string['dontsend'] = 'Don\'t send this message';
$string['editcoursereminders'] = 'Edit course reminders';
$string['error:courseidincorrect'] = 'Course ID was incorrect';
$string['error:courseidorcategory'] = 'Either course id or category must be specified';
$string['error:createreminder'] = 'Could not create reminder message';
$string['error:deletereminder'] = 'Could not delete reminder message';
$string['error:updatereminder'] = 'Could not update reminder message';
$string['escalation'] = 'Escalation';
$string['escalationmessagedefault'] = 'This is to advise that the following staff member has an outstanding course evaluation:

[firstname] [lastname]
[coursename]

Course evaluations are important to the business and help inform on the value specific training provides and if they are being delivered in the most effective way.
As you maybe aware your involvement with your staff member around this training is also measured.

There are no further reminders of this but please be aware that the return rates are noted and reported at the business unit & business group level.';
$string['escalationsubjectdefault'] = 'Outstanding Course evaluation from [firstname] [lastname]';
$string['findlearning'] = 'Find Learning';
$string['invitation'] = 'Invitation';
$string['invitationmessage'] = 'Message';
$string['invitationmessage_help'] = 'This is the message for the invitation email.

The message can include a number of placeholders:

* [firstname] - Users firstname
* [lastname] - Users lastname
* [coursepageurl] - A clickable link to the course homepage
* [coursename] - Name of the course
* [managername] - Name of users manager
* [days counter up] - Number of days since completion
* [days count down] - Number of days until deadline
';
$string['invitationmessagedefault'] = 'Dear [firstname]

Congratulations on completing [coursename].

We would now like you to complete a course evaluation. This is important as it informs those responsible for training what is and what isn’t working. The course evaluation is mostly multiple choice questions and takes 2 to 3 minutes to complete.

Please visit the course page link below to access the evaluation:

[coursepageurl]

Please action this within three days.

Should this still be outstanding in [days count down] days time we will escalate this to your Team Leader.';
$string['invitationperiod'] = 'Period';
$string['invitationperiod_help'] = 'The period before the invitation is sent in days.
';
$string['invitationsubject'] = 'Invitiation Reminder Subject';
$string['invitationsubject_help'] = 'This is the subject of the invitation email that is sent.

The subject can include a number of placeholders:

* [firstname] - Users firstname
* [lastname] - Users lastname
* [coursepageurl] - A clickable link to the course homepage
* [coursename] - Name of the course
* [managername] - Name of users manager
* [days counter up] - Number of days since completion
* [days count down] - Number of days until deadline
';
$string['invitationsubjectdefault'] = 'Please evaluate [coursename]';
$string['message'] = 'Message';
$string['missingtitle'] = 'Missing title';
$string['new'] = 'Add New';
$string['nextday'] = 'Next day';
$string['noactivitieswithcompletionenabled'] = 'Course reminders are unavailable as no activities in this course have completion enabled';
$string['nocertificationsfound'] = 'No certifications were found with the words "{$a}"';
$string['noeditsite'] = 'You cannot edit the site course using this form';
$string['nofeedbackactivities'] = 'Course reminders are unavailable as there are no Feedback activities in this course';
$string['nomanagermessage'] = '(no manager set)';
$string['noprogramsfound'] = 'No programs were found with the words "{$a}"';
$string['orviewcertifications'] = 'or view certifications in this category ({$a})';
$string['orviewcourses'] = 'or view courses in this category ({$a})';
$string['orviewprograms'] = 'or view programs in this category ({$a})';
$string['performsearchoncertifications'] = 'or perform this search on certifications';
$string['performsearchoncourses'] = 'or perform this search on courses';
$string['performsearchonprograms'] = 'or perform this search on programs';
$string['period'] = 'Period';
$string['placeholder:coursename'] = '[coursename]';
$string['placeholder:coursepageurl'] = '[coursepageurl]';
$string['placeholder:dayssincecompletion'] = '[days counter up]';
$string['placeholder:daysuntildeadline'] = '[days count down]';
$string['placeholder:firstname'] = '[firstname]';
$string['placeholder:lastname'] = '[lastname]';
$string['placeholder:managername'] = '[managername]';
$string['pluginname'] = 'Course Catalog';
$string['programs'] = 'Programs';
$string['reminder'] = 'Reminder';
$string['remindermessage'] = 'Message';
$string['remindermessage_help'] = 'This is the message for the reminder email.

The message can include a number of placeholders:

* [firstname] - Users firstname
* [lastname] - Users lastname
* [coursepageurl] - A clickable link to the course homepage
* [coursename] - Name of the course
* [managername] - Name of users manager
* [days counter up] - Number of days since completion
* [days count down] - Number of days until deadline
';
$string['remindermessagedefault'] = 'Dear [firstname]

This is a reminder to complete your course evaluation of [coursename].

This is important and only takes 2 to 3 minutes of your time.

Please visit the course page link below to access the evaluation:

[coursepageurl]

Should this remain outstanding we will escalate that this to your team leader.';
$string['reminderperiod'] = 'Period';
$string['reminderperiod_help'] = 'The period before the reminder is sent in days.
';
$string['remindersmenuitem'] = 'Reminders';
$string['remindersubject'] = 'Subject';
$string['remindersubject_help'] = 'This is the subject for the reminder email.

The subject can include a number of placeholders:

* [firstname] - Users firstname
* [lastname] - Users lastname
* [coursepageurl] - A clickable link to the course homepage
* [coursename] - Name of the course
* [managername] - Name of users manager
* [days counter up] - Number of days since completion
* [days count down] - Number of days until deadline
';
$string['remindersubjectdefault'] = 'Reminder to evaluate [coursename]';
$string['requirement'] = 'Requirement';
$string['requirement_help'] = 'The Required feedback activity that needs to be completed.
';
$string['sameday'] = 'Same day';
$string['search'] = 'Search';
$string['searchagain'] = 'Search again';
$string['searchallcategories'] = 'Search all categories';
$string['searchallcertifications'] = 'Search all certifications';
$string['searchallcourses'] = 'Search all courses';
$string['searchallprograms'] = 'Search all programs';
$string['searchcategories'] = 'Search categories';
$string['searchcategoriesmatchesplural'] = '{$a->count} categories match search "{$a->terms}"';
$string['searchcategoriesmatchessingle'] = '1 category matches search "{$a->terms}"';
$string['searchcategoriesshowall'] = 'Showing all categories';
$string['searchcertificationsmatchesplural'] = '{$a->count} certifications match search "{$a->terms}"';
$string['searchcertificationsmatchessingle'] = '1 certification matches search "{$a->terms}"';
$string['searchcertificationsshowall'] = 'Showing all certifications';
$string['searchcourses'] = 'Search courses';
$string['searchcoursesmatchesplural'] = '{$a->count} courses match search "{$a->terms}"';
$string['searchcoursesmatchessingle'] = '1 course matches search "{$a->terms}"';
$string['searchcoursesshowall'] = 'Showing all courses';
$string['searchprogramsmatchesplural'] = '{$a->count} programs match search "{$a->terms}"';
$string['searchprogramsmatchessingle'] = '1 program matches search "{$a->terms}"';
$string['searchprogramsshowall'] = 'Showing all programs';
$string['showallcategorys'] = 'Show all categories';
$string['showallcertifications'] = 'Show all certifications';
$string['showallcourses'] = 'Show all courses';
$string['showallprograms'] = 'Show all programs';
$string['skipmanager'] = 'Don\'t send to Team Leader / Manager';
$string['subject'] = 'Subject';
$string['therearenocoursestodisplay'] = 'There are no courses to display';
$string['title'] = 'Title';
$string['title_help'] = 'A title that is used to identify the reminder.';
$string['tracking'] = 'Completion to track';
$string['tracking_help'] = 'The Completion to track, this can be any activity with or course with completion enabled.';
$string['viewallcategories'] = 'View all categories';
$string['viewallcertifications'] = 'View all certifications';
$string['viewallcourses'] = 'View all courses';
