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
 * @author Andrew Hancox <andrewdchancox@googlemail.com> on behalf of Synergy Learning
 * @package totara
 * @subpackage enrol_totara_facetoface
 */

/**
 * Strings for component 'enrol_totara_facetoface', language 'en'.
 */

$string['additionalinformation'] = 'Additional signup information';
$string['autobookingcompleted'] = 'Your booking has been completed and you have been enrolled on {$a} session(s).';
$string['autosignup'] = 'Automatically sign users up to face to face sessions';
$string['autosignup_help'] = 'If set to yes then on enrolling the user will be signed up to all face to face activities on the course.
If multiple enrolment is enabled then the user will be signed up to every session that has availability or a waiting list, otherwise they will be signed up to the earliest session with either a space or waiting list.';
$string['cannotenrol'] = 'Enrolment is disabled or inactive';
$string['cannotenrolalreadyrequested'] = 'It is not possible to sign up for these sessions (manager request already pending).';
$string['cannotenrolnosessions'] = 'Cannot enrol (no face-fo-face sessions in this course)';
$string['cohortnonmemberinfo'] = 'Only members of cohort \'{$a}\' can use face-to-face enrolment.';
$string['cohortonly'] = 'Only cohort members';
$string['cohortonly_help'] = 'Face-to-Face Direct enrolment may be restricted to members of a specified cohort only. Note that changing this setting has no effect on existing enrolments.';
$string['customwelcomemessage'] = 'Custom welcome message';
$string['customwelcomemessage_help'] = 'A custom welcome message may be added as plain text or Moodle-auto format, including HTML tags and multi-lang tags.

The following placeholders may be included in the message:

* Course name {$a->coursename}
* Link to user\'s profile page {$a->profileurl}';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during use face-to-face enrolment';
$string['enroldelete'] = 'Delete enrolment';
$string['enroledit'] = 'Edit enrolment';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can enrol themselves until this date only.';
$string['enrolenddateerror'] = 'Enrolment end date cannot be earlier than start date';
$string['enrolme'] = 'Enrol me';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user enrols themselves. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can enrol themselves from this date onward only.';
$string['expiredaction'] = 'Enrolment expiration action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['expirymessageenrollerbody'] = 'Face-to-Face Direct enrolment in the course \'{$a->course}\' will expire within the next {$a->threshold} for the following users:

{$a->users}

To extend their enrolment, go to {$a->extendurl}';
$string['expirymessageenrolledsubject'] = 'Face-to-Face Direct enrolment expiry notification';
$string['expirymessageenrolledbody'] = 'Dear {$a->user},

This is a notification that your enrolment in the course \'{$a->course}\' is due to expire on {$a->timeend}.

If you need help, please contact {$a->enroller}.';
$string['expirymessageenrollersubject'] = 'Face-to-Face Direct enrolment expiry notification';
$string['longtimenosee'] = 'Unenrol inactive after';
$string['longtimenosee_help'] = 'If users haven\'t accessed a course for a long time, then they are automatically unenrolled. This parameter specifies that time limit.';
$string['managermissingallsessions'] = 'Direct enrolment is not available to you because you are not assigned a manager.';
$string['managermissingsomesessions'] = 'Some sessions are not available to you because you are not assigned a manager.';
$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_help'] = 'Specifies the maximum number of users that can use face-to-face enrolment. 0 means no limit.';
$string['maxenrolledreached'] = 'Maximum number of users allowed to use face-to-face enrolment was already reached.';
$string['messageprovider:expiry_notification'] = 'Face-to-Face Direct enrolment expiry notifications';
$string['newenrols'] = 'Allow new enrolments';
$string['newenrols_desc'] = 'Allow users to use face-to-face enrolment on new courses by default.';
$string['newenrols_help'] = 'This setting determines whether a user can enrol into this course.';
$string['pluginname'] = 'Face-to-face direct enrolment';
$string['pluginname_desc'] = 'The face-to-face direct enrolment plugin allows users to choose which courses they want to participate in. The courses may be protected by an enrolment key.';
$string['role'] = 'Default assigned role';
$string['selectthissession'] = 'Select this session:';
$string['selectsession'] = 'Select session';
$string['self:config'] = 'Configure Face-to-face direct enrol instances';
$string['self:manage'] = 'Manage enrolled users';
$string['self:unenrol'] = 'Unenrol users from course';
$string['self:unenrolself'] = 'Unenrol self from the course';
$string['selfapprovalrequired'] = 'Please check the box below confirming that self-approval has been sought for the chosen session.';
$string['sendcoursewelcomemessage'] = 'Send course welcome message';
$string['sendcoursewelcomemessage_help'] = 'If enabled, users receive a welcome message via email when they sign up for a course using face-to-face enrolment.';
$string['showhint'] = 'Show hint';
$string['showhint_desc'] = 'Show first letter of the guest access key.';
$string['signuptoenrol'] = 'To enrol in the session and course, choose a session below and click \'Sign-up\'. Manager approval may be required.';
$string['status'] = 'Enable existing enrolments';
$string['status_desc'] = 'Enable face-to-face enrolment method in new courses.';
$string['status_help'] = 'If disabled all existing Face-to-face direct enrolments are suspended and new users can not enrol.';
$string['totara_facetoface:config'] = 'Configure Face-to-Face Direct enrolment instances';
$string['totara_facetoface:manage'] = 'Manage Face-to-Face Direct enrolled users';
$string['totara_facetoface:unenrol'] = 'Unenrol Face-to-Face Direct enrolment users from course';
$string['totara_facetoface:unenrolself'] = 'Unenrol self from the course and Face-to-Face sessions';
$string['unenrol'] = 'Unenrol user';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';
$string['unenroluser'] = 'Do you really want to unenrol "{$a->user}" from course "{$a->course}"?';
$string['unenrolwhenremoved'] = 'Unenrol users when removed from all Face-to-face sessions';
$string['welcometocourse'] = 'Welcome to {$a}';
$string['welcometocoursetext'] = 'Welcome to {$a->coursename}!

If you have not done so already, you should edit your profile page so that we can learn more about you:

  {$a->profileurl}';
$string['withdrawconfifm'] = 'Are you sure you want to withdraw your manager sign-up request from this course and session?';
$string['withdrawpending'] = 'Withdraw pending request';
