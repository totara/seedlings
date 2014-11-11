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
 * @package mod_facetoface
 */

$string['activate'] = 'Activate';
$string['addeditattendeeserror'] = 'Add/edit attendees error';
$string['addeditattendeesresults'] = 'Add/edit attendees results';
$string['addeditattendeessuccess'] = 'Add/edit attendees success';
$string['addedsuccessfully'] = 'Added successfully';
$string['addmanageremailaddress'] = 'Add manager email address';
$string['addmanageremailinstruction'] = 'You have not previously entered your manager\'s email address. Please enter it below to sign-up for this session. ';
$string['addnotification'] = 'Add notification';
$string['addnotificationtemplate'] = 'Add notification template';
$string['address'] = 'Address';
$string['addsession'] = 'Add a new session';
$string['addingsession'] = 'Adding a new session in {$a}';
$string['addnewfield'] = 'Add a new custom field';
$string['addnewfieldlink'] = 'Create a new custom field';
$string['addnewnotice'] = 'Add a new site notice';
$string['addnewnoticelink'] = 'Create a new site notice';
$string['addremoveattendees'] = 'Add/remove attendees';
$string['addremoveattendeeswaitlisted'] = 'Please Note: Attendees added will be automatically added to the waiting list';
$string['addroom'] = 'Add a room';
$string['addstudent'] = 'Add student';
$string['afterendofsession'] = 'after end of session';
$string['alllocations'] = 'All locations';
$string['allocate'] = 'Allocate spaces for team';
$string['allocatenoteam'] = 'There are no team members you can allocate to this session';
$string['allocationfull_noreserve'] = 'Without replacing your current reservations, you can only allocate {$a} space(s) for this session';
$string['allocationfull_reserve'] = 'You can only allocate {$a} space(s) for this session';
$string['allowcancellations'] = 'Allow booking cancellations';
$string['allowcancellationsdefault'] = 'Allow booking cancellations default';
$string['allowcancellationsdefault_help'] = 'Whether sessions in this activity will allow booking cancellations by default, can be overridden in the session settings';
$string['allowcancellations_help'] = 'Whether session attendees will be able to cancel their bookings';
$string['allowconflicts'] = 'Allow conflicts';
$string['allowconflicts_help'] = 'This will allow scheduling conflicts to exist';
$string['allowoverbook'] = 'Allow overbooking';
$string['allowschedulingconflicts'] = 'Override user conflicts';
$string['allowschedulingconflicts_help'] = 'If trainers or users are already assigned or booked onto another Facetoface session at the same time as this session then the administrator will be warned, but can override such warnings and proceed anyway by selecting "Yes" from the drop-down menu';
$string['allowselectedschedulingconflicts'] = 'Allow selected scheduling conflicts';
$string['allowsignupnotedefault'] = 'Allow "User sign-up note" default';
$string['allowsignupnotedefault_help'] = 'Whether sessions in this activity will allow a sign-up note by default, can be overridden in the session settings';
$string['allsessionsin'] = 'All sessions in {$a}';
$string['alreadysignedup'] = 'You have already signed-up for this Face-to-face activity.';
$string['answer'] = 'Sign in';
$string['answercancel'] = 'Sign out';
$string['approvalreqd'] = 'Approval required';
$string['applyfilter'] = 'Apply filter';
$string['approve'] = 'Approve';
$string['areyousureconfirmwaitlist'] = 'This will be over the session capacity allowance. Are you sure you want to continue?';
$string['assessmentyour'] = 'Your assessment';
$string['attendance'] = 'Attendance';
$string['attendanceinstructions'] = 'Select users who attended the session:';
$string['attendedsession'] = 'Attended session';
$string['attendeenote'] = 'Attendee\'s note';
$string['attendees'] = 'Attendees';
$string['approvalnocapacity'] = 'There are {$a->waiting} learners awaiting approval but no spaces available, you cannot approve any more learners at this time.';
$string['approvalnocapacitywaitlist'] = 'There are {$a->waiting} learners awaiting approval but no spaces available - any approvals will be added to the waitlist instead.';
$string['approvalovercapacity'] = 'There are {$a->waiting} learners awaiting approval but only {$a->available} spaces available. Only the first {$a->available} learners you approve will be added to the session.';
$string['approvalovercapacitywaitlist'] = 'There are {$a->waiting} learners awaiting approval but only {$a->available} spaces available.<br /> Only the first {$a->available} learners you approve will be added to the session - additional approvals will be added to the waitlist.';
$string['potentialattendees'] = 'Potential Attendees';
$string['attendeestablesummary'] = 'People planning on or having attended this session.';
$string['availablesignupnote'] = 'User sign-up note';
$string['requeststablesummary'] = 'People requesting to attended this session.';
$string['beforestartofsession'] = 'before start of session';
$string['body'] = 'Body';
$string['body_help'] = 'This is the body of the notification to be sent.

In the notification there are a number of placeholders that can be used, these placeholders will be replaced with the appropriate values when the message is sent.

Available Face to Face placholders:

* [coursename] - Name of course
* [facetofacename] - Name of Face to face activity
* [cost] - Cost of session
* [alldates] - All session dates for the Face to face
* [reminderperiod] - Amount of time before the session that the reminder message is sent
* [sessiondate] - Date of the session the learner is booked on
* [startdate] - Date at the start of the session. If there are multiple session dates it will use the first one.
* [finishdate] - Date at the end of the session. If there are multiple session dates it will use the first one.
* [starttime] - Start time of the session. If there are multiple session dates it will use the first one.
* [finishtime] - Finish time of the session. If there are multiple session dates it will use the first one.
* [duration] - Length of the session
* [details] - Details about the session
* [attendeeslink] - Link to the attendees page for the session
* [session:location] - Location of the session
* [session:venue] - Venue the session is being held in
* [session:room] - Room the session is being held in

There are also placeholders available for session custom fields and they follow the format [session:shortname]. Where "shortname" is the shortname of the Face to face custom field.

Available user placeholders:

* [firstname] - User\'s first name
* [lastname] - User\'s last name
* [middlename] - User\'s middle name
* [firstnamephonetic] - Phonetic spelling of the User\'s first name
* [lastnamephonetic] - Phonetic spelling of the User\'s last name
* [alternatename] - Alternate name the user is known by
* [fullname] - User\'s full name
* [username] - User\'s username
* [idnumber] - User\'s ID Number
* [email] - User\'s email address
* [address] - User\'s address
* [city] - User\'s city
* [country] - User\'s country
* [department] - User\'s department
* [description] - User\'s description
* [institution] - User\'s institution
* [lang] - User\'s language
* [icq] - User\'s ICQ number
* [aim] - User\'s AIM ID
* [msn] - Users\'s MSN ID
* [yahoo] - User\'s Yahoo ID
* [skype] - User\'s Skype ID
* [phone1] - User\'s phone number
* [phone2] - User\'s mobile phone number
* [timezone] - User\'s timezone
* [url] - User\'s URL

