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
 * @package totara
 * @subpackage totara_core
 *
 * totara_core specific language strings.
 * these should be called like get_string('key', 'totara_core');
 */

$string['addanothercolumn'] = 'Add another column...';
$string['allf2fbookings'] = 'All Face to Face Bookings';
$string['alllearningrecords'] = 'All Learning Records';
$string['allmycourses'] = 'All My Courses';
$string['allteammembers'] = 'All Team Members';
$string['alreadyselected'] = '(already selected)';
$string['ampersand'] = 'and';
$string['archivecompletionrecords'] = 'Archive completion records';
$string['assessments'] = 'Assessments';
$string['assessmenttype'] = 'Assessment Type';
$string['assessor'] = 'Assessor';
$string['assessorname'] = 'Assessor Name';
$string['assignedvia'] = 'Assigned Via';
$string['assigngroup'] = 'Assign User Group';
$string['assignincludechildren'] = ' and all below';
$string['blended'] = 'Blended';
$string['bookings'] = 'Bookings';
$string['bookingsfor'] = 'Bookings for ';
$string['browse'] = 'Browse';
$string['browsecategories'] = 'Browse Categories';
$string['calendar'] = 'Calendar';
$string['cannotdownloadtotaralanguageupdatelist'] = 'Cannot download list of language updates from download.totaralms.com';
$string['cannotundeleteuser'] = 'Cannot undelete user';
$string['choosetempmanager'] = 'Choose temporary manager';
$string['choosetempmanager_help'] = 'A temporary manager can be assigned. The assigned Temporary Manager will have the same rights as a normal manager, for the specified amount of time.

Click **Choose temporary manager** to select a temporary manager.