There are also placeholders available for user custom profile fields and they follow the format [user:shortname]. Where "shortname" is the shortname of the User custom profile field.
';
$string['booked'] = 'Booked';
$string['bookingcancelled'] = 'Your booking has been cancelled.';
$string['bookingcompleted'] = 'Your booking has been completed.';
$string['bookingcompleted_approvalrequired'] = 'Your booking has been completed but requires approval from your manager.';
$string['bookingfull'] = 'Booking full';
$string['bookingopen'] = 'Booking open';
$string['bookingstatus'] = 'You are booked for the following session';
$string['building'] = 'Building';
$string['bulkactions'] = 'Bulk actions';
$string['bulkaddattendeeserror'] = 'Bulk add attendees error';
$string['bulkaddattendeesfromfile'] = 'Bulk add attendees from file';
$string['bulkaddattendeesfrominput'] = 'Bulk add attendees from text input';
$string['bulkaddattendeesresults'] = 'Bulk add attendees results';
$string['bulkaddattendeessuccess'] = 'Bulk add attendees success';
$string['bulkaddheading'] = 'Bulk Add';
$string['bulkaddhelptext'] = 'Note: Users must be referenced by their {$a} and must be delimited by a comma or newline';
$string['bulkaddsourceidnumber'] = 'ID number';
$string['bulkaddsourceuserid'] = 'user id';
$string['bulkaddsourceusername'] = 'username';
$string['calendareventdescriptionbooking'] = 'You are booked for this <a href="{$a}">Face-to-face session</a>.';
$string['calendareventdescriptionsession'] = 'You have created this <a href="{$a}">Face-to-face session</a>.';
$string['calendarfiltersheading'] = 'Facetoface calendar filters';
$string['calendaroptions'] = 'Calendar options';
$string['cancelbooking'] = 'Cancel booking';
$string['cancelbookingfor'] = 'Cancel booking for {$a}';
$string['cancellationreasoncourseunenrollment'] = '{$a->username} has been unenrolled from the course {$a->coursename}.';
$string['cancellationsent'] = 'You should immediately receive a cancellation email.';
$string['cancellationnotsent'] = 'Face-to-face activity email notifications are turned off.';
$string['cancellationsentmgr'] = 'You and your manager should immediately receive a cancellation email.';
$string['cancellationstablesummary'] = 'List of people who have cancelled their session signups.';
$string['cancelreason'] = 'Reason';
$string['capacity'] = 'Capacity';
$string['capacityallowoverbook'] = '{$a} (allow overbooking)';
$string['capacitycurrentofmaximum'] = '{$a->current} / {$a->maximum}';
$string['capacityoverbooked'] = ' (Overbooked)';
$string['capacityoverbookedlong'] = 'This session is overbooked ({$a->current} / {$a->maximum})';
$string['changemanageremailaddress'] = 'Change manager email address';
$string['changemanageremailinstruction'] = 'Please enter the email address for your current manager below.';
$string['cancelreservation'] = 'Cancel reservation';
$string['cannotsignupguest'] = 'Cannot sign up guest';
$string['cannotsignupsessioninprogress'] = 'You cannot sign up, this session is in progress';
$string['cannotsignupsessionover'] = 'You cannot sign up, this session is over.';
$string['cannotapproveatcapacity'] = 'You cannot approve any more attendees as this session is full.';
$string['ccmanager'] = 'Manager copy';
$string['ccmanager_note'] = 'Send a copy of this notification to the user\'s manager';
$string['chooseroom'] = 'Choose a room';
$string['choosepredefinedroom'] = 'Choose a pre-defined room';
$string['cannotapproveatcapacity'] = 'You cannot approve any more attendees as this session is full.';
$string['clearall'] = 'Clear all';
$string['close'] = 'Close';
$string['closed'] = 'Closed';
$string['conditions'] = 'Conditions';
$string['conditionsexplanation'] = 'All of these criteria must be met for the notice to be shown on the training calendar:';
$string['confirm'] = 'Confirm';
$string['confirmlotteryheader'] = 'Confirm Play Lottery';
$string['confirmlotterybody'] = '"Play Lottery" randomly chooses attendees from the selected users in order to fill the session to its capacity. The chosen users will be immediately booked to the session and sent a booking confirmation email. Do you want to continue?';
$string['confirmanager'] = 'Confirm manager\'s email address';
$string['confirmmanageremailaddress'] = 'Confirm manager email address';
$string['confirmmanageremailaddressquestion'] = 'Is <strong>{$a}</strong> still your manager\'s email address?';
$string['confirmmanageremailinstruction1'] = 'You previously entered the following as your manager\'s email address:';
$string['confirmmanageremailinstruction2'] = 'Is this still your manager\'s email address?';
$string['confirmation'] = 'Confirmation';
$string['confirmationmessage'] = 'Confirmation message';
$string['confirmationsent'] = 'You should immediately receive a confirmation email.';
$string['confirmationsentmgr'] = 'You and your manager should immediately receive a confirmation email.';
$string['completionstatusrequired'] = 'Require status';
$string['completionstatusrequired_help'] = 'Checking one or more statuses will require a user to achieve at least one of the checked statuses in order to be marked complete in this Face to face activity, as well as any other Activity Completion requirements.';
$string['copyingsession'] = 'Copying as a new session in {$a}';
$string['copysession'] = 'Copy session';
$string['copy'] = 'Copy';
$string['cost'] = 'Cost';
$string['cancelbooking'] = 'Cancel booking';
$string['cancellation'] = 'Cancellation';
$string['cancellations'] = 'Cancellations';
$string['cancellationmessage'] = 'Cancellation message';
$string['cancellationconfirm'] = 'Are you sure you want to cancel your booking to this session?';
$string['close'] = 'Close';
$string['costheading'] = 'Session Cost';
$string['cutoff'] = 'Cut-off';
$string['cutoff_help'] = 'The amount of time before the first session that messages about minimum capacity will be sent.
This must be at least 24 hours before the session.
The start date of the earliest session must be at least this far in the future.';
$string['csvtextfile'] = 'Text file';
$string['csvtextinput'] = 'CSV text input';
$string['currentallocations'] = 'Current allocations ({$a->allocated} / {$a->max})';
$string['currentstatus'] = 'Current status';
$string['customfieldsheading'] = 'Custom Session Fields';
$string['date'] = 'Date';
$string['dateadd'] = 'Add a new date';
$string['dateremove'] = 'Remove this date';
$string['datetext'] = 'You are signed in for date';
$string['datetimeknownhinttext'] = '';
$string['deactivate'] = 'Deactivate';
$string['decidelater'] = 'Decide Later';
$string['declareinterest'] = 'Declare interest';
$string['declareinterest_help'] = 'Displays a option within the face to face activity to allow a user to flag their interest and write a message without signing up.
Information about those who have declared an interest can be reported on from within the activity.';
$string['declareinterestfiltercheckbox'] = 'Show only users who declared interest in this activity';
$string['declareinterestin'] = 'Declare interest in {$a}';
$string['declareinterestinconfirm'] = 'You can declare an interest in {$a} in order to be considered when new sessions are added or places become available in existing sessions.';
$string['declareinterestenable'] = 'Enable "Declare Interest" option';
$string['declareinterestonlyiffull'] = 'Show "Declare Interest" link only if all sessions are closed';
$string['declareinterestonlyiffull_help'] = 'Only show the declare interest option if there are no sessions with spaces or waiting lists.';
$string['declareinterstreason'] = 'Reason for interest:';
$string['declareinterestreport'] = 'Declared interest report';
$string['declareinterestreportdate'] = 'Date of declared interest';
$string['declareinterestreportreason'] = 'Stated reason for interest';
$string['declareinterestwithdraw'] = 'Withdraw interest';
$string['declareinterestwithdrawfrom'] = 'Withdraw interest declaration from {$a}';
$string['declareinterestwithdrawfromconfirm'] = 'Are you sure you want to withdraw your interest declaration from {$a}?';
$string['delete'] = 'Delete';
$string['deleteall'] = 'Delete all';
$string['deletenotificationconfirm'] = 'Confirm you would like to delete the notification <strong>"{$a}"</strong>:';
$string['deletenotificationtemplateconfirm'] = 'Confirm you would like to delete the notification template <strong>"{$a}"</strong>:';
$string['deleteroomconfirm'] = 'Are you sure you want to delete room <strong>"{$a}"</strong>:';
$string['deletesession'] = 'Delete session';
$string['deletesessionconfirm'] = 'Are you completely sure you want to delete this session and all sign-ups and attendance for this session?';
$string['deletingsession'] = 'Deleting session in {$a}';
$string['decline'] = 'Decline';
$string['description'] = 'Introduction text';
$string['details'] = 'Details';
$string['discardmessage'] = 'Discard message';
$string['discountcode'] = 'Discount code';
$string['discountcost'] = 'Discount cost';
$string['discountcosthinttext'] = '';
$string['due'] = 'due';
$string['duration'] = 'Duration';
$string['early'] = '{$a} early';
$string['edit'] = 'Edit';
$string['editmessagerecipientsindividually'] = 'Edit recipients individually';
$string['editnotificationx'] = 'Edit "{$a}"';
$string['editnotificationtemplate'] = 'Edit notification template';
$string['editsession'] = 'Edit session';
$string['editroom'] = 'Edit room';
$string['editingsession'] = 'Editing session in {$a}';
$string['emailmanager'] = 'Send notice to manager';
$string['email:instrmngr'] = 'Notice for manager';
$string['email:message'] = 'Message';
$string['email:subject'] = 'Subject';
$string['emptylocation'] = 'Location was empty';
$string['enablemincapacity'] = 'Enable minimum capacity';
$string['enrolled'] = 'enrolled';
$string['error:addalreadysignedupattendee'] = 'This user is already signed-up for this Face-to-face activity.';
$string['error:addalreadysignedupattendeeaddself'] = 'You are already signed-up for this Face-to-face activity.';
$string['error:addattendee'] = 'Could not add {$a} to the session.';
$string['error:cancellationsnotallowed'] = 'You are not allowed to cancel this booking.';
$string['error:cancelbooking'] = 'There was a problem cancelling your booking';
$string['error:cannotdeclareinterest'] = 'Cannot declare interest in this face-to-face activity.';
$string['error:cannotemailmanager'] = 'Sent reminder mail for submission id {$a->submissionid} to user {$a->userid}, but could not send the message for the user\'s manager email address ({$a->manageremail}).';
$string['error:cannotemailuser'] = 'Could not send out mail for submission id {$a->submissionid} to user {$a->userid} ({$a->useremail}).';
$string['error:cannotsendconfirmationmanager'] = 'A confirmation message was sent to your email account, but there was a problem sending the confirmation messsage to your manager\'s email address.';
$string['error:cannotsendconfirmationthirdparty'] = 'A confirmation message was sent to your email account and your manager\'s email account, but there was a problem sending the confirmation messsage to the third party\'s email address.';
$string['error:cannotsendconfirmationuser'] = 'There was a problem sending the confirmation message to your email account.';
$string['error:cannotsendrequestuser'] = 'There was a problem sending the signup request message to your email account.';
$string['error:cannotsendrequestmanager'] = 'There was a problem sending the signup request message to your manager\'s email account.';
$string['error:cannotsendconfirmationusermanager'] = 'A confirmation message could not be sent to your email address and to your manager\'s email address.';
$string['error:canttakeattendanceforunstartedsession'] = 'Can not take attendance for a session that has yet to start.';
$string['error:capabilityaddattendees'] = 'You do not have the necessary permissions to add attendees';
$string['error:capabilityremoveattendees'] = 'You do not have the necessary permissions to remove attendees';
$string['error:capacitynotnumeric'] = 'Session capacity is not a number';
$string['error:capacityzero'] = 'Session capacity must be greater than zero';
$string['error:conflictingsession'] = 'The user {$a} is already signed up for another session';
$string['error:couldnotaddfield'] = 'Could not add custom session field.';
$string['error:couldnotaddnotice'] = 'Could not add site notice.';
$string['error:couldnotaddsession'] = 'Could not add session';
$string['error:couldnotaddtrainer'] = 'Could not save new face-to-face session trainer';
$string['error:couldnotcopysession'] = 'Could not copy session';
$string['error:couldnotdeletefield'] = 'Could not delete custom session field';
$string['error:couldnotdeletenotice'] = 'Could not delete site notice';
$string['error:couldnotdeletesession'] = 'Could not delete session';
$string['error:couldnotdeletetrainer'] = 'Could not delete a face-to-face session trainer';
$string['error:couldnotfindsession'] = 'Could not find the newly inserted session';
$string['error:couldnotsavecustomfield'] = 'Could not save custom field';
$string['error:couldnotupdatecalendar'] = 'Could not update session event in the calendar.';
$string['error:couldnotupdatefield'] = 'Could not update custom session field.';
$string['error:couldnotupdatemanageremail'] = 'Could not update manager email address.';
$string['error:couldnotupdatef2frecord'] = 'Could not update face-to-face signup record in database';
$string['error:couldnotupdatenotice'] = 'Could not update site notice.';
$string['error:couldnotupdatesession'] = 'Could not update session';
$string['error:coursemisconfigured'] = 'Course is misconfigured';
$string['error:cronprefix'] = 'Error: facetoface cron:';
$string['error:cutofftoolate'] = 'The minimum cut off date is 24 hours and the start date of the earliest session must be at least this far in the future.';
$string['error:emptymanageremail'] = 'Manager email address empty.';
$string['error:emptylocation'] = 'Location was empty.';
$string['error:emptyvenue'] = 'Venue was empty.';
$string['error:enrolmentfailed'] = 'Could not enrol {$a} into the course.';
$string['error:eventoccurred'] = 'You cannot cancel an event that has already occurred.';
$string['error:fieldidincorrect'] = 'Field ID is incorrect: {$a}';
$string['error:f2ffailedupdatestatus'] = 'Face-to-face failed to update the user\'s status';
$string['error:incorrectcoursemodule'] = 'Course module is incorrect';
$string['error:incorrectcoursemoduleid'] = 'Course Module ID was incorrect';
$string['error:incorrectcoursemodulesession'] = 'Course Module Face-to-face Session was incorrect';
$string['error:incorrectfacetofaceid'] = 'Face-to-face ID was incorrect';
$string['error:incorrectnotificationtype'] = 'Incorrect notification type supplied';
$string['error:invaliduserid'] = 'Invalid user ID';
$string['error:manageremailaddressmissing'] = 'You are currently not assigned to a manager in the system. Please contact the site administrator.';
$string['error:mincapacitynotnumeric'] = 'Session minimum capacity is not a number';
$string['error:mincapacitytoolarge'] = 'Session minimum capacity cannot be greater than the capacity';
$string['error:mincapacityzero'] = 'Session minimum capacity cannot be zero';
$string['error:mustspecifycoursemodulefacetoface'] = 'Must specify a course module or a facetoface ID';
$string['error:mustspecifytimezone'] = 'You must set the timezone for each date';
$string['error:nodatasupplied'] = 'No data supplied';
$string['error:nomanageremail'] = 'You didn\'t provide an email address for your manager';
$string['error:nomanagersemailset'] = 'No manager email is set';
$string['error:nopermissiontosignup'] = 'You don\'t have permission to signup to this facetoface session.';
$string['error:nopositionselected'] = 'You must have a suitable position assigned to sign up for this facetoface session.';
$string['error:nopositionselectedactivity'] = 'You must have a suitable position assigned to sign up for this facetoface activity.';
$string['error:nopredefinedrooms'] = 'No pre-defined rooms';
$string['error:noticeidincorrect'] = 'Notice ID is incorrect: {$a}';
$string['error:problemsigningup'] = 'There was a problem signing you up.';
$string['error:removeattendee'] = 'Could not remove {$a} from the session.';
$string['error:sessionstartafterend'] = 'Session start date/time is after end.';
$string['error:sessiondatesconflict'] = 'This date conflicts with an earlier date in this session';
$string['error:signedupinothersession'] = 'You are already signed up in another session for this activity. You can only sign up for one session per Face-to-face activity.';
$string['error:therearexconflicts'] = 'There are ({$a}) conflicts with the proposed time period.';
$string['error:thereisaconflict'] = 'There is a conflict with the proposed time period.';
$string['error:unknownbuttonclicked'] = 'No action associated with the button that was clicked';
$string['error:userassignedsessionconflictsameday'] = '{$a->fullname} is already assigned as a \'{$a->participation}\' for {$a->session} at {$a->timestart} to {$a->timefinish} on {$a->datestart}. Please select another user or change the session date';
$string['error:userbookedsessionconflictsameday'] = '{$a->fullname} is already booked to attend {$a->session} at {$a->timestart} to {$a->timefinish} on {$a->datestart}. Please select another user or change the session date';
$string['error:userassignedsessionconflictmultiday'] = '{$a->fullname} is already assigned as a \'{$a->participation}\' for {$a->session} at {$a->datetimestart} to {$a->datetimefinish}. Please select another user or change the session date';
$string['error:userbookedsessionconflictmultiday'] = '{$a->fullname} is already booked to attend {$a->session} at {$a->datetimestart} to {$a->datetimefinish}. Please select another user or change the session date';
$string['error:userassignedsessionconflictsamedayselfsignup'] = 'You are already assigned as a \'{$a->participation}\' for {$a->session} at {$a->timestart} to {$a->timefinish} on {$a->datestart}.';
$string['error:userbookedsessionconflictsamedayselfsignup'] = 'You are already booked to attend {$a->session} at {$a->timestart} to {$a->timefinish} on {$a->datestart}.';
$string['error:userassignedsessionconflictmultidayselfsignup'] = 'You are already assigned as a \'{$a->participation}\' for {$a->session} at {$a->datetimestart} to {$a->datetimefinish}.';
$string['error:userbookedsessionconflictmultidayselfsignup'] = 'You are already booked to attend {$a->session} at {$a->datetimestart} to {$a->datetimefinish}.';
$string['error:userdeleted'] = 'Can not add deleted user {$a} to the facetoface.';
$string['error:userimportuseridnotanint'] = 'Cannot add user with user id {$a} because it is not an integer';
$string['error:usersuspended'] = 'Can not add suspended user {$a} to the facetoface.';
$string['excelformat'] = 'Excel';
$string['existingbookings'] = 'Bookings in other sessions';
$string['existingrecipients'] = 'Existing recipients';
$string['export'] = 'Export';
$string['exportattendanceods'] = 'Export attendance form (ods)';
$string['exportattendancetxt'] = 'Export attendance form (txt)';
$string['exportattendancexls'] = 'Export attendance form (xls)';
$string['exportheading'] = 'Export';
$string['exporttofile'] = 'Export to file';
$string['exportattendance'] = 'Export attendance';
$string['exportcustomprofilefields'] = 'Export custom profile fields';
$string['exportcustomprofilefields_desc'] = 'Include these custom user profile fields (short names) in Face-to-face exports, separated by commas.';
$string['exportuserprofilefields'] = 'Export user profile fields';
$string['exportuserprofilefields_desc'] = 'Include these user profile fields in the Face-to-face exports, separated by commas.';
$string['external'] = 'Allow room conflicts';
$string['f2f-waitlist-actions'] = 'Actions';
$string['f2f-waitlist-actions_help'] = '<p><strong>Confirm:</strong> Book the selected users into the session and remove them from the wait-list.</p>
<p><strong>Cancel:</strong> Cancel the selected user\'s requests and remove them from the wait-list.</p>
<p><strong>Play Lottery:</strong> Fill the available places on the sessions with a random selection of the users from the wait-list. Users who are not selected will be left on the wait-list.</p>';
$string['facetoface'] = 'Face-to-face';
$string['facetoface:addattendees'] = 'Add attendees to a face-to-face session';
$string['facetoface:addinstance'] = 'Add a new facetoface';
$string['facetoface:configurecancellation'] = 'Allow the configuration of booking cancellations, upon adding/editing a face-to-face activity.';
$string['facetoface:changesignedupjobposition'] = 'Change signed up job position';
$string['facetoface:editsessions'] = 'Add, edit, copy and delete face-to-face sessions';
$string['facetoface:manageattendeesnote'] = 'Manage session attendee\'s notes';
$string['facetoface:overbook'] = 'Sign-up to full sessions.';
$string['facetoface:removeattendees'] = 'Remove attendees from a face-to-face session';
$string['facetoface:reserveother'] = 'Reserve on behalf of other managers';
$string['facetoface:reservespace'] = 'Reserve or allocate spaces for team members';
$string['facetoface:signup'] = 'Sign-up for a session';
$string['facetoface:takeattendance'] = 'Take attendance';
$string['facetoface:view'] = 'View face-to-face activities and sessions';
$string['facetoface:viewattendees'] = 'View attendance list and attendees';
$string['facetoface:viewattendeesnote'] = 'View session attendee\'s notes';
$string['facetoface:viewcancellations'] = 'View cancellations';
$string['facetoface:viewemptyactivities'] = 'View empty face-to-face activities';
$string['facetoface:viewinterestreport'] = 'View face-to-face declared interest report';
$string['facetofacebooking'] = 'Face-to-face booking';
$string['facetofacename'] = 'Face-to-face name';
$string['facetofacesession'] = 'Face-to-face session';
$string['feedback'] = 'Feedback';
$string['feedbackupdated'] = 'Feedback updated for \{$a} people';
$string['field:text'] = 'Text';
$string['field:multiselect'] = 'Multiple selection';
$string['field:select'] = 'Menu of choices';
$string['fielddeleteconfirm'] = 'Delete field \'{$a}\' and all session data associated with it?';
$string['filterbyroom'] = 'Filter by Room';
$string['floor'] = 'Floor';
$string['forceselectposition'] = 'Prevent signup if no position is selected or can be found';
$string['format'] = 'Format';
$string['full'] = 'Date is fully occupied';
$string['generalsettings'] = 'General Settings';
$string['gettointerestreport'] = 'To view the declare interest report go to the facetoface activity and follow the \'Declared interest report\' link in the module admin menu.';
$string['goback'] = 'Go back';
$string['guestsno'] = 'Sorry, guests are not allowed to sign up for sessions.';
$string['icalendarheading'] = 'iCalendar Attachments';
$string['id'] = 'Id';
$string['icaldescription'] = 'This calendar event is for the "{$a->name}" face-to-face session you have been booked on to.';
$string['import'] = 'Import';
$string['individuals'] = 'Individuals';
$string['info'] = 'Info';
$string['internal'] = 'Prevent room conflicts';
$string['lastreservation'] = 'Last reservations are {$a->reservedays} days before the session starts. Unallocated reservations will be deleted {$a->reservecanceldays} days before the session starts.';
$string['late'] = '\{$a} late';
$string['location'] = 'Location';
$string['lookfor'] = 'Search';
$string['managenotificationtemplates'] = 'Manage notification templates';
$string['managerooms'] = 'Manage rooms';
$string['manageradded'] = 'Your manager\'s email address has been accepted.';
$string['managerbookings'] = 'Bookings / reservations made by {$a}';
$string['managerchanged'] = 'Your manager\'s email address has been changed.';
$string['manageremail'] = 'Manager\'s email';
$string['manageremailaddress'] = 'Manager\'s email address';
$string['manageremailformat'] = 'The email address must be of the format \'{$a}\' to be accepted.';
$string['manageremailheading'] = 'Manager Emails';
$string['manageremailinstruction'] = 'In order to sign-up for a training session, a confirmation email must be sent to your email address and copied to your manager\'s email address.';
$string['manageremailinstructionconfirm'] = 'Please confirm that this is your manager\'s email address:';
$string['managername'] = 'Manager\'s name';
$string['managerprefix'] = 'Manager copy prefix';
$string['managerreserve'] = 'Allow manager reservations';
$string['managerreserve_help'] = 'Managers are able to make reservations or bookings on behalf of their team members';
$string['managerreserveheader'] = 'Manager reservations';
$string['managerupdated'] = 'Your manager\'s email address has been updated.';
$string['mark_selected_as'] = 'Mark all selected as: ';
$string['maximumpoints'] = 'Maximum number of points';
$string['maximumsize'] = 'Maximum number of attendees';
$string['maxmanagerreserves'] = 'Maximum reservations';
$string['maxmanagerreserves_help'] = 'The maximum number of reservations / bookings that a manager can make for their team';
$string['message'] = 'Change in booking in the course {$a->coursename}!

There has been a free place in the session on {$a->duedate} ({$a->name}) in the course {$a->coursename}.
You have been registered. If the date does not suit you anymore, please unregister at <a href=\'{$a->url}\'>{$a->url}</a>.';
$string['messagebody'] = 'Body';
$string['messagecc'] = 'CC recipient\'s managers';
$string['messageheader'] = 'Message';
$string['messagerecipients'] = 'Recipients';
$string['messagerecipientgroups'] = 'Recipient Groups';
$string['messagesenttostaffmember'] = 'The following message has been sent to your staff member {$a}';
$string['messagesubject'] = 'Subject';
$string['messageusers'] = 'Message users';
$string['mincapacity'] = 'Minimum capacity';
$string['mincapacity_help'] = 'If the minimum capacity has not been reached at the cut off point, then course tutors (those with moodle/course:manageactivities capability) will be automatically notified.';
$string['modulename'] = 'Face-to-face';
$string['modulenameplural'] = 'Face-to-face';
$string['moreinfo'] = 'More info';
$string['multidate'] = '(multi-date)';
$string['multiplesessions'] = 'Allow multiple sessions signup per user';
$string['multiplesessionsheading'] = 'Multiple sessions signup settings';
$string['namewithmanager'] = '{$a->attendeename} ({$a->managername})';
$string['newmanageremailaddress'] = 'Manager\'s email address';
$string['noactionableunapprovedrequests'] = 'No actionable unapproved requests';
$string['nocustomfields'] = '<p>No custom fields are defined.</p>';
$string['nofacetofaces'] = 'There are no Face-to-face activities';
$string['nositenotices'] = '<p>No site notices are defined.</p>';
$string['none'] = 'none';
$string['nonotifications'] = 'No notifications';
$string['nonotificationsmatchingsearch'] = 'No notifications matching search';
$string['nonotificationsofthistype'] = 'No notifications of this type';
$string['nonotificationtemplates'] = 'No notification templates';
$string['nonotificationtemplatesmatchingsearch'] = 'No notification templates matching search';
$string['noposition'] = 'User has no positions assigned.';
$string['norecipients'] = 'No recipients';
$string['normalcost'] = 'Normal cost';
$string['normalcosthinttext'] = '';
$string['noremindersneedtobesent'] = 'No reminders need to be sent.';
$string['noreservations'] = 'None';
$string['nosignedupusers'] = 'No users have signed-up for this session.';
$string['nosignedupusersnumrequests'] = 'No users are fully booked for this session. {$a} users are awaiting approval.';
$string['nosignedupusersonerequest'] = 'No users are fully booked for this session. 1 user is awaiting approval.';
$string['nostarttime'] = 'No dates specified';
$string['note'] = 'Note';
$string['notefull'] = 'Even if the Session is fully booked you can still register. You will be queued (marked in red). If someone signs out, the first student in the queue will be moved into registeres students and a notification will be sent to him/her by mail.';
$string['notificationalreadysent'] = 'This notification has already been sent, so can no longer be edited.';
$string['notificationdeleted'] = 'Notification deleted';
$string['notificationnotesetonintendeddate'] = 'NOTE: This notification was not sent on it\'s originally intended date';
$string['notifications'] = 'Notifications';
$string['notificationsaved'] = 'Notification saved';
$string['notificationtemplatedeleted'] = 'Notification template deleted';
$string['notificationtemplates'] = 'Notification templates';
$string['notificationtemplatestatus'] = 'Notification template status';
$string['notificationtemplatestatus_help'] = 'This status allows a notification template to be marked as Active or Inactive. Inactive notification templates will not be available to be used when setting up notifications for a Face-to-face activity.';
$string['notificationtemplatesaved'] = 'Notification template saved';
$string['notificationtitle'] = 'Notification title';
$string['notificationtype'] = 'Notification Type';
$string['notificationtype_1'] = 'Instant';
$string['notificationtype_2'] = 'Scheduled';
$string['notificationtype_4'] = 'Auto';
$string['notificationboth'] = 'Email Notification and iCalendar Appointment';
$string['notificationemail'] = 'Email Notification only';
$string['notificationnone'] = 'No Email Notification';
$string['notifications_help'] = 'Here you can manage notifications for this Face-to-face acitivity'; //TODO: write better help
$string['noticedeleteconfirm'] = 'Delete site notice \'{$a->name}\'?<br/><blockquote>{$a->text}</blockquote>';
$string['noticetext'] = 'Notice text';
$string['notrequired'] = 'Not required';
$string['notsignedup'] = 'You are not signed up for this session.';
$string['notsubmittedyet'] = 'Not yet evaluated';
$string['noupcoming'] = '<p><i>No upcoming sessions</i></p>';
$string['uploadfile'] = 'Upload file';
$string['occuredonx'] = 'Occured on {$a}';
$string['occurswhenenabled'] = 'Occurs when enabled';
$string['occurswhenuserbookssession'] = 'Occurs when a learner books a session';
$string['occurswhenuserrequestssessionwithmanagerapproval'] = 'Occurs when a user attempts to book a session with manager approval required';
$string['occurswhenuserrequestssessionwithmanagerdecline'] = 'Occurs when a user attempts to declined a session with manager approval required';
$string['occurswhenusersbookingiscancelled'] = 'Occurs when a learner\'s booking is cancelled';
$string['occurswhenuserwaitlistssession'] = 'Occurs when a learner is waitlisted on a session';
$string['occursxaftersession'] = 'Occurs {$a} after end of session';
$string['occursxbeforesession'] = 'Occurs {$a} before start of session';
$string['odsformat'] = 'OpenDocument';
$string['onehour'] = '1 hour';
$string['oneminute'] = '1 minute';
$string['options'] = 'Options';
$string['or'] = 'or';
$string['order'] = 'Order';
$string['otherbookedby'] = 'Booked by another manager';
$string['otherroom'] = 'Other room';
$string['othersession'] = 'Other session(s) in this activity';
$string['pdroomcapacityexceeded'] = '<strong>Warning:</strong> Note that pre-defined room capacity is being exceeded by session capacity';
$string['place'] = 'Room';
$string['placeholder:address'] = '[address]';
$string['placeholder:aim'] = '[aim]';
$string['placeholder:alternatename'] = '[alternatename]';
$string['placeholder:city'] = '[city]';
$string['placeholder:country'] = '[country]';
$string['placeholder:department'] = '[department]';
$string['placeholder:description'] = '[description]';
$string['placeholder:email'] = '[email]';
$string['placeholder:firstname'] = '[firstname]';
$string['placeholder:firstnamephonetic'] = '[firstnamephonetic]';
$string['placeholder:fullname'] = '[fullname]';
$string['placeholder:icq'] = '[icq]';
$string['placeholder:idnumber'] = '[idnumber]';
$string['placeholder:institution'] = '[institution]';
$string['placeholder:lang'] = '[lang]';
$string['placeholder:lastname'] = '[lastname]';
$string['placeholder:lastnamephonetic'] = '[lastnamephonetic]';
$string['placeholder:middlename'] = '[middlename]';
$string['placeholder:msn'] = '[msn]';
$string['placeholder:phone1'] = '[phone1]';
$string['placeholder:phone2'] = '[phone2]';
$string['placeholder:skype'] = '[skype]';
$string['placeholder:timezone'] = '[timezone]';
$string['placeholder:url'] = '[url]';
$string['placeholder:username'] = '[username]';
$string['placeholder:yahoo'] = '[yahoo]';
$string['playlottery'] = 'Play Lottery';
$string['position'] = 'Position';
$string['reserve'] = 'Reserve spaces for team';
$string['reserveallallocated'] = 'You have already allocated the maximum number of spaces you are able for this activity, you cannot reserve any more';
$string['reserveallallocatedother'] = 'This manager has already allocated the maximum number of spaces they are able to for this activity, you cannot reserve any more for them';
$string['reservecancel'] = 'Automatically cancel reservations';
$string['reservecanceldays'] = 'Reservation cancellation days';
$string['reservecanceldays_help'] = 'The number of days in advance of the session that reservations will be automatically cancelled, if not confirmed';
$string['reservecapacitywarning'] = '* Any new reservations over the current session capacity ({$a} left) will be added to the waiting list';
$string['reserved'] = 'Reserved';
$string['reservedby'] = 'Reserved ({$a})';
$string['reservedays'] = 'Reservation deadline';
$string['reservedays_help'] = 'The number of days before the session starts after which no more reservations are allowed (must be greater than the cancellation days)';
$string['reservegtcancel'] = 'The reservation deadline must be greater than the cancellation days';
$string['reserveintro'] = 'You can use this form to change the number of reservations you have for this session - to cancel existing reservations, just reduce the number below.';
$string['reserveintroother'] = 'You can use this form to change the number of reservations {$a} has for this session - to cancel existing reservations, just reduce the number below.';
$string['reservenocapacity'] = 'There are no spaces left on this course, so you will not be able to make any reservations unless one of the participants cancels';
$string['reserveother'] = 'Reserve for another manager';
$string['reservepastdeadline'] = 'You cannot make any further reservations within {$a} days of the session starting';
$string['result'] = 'Result';
$string['return'] = 'Return';
$string['roomdeleted'] = 'Room deleted';
$string['roomdoesnotexist'] = 'Room does not exist';
$string['roomisinuse'] = 'Room is in use';
$string['roomdescription'] = 'Room description';
$string['roommustbebookedtoexternalcalendar'] = 'Note: Please ensure that this room is available before creating this booking.';
$string['roomname'] = 'Room name';
$string['rooms'] = 'Rooms';
$string['roomcreatesuccess'] = 'Successfully created room';
$string['roomtype'] = 'Room type';
$string['roomtype_help'] = 'The room type is used to determine if the system should try and prevent room booking conflicts. This will prevent a room from being booked for 2 sessions that are running at the same time.';
$string['roomupdatesuccess'] = 'Successfully updated room';
$string['placeholder:coursename'] = '[coursename]';
$string['placeholder:facetofacename'] = '[facetofacename]';
$string['placeholder:firstname'] = '[firstname]';
$string['placeholder:lastname'] = '[lastname]';
$string['placeholder:cost'] = '[cost]';
$string['placeholder:alldates'] = '[alldates]';
$string['placeholder:reminderperiod'] = '[reminderperiod]';
$string['placeholder:sessiondate'] = '[sessiondate]';
$string['placeholder:startdate'] = '[startdate]';
$string['placeholder:finishdate'] = '[finishdate]';
$string['placeholder:starttime'] = '[starttime]';
$string['placeholder:finishtime'] = '[finishtime]';
$string['placeholder:duration'] = '[duration]';
$string['placeholder:details'] = '[details]';
$string['placeholder:attendeeslink'] = '[attendeeslink]';
$string['placeholder:location'] = '[session:location]';
$string['placeholder:venue'] = '[session:venue]';
$string['placeholder:room'] = '[session:room]';
$string['pluginadministration'] = 'Facetoface administration';
$string['pluginname'] = 'Face-to-face';
$string['points'] = 'Points';
$string['pointsplural'] = 'Points';
$string['potentialallocations'] = 'Potential allocations ({$a} left)';
$string['potentialrecipients'] = 'Potential recipients';
$string['predefinedroom'] = '{$a->name}, {$a->building}, {$a->address}, {$a->description} (Capacity: {$a->capacity})';
$string['previoussessions'] = 'Previous sessions';
$string['previoussessionslist'] = 'List of all past sessions for this Face-to-face activity';
$string['printversionid'] = 'Print version: without name';
$string['printversionname'] = 'Print version: with name';
$string['really'] = 'Do you really want to delete all results for this facetoface?';
$string['recipients'] = 'Recipients';
$string['recipients_allbooked'] = 'All booked';
$string['recipients_attendedonly'] = 'Attended only';
$string['recipients_noshowsonly'] = 'No shows only';
$string['registeredon'] = 'Registered On';
$string['registrations'] = 'Registrations';
$string['reminder'] = 'Reminder';
$string['remindermessage'] = 'Reminder message';
$string['removedsuccessfully'] = 'Removed successfully';
$string['removeroominuse'] = 'This room is currently being used';
$string['replaceallocations'] = 'Create reservations when removing allocations';
$string['replacereservations'] = 'Replace reservations when adding allocations';
$string['requestmessage'] = 'Request message';
$string['reservations'] = '{$a} reservation(s)';
$string['room'] = 'Room';
$string['roomalreadybooked'] = ' (room unavailable on selected dates)';
$string['saveallfeedback'] = 'Save all responses';
$string['saveattendance'] = 'Save attendance';
$string['savenote'] = 'Save note';
$string['schedule_unit_1'] = '{$a} hours';
$string['schedule_unit_1_singular'] = '1 hour';
$string['schedule_unit_2'] = '{$a} days';
$string['schedule_unit_2_singular'] = '1 day';
$string['schedule_unit_3'] = '{$a} weeks';
$string['schedule_unit_3_singular'] = '1 week';
$string['scheduledsession'] = 'Scheduled session';
$string['scheduledsessions'] = 'Scheduled sessions';
$string['scheduling'] = 'Scheduling';
$string['seatsavailable'] = 'Seats available';
$string['seeattendees'] = 'See attendees';
$string['selected'] = 'Selected';
$string['select'] = ' Select ';
$string['selectall'] = 'Select all';
$string['selectedposition_help'] = 'Select the position that this training is for.';
$string['selectedposition'] = 'Position on sign up';
$string['selectedpositionassignment'] = 'Position Assignment on sign up';
$string['selectedpositionname'] = 'Position Name on sign up';
$string['selectedpositiontype'] = 'Position Type on sign up';
$string['selectpositiononsignup'] = 'Select position on signup';
$string['selectnone'] = 'Select none';
$string['selectallop'] = 'All';
$string['selectmanager'] = 'Select manager';
$string['selectnoneop'] = 'None';
$string['selectnotsetop'] = 'Not Set';
$string['selectoptionbefore'] = ' Please choose an option (All, Set or Not set) before selecting this option';
$string['selectposition'] = 'Select a position';
$string['selectsetop'] = 'Set';
$string['selfapproval'] = 'Self Approval';
$string['selfapproval_help'] = 'This setting allows a user to confirm that they have sought approval to attend the session. Instead of their manager needing to approve their booking the user is presented with a check box when signing up and must confirm they have met the specified terms and conditions.
This setting will be disabled unless "Requires approval" is enabled in the Face-to-face activity settings.';
$string['selfapprovalsought'] = 'Self Approval Sought';
$string['selfapprovalsoughtbrief'] = 'I accept the terms and conditions.';
$string['selfapprovalsoughtdesc'] = 'By checking this box, I confirm that I have read and agreed to the {$a} (opens a new window).';
$string['selfapprovaltandc'] = 'Self Approval Terms and Conditions';
$string['selfapprovaltandc_help'] = 'Where an activity has approval required and a session has self approval enabled these are the terms and conditions that will be displayed when a user signs up.';
$string['selfapprovaltandccontents'] = 'By checking the box you confirm that permission to sign up to this Face to Face activity has been granted by your manager.