If the name you are looking for does not appear in the list, it might be that the user does not have the necessary rights to act as a temporary manager.';
$string['column'] = 'Column';
$string['competency_typeicon'] = 'Competency type icon';
$string['completed'] = 'Completed';
$string['configenhancedcatalog'] = 'This setting allows you to specify if the enhanced catalog appears when clicking on \'Find Learning\' or any of the menu options under \'Find Learning\'.
    The enhanced catalog supports faceted search by multiple criteria using custom fields instead of relying on a single category.
    When disabled, the standard catalog (i.e., the hierarchical category system configured in the \'Manage categories\' administration area) appears when clicking on \'Find Learning\' or any of the menu options under \'Find Learning\'.
    Note: When enabled, the standard catalog remains available for Admins to manage course and program/certification administration in the "backend" (e.g., to assign Instructors to courses and course categories).';
$string['configforcelogintotara'] = 'Normally, the entire site is only available to logged in users. If you would like to make the front page and the course listings (but not the course contents) available without logging in, then you should uncheck this setting.';
$string['core:appearance'] = 'Configure site appearance settings';
$string['core:createcoursecustomfield'] = 'Create a course custom field';
$string['core:createprogramcustomfield'] = 'Create a program custom field';
$string['core:delegateownmanager'] = 'Assign a temporary manager to yourself';
$string['core:delegateusersmanager'] = 'Assign a temporary manager to other users';
$string['core:deletecoursecustomfield'] = 'Delete a course custom field';
$string['core:deleteprogramcustomfield'] = 'Delete a program custom field';
$string['core:editmainmenu'] = 'Edit the main menu';
$string['core:seedeletedusers'] = 'See deleted users';
$string['core:undeleteuser'] = 'Undelete user';
$string['core:updatecoursecustomfield'] = 'Update a course custom field';
$string['core:updateprogramcustomfield'] = 'Update a program custom field';
$string['core:updateuseridnumber'] = 'Update user ID number';
$string['core:viewrecordoflearning'] = 'View a learners Record of Learning';
$string['couldntreaddataforblockid'] = 'Could not read data for blockid={$a}';
$string['couldntreaddataforcourseid'] = 'Could not ready data for courseid={$a}';
$string['coursecategoryicon'] = 'Category icon';
$string['coursecompletion'] = 'Course completion';
$string['coursecompletionsfor'] = 'Course Completions for ';
$string['courseicon'] = 'Course icon';
$string['courseprogress'] = 'Course progress';
$string['courseprogresshelp'] = 'This specifies if the course progress block appears on the homepage';
$string['coursetype'] = 'Course Type';
$string['csvdateformat'] = 'CSV Import date format';
$string['csvdateformatconfig'] = 'Date format to be used in CSV imports like user uploads with date custom profile fields, or Totara Sync.

The date format should be compatible with the formats defined in the <a target="_blank" href="http://www.php.net/manual/en/datetime.createfromformat.php">PHP DateTime class</a>

Examples:
<ul>
<li>d/m/Y if the dates in the CSV are of the form 21/03/2012</li>
<li>d/m/y if the dates in the CSV have 2-digit years 21/03/12</li>
<li>m/d/Y if the dates in the CSV are in US form 03/21/2012</li>
<li>Y-m-d if the dates in the CSV are in ISO form 2012-03-21</li>
</ul>';
$string['csvdateformatdefault'] = 'd/m/Y';
$string['currenticon'] = 'Current icon';
$string['currentlyselected'] = 'Currently selected';
$string['customicons'] = 'Custom icons';
$string['datatable:oPaginate:sFirst'] = 'First';
$string['datatable:oPaginate:sLast'] = 'Last';
$string['datatable:oPaginate:sNext'] = 'Next';
$string['datatable:oPaginate:sPrevious'] = 'Previous';
$string['datatable:sEmptyTable'] = 'No data available in table';
$string['datatable:sInfo'] = 'Showing _START_ to _END_ of _TOTAL_ entries';
$string['datatable:sInfoEmpty'] = 'Showing 0 to 0 of 0 entries';
$string['datatable:sInfoFiltered'] = '(filtered from _MAX_ total entries)';
$string['datatable:sInfoPostFix'] = '';
$string['datatable:sInfoThousands'] = ',';
$string['datatable:sLengthMenu'] = 'Show _MENU_ entries';
$string['datatable:sLoadingRecords'] = 'Loading...';
$string['datatable:sProcessing'] = 'Processing...';
$string['datatable:sSearch'] = 'Search:';
$string['datatable:sZeroRecords'] = 'No matching records found';
$string['datepickerlongyeardisplayformat'] = 'dd/mm/yy';
$string['datepickerlongyearparseformat'] = 'd/m/Y';
$string['datepickerlongyearphpuserdate'] = '%d/%m/%Y';
$string['datepickerlongyearplaceholder'] = 'dd/mm/yyyy';
$string['datepickerlongyearregexjs'] = '[0-3][0-9]/(0|1)[0-9]/[0-9]{4}';
$string['datepickerlongyearregexphp'] = '@^(0?[1-9]|[12][0-9]|3[01])/(0?[1-9]|1[0-2])/([0-9]{4})$@';
$string['debugstatus'] = 'Debug status';
$string['delete'] = 'Delete';
$string['deleted'] = 'Deleted';
$string['developmentplan'] = 'Development Planner';
$string['disablefeature'] = 'Disable';
$string['downloaderrorlog'] = 'Download error log';
$string['editheading'] = 'Edit the Report Heading Block';
$string['edition'] = 'Edition';
$string['elearning'] = 'E-learning';
$string['elementlibrary'] = 'Element Library';
$string['enabledisabletotarasync'] = 'Select Enable or Disable and then click continue to update Totara Sync for {$a}';
$string['enabletempmanagers'] = 'Enable temporary managers';
$string['enabletempmanagersdesc'] = 'Enable functionality that allows for assigning a temporary manager to a user. Disabling this will cause all current temporary managers to be unassigned on next cron run.';
$string['enhancedcatalog'] = 'Enhanced catalog';
$string['enrolled'] = 'Enrolled';
$string['error:addpdroom-dialognotselected'] = 'Please select a room';
$string['error:appraisernotselected'] = 'Please select an appraiser';
$string['error:assigncannotdeletegrouptypex'] = 'You cannot delete groups of type {$a}';
$string['error:assignmentbadparameters'] = 'Bad parameter array passed to dialog set_parameters';
$string['error:assignmentgroupnotallowed'] = 'You cannot assign groups of type {$a->grouptype} to {$a->module}';
$string['error:assignmentmoduleinstancelocked'] = 'You cannot make changes to an assignment module instance which is locked';
$string['error:assignmentprefixnotfound'] = 'Assignment class for group type {$a} not found';
$string['error:assigntablenotexist'] = 'Assignment table {$a} does not exist!';
$string['error:autoupdatedisabled'] = 'Automatic checking for Moodle updates is currently disabled in Totara';
$string['error:cannotupgradefromnewermoodle'] = 'You cannot upgrade to Totara {$a->newtotaraversion} from this version of Moodle. Please use a newer version of Totara which is based on Moodle core {$a->oldversion} or above.';
$string['error:cannotupgradefrommoodle'] = 'You cannot upgrade to Totara 2.4 from a Moodle version prior to 2.2.7. Please upgrade to Totara 2.2.13+ or Moodle 2.2.7+ first.';
$string['error:cannotupgradefromtotara'] = 'You cannot upgrade to Totara 2.4 from this version of Totara. Please upgrade to Totara 2.2.13 or greater first.';
$string['error:categoryidincorrect'] = 'Category ID was incorrect';
$string['error:columntypenotfound'] = 'The column type \'{$a}\' was defined but is not a valid option. This can happen if you have deleted a custom field or hierarchy depth level. The best course of action is to delete this column by pressing the red cross to the right.';
$string['error:columntypenotfound11'] = 'The column type \'{$a}\' was defined but is not a valid option. This can happen if you have deleted a custom field or hierarchy type. The best course of action is to delete this column by pressing the red cross to the right.';
$string['error:couldnotcreatedefaultfields'] = 'Could not create default fields';
$string['error:couldnotupdatereport'] = 'Could not update report';
$string['error:courseidincorrect'] = 'Course id is incorrect.';
$string['error:dashboardnotfound'] = 'Cannot fully initialize page - could not retrieve dashboard details';
$string['error:datenotinfuture'] = 'The date needs to be in the future';
$string['error:dialognotreeitems'] = 'No items available';
$string['error:duplicaterecordsdeleted'] = 'Duplicate {$a} record deleted: ';
$string['error:duplicaterecordsfound'] = '{$a->count} duplicate record(s) found in the {$a->tablename} table...fixing (see error log for details)';
$string['error:emptyidnumberwithsync'] = 'Totara Sync is enabled but the ID number field is empty. Either disable sync for this user or provide a valid ID number.';
$string['error:findingmenuitem'] = 'Error finding the menu item';
$string['error:importtimezonesfailed'] = 'Failed to update timezone information.';
$string['error:itemhaschildren'] = 'Note: you cannot change the parent of this item as it has children. Two menu levels are supported. Please move this item\'s children first.';
$string['error:managernotselected'] = 'Please select a manager';
$string['error:menuitemcannotberemoved'] = '"{$a}" item can not be removed, please review your settings.';
$string['error:menuitemcannotremove'] = '"{$a}" has the children which can not be removed, please review your settings.';
$string['error:menuitemcannotremovechild'] = ' - can not delete this item';
$string['error:menuitemclassnametoolong'] = 'Class name too long';
$string['error:menuitemparentismissing'] = 'Menu item get_default_parent() method is missing in {$a} class';
$string['error:menuitemtargetattrismissing'] = 'Menu item get_default_targetattr() method is missing in {$a} class';
$string['error:menuitemtargetattrtoolong'] = 'Menu target attribute too long';
$string['error:menuitemtitleismissing'] = 'Menu item get_default_title() method is missing in {$a} class';
$string['error:menuitemtitletoolong'] = 'Menu title too long';
$string['error:menuitemtitlerequired'] = 'Menu title required';
$string['error:menuitemurlinvalid'] = 'Menu url address is invalid. Use "/" for a relative link of your domain name or full address for external link, i.e. http://extdomain.com';
$string['error:menuitemurlismissing'] = 'Menu item get_default_url() method is missing in {$a} class';
$string['error:menuitemurltoolong'] = 'Menu url address too long';
$string['error:menuitemurlrequired'] = 'Menu url address required';
$string['error:menuitemvisibilityismissing'] = 'Menu item get_default_visibility() method is missing in {$a} class';
$string['error:morethanxitemsatthislevel'] = 'There are more than {$a} items at this level.';
$string['error:norolesfound'] = 'No roles found';
$string['error:notificationsparamtypewrong'] = 'Incorrect param type sent to Totara notifications';
$string['error:organisationnotselected'] = 'Please select an organisation';
$string['error:parentnotexists'] = '"{$a}" parent item does not exists, please check your settings';
$string['error:positionnotselected'] = 'Please select a position';
$string['error:positionvalidationfailed'] = 'The problems indicated below must be fixed before your changes can be saved.';
$string['error:staffmanagerroleexists'] = 'A role "staffmanager" already exists. This role must be renamed before the upgrade can proceed.';
$string['error:tempmanagerexpirynotset'] = 'An expiry date for the temporary manager needs to be set';
$string['error:tempmanagernotselected'] = 'Please select a temporary manager';
$string['error:tempmanagernotset'] = 'Temporary manager needs to be set';
$string['error:unknownbuttonclicked'] = 'Unknown button clicked';
$string['error:useridincorrect'] = 'User id is incorrect.';
$string['error:usernotfound'] = 'User not found';
$string['errorfindingcategory'] = 'Error finding the category';
$string['errorfindingprogram'] = 'Error finding the program';
$string['eventfirstaccess'] = 'First access';
$string['eventmenuadminviewed'] = 'Main menu viewed';
$string['eventmenuitemcreated'] = 'Menu item created';
$string['eventmenuitemdeleted'] = 'Menu item deleted';
$string['eventmenuitemupdated'] = 'Menu item updated';
$string['eventmodulecompletion'] = 'Activity completion';
$string['eventundeleted'] = 'User undeleted';
$string['eventusersuspended'] = 'User suspended';
$string['eventuserenrolment'] = 'User enrolled';
$string['facetoface'] = 'Face-to-face';
$string['findcourses'] = 'Find Courses';
$string['findlearning'] = 'Find Learning';
$string['framework'] = 'Framework';
$string['heading'] = 'Heading';
$string['headingcolumnsdescription'] = 'The fields below define which data appear in the Report Heading Block. This block contains information about a specific user, and can appear in many locations throughout the site.';
$string['headingmissingvalue'] = 'Value to display if no data found';
$string['hidefeature'] = 'Hide';
$string['hierarchies'] = 'Hierarchies';
$string['home'] = 'Home';
$string['icon'] = 'Icon';
$string['inforesizecustomicons'] = 'Any file with width and height greater than 35x35 will be resized.';
$string['idnumberduplicates'] = 'Table: "{$a->table}". ID numbers: {$a->idnumbers}.';
$string['idnumberexists'] = 'Record with this ID number already exists';
$string['importtimezonesskipped'] = 'Skipped updating timezone information.';
$string['importtimezonessuccess'] = 'Timezone information updated from source {$a}.';
$string['inprogress'] = 'In Progress';
$string['installdemoquestion'] = 'Do you want to include demo data with this installation?<br /><br />(This will take a long time.)';
$string['installingdemodata'] = 'Installing Demo Data';
$string['invalidsearchtable'] = 'Invalid search table';
$string['itemstoadd'] = 'Items to add';
$string['lasterroroccuredat'] = 'Last error occured at {$a}';
$string['learningplans'] = 'Learning Plans';
$string['learningrecords'] = 'Learning Records';
$string['localpostinstfailed'] = 'There was a problem setting up local modifications to this installation.';
$string['managecertifications'] = 'Manage certifications';
$string['managecustomicons'] = 'Manage custom icons';
$string['managers'] = 'Manager\'s ';
$string['menuitem:addnew'] = 'Add new menu item';
$string['menuitem:delete'] = 'Are you sure you want to delete the "{$a}" item?';
$string['menuitem:deletechildren'] = 'All children of "{$a}" will be deleted:';
$string['menuitem:deletesuccess'] = 'The item was deleted successfully';
$string['menuitem:edit'] = 'Edit menu item';
$string['menuitem:editingx'] = 'Editing menu item "{$a}"';
$string['menuitem:formitemparent'] = 'Parent item';
$string['menuitem:formitemtargetattr'] = 'Open link in new window';
$string['menuitem:formitemtargetattr_help'] = 'If selected, clicking this menu item will open the page in a new browser window instead of the current window.';
$string['menuitem:formitemtitle'] = 'Menu title';
$string['menuitem:formitemtitle_help'] = 'The name of this menu item. This field supports the <a href="http://docs.moodle.org/20/en/Multi-language_content_filter">multi-language content filter</a>.';
$string['menuitem:formitemurl'] = 'Menu default url address';
$string['menuitem:formitemurl_help'] = 'Start the URL with a "/" to make the link relative to your site URL. Otherwise start the URL with http:// or https://, i.e. http://extdomain.com';
$string['menuitem:formitemvisibility'] = 'Visibility';
$string['menuitem:hide'] = 'Hide';
$string['menuitem:movesuccess'] = 'The item was moved successfully';
$string['menuitem:show'] = 'Show';
$string['menuitem:showwhenrequired'] = 'Show when required';
$string['menuitem:title'] = 'Item title';
$string['menuitem:updatesuccess'] = 'Main menu updated successfully';
$string['menuitem:url'] = 'Default url address';
$string['menuitem:visibility'] = 'Visibility';
$string['modulearchive'] = 'Activity archives';
$string['moodlecore'] = 'Moodle core';
$string['movedown'] = 'Move Down';
$string['moveup'] = 'Move Up';
$string['mssqlgroupconcatfail'] = 'Automatic update failed with reason "{$a}". Please, copy code from textarea below and execute it in MSSQL Server as Administrator. Afterwards refresh this page.';
$string['mybookings'] = 'My Bookings';
$string['mycoursecompletions'] = 'My Course Completions';
$string['mydevelopmentplans'] = 'My development plans';
$string['myfuturebookings'] = 'My Future Bookings';
$string['mylearning'] = 'My Learning';
$string['mypastbookings'] = 'My Past Bookings';
$string['myprofile'] = 'My Profile';
$string['myrecordoflearning'] = 'My Record of Learning';
$string['myreports'] = 'My Reports';
$string['myteam'] = 'My Team';
$string['myteaminstructionaltext'] = 'Choose a team member from the table on the right.';
$string['noassessors'] = 'No assessors found';
$string['none'] = 'None';
$string['noresultsfor'] = 'No results found for "{$a->query}".';
$string['nostaffassigned'] = 'You currently do not have a team.';
$string['notapplicable'] = 'Not applicable';
$string['notavailable'] = 'Not available';
$string['notenrolled'] = '<em>You are not currently enrolled in any courses.</em>';
$string['notfound'] = 'Not found';
$string['notimplementedtotara'] = 'Sorry, this feature is only implemented on MySQL, MSSQL and PostgreSQL databases.';
$string['numberofactiveusers'] = '{$a} users have logged in to this site in the last year';
$string['numberofstaff'] = '({$a} staff)';
$string['old_release_security_text_plural'] = ' (including [[SECURITY_COUNT]] new security releases)';
$string['old_release_security_text_singular'] = ' (including 1 new security release)';
$string['old_release_text_plural'] = 'You are not using the most recent release available for this version. There are [[ALLTYPES_COUNT]] new releases available ';
$string['old_release_text_singular'] = 'You are not using the most recent release available for this version. There is 1 new release available ';
$string['options'] = 'Options';
$string['organisation_typeicon'] = 'Organisation type icon';
$string['organisationatcompletion'] = 'Organisation at completion';
$string['organisationsarrow'] = 'Organisations > ';
$string['participant'] = 'Participant';
$string['pastbookingsfor'] = 'Past Bookings for ';
$string['performinglocalpostinst'] = 'Local Post-installation setup';
$string['pluginname'] = 'Totara core';
$string['pluginnamewithkey'] = 'Self enrolment with key';
$string['pos_description'] = 'Description';
$string['pos_description_help'] = 'Description of the position';
$string['position_typeicon'] = 'Position type icon';
$string['positionatcompletion'] = 'Position at completion';
$string['positionsarrow'] = 'Positions > ';
$string['poweredby'] = 'Powered by TotaraLMS';
$string['execpathnotallowed'] = 'This setting is currently disabled. To enable, add<br />$CFG->preventexecpath = 0;<br /> to config.php';
$string['proficiency'] = 'Proficiency';
$string['progdoesntbelongcat'] = 'The program doesn\'t belong to this category';
$string['programicon'] = 'Program icon';
$string['queryerror'] = 'Query error. No results found.';
$string['recordnotcreated'] = 'Record could not be created';
$string['recordnotupdated'] = 'Record could not be updated';
$string['recordoflearning'] = 'Record of Learning';
$string['recordoflearningfor'] = 'Record of Learning for ';
$string['remotetotaralangnotavailable'] = 'Because Totara can not connect to download.totaralms.com, we are unable to do language pack installation automatically. Please download the appropriate zip file(s) from http://download.totaralms.com/lang/T{$a->totaraversion}/, copy them to your {$a->langdir} directory and unzip them manually.';
$string['replaceareyousure'] = 'Are you sure you want to replace \'{$a->search}\' with \'{$a->replace}\'? (y/n)';
$string['replacedevdebuggingrequired'] = 'Error, you must have developer debugging enabled to run this script.';
$string['replacedonotrunlive'] = 'DO NOT RUN THIS ON A LIVE SITE.';
$string['replaceenterfindstring'] = 'Enter string to find:';
$string['replaceenternewstring'] = 'Enter new string:';
$string['replacemissingparam'] = 'Missing either Search or Replace parameters.';
$string['replacereallysure'] = 'Are you really sure? This will replace all instances of \'{$a->search}\' with \'{$a->replace}\' and may break your database! (y/n)';
$string['report'] = 'Report';
$string['reportedat'] = 'Reported at';
$string['requiresjs'] = 'This {$a} requires Javascript to be enabled.';
$string['returntocourse'] = 'Return to the course';
$string['roledefaults'] = 'Default role settings';
$string['roledefaultsnochanges'] = 'No role changes detected';
$string['save'] = 'Save';
$string['search'] = 'Search';
$string['searchcourses'] = 'Search Courses';
$string['searchx'] = 'Search {$a}';
$string['selectanassessor'] = 'Select an assessor...';
$string['selectaproficiency'] = 'Select a proficiency...';
$string['settings'] = 'Settings';
$string['showfeature'] = 'Show';
$string['sitemanager'] = 'Site Manager';
$string['siteregistrationemailbody'] = 'Site {$a} was not able to register itself automatically. Access to push data to our registrations site is probably blocked by a firewall.';
$string['staffmanager'] = 'Staff Manager';
$string['startdate'] = 'Start Date';
$string['started'] = 'Started';
$string['strftimedateshortmonth'] = '%d %b %Y';
$string['successuploadicon'] = 'Icon(s) successfully saved';
$string['supported_branch_old_release_text'] = 'You may also want to consider upgrading from {$a} to the most recent version ([[CURRENT_MAJOR_VERSION]]) to benefit from the latest features. ';
$string['supported_branch_text'] = 'You may want to consider upgrading from {$a} to the most recent version ([[CURRENT_MAJOR_VERSION]]) to benefit from the latest features. ';
$string['tab:futurebookings'] = 'Future Bookings';
$string['tab:pastbookings'] = 'Past Bookings';
$string['teammembers'] = 'Team Members';
$string['teammembers_text'] = 'All members of your team are shown below.';
$string['template'] = 'Template';
$string['tempmanager'] = 'Temporary manager';
$string['tempmanagerassignmsgmgr'] = '{$a->tempmanager} has been assigned as temporary manager to {$a->staffmember} (one of your team members).<br>Temporary manager expiry: {$a->expirytime}.<br>View details <a href="{$a->url}">here</a>.';
$string['tempmanagerassignmsgmgrsubject'] = '{$a->tempmanager} is now temporary manager for {$a->staffmember}';
$string['tempmanagerassignmsgstaff'] = '{$a->tempmanager} has been assigned as temporary manager to you.<br>Temporary manager expiry: {$a->expirytime}.<br>View details <a href="{$a->url}">here</a>.';
$string['tempmanagerassignmsgstaffsubject'] = '{$a->tempmanager} is now your temporary manager';
$string['tempmanagerassignmsgtmpmgr'] = 'You have been assigned as temporary manager to {$a->staffmember}.<br>Temporary manager expiry: {$a->expirytime}.<br>View details <a href="{$a->url}">here</a>.';
$string['tempmanagerassignmsgtmpmgrsubject'] = 'You are now {$a->staffmember}\'s temporary manager';
$string['tempmanagerexpiry'] = 'Temporary manager expiry date';
$string['tempmanagerexpiry_help'] = 'Click the calendar icon to select the date the temporary manager will expire.';
$string['tempmanagerexpirydays'] = 'Temporary manager expiry days';
$string['tempmanagerexpirydaysdesc'] = 'Set a default temporary manager expiry period (in days).';
$string['tempmanagerexpiryupdatemsgmgr'] = 'The expiry date for {$a->staffmember}\'s temporary manager ({$a->tempmanager}) has been updated to {$a->expirytime}.<br>View details <a href="{$a->url}">here</a>.';
$string['tempmanagerexpiryupdatemsgmgrsubject'] = 'Expiry date updated for {$a->staffmember}\'s temporary manager';
$string['tempmanagerexpiryupdatemsgstaff'] = 'The expiry date for {$a->tempmanager} (your temporary manager) has been updated to {$a->expirytime}.<br>View details <a href="{$a->url}">here</a>.';
$string['tempmanagerexpiryupdatemsgstaffsubject'] = 'Expiry date updated for your temporary manager';
$string['tempmanagerexpiryupdatemsgtmpmgr'] = 'Your expiry date as temporary manager for {$a->staffmember} has been updated to {$a->expirytime}.<br>View details <a href="{$a->url}">here</a>.';
$string['tempmanagerexpiryupdatemsgtmpmgrsubject'] = 'Temporary manager expiry updated for {$a->staffmember}';
$string['tempmanagerrestrictselection'] = 'Temporary manager selection';
$string['tempmanagerrestrictselectiondesc'] = 'Determine which users will be available in the temporary manager selection dialog. Selecting \'Only staff managers\' will remove any assigned temporary managers who don\'t have the \'staff manager\' role on the next cron run.';
$string['tempmanagers'] = 'Temporary managers';
$string['tempmanagerselectionallusers'] = 'All users';
$string['tempmanagerselectiononlymanagers'] = 'Only staff managers';
$string['tempmanagersupporttext'] = ' Note, only current team managers can be selected.';
$string['timecompleted'] = 'Time completed';
$string['toggletotarasync'] = 'Toggle Totara sync';
$string['toggletotarasyncerror'] = 'Could not enable/disable the totara sync field for user {$a}';
$string['toggletotarasyncerror:noidnumber'] = 'The ID Number field is empty and so totara sync cannot be enabled for these users: {$a}';
$string['totarabuild'] = 'Totara build number';
$string['totaracopyright'] = '<p class="totara-copyright"><a href="http://www.totaralms.com">TotaraLMS</a> is a distribution of Moodle. A "distro" or distribution is a ready-made extended version of the standard product with its own particular focus and vision. Totara is specifically designed for the requirements of corporate, industry and vocational training in contrast to standard Moodle\'s traditional educational setting.</p><p class="totara-copyright"><a href="http://www.totaralms.com">TotaraLMS</a> extensions are Copyright &copy; 2010 onwards, Totara Learning Solutions Limited.</p>';
$string['totaracore'] = 'Totara core';
$string['totarafeatures'] = 'Totara features';
$string['totaralogo'] = 'Totara Logo';
$string['totaramenu'] = 'Totara Menu';
$string['totaranavigation'] = 'Main menu';
$string['totararegistration'] = 'Totara Registration';
$string['totararegistrationinfo'] = '<p>This page configures registration updates which are sent to totaralms.com.
These updates allow Totara to know what versions of Totaralms and support software you are running.
This information will allow Totara to better examine and resolve any support issues you face in the future.</p>
<p>This information will be securely transmitted and held in confidence.</p>';
$string['totararelease'] = 'Totara release identifier';
$string['totarareleaselink'] = 'See the <a href="http://community.totaralms.com/mod/forum/view.php?id=1834" target=\"_blank\">release notes</a> for more details.';
$string['totararequiredupgradeversion'] = 'Totara 2.2.13';
$string['totarauniqueidnumbercheckfail'] = 'The following tables contain non-unique values in the column idnumber:<br/><br/>
{$a}
<br/>
Please fix these records before attempting the upgrade.';
$string['totaraunsupportedupgradepath'] = 'You cannot upgrade directly to {$a->attemptedversion} from {$a->currentversion}. Please upgrade to at least {$a->required} before attempting the upgrade to {$a->attemptedversion}.';
$string['totaraupgradecheckduplicateidnumbers'] = 'Check duplicate ID numbers';
$string['totaraupgradesetstandardtheme'] = 'Enable Standard Totara theme';
$string['totaraversion'] = 'Totara version number';
$string['trysearchinginstead'] = 'Try searching instead.';
$string['type'] = 'Type';
$string['typeicon'] = 'Type icon';
$string['unassignall'] = 'Unassign all';
$string['undelete'] = 'Undelete';
$string['undeletecheckfull'] = 'Are you sure you want to undelete {$a}?';
$string['undeletednotx'] = 'Could not undelete {$a} !';
$string['undeletedx'] = 'Undeleted {$a}';
$string['undeleteuser'] = 'Undelete User';
$string['undeleteusernoperm'] = 'You do not have the required permission to undelete a user';
$string['unexpected_installer_result'] = 'Unspecified component install error: {$a}';
$string['unsupported_branch_text'] = 'The version you are using ({$a})  is no longer supported. That means that bugs and security issues are no longer being fixed. You should upgrade to a supported version (such as [[CURRENT_MAJOR_VERSION]]) as soon as possible';
$string['uploadcompletionrecords'] = 'Upload completion records';
$string['userdoesnotexist'] = 'User does not exist';
$string['viewmyteam'] = 'View My Team';
$string['xofy'] = '{$a->count} / {$a->total}';
$string['xpercent'] = '{$a}%';
$string['xpercentcomplete'] = '{$a} % complete';
$string['xpositions'] = '{$a}\'s Positions';
$string['xresultsfory'] = '<strong>{$a->count}</strong> results found for "{$a->query}"';