Falsely claiming that approval has been granted can result in non-admittance and disciplinary action.
';
$string['selfbooked'] = 'Self booked';
$string['sendlater'] = 'Send later';
$string['sendmessage'] = 'Send message';
$string['sendnow'] = 'Send now';
$string['sentxnotifications'] = 'Send {$a} notifications';
$string['sentremindermanager'] = 'Sent reminder email to user manager';
$string['sentreminderuser'] = 'Sent reminder email to user';
$string['sessiondate'] = 'Session date';
$string['sessiondateformat'] = '%d %B %Y';
$string['sessiondatetime'] = 'Session date/time';
$string['sessiondatetimecourseformat'] = '{$a->startdate}, {$a->starttime} - {$a->endtime} (time zone: {$a->timezone})';
$string['sessiondatetimecourseformatwithouttimezone'] = '{$a->startdate}, {$a->starttime} - {$a->endtime}';
$string['sessiondatetimeformat'] = '%I:%M %p';
$string['sessiondatetimeknown'] = 'Session date/time known';
$string['sessionsdetailstablesummary'] = 'Full description of the current session.';
$string['sessionfinishdateshort'] = 'Finish date';
$string['sessionfinishtime'] = 'Session finish time';
$string['sessioninprogress'] = 'Session in progress';
$string['sessionisfull'] = 'This session is now full. You will need to pick another time or talk to the instructor.';
$string['sessionnoattendeesaswaitlist'] = 'This session does not have any attendees because it does not have a known date and time.<br />See the wait-list tab for users that have signed up.';
$string['sessionover'] = 'Session over';
$string['sessions'] = 'Sessions';
$string['sessionsoncoursepage'] = 'Sessions displayed on course page';
$string['sessionstartdateandtime'] = '{$a->startdate}, {$a->starttime} - {$a->endtime} (time zone: {$a->timezone})';
$string['sessionstartdateandtimewithouttimezone'] = '{$a->startdate}, {$a->starttime} - {$a->endtime}';
$string['sessionstartfinishdateandtime'] = '{$a->startdate} - {$a->enddate}, {$a->starttime} - {$a->endtime} (time zone: {$a->timezone})';
$string['sessionstartfinishdateandtimewithouttimezone'] = '{$a->startdate} - {$a->enddate}, {$a->starttime} - {$a->endtime}';
$string['sessionrequiresmanagerapproval'] = 'This session requires manager approval to book.';
$string['sessionroles'] = 'Session roles';
$string['sessionstartdate'] = 'Session start date';
$string['sessionstartdateshort'] = 'Start date';
$string['sessionstarttime'] = 'Session start time';
$string['sessiontimezone'] = 'Timezone';
$string['sessiontimezoneunknown'] = 'Unknown Timezone';
$string['sessionundercapacity'] = 'Session under capacity for: {$a}';
$string['sessionundercapacity_body'] = 'The following session is under capacity:

Name: {$a->name}
Session start: {$a->starttime}
Capacity: {$a->booked} / {$a->capacity} (minimum: {$a->mincapacity})
{$a->link}';
$string['sessionvenue'] = 'Session venue';
$string['setactive'] = 'Set active';
$string['setinactive'] = 'Set inactive';
$string['setting:addchangemanageremail'] = 'Ask users for their manager\'s email address.';
$string['setting:addchangemanageremaildefault'] = 'Ask users for their manager\'s email address.';
$string['setting:addchangemanageremail_caption'] = 'Manager\'s email:';
$string['setting:allowschedulingconflicts_caption'] = 'Allow scheduling conflicts:';
$string['setting:allowschedulingconflicts'] = 'Allow or disallow scheduling conflicts to exist while creating a new session.';
$string['setting:allowwaitlisteveryone_caption'] = 'Enable everyone on waiting list option';
$string['setting:allowwaitlisteveryone'] = 'When enabled a setting will appear in face to face session settings to put all users onto the waiting list when they signup regardless of session capacity.';
$string['setting:bulkaddsource_caption'] = 'Bulk add field:';
$string['setting:bulkaddsource'] = 'When bulk adding attendees, match to the selected field.';
$string['setting:calendarfilters'] = 'Selected fields will be displayed as filters in Face-to-face Calendar';
$string['setting:calendarfilterscaption'] = 'Add calendar filters:';
$string['setting:defaultcancellationinstrmngr'] = 'Default cancellation message sent to managers.';
$string['setting:defaultcancellationinstrmngr_caption'] = 'Cancellation message (managers)';
$string['setting:defaultcancellationinstrmngrdefault'] = '*** Advice only ****

This is to advise that [firstname] [lastname] is no longer signed-up for the following course and listed you as their Team Leader / Manager.

';
$string['setting:defaultcancellationinstrmngrcopybelow'] = '*** [firstname] [lastname]\'s booking cancellation is copied below ****';
$string['setting:defaultcancellationmessage'] = 'Default cancellation message sent to the user.';
$string['setting:defaultcancellationmessage_caption'] = 'Cancellation message';
$string['setting:defaultcancellationmessagedefault'] = 'This is to advise that your booking on the following course has been cancelled:

***BOOKING CANCELLED***

Participant:   [firstname] [lastname]
Course:   [coursename]
Face-to-face:   [facetofacename]

Duration:   [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]
';
$string['setting:defaultcancellationsubject'] = 'Default subject line for cancellation emails.';
$string['setting:defaultcancellationsubject_caption'] = 'Cancellation subject';
$string['setting:defaultcancellationsubjectdefault'] = 'Face-to-face booking cancellation';
$string['setting:defaultcancelallreservationssubjectdefault'] = 'All reservations cancelled';
$string['setting:defaultcancelallreservationsmessagedefault'] = 'This is to advise you that all unallocated reservations for the following course have been automatically cancelled, as the course will be starting soon:

***ALL RESERVATIONS CANCELLED***

Course:   [facetofacename]

Duration:   [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]
';
$string['setting:defaultcancelreservationsubjectdefault'] = 'Reservation cancellation';
$string['setting:defaultcancelreservationmessagedefault'] = 'This is to advise you that your reservation for the following course has been cancelled:

***RESERVATION CANCELLED***

Course:   [facetofacename]

Duration:   [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]';
$string['setting:defaultdeclineinstrmngr'] = 'Default decline message sent to managers.';
$string['setting:defaultdeclineinstrmngr_caption'] = 'Decline message (managers)';
$string['setting:defaultdeclineinstrmngrdefault'] = '*** Advice only ****

This is to advise that [firstname] [lastname] is no longer signed-up for the following course and listed you as their Team Leader / Manager.

';
$string['setting:defaultdeclineinstrmngrcopybelow'] = '*** [firstname] [lastname]\'s booking decline is copied below ****';
$string['setting:defaultdeclinemessage'] = 'Default decline message sent to the user.';
$string['setting:defaultdeclinemessage_caption'] = 'Decline message';
$string['setting:defaultdeclinemessagedefault'] = 'This is to advise that your booking on the following course has been declined:

***BOOKING DECLINED***

Participant:   [firstname] [lastname]
Course:   [coursename]
Face-to-face:   [facetofacename]

Duration:   [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]
';
$string['setting:defaultdeclinesubject'] = 'Default subject line for decline emails.';
$string['setting:defaultdeclinesubject_caption'] = 'Decline subject';
$string['setting:defaultdeclinesubjectdefault'] = 'Face-to-face booking decline';
$string['setting:defaultconfirmationinstrmngr'] = 'Default confirmation message sent to managers.';
$string['setting:defaultconfirmationinstrmngr_caption'] = 'Confirmation message (managers)';
$string['setting:defaultconfirmationinstrmngrdefault'] = '*** Advice only ****

This is to advise that [firstname] [lastname] has been booked for the following course and listed you as their Team Leader / Manager.

If you are not their Team Leader / Manager and believe you have received this email by mistake please reply to this email.  If you have concerns about your staff member taking this course please discuss this with them directly.

';
$string['setting:defaultconfirmationinstrmngrcopybelow'] = '*** [firstname] [lastname]\'s booking confirmation is copied below ****';
$string['setting:defaultconfirmationmessage'] = 'Default confirmation message sent to users.';
$string['setting:defaultconfirmationmessage_caption'] = 'Confirmation message';
$string['setting:defaultconfirmationmessagedefault'] = 'This is to confirm that you are now booked on the following course:

Participant:   [firstname] [lastname]
Course:   [coursename]
Face-to-face:   [facetofacename]
Cost:   [cost]

Duration:    [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]

***Please arrive ten minutes before the course starts***

To re-schedule or cancel your booking
To re-schedule your booking you need to cancel this booking and then re-book a new session.  To cancel your booking, return to the site, then to the page for this course, and then select \'cancel\' from the booking information screen.

[details]
';
$string['setting:defaultconfirmationsubject'] = 'Default subject line for confirmation emails.';
$string['setting:defaultconfirmationsubject_caption'] = 'Confirmation subject';
$string['setting:defaultconfirmationsubjectdefault'] = 'Face-to-face booking confirmation: [facetofacename], [starttime]-[finishtime], [sessiondate]';
$string['setting:defaultdatetimechangemessagedefault'] = 'Your session date/time has changed:

Participant:   [firstname] [lastname]
Course:   [coursename]
Face-to-face:   [facetofacename]

Duration:   [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]
';
$string['setting:defaultdatetimechangesubject'] = 'Default subject line for date/time change emails.';
$string['setting:defaultdatetimechangesubject_caption'] = 'Date/time change subject';
$string['setting:defaultdatetimechangesubjectdefault'] = 'Face-to-face booking date/time changed: [facetofacename], [starttime]-[finishtime], [sessiondate]';
$string['setting:defaultreminderinstrmngr'] = 'Default reminder message sent to managers.';
$string['setting:defaultreminderinstrmngr_caption'] = 'Reminder message (managers)';
$string['setting:defaultreminderinstrmngrdefault'] = '*** Reminder only ****

Your staff member [firstname] [lastname] is booked to attend and above course and has also received this reminder email.

If you are not their Team Leader / Manager and believe you have received this email by mistake please reply to this email.

';
$string['setting:defaultreminderinstrmngrcopybelow'] = '*** [firstname] [lastname]\'s reminder email is copied below ****';
$string['setting:defaultremindermessage'] = 'Default reminder message sent to users.';
$string['setting:defaultremindermessage_caption'] = 'Reminder message';
$string['setting:defaultremindermessagedefault'] = 'This is a reminder that you are booked on the following course:

Participant:   [firstname] [lastname]
Course:   [coursename]
Face-to-face:   [facetofacename]
Cost:   [cost]

Duration:   [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]

***Please arrive ten minutes before the course starts***

To re-schedule or cancel your booking
To re-schedule your booking you need to cancel this booking and then re-book a new session.  To cancel your booking, return to the site, then to the page for this course, and then select \'cancel\' from the booking information screen.

[details]
';
$string['setting:defaultremindersubject'] = 'Default subject line for reminder emails.';
$string['setting:defaultremindersubject_caption'] = 'Reminder subject';
$string['setting:defaultremindersubjectdefault'] = 'Face-to-face booking reminder: [facetofacename], [starttime]-[finishtime], [sessiondate]';
$string['setting:defaultrequestinstrmngrdefault'] = 'This is to advise that [firstname] [lastname] has requested to be booked into the following course, and you are listed as their Team Leader / Manager.

Course:   [coursename]
Face-to-face:   [facetofacename]
Cost:   [cost]

Duration:   [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]

Please follow the link below to approve the request:
[attendeeslink]

';
$string['setting:defaultrequestinstrmngrcopybelow'] = '*** [firstname] [lastname]\'s booking request is copied below ****';
$string['setting:defaultrequestmessagedefault'] = 'Your request to book into the following course has been sent to your manager:

Participant:   [firstname] [lastname]
Course:   [coursename]
Face-to-face:   [facetofacename]
Cost:   [cost]

Duration:   [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]
';
$string['setting:defaultrequestsubjectdefault'] = 'Face-to-face booking request: [facetofacename], [starttime]-[finishtime], [sessiondate]';
$string['setting:defaulttrainerconfirmationmessage'] = 'Default message sent to trainers when assigned to a session.';
$string['setting:defaulttrainerconfirmationmessage_caption'] = 'Trainer confirmation message';
$string['setting:defaulttrainerconfirmationmessagedefault'] = 'This is to confirm that you are now assigned to deliver training on the following course:

Participant:   [firstname] [lastname]
Course:   [coursename]
Face-to-face:   [facetofacename]

Duration:    [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]

***Please arrive ten minutes before the course starts***

[details]
';
$string['setting:defaulttrainerconfirmationsubject'] = 'Default subject line for trainer confirmation emails.';
$string['setting:defaulttrainerconfirmationsubject_caption'] = 'Trainer confirmation subject';
$string['setting:defaulttrainerconfirmationsubjectdefault'] = 'Face-to-face trainer confirmation: [facetofacename], [starttime]-[finishtime], [sessiondate]';

$string['setting:defaulttrainersessioncancellationmessage'] = 'Default session cancellation message sent to the trainer.';
$string['setting:defaulttrainersessioncancellationmessage_caption'] = 'Trainer session cancellation message';
$string['setting:defaulttrainersessioncancellationmessagedefault'] = 'This is to advise that your assigned training session the following course has been cancelled:

***SESSION CANCELLED***

Participant:   [firstname] [lastname]
Course:   [coursename]
Face-to-face:   [facetofacename]

Duration:   [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]
';

$string['setting:defaulttrainersessioncancellationsubject'] = 'Default subject line for trainer session cancellation emails.';
$string['setting:defaulttrainersessioncancellationsubject_caption'] = 'Trainer session cancellation subject';
$string['setting:defaulttrainersessioncancellationsubjectdefault'] = 'Face-to-face session trainer cancellation';

$string['setting:defaulttrainersessionunassignedmessage'] = 'Default session unassigned message sent to the trainer.';
$string['setting:defaulttrainersessionunassignedmessage_caption'] = 'Trainer session unassigned message';
$string['setting:defaulttrainersessionunassignedmessagedefault'] = 'This is to advise that you have been unassigned from training for following course:

***SESSION UNASSIGNED***

Participant:   [firstname] [lastname]
Course:   [coursename]
Face-to-face:   [facetofacename]

Duration:   [duration]
Date(s):
[alldates]

Location:   [session:location]
Venue:   [session:venue]
Room:   [session:room]
';

$string['setting:defaulttrainersessionunassignedsubject'] = 'Default subject line for trainer session unassigned emails.';
$string['setting:defaulttrainersessionunassignedsubject_caption'] = 'Trainer session unassigned subject';
$string['setting:defaulttrainersessionunassignedsubjectdefault'] = 'Face-to-face session trainer unassigned';
$string['setting:defaultvalue'] = 'Default value';
$string['setting:defaultwaitlistedmessage'] = 'Default wait-listed message sent to users.';
$string['setting:defaultwaitlistedmessage_caption'] = 'Wait-listed message';
$string['setting:defaultwaitlistedmessagedefault'] = 'This is to advise that you have been added to the waitlist for:

Course:   [coursename]
Face-to-face:   [facetofacename]
Location:  [session:location]
Participant:   [firstname] [lastname]

***Please note this is not a course booking confirmation***

By waitlisting you have registered your interest in this course and will be contacted directly when sessions become available.

To remove yourself from this waitlist please return to this course and click Cancel Booking. Please note there is no waitlist removal confirmation email.
';
$string['setting:defaultwaitlistedsubject'] = 'Default subject line for wait-listed emails.';
$string['setting:defaultwaitlistedsubject_caption'] = 'Wait-listed subject';
$string['setting:defaultwaitlistedsubjectdefault'] = 'Waitlisting advice for [facetofacename]';
$string['setting:displaysessiontimezones'] = 'When enabled the timezone of the Face-to-face session will be shown otherwise it will be hidden (selecting a timezone for a session will also be enabled/disabled).';
$string['setting:displaysessiontimezones_caption'] = 'Display session timezones';
$string['setting:disableicalcancel'] = 'Disable cancellation emails with an iCalendar attachment.';
$string['setting:disableicalcancel_caption'] = 'Disable iCalendar cancellations:';
$string['setting:fromaddress'] = 'What will appear in the From field of email reminders sent by this module.';
$string['setting:fromaddress_caption'] = 'Sender address:';
$string['setting:fromaddressdefault'] = 'totara@example.com';
$string['setting:lotteryenabled_caption'] = 'Enable waitlist lottery';
$string['setting:lotteryenabled'] = 'Enable or disable waitlist lottery';
$string['setting:manageraddressformat'] = 'Suffix which must be present in the email address of the manager in order to be considered valid.';
$string['setting:manageraddressformat_caption'] = 'Required suffix:';
$string['setting:manageraddressformatdefault'] = '';
$string['setting:manageraddressformatreadable'] = 'Short description of the restrictions on a manager\'s email address.  This setting has no effect if the previous one is not set.';
$string['setting:manageraddressformatreadable_caption'] = 'Format example:';
$string['setting:manageraddressformatreadabledefault'] = 'firstname.lastname@company.com';
$string['setting:managerreserve'] = 'Allow reserve/assign (default)';
$string['setting:managerreserve_desc'] = 'Managers are able to make reservations or bookings on behalf of their team members';
$string['setting:managerreserveheader'] = 'Manager reservations';
$string['setting:maxmanagerreserves'] = 'Max reservations (default)';
$string['setting:maxmanagerreserves_desc'] = 'The total number of reservations / bookings that a manager can make for their team';
$string['setting:multiplesessions'] = 'Default value for allowing multiple sessions signup per user';
$string['setting:multiplesessions_caption'] = 'Multiple sessions default';
$string['setting:oneemailperday'] = 'Send multiple confirmation emails for multi-date sessions. Note: If there is more than one session date on a single day then each session date will generate an email. One session date spanning over multiple days will generate only one email.';
$string['setting:oneemailperday_caption'] = 'One message per date:';
$string['setting:hidecost'] = 'Hide the cost and discount code fields.';
$string['setting:hidecost_caption'] = 'Hide cost and discount:';
$string['setting:hidediscount'] = 'Hide only the discount code field.';
$string['setting:hidediscount_caption'] = 'Hide discount:';
$string['setting:selectpositiononsignupglobal'] = 'Select position on signup';
$string['setting:selectpositiononsignupglobal_caption'] = 'When enabled a setting will appear in face to face activity settings to force users with multiple positions to choose which capacity they will be signing up on.';
$string['setting:selectpositiononsignupglobal'] = 'Select position on signup';
$string['setting:selectpositiononsignupglobal_caption'] = 'When enabled a setting will appear in face to face activity settings to force users with multiple positions to choose which capacity they will be signing up on.';
$string['setting:sitenotices'] = 'Notices only appear on the Face-to-face Calendar found {$a}';
$string['setting:sitenoticeshere'] = 'here';
$string['setting:possiblevalues'] = 'List of possible values';
$string['setting:reservecanceldays'] = 'Reservation cancellation days (default)';
$string['setting:reservecanceldays_desc'] = 'The number of days in advance of the session that reservations will be automatically cancelled, if not confirmed.';
$string['setting:reservedays'] = 'Reservation deadline (default)';
$string['setting:reservedays_desc'] = 'The number of days before the session starts after which no more reservations are allowed (must be greater than the cancellation days)';
$string['setting:showinsummary'] = 'Show in exports and lists';
$string['setting:sessionroles'] = 'Users assigned to the selected roles in a course can be tracked with each face-to-face session';
$string['setting:sessionroles_caption'] = 'Session roles:';
$string['setting:type'] = 'Field type';
$string['setting:notificationdisable'] = 'Turn on/off Face-to-face activity notification emails to users';
$string['setting:notificationdisable_caption'] = 'Disable Face-to-face activity notifications';
$string['showbylocation'] = 'Show by location';
$string['showoncalendar'] = 'Calendar display settings';
$string['signup'] = 'Sign-up';
$string['signups'] = 'Sign-ups';
$string['signupfor'] = 'Sign-up for {$a}';
$string['signupforsession'] = 'Sign-up for an available upcoming session';
$string['signupforthissession'] = 'Sign-up for this Face-to-face session';
$string['sign-ups'] = 'Sign-ups';
$string['sitelogseditattendees'] = 'Facetoface "{$a->f2fname}", session "{$a->sessionid}": {$a->usercount} learners edited with {$a->errorcount} errors';
$string['sitenoticesheading'] = 'Site Notices';
$string['startdateafter'] = 'Start date after';
$string['finishdatebefore'] = 'Finish date before';
$string['subject'] = 'Change in booking in the course {$a->coursename} ({$a->duedate})';
$string['submissions'] = 'Submissions';
$string['submitted'] = 'Submitted';
$string['submit'] = 'Submit';
$string['suppressemail'] = 'Suppress email notification';
$string['suppressemailforattendees'] = 'Suppress the confirmation and calendar invite emails for newly added attendees and the cancellation emails for removed attendees';
$string['status'] = 'Status';
$string['status_booked'] = 'Booked';
$string['status_fully_attended'] = 'Fully attended';
$string['status_no_show'] = 'No show';
$string['status_not_set'] = 'Not set';
$string['status_partially_attended'] = 'Partially attended';
$string['status_requested'] = 'Requested';
$string['status_user_cancelled'] = 'User Cancelled';
$string['status_waitlisted'] = 'Wait-listed';
$string['status_approved'] = 'Approved';
$string['status_declined'] = 'Declined';
$string['status_session_cancelled'] = 'Session Cancelled';
$string['submitcsvtext'] = 'Submit CSV text';
$string['successfullyaddededitedxattendees'] = 'Successfully added/edited {$a} attendees.';
$string['summary'] = 'Summary';
$string['takeattendance'] = 'Take attendance';
$string['template'] = 'Template';
$string['thissession'] = 'This session';
$string['time'] = 'Time';
$string['timeandtimezone'] = 'Time and Time Zone';
$string['timedue'] = 'Registration deadline';
$string['timefinish'] = 'Finish time';
$string['timestart'] = 'Start time';
$string['timecancelled'] = 'Time Cancelled';
$string['timerequested'] = 'Time Requested';
$string['timesignedup'] = 'Time Signed Up';
$string['title'] = 'Title';
$string['timezoneupgradeinfomessage'] = 'WARNING : This upgrade to Facetoface adds the ability to specify the timezone in which a Facetoface session will occur.<br /><br />It is <b>strongly</b> recommended that you check the session timezones, start times and end times for all upcoming Facetoface sessions that were created prior to this upgrade.';
$string['datesignedup'] = 'Date Signed Up';
$string['thirdpartyemailaddress'] = 'Third-party email address(es)';
$string['thirdpartywaitlist'] = 'Notify third-party about wait-listed sessions';
$string['type'] = 'Type';
$string['unapprovedrequests'] = 'Unapproved Requests';
$string['unknowndate'] = '(unknown date)';
$string['unknowntime'] = '(unknown time)';
$string['upcomingsessions'] = 'Upcoming sessions';
$string['upcomingsessionslist'] = 'List of all upcoming sessions for this Face-to-face activity';
$string['updateattendeessuccessful'] = 'Successfully updated attendance';
$string['updateattendeesunsuccessful'] = 'An error has occurred, attendance could not be updated';
$string['updateposition'] = 'Update position';
$string['updaterequests'] = 'Update requests';
$string['updatewaitlist'] = 'Update waitlist';
$string['upgradeprocessinggrades'] = 'Processing Face-to-face grades, this may take a while if there are many sessions...';
$string['usercancelledon'] = 'User cancelled on {$a}';
$string['userdoesnotexist'] = 'User with {$a->fieldname} "{$a->value}" does not exist';
$string['useriddoesnotexist'] = 'User with ID "{$a}" does not exist';
$string['usercalentry'] = 'Show entry on user\'s calendar';
$string['userdeletedcancel'] = 'User has been deleted';
$string['usersuspendedcancel'] = 'User has been suspended';
$string['usernotsignedup'] = 'Status: not signed up';
$string['usernote'] = 'Sign-up note';
$string['userpositionheading'] = '{$a} - update selected position';
$string['usernoteupdated'] = 'Attendee\'s note updated';
$string['usernoteheading'] = '{$a} - update note';
$string['usersignedup'] = 'Status: signed up';
$string['usersignedupmultiple'] = 'User signed up on {$a} sessions';
$string['usersignedupon'] = 'User signed up on {$a}';
$string['userwillbewaitlisted'] = 'This session is currently full. By clicking the "Sign-up" button, you will be placed on the sessions\'s wait-list.';
$string['validation:needatleastonedate'] = 'You need to provide at least one date, or else mark the session as wait-listed.';
$string['venue'] = 'Venue';
$string['viewallsessions'] = 'View all sessions';
$string['viewresults'] = 'View results';
$string['viewsubmissions'] = 'View submissions';
$string['waitlistedmessage'] = 'Wait-listed message';
$string['waitlisteveryone'] = 'Send all bookings to the waiting list';
$string['waitlisteveryone_help'] = 'Everyone who signs up for this session will be added to the waiting list. Allow overbooking must be enabled.';
$string['waitlistselectoneormoreusers'] = 'Please select one or more users to update';
$string['wait-list'] = 'Wait-list';
$string['wait-listed'] = 'Wait-listed';
$string['xerrorsencounteredduringimport'] = '{$a} problem(s) encountered during import.';
$string['xhours'] = '{$a} hour(s)';
$string['xmessagesfailed'] = '{$a} message(s) failed to send';
$string['xmessagessenttoattendees'] = '{$a} message(s) successfully sent to attendees';
$string['xmessagessenttoattendeesandmanagers'] = '{$a} message(s) successfully sent to attendees and their managers';
$string['xminutes'] = '{$a} minute(s)';
$string['xusers'] = '{$a} user(s)';
$string['youarebooked'] = 'You are booked for the following session';
$string['yourbookings'] = 'Your bookings / reservations';
$string['youremailaddress'] = 'Your email address';
$string['youwillbeaddedtothewaitinglist'] = 'Please Note: You will be added to the waiting list for this session';
$string['error:shortnametaken'] = 'Custom field with this short name already exists.';

// -------------------------------------------------------
// Help Text

$string['allowoverbook_help'] = 'When "Allow overbooking" is checked, learners will be able to sign up for a face-to-face session even if it is already full.

When a learner signs up for a session that is already full, they will receive an email advising that they have been waitlisted for the session and will be notified when a booking becomes available.';

$string['approvalreqd_help'] = 'When "Approval required" is checked, a learner will need approval from their manager to be permitted to attend a face-to-face session.';

$string['availablesignupnote_help'] = 'When "User sign-up note" is checked, learners will be able to enter any specific requirements that the session organiser might need to know about:

* Dietary requirements
* Disabilities';

$string['cancellationinstrmngr'] = '# Notice for manager';
$string['cancellationinstrmngr_help'] = 'When **Send notice to manager** is checked, the text in the **Notice for manager** field is sent to a learner\'s manager advising that they have cancelled a face-to-face booking.';

$string['cancellationmessage_help'] = 'This message is sent out whenever users cancel their booking for a session.';

$string['capacity_help'] = '**Capacity** is the number of seats available in a session.

When a face-to-face session reaches capacity the session details do not appear on the course page. The details will appear greyed out on the \'View all sessions\' page and the learner cannot enrol on the session.
&nbsp;';

$string['confirmationinstrmngr'] = '# Notice for manager';
$string['confirmationinstrmngr_help'] = 'When "Send notice to manager" is checked, the text in the "Notice for manager" field is sent to a manager advising that a staff member has signed up for a face-to-face session.';

$string['confirmationmessage_help'] = 'This message is sent out whenever users sign up for a session.';

$string['description_help'] = '**Description** is the course description that displays when a learner enrols on a face-to-face session.

The **Description** also displays in the training calendar.';

$string['details_help'] = 'Details are tracked per session basis.
If text is populated in the details field, the details text will be displayed on the user signup page.
By default, the details text also appears in the confirmation, reminder, waitlist and cancellation email messages.';

$string['discountcode_help'] = 'Discount code is the code required for the discount cost to be tracked for the training of a staff member.
If the staff member does not enter the discount code, the normal cost appears in the training record.';

$string['discountcodelearner'] = 'Discount Code';
$string['discountcodelearner_help'] = 'If you know the discount code enter it here. If you leave this field blank you will be charged the normal cost for this session';

$string['discountcost_help'] = 'Discount cost is the amount charged to staff members who have a membership id.';

$string['duration_help'] = '**Duration** is the total length of the training in hours.
For example:

* "2 hours" is enters as **2** or **2:00**
* "1 hour and 30 minutes" is entered as **1:30**
* "45 minutes" is entered as **0:45**
* "20 minutes" is entered as **0:20**.

If the training occurs over two or more time periods, the duration is the combined total.';

$string['emailmanagercancellation'] = '# Send notice to manager';
$string['emailmanagercancellation_help'] = 'When "Send notice to manager" is checked, an email will be sent to the learner\'s manager advising them that the face-to-face booking has been cancelled.';

$string['emailmanagerconfirmation'] = '# Send notice to manager';
$string['emailmanagerconfirmation_help'] = 'When "Send notice to manager" is checked, a confirmation email will be sent to the learner\'s manager when the learner signs up for a face-to-face session.';

$string['emailmanagerreminder'] = '# Send notice to manager';
$string['emailmanagerreminder_help'] = 'When "Send notice to manager" is checked, a reminder message will be sent to the learner\'s manager a few days before the start date of the face-to-face session.';

$string['location_help'] = '**Location** describes the vicinity of the session (city, county, region, etc).

**Location** displays on the course page, \'Sign-up page\', the \'View all sessions\' page, and in all email notifications.

On the \'View all sessions\' page, the listed sessions can be filtered by location.';

$string['modulename_help'] = 'The Face-to-Face activity module enables a teacher to set up a booking system for one or many in-person/classroom based sessions.

Each session within a Face-to-Face activity can have customised settings around room, start time, finish time, cost, capacity, etc. These can be set to run over multiple days or allow for unscheduled and waitlisted sessions.

An Activity may be set to require manager approval and teachers can configure automated notifications and session reminders for attendees.

Students can view and sign-up for sessions with their attendance tracked and recorded within the Grades area.';

$string['mods_help'] = 'Face-to-face activities are used to keep track of in-person trainings which require advance booking.

Each activity is offered in one or more identical sessions. These sessions can be given over multiple days.

Reminder messages are sent to users and their managers a few days before the session is scheduled to start. Confirmation messages are sent when users sign-up for a session or cancel.';

$string['multiplesessions_help'] = 'Use this option if you want users be able to sign up to multiple sessions . When this option is toggled, users can sign up for multiple sessions in the activity.';
$string['normalcost_help'] = 'Normal cost is the amount charged to staff members who do not have a membership id.';

$string['notificationtype_help'] = 'Notification Type allows the learner to select how they would like to be notified of their booking.

* Email notification and iCalendar appointment
* Email notification only
* No Email notification ';

$string['reminderinstrmngr'] = '# Notice for Manager';
$string['reminderinstrmngr_help'] = 'When **Send notice to manager** is checked, the text in the **Notice for Manager** field is sent to a learner\'s manager advising that they have signed up for a face-to-face session.';

$string['remindermessage_help'] = 'This message is sent out a few days before a session\'s start date.';

$string['requestmessage_help'] = 'When **Approval required** is enabled, the **Request message** section is available.

The **Request message** section displays the notices sent to the learner and their manager regarding the approval process for the learner to attend the face-to-face session.

**Subject:** is the subject line that appears on the request approval emails sent to the manager and the learner.

**Message:** is the email text sent to the learner advising them that their request to attend the face-to-face session has been sent to their manager for approval.

**Notice for manager:** is the email text sent to the learner\'s manager requesting approval to attend the face-to-face session.';

$string['room_help'] = '**Room** is the name/number/identifier of the room being used for the training session.

The **Room** displays on the \'Sign-up\' page, the \'View all sessions\' page and in all email notifications.';

$string['sessiondatetimeknown_help'] = '**If a session\'s date/time is known**

If "Yes" is entered for this setting, the session date and time will be displayed on the course page (if the session is upcoming and available), the "View all sessions page", the session sign-up page, as well as all email notifications related to the session.

When a staff member signs up for a session with a known date and time:

* The staff member and the staff member\'s manager will be sent a confirmation email (i.e., the one formatted per the "Confirmation message" section of the face-to-face instance\'s settings).
* The staff member will be sent a reminder email message (i.e., the one formatted per the "Reminder message" section of the face-to-face instance\'s settings). The reminder will be sent a number of days before the session, according to the "Days before message is sent" setting also found in the "Reminder message" section of the face-to-face instance\'s settings.

**If a session\'s date/time is not known (or wait-listed)**

If "No" is entered for this setting, the text "wait-listed" will be displayed on the course page, the "View all sessions page", the session sign-up page, as well as all email notifications related to the session.

When a staff member signs up for a wait-listed session:

* The staff member will be sent a confirmation email (i.e. the one formatted per the "Wait-listed message" section of the face-to-face instance\'s settings).
* The staff member will not be sent a reminder email message.
* The staff member\'s manager will not be sent confirmation and cancellation email messages.';

$string['sessionsoncoursepage_help'] = 'This is the number of sessions for each face-to-face activity that will be shown on the main course page.';

$string['shortname'] = '# Short Name';
$string['shortname_help'] = '**Short name** is the description of the session that appears on the training calendar when **Show on the calendar** is enabled.';

$string['showoncalendar_help'] = 'When **Site** is selected the face-to-face activity sessions will be displayed on the site calendar as a Global Event.  All site users will be able to view these sessions.

When **Course** is selected all of the face-to-face activity sessions will be displayed on the course calendar and as Course Event on the site level calendar and visible to all users enrolled in the course.

When **None** is selected, face-to-face activity sessions will only be displayed as User Events on a confirmed attendee\'s calendar, provided the **Show on user\'s calendar** option has been selected.';

$string['suppressemail_help'] = 'Use this option if you want to silently add/remove users from a Face-to-face session. When this option is toggled, the usual email
  confirmation is not sent to the selected users.';

$string['thirdpartyemailaddress_help'] = '**Third-party email address(es)** is an optional field used to specify the email address of a third-party (such as an external instructor) who will then receive confirmation messages whenever a user signs-up for a session.
When entering **multiple email addresses**, separate each address with a comma. For example: bob@example.com,joe@example.com';

$string['thirdpartywaitlist_help'] = 'When **Notify third-party about wait-listed sessions** is selected the third-party(s) will be notified when a learner signs up for a wait-listed session. When

**Notify third-party about wait-listed sessions** is not enabled third-party(s) will only be notified when a user signs up (or cancels) for a scheduled session.';

$string['timefinish_help'] = 'Finish time is the time when the session ends.';

$string['timestart_help'] = 'Start time is the time when the session begins.';

$string['usercalentry_help'] = 'When active this setting adds a User Event entry to the calendar of an attendee of a face-to-face session. When turned off this prevents a duplicate event appearing in a session attendee\'s calendar, where you have calendar display settings set to Course or Site.';

$string['venue_help'] = '**Venue** is the building the session will be held in.

The **Venue** displays on the \'Sign-up\' page, the \'View all sessions\' page and in all email notifications.';

$string['waitlistedmessage_help'] = 'This message is sent out whenever users sign-up for a wait-listed session.';
$string['usernote_help'] = 'Any specific requirements that the session organiser might need to know about:

* Dietary requirements
* Disabilities';

//Totara Messaging strings
$string['requestattendsession'] = 'Request to attend session {$a}';
$string['requestattendsessionsent'] = 'Request to attend session {$a} sent to manager';

$string['bookedforsession'] = 'Booked for session {$a}';
$string['waitlistedforsession'] = 'Waitlisted for session {$a}';
$string['cancelledforsession'] = 'Cancelled for session {$a}';

$string['requestuserattendsession'] = 'Request for {$a->usermsg} to attend session {$a->url}';
$string['cancelusersession'] = 'Cancelled for {$a->usermsg} session {$a->url}';

$string['approveinstruction'] = 'To approve session registration, press accept';
$string['rejectinstruction'] = 'To reject session registration, press reject';
$string['sessiondate_help'] = 'Session date is the date on which the session occurs.';

