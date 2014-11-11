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

$string['abstractmethodcalled'] = 'Abstract method {$a} called - must be implemented';
$string['access'] = 'Access';
$string['accessbyrole'] = 'Restrict access by role';
$string['accesscontrols'] = 'Access Controls';
$string['activeonly'] = 'Active users only';
$string['activeuser'] = 'Active user';
$string['activities'] = 'Activities';
$string['activitygroupdesc'] = 'Activity groups let you define sets of activites for the purpose of site-wide reporting.';
$string['activitygroupingx'] = 'Activity grouping \'{$a}\'';
$string['activitygroupnotfound'] = 'The activity group could not be found';
$string['activitygroups'] = 'Activity groups';
$string['add'] = 'Add';
$string['addanothercolumn'] = 'Add another column...';
$string['addanotherfilter'] = 'Add another filter...';
$string['addanothersearchcolumn'] = 'Add another search column...';
$string['addbadges'] = 'Add badges';
$string['addcohorts'] = 'Add audiences';
$string['addedscheduledreport'] = 'Added new scheduled report';
$string['addscheduledreport'] = 'Add scheduled report';
$string['addnewscheduled'] = 'Add scheduled';
$string['advanced'] = 'Advanced?';
$string['advancedcolumnheading'] = 'Aggregation or grouping';
$string['advancedgroupaggregate'] = "Aggregations";
$string['advancedgrouptimedate'] = "Time and date (DB server time zone)";
$string['aggregatetypeavg_heading'] = 'Average of {$a}';
$string['aggregatetypeavg_name'] = 'Average';
$string['aggregatetypecountany_heading'] = 'Count of {$a}';
$string['aggregatetypecountany_name'] = 'Count';
$string['aggregatetypecountdistinct_heading'] = 'Count unique values of {$a}';
$string['aggregatetypecountdistinct_name'] = 'Count unique';
$string['aggregatetypegroupconcat_heading'] = '{$a}';
$string['aggregatetypegroupconcat_name'] = 'Comma separated values';
$string['aggregatetypegroupconcatdistinct_heading'] = '{$a}';
$string['aggregatetypegroupconcatdistinct_name'] = 'Comma separated values without duplicates';
$string['aggregatetypemaximum_heading'] = 'Maximum value from {$a}';
$string['aggregatetypemaximum_name'] = 'Maximum';
$string['aggregatetypeminimum_heading'] = 'Minimum value from {$a}';
$string['aggregatetypeminimum_name'] = 'Minimum';
$string['aggregatetypestddev_heading'] = 'Standard deviation of {$a}';
$string['aggregatetypestddev_name'] = 'Standard deviation';
$string['aggregatetypesum_name'] = 'Sum';
$string['aggregatetypesum_heading'] = 'Sum of {$a}';
$string['alldata'] = 'All data';
$string['allofthefollowing'] = 'All of the following';
$string['allreports'] = 'All Reports';
$string['allscheduledreports'] = 'All scheduled reports';
$string['and'] = ' and ';
$string['anycontext'] = 'Users may have role in any context';
$string['anyofthefollowing'] = 'Any of the following';
$string['ascending'] = 'Ascending (A to Z, 1 to 9)';
$string['assignedactivities'] = 'Assigned activities';
$string['at'] = 'at';
$string['audiencevisibility'] = 'Audience Visibility';
$string['backtoallgroups'] = 'Back to all groups';
$string['badcolumns'] = 'Invalid columns';
$string['badcolumnsdesc'] = 'The following columns have been included in this report, but do not exist in the report\'s source. This can occur if the source changes on disk after reports have been generated. To fix, either restore the previous source file, or delete the columns from this report.';
$string['baseactivity'] = 'Base activity';
$string['basedon'] = 'Group based on';
$string['baseitem'] = 'Base item';
$string['baseitemdesc'] = 'The aggregated data available to this group is based on the questions in the activity \'<a href="{$a->url}">{$a->activity}</a>\'.';
$string['both'] = 'Both';
$string['bydateenable'] = 'Show records based on the record date';
$string['bytrainerenable'] = 'Show records by trainer';
$string['byuserenable'] = 'Show records by user';
$string['cache'] = 'Enable Report Caching';
$string['cachegenfail'] = 'The last attempt to generate cache failed. Please try again later.';
$string['cachegenstarted'] = 'Cache generation started at {$a}. This process can take several minutes.';
$string['cachenow'] = 'Generate Now';
$string['cachenow_help'] = '
If **Generate now** is checked, then report cache will be generated immediately after form submit.';
$string['cachenow_title'] = 'Report cache';
$string['cachepending'] = '{$a} There are changes to this report\'s configuration that have not yet been applied. The report will be updated next time the report is generated.';
$string['cannotviewembedded'] = 'Embedded reports can only be accessed through their embedded url';
$string['category'] = 'Category';
$string['choosecatplural'] = 'Choose Categories';
$string['choosecomp'] = 'Choose Competency...';
$string['choosecompplural'] = 'Choose Competencies';
$string['chooseorg'] = 'Choose Organisation...';
$string['chooseorgplural'] = 'Choose Organisations';
$string['choosepos'] = 'Choose Position...';
$string['chooseposplural'] = 'Choose Positions';
$string['clearform'] = 'Clear';
$string['column'] = 'Column';
$string['column_deleted'] = 'Column deleted';
$string['column_moved'] = 'Column moved';
$string['column_vis_updated'] = 'Column visibility updated';
$string['columns'] = 'Columns';
$string['columns_updated'] = 'Columns updated';
$string['competency_evidence'] = 'Competency Evidence';
$string['completedorgenable'] = 'Show records completed in the user\'s organisation';
$string['configenablereportcaching'] = 'This will allow administrators to configure report caching';
$string['confirmcoldelete'] = 'Are you sure you want to delete this column?';
$string['confirmcolumndelete'] = 'Are you sure you want to delete this column?';
$string['confirmfilterdelete'] = 'Are you sure you want to delete this filter?';
$string['confirmsearchcolumndelete'] = 'Are you sure you want to delete this search column?';
$string['content'] = 'Content';
$string['contentclassnotexist'] = 'Content class {$a} does not exist';
$string['contentcontrols'] = 'Content Controls';
$string['context'] = 'Context';
$string['couldnotsortjoinlist'] = 'Could not sort join list. Source either contains circular dependencies or references a non-existent join';
$string['course_completion'] = 'Course Completion';
$string['courseenddate'] = 'End date';
$string['courseenrolavailable'] = 'Open enrolment';
$string['courseenroltype'] = 'Enrolment type';
$string['courseexpandlink'] = 'Course Name (expanding details)';
$string['coursecategory'] = 'Course Category';
$string['coursecategoryid'] = 'Course Category ID';
$string['coursecategorylinked'] = 'Course Category (linked to category)';
$string['coursecategorylinkedicon'] = 'Course Category (linked to category with icon)';
$string['coursecategorymultichoice'] = 'Course Category (multichoice)';
$string['coursecompletedon'] = 'Course completed on {$a}';
$string['courseenrolledincohort'] = 'Course is enrolled in by audience';
$string['courseicon'] = 'Course Icon';
$string['courseid'] = 'Course ID';
$string['courseidnumber'] = 'Course ID Number';
$string['courselanguage'] = 'Course language';
$string['coursename'] = 'Course Name';
$string['coursenameandsummary'] = 'Course Name and Summary';
$string['coursenamelinked'] = 'Course Name (linked to course page)';
$string['coursenamelinkedicon'] = 'Course Name (linked to course page with icon)';
$string['coursenotset'] = 'Course Not Set';
$string['courseprogress'] = 'Progress';
$string['courseshortname'] = 'Course Shortname';
$string['coursestartdate'] = 'Course Start Date';
$string['coursestatuscomplete'] = 'You have completed this course';
$string['coursestatusenrolled'] = 'You are currently enrolled in this course';
$string['coursestatusnotenrolled'] = 'You are not currently enrolled in this course';
$string['coursesummary'] = 'Course Summary';
$string['coursetypeicon'] = 'Type';
$string['coursetype'] = 'Course Type';
$string['coursevisible'] = 'Course Visible';
$string['createasavedsearch'] = 'Create a saved search';
$string['createreport'] = 'Create report';
$string['csvformat'] = 'CSV format';
$string['currentfinancial'] = 'The current financial year';
$string['currentorg'] = 'The user\'s current organisation';
$string['currentpos'] = 'The user\'s current position';
$string['currentorgenable'] = 'Show records from staff in the user\'s organisation';
$string['currentposenable'] = 'Show records from staff in the user\'s position';
$string['currentsearchparams'] = 'Settings to be saved';
$string['customiseheading'] = 'Customise heading';
$string['customisename'] = 'Customise Field Name';
$string['daily'] = 'Daily';
$string['data'] = 'Data';
$string['dateisbetween'] = 'is between today and ';
$string['datelabelisdaysafter'] = 'After today\'s date and before {$a->daysafter}';
$string['datelabelisdaysbefore'] = 'Before today\'s date and after {$a->daysbefore}.';
$string['datelabelisdaysbetween'] = '{$a->label} is after {$a->daysbefore} and before {$a->daysafter}';
$string['defaultsortcolumn'] = 'Default column';
$string['defaultsortorder'] = 'Default order';
$string['delete'] = 'Delete';
$string['deletecheckschedulereport'] = 'Are you sure you would like to delete the \'{$a}\' scheduled report?';
$string['deletedonly'] = 'Deleted users only';
$string['deletedscheduledreport'] = 'Successfully deleted Scheduled Report \'{$a}\'';
$string['deleteduser'] = 'Deleted user';
$string['deletereport'] = 'Report Deleted';
$string['deletescheduledreport'] = 'Delete scheduled report?';
$string['descending'] = 'Descending (Z to A, 9 to 1)';
$string['disabled'] = 'Disabled?';
$string['editingsavedsearch'] = 'Editing saved search';
$string['editreport'] = 'Edit Report \'{$a}\'';
$string['editscheduledreport'] = 'Edit Scheduled Report';
$string['editthisreport'] = 'Edit this report';
$string['embedded'] = 'Embedded';
$string['embeddedaccessnotes'] = '<strong>Warning:</strong> Embedded reports may have their own access restrictions applied to the page they are embedded into. They may ignore the settings below, or they may apply them as well as their own restrictions.';
$string['embeddedcontentnotes'] = '<strong>Warning:</strong> Embedded reports may have further content restrictions applied via <em>embedded parameters</em>. These can further limit the content that is shown in the report';
$string['embeddedreports'] = 'Embedded Reports';
$string['enablereportcaching'] = 'Enable report caching';
$string['enrol'] = 'Enrol';
$string['enrolledcoursecohortids'] = 'Enrolled course audience IDs';
$string['enrolledprogramcohortids'] = 'Enrolled program audience IDs';
$string['enrolusing'] = 'Enrol with - {$a}';
$string['error:addscheduledreport'] = 'Error adding new Scheduled Report';
$string['error:bad_sesskey'] = 'There was an error because the session key did not match';
$string['error:cachenotfound'] = 'Cannot purge cache. Seems it is already clean.';
$string['error:column_not_deleted'] = 'There was a problem deleting that column';
$string['error:column_not_moved'] = 'There was a problem moving that column';
$string['error:column_vis_not_updated'] = 'Column visibility could not be updated';
$string['error:columnextranameid'] = 'Column extra field \'{$a}\' alias must not be \'id\'';
$string['error:columnnameid'] = 'Field \'{$a}\' alias must not be \'id\'';
$string['error:columnoptiontypexandvalueynotfoundinz'] = 'Column option with type "{$a->type}" and value "{$a->value}" not found in source "{$a->source}"';
$string['error:columns_not_updated'] = 'There was a problem updating the columns.';
$string['error:couldnotcreatenewreport'] = 'Could not create new report';
$string['error:couldnotgenerateembeddedreport'] = 'There was a problem generating that report';
$string['error:couldnotsavesearch'] = 'Could not save search';
$string['error:couldnotupdateglobalsettings'] = 'There was an error while updating the global settings';
$string['error:couldnotupdatereport'] = 'Could not update report';
$string['error:creatingembeddedrecord'] = 'Error creating embedded record: {$a}';
$string['error:emptyexportfilesystempath'] = 'If you enabled export to file system, you need to specify file system path.';
$string['error:failedtoremovetempfile'] = 'Failed to remove temporary report export file';
$string['error:filter_not_deleted'] = 'There was a problem deleting that filter';
$string['error:filter_not_moved'] = 'There was a problem moving that filter';
$string['error:filteroptiontypexandvalueynotfoundinz'] = 'Filter option with type "{$a->type}" and value "{$a->value}" not found in source "{$a->source}"';
$string['error:filters_not_updated'] = 'There was a problem updating the filters';
$string['error:fusion_oauthnotsupported'] = 'Fusion export via OAuth is not currently supported.';
$string['error:grouphasreports'] = 'You cannot delete a group that is being used by reports.';
$string['error:groupnotcreated'] = 'Group could not be created';
$string['error:groupnotcreatedinitfail'] = 'Group could not be created - failed to initialize tables!';
$string['error:groupnotcreatedpreproc'] = 'Group could not be created - preprocessor not found!';
$string['error:groupnotdeleted'] = 'Group could not be deleted';
$string['error:invalidreportid'] = 'Invalid report ID';
$string['error:invalidreportscheduleid'] = 'Invalid scheduled report ID';
$string['error:invalidsavedsearchid'] = 'Invalid saved search ID';
$string['error:invaliduserid'] = 'Invalid user ID';
$string['error:joinsforfiltertypexandvalueynotfoundinz'] = 'Joins for filter with type "{$a->type}" and value "{$a->value}" not found in source "{$a->source}"';
$string['error:joinsfortypexandvalueynotfoundinz'] = 'Joins for columns with type "{$a->type}" and value "{$a->value}" not found in source "{$a->source}"';
$string['error:joinxhasdependencyyinz'] = 'Join name "{$a->join}" contains a dependency "{$a->dependency}" that does not exist in the joinlist for source "{$a->source}"';
$string['error:joinxisreservediny'] = 'Join name "{$a->join}" in source "{$a->source}" is an SQL reserved word. Please rename the join';
$string['error:joinxusedmorethanonceiny'] = 'Join name "{$a->join}" used more than once in source "{$a->source}"';
$string['error:missingdependencytable'] = 'In report source {$a->source}, missing dependency table in joinlist: {$a->join}!';
$string['error:mustselectsource'] = 'You must pick a source for the report';
$string['error:nocolumns'] = 'No columns found. Ask your developer to add column options to the \'{$a}\' source.';
$string['error:nocolumnsdefined'] = 'No columns have been defined for this report. Ask you site administrator to add some columns.';
$string['error:nocontentrestrictions'] = 'No content restrictions are available for this source. To use restrictions, ask your developer to add the necessary code to the \'{$a}\' source.';
$string['error:nopdf'] = 'No PDF plugin found';
$string['error:nopermissionsforscheduledreport'] = 'Scheduled Report Error: User {$a->userid} is not capable of viewing report {$a->reportid}.';
$string['error:norolesfound'] = 'No roles found';
$string['error:nosavedsearches'] = 'This report does not yet have any saved searches';
$string['error:nosources'] = 'No sources found. You must have at least one source before you can add reports. Ask your developer to add the necessary files to the codebase.';
$string['error:nosvg'] = 'SVG not supported';
$string['error:notapathexportfilesystempath'] = 'Specified file system path is not found.';
$string['error:notdirexportfilesystempath'] = 'Specified file system path does not exist or is not a directory.';
$string['error:notwriteableexportfilesystempath'] = 'Specified file system path is not writeable.';
$string['error:problemobtainingcachedreportdata'] = 'There was a problem obtaining the cached data for this report. It might be due to cache regeneration. Please, try again. If problem persist, disable cache for this report. <br /><br />{$a}';
$string['error:problemobtainingreportdata'] = 'There was a problem obtaining the data for this report: {$a}';
$string['error:processfile'] = 'Unable to create process file. Please, try later.';
$string['error:propertyxmustbesetiny'] = 'Property "{$a->property}" must be set in class "{$a->class}"';
$string['error:reportcacheinitialize'] = 'Cache is disabled for this report';
$string['error:reporturlnotset'] = 'The url property for report {$a} is missing, please ask your developers to check your code';
$string['error:savedsearchnotdeleted'] = 'Saved search could not be deleted';
$string['error:unknownbuttonclicked'] = 'Unknown button clicked';
$string['error:updatescheduledreport'] = 'Error updating Scheduled Report';
$string['excludetags'] = 'Exclude records tagged with';
$string['export'] = 'Export';
$string['exportcsv'] = 'Export in CSV format';
$string['exportfilesystemoptions'] = 'Export options';
$string['exportfilesystempath'] = 'File export path';
$string['exportfilesystempath_help'] = 'Absolute file system path to a writeable directory where reports can be exported and stored.

**Warning!** Make sure to configure a correct system path if you are going to export reports to file system.';
$string['exportfusion'] = 'Export to Google Fusion';
$string['exportods'] = 'Export in ODS format';
$string['exportoptions'] = 'Format export options';
$string['exportpdf_landscape'] = 'Export in PDF (Landscape) format';
$string['exportpdf_mramlimitexceeded'] = 'Notice: Ram memory limit exceeded! Probably the report being exported is too big, as it took almost {$a} MB of ram memory to create it, please consider reducing the size of the report, applying filters or splitting the report in several files.';
$string['exportpdf_portrait'] = 'Export in PDF (Portrait) format';
$string['exportproblem'] = 'There was a problem downloading the file';
$string['exporttoemail'] = 'Email scheduled report';
$string['exporttoemailandsave'] = 'Email and save scheduled report to file';
$string['exporttofilesystem'] = 'Export to file system';
$string['exporttofilesystemenable'] = 'Enable exporting to file system';
$string['exporttosave'] = 'Save scheduled report to file system only';
$string['exportxls'] = 'Export in Excel format';
$string['extrasqlshouldusenamedparams'] = 'get_sql_filter() extra sql should use named parameters';
$string['eventreportcreated'] = 'Report created';
$string['eventreportdeleted'] = 'Report deleted';
$string['eventreportexported'] = 'Report exported';
$string['eventreportupdated'] = 'Report updated';
$string['eventreportviewed'] = 'Report viewed';
$string['filter'] = 'Filter';
$string['filterby'] = 'Filter by';
$string['filterdeleted'] = 'Filter deleted';
$string['filtermoved'] = 'Filter moved';
$string['filternameformatincorrect'] = 'get_filter_joins(): filter name format incorrect. Query snippets may have included a dash character.';
$string['filters'] = 'Filters';
$string['filters_updated'] = 'Filters updated';
$string['financialyear'] = 'Financial year start';
$string['format'] = 'Format';
$string['globalsettings'] = 'General settings';
$string['globalsettingsupdated'] = 'Global settings updated';
$string['gotofacetofacesettings'] = 'To view this report go to a facetoface activity and use the \'Declared interest report\' link in the \'Facetoface administration\' admin menu.';
$string['gradeandgradetocomplete'] = '{$a->grade}% ({$a->pass}% to complete)';
$string['graph'] = 'Graph';
$string['graphcategory'] = 'Category';
$string['graphlegend'] = 'Legend';
$string['graphmaxrecords'] = 'Maximum number of used records';
$string['graphnocategory'] = 'Numbered';
$string['graphorientation'] = 'Orientation';
$string['graphorientation_help'] = 'Determines how the report data is interpreted to build the graph. If "Data series in columns" is selected, then report builder will treat report columns as data series. In most cases this is what you want. If "Data series in rows" is selected, report builder treats every item in the column as a separate data series - data rows will be treated as data points. Typically you only want to select "Data series in rows" if you have more columns in your report than rows.';
$string['graphorientationcolumn'] = 'Data series in columns';
$string['graphorientationrow'] = 'Data series in rows';
$string['graphseries'] = 'Data sources';
$string['graphsettings'] = 'Custom settings';
$string['graphsettings_help'] = 'Advanced SGVGraph settings in PHP ini file format. See <a href="http://www.goat1000.com/svggraph-settings.php" target="_blank">http://www.goat1000.com/svggraph-settings.php<a/> for more information.';
$string['graphstacked'] = 'Stacked';
$string['graphtype'] = 'Graph type';
$string['graphtypearea'] = 'Area';
$string['graphtypebar'] = 'Horizontal bar';
$string['graphtypecolumn'] = 'Column';
$string['graphtypeline'] = 'Line';
$string['graphtypepie'] = 'Pie';
$string['graphtypescatter'] = 'Scatter';
$string['graph_updated'] = 'Graph updated';
$string['groupconfirmdelete'] = 'Are you sure you want to delete this group?';
$string['groupcontents'] = 'This group currently contains {$a->count} feedback activities tagged with the <strong>\'{$a->tag}\'</strong> official tag:';
$string['groupdeleted'] = 'Group deleted.';
$string['groupingfuncnotinfieldoftypeandvalue'] = 'Grouping function \'{$a->groupfunc}\' doesn\'t exist in field of type \'{$a->type}\' and value \'{$a->$value}\'';
$string['groupname'] = 'Group name';
$string['grouptag'] = 'Group tag';
$string['heading'] = 'Heading';
$string['help:columnsdesc'] = 'The choices below determine which columns appear in the report and how those columns are labelled.';
$string['help:restrictionoptions'] = 'The checkboxes below determine who has access to this report, and which records they are able to view. If no options are checked no results are visible. Click the help icon for more information';
$string['hidden'] = 'Hide in My Reports';
$string['hide'] = 'Hide';
$string['hierarchyfiltermusthavetype'] = 'Hierarchy filter of type "{$a->type}" and value "{$a->value}" must have "hierarchytype" set in source "{$a->source}"';
$string['includechildorgs'] = 'Include records from child organisations';
$string['includechildpos'] = 'Include records from child positions';
$string['includeemptydates'] = 'Include record if date is missing';
$string['includerecordsfrom'] = 'Include records from';
$string['includetags'] = 'Include records tagged with';
$string['includetrainerrecords'] = 'Include records from particular trainers';
$string['includeuserrecords'] = 'Include records from particular users';
$string['initialdisplay'] = 'Restrict Initial Display';
$string['initialdisplay_disabled'] = 'This setting is not available when there are no filters enabled';
$string['initialdisplay_error'] = 'The last filter can not be deleted when initial display is restricted';
$string['initialdisplay_heading'] = 'Filters Performance Settings';
$string['initialdisplay_help'] = 'This setting controls how the report is initially displayed and is recommended for larger reports where you will be filtering the results (e.g. sitelogs). It increases the speed of the report by allowing you to apply filters and display only the results instead of initially trying to display *all* the data.

**Disabled**: the report will display all results immediately *(default)*

**Enabled**: the report will not generate results until a filter is applied or an empty search is run.';
$string['initialdisplay_pending'] = 'Please apply a filter to view the results of this report, or hit search without adding any filters to view all entries';
$string['is'] = 'is';
$string['isaftertoday'] = 'days after today';
$string['isbeforetoday'] = 'days before today';
$string['isrelativetotoday'] = ' (date of report generation)';
$string['isbelow'] = 'is below';
$string['isnotfound'] = ' is NOT FOUND';
$string['isnt'] = 'isn\'t';
$string['isnttaggedwith'] = 'isn\'t tagged with';
$string['istaggedwith'] = 'is tagged with';
$string['joinnotinjoinlist'] = '\'{$a->join}\' not in join list for {$a->usage}';
$string['last30days'] = 'The last 30 days';
$string['lastcached'] = 'Last cached at {$a}';
$string['lastchecked'] = 'Last process date';
$string['lastfinancial'] = 'The previous financial year';
$string['manageactivitygroups'] = 'Manage activity groups';
$string['managereports'] = 'Manage reports';
$string['managername'] = 'Manager\'s Name';
$string['managesavedsearches'] = 'Manage searches';
$string['missingsearchname'] = 'Missing search name';
$string['monthly'] = 'Monthly';
$string['movedown'] = 'Move Down';
$string['moveup'] = 'Move Up';
$string['myreports'] = 'My Reports';
$string['name'] = 'Name';
$string['newgroup'] = 'Create a new activity group';
$string['newreport'] = 'New Report';
$string['newreportcreated'] = 'New report created. Click settings to edit filters and columns';
$string['next30days'] = 'The next 30 days';
$string['nice_date_in_timezone_format'] = '%d %B %Y';
$string['nice_time_in_timezone_format'] = '%I:%M %p';
$string['nice_time_unknown_timezone'] = 'Unknown Timezone';
$string['nocolumnsyet'] = 'No columns have been created yet - add them by selecting a column name in the pulldown below.';
$string['nocontentrestriction'] = 'Show all records';
$string['nodeletereport'] = 'Report could not be deleted';
$string['noembeddedreports'] = 'There are no embedded reports. Embedded reports are reports that are hard-coded directly into a page. Typically they will be set up by your site developer.';
$string['noemptycols'] = 'You must include a column heading';
$string['nofilteraskdeveloper'] = 'No filters found. Ask your developer to add filter options to the \'{$a}\' source.';
$string['nofilteroptions'] = 'This filter has no options to select';
$string['nofiltersetfortypewithvalue'] = 'get_field(): no filter set in filteroptions for type\'{$a->type}\' with value \'{$a->value}\'';
$string['nofiltersyet'] = 'No search fields have been created yet - add them by selecting a search term in the pulldown below.';
$string['nogroups'] = 'There are currently no activity groups';
$string['noheadingcolumnsdefined'] = 'No heading columns defined';
$string['noneselected'] = 'None selected';
$string['nopermission'] = 'You do not have permission to view this page';
$string['norecordsinreport'] = 'There are no records in this report';
$string['norecordswithfilter'] = 'There are no records that match your selected criteria';
$string['noreloadreport'] = 'Report settings could not be reset';
$string['norepeatcols'] = 'You cannot include the same column more than once';
$string['norepeatfilters'] = 'You cannot include the same filter more than once';
$string['noreports'] = 'No reports have been created. You can create a report using the form below.';
$string['noreportscount'] = 'No reports using this group';
$string['norestriction'] = 'All users can view this report';
$string['norestrictionsfound'] = 'No restrictions found. Ask your developer to add restrictions to /totara/reportbuilder/sources/{$a}/restrictionoptions.php';
$string['noscheduledreports'] = 'There are no scheduled reports';
$string['nosearchcolumnsaskdeveloper'] = 'No search columns found. Ask your developer to define text and long text fields as searchable in the \'{$a}\' source.';
$string['nosearchcolumnsyet'] = 'No search columns have been added yet - add them by selecting a column in the pulldown below.';
$string['noshortnameorid'] = 'Invalid report id or shortname';
$string['notags'] = 'No official tags exist. You must create one or more official tags to base your groups on.';
$string['notcached'] = 'Not cached yet';
$string['notspecified'] = 'Not specified';
$string['notyetchecked'] = 'Not yet processed';
$string['nouserreports'] = 'You do not have any reports. Report access is configured by your site administrator. If you are expecting to see a report, ask them to check the access permissions on the report.';
$string['numresponses'] = '{$a} response(s).';
$string['occurredafter'] = 'occurred after';
$string['occurredbefore'] = 'occurred before';
$string['occurredprevfinancialyear'] = 'occurred in the previous financial year';
$string['occurredthisfinancialyear'] = 'occurred in this finanicial year';
$string['odsformat'] = 'ODS format';
$string['on'] = 'on';
$string['onlydisplayrecordsfor'] = 'Only display records for';
$string['onthe'] = 'on the';
$string['options'] = 'Options';
$string['or'] = ' or ';
$string['organisationtype'] = 'User\'s Organisation Type';
$string['organisationtypeid'] = 'User\'s Organisation Type ID';
$string['orsuborg'] = '(or a sub organisation)';
$string['orsubpos'] = '(or a sub position)';
$string['participantscurrentorg'] = 'Participant\'s Current Organisation';
$string['participantscurrentorgbasic'] = 'Participant\'s Current Organisation (basic)';
$string['participantscurrentpos'] = 'Participant\'s Current Position';
$string['participantscurrentposbasic'] = 'Participant\'s Current Position (basic)';
$string['pdf_landscapeformat'] = 'pdf format (landscape)';
$string['pdf_portraitformat'] = 'pdf format (portrait)';
$string['performance'] = 'Performance';
$string['pluginadministration'] = 'Report Builder administration';
$string['pluginname'] = 'Report Builder';
$string['posenddate'] = 'User\'s Position End Date';
$string['positiontype'] = 'User\'s Position Type';
$string['positiontypeid'] = 'User\'s Position Type ID';
$string['posstartdate'] = 'User\'s Position Start Date';
$string['programenrolledincohort'] = 'Program is enrolled in by audience';
$string['publicallyavailable'] = 'Let other users view';
$string['publicsearch'] = 'Is search public?';
$string['records'] = 'Records';
$string['recordsperpage'] = 'Number of records per page';
$string['refreshdataforthisgroup'] = 'Refresh data for this group';
$string['reloadreport'] = 'Report settings have been reset';
$string['report'] = 'Report';
$string['report:cachelast'] = 'Report data last updated: {$a}';
$string['report:cachenext'] = 'Next update due: {$a}';
$string['report:completiondate'] = 'Completion date';
$string['report:coursetitle'] = 'Course title';
$string['report:enddate'] = 'End date';
$string['report:learner'] = 'Learner';
$string['report:learningrecords'] = 'Learning records';
$string['report:nodata'] = 'There is no available data for that combination of criteria, start date and end date';
$string['report:organisation'] = 'Office';
$string['report:startdate'] = 'Start date';
$string['reportbuilder'] = 'Report builder';
$string['reportbuilder:managereports'] = 'Create, edit and delete report builder reports';
$string['reportbuilderaccessmode'] = 'Access Mode';
$string['reportbuilderaccessmode_help'] = '
Access controls are used to restrict which users can view the report.

**Restrict access** sets the overall access setting for the report.

When set to **All users can view this report** there are no restrictions applied to the report and all users will be able to view the report.

When set to **Only certain users can view this report** the report will be restricted to the user groups selected below.

**Note:** access restrictions only control who can view the report, not which records it contains. See the \'Content\' tab for controlling the report contents.';
$string['reportbuilderbaseitem'] = 'Report Builder: Base item';
$string['reportbuilderbaseitem_help'] = '
By grouping a set of activities you are saying that they have something in common, which will allow reports to be generated for all the activities in a group. The base item defines the properties that are considered when aggregation is performed on each member of the group.';
$string['reportbuildercache'] = 'Enable report caching';
$string['reportbuildercache_disabled'] = 'This setting is not available for this report source';
$string['reportbuildercache_heading'] = 'Caching Performance Settings';
$string['reportbuildercache_help'] = '
If **Enable report caching** is checked, then a copy of this report will be generated on a set schedule, and users will see data from the stored report. This will make displaying and filtering of the report faster, but the data displayed will be from the last time the report was generated rather than "live" data. We recommend enabling this setting only if necessary (reports are taking too long to be displayed), and only for specific reports where this is a problem.';
$string['reportbuildercachescheduler'] = 'Cache Schedule (Server Time)';
$string['reportbuildercachescheduler_help'] = 'Determines the schedule used to control how often a new version of the report is generated. The report will be generated on the cron that immediately follows the specified time.

For example, if you have set up your cron to run every 20 minutes at 10, 30 and 50 minutes past the hour and you schedule a report to run at midnight, it will actually run at 10 minutes past midnight.';
$string['reportbuildercacheservertime'] = 'Current Server Time';
$string['reportbuildercacheservertime_help'] = 'All reports are being cached based on server time. Cache status shows you current local time which might be different from server time. Make sure to take into account your server time when scheduling cache.';
$string['reportbuildercolumns'] = 'Columns';
$string['reportbuildercolumns_help'] = '
**Report Columns** allows you to customise the columns that appear on your report. The available columns are determined by the data **Source** of the report. Each report source has a set of default columns set up.

Columns can be added, removed, renamed and sorted.

**Adding Columns:** To add a new column to the report choose the required column from the \'Add another column...\' dropdown list and click **Save changes**. The new column will be added to the end of the list.

**Note:** You can only create one column of each type within a single report. You will receive a validation error if you try to include the same column more than once.

**Hiding columns:** By default all columns appear when a user views the report. Use the \'show/hide\' button (the eye icon) to hide columns you do not want users to see by default.

**Note:** A hidden column is still available to a user viewing the report. Delete columns (the cross icon) that you do not want users to see at all.

**Moving columns:** The columns will appear on the report in the order they are listed. Use the up and down arrows to change the order.

**Deleting columns:** Click the \'Delete\' button (the cross icon) to the right of the report column to remove that column from the report.

**Renaming columns:** You can customise the name of a column by changing the **Heading** name and clicking **Save changes**. The **Heading** is the name that will appear on the report.

**Changing multiple column types:** You can modify multiple column types at the same time by selecting a different column from the dropdown menu and clicking **Save changes**.';
$string['reportbuildercompletedorg'] = 'Show by Completed Organisation';
$string['reportbuildercompletedorg_help'] = '
When **Show records completed in the user\'s organisation** is selected the report displays different completed records depending on the organisation the user has been assigned to. (A user is assigned an organisation in their \'User Profile\' on the \'Positions\' tab).

When **Include records from child organisations** is set to:

*   **Yes** the user viewing the report will be able to view completed records related to their organisation and any child organisations of that organisation
*   **No** the user can only view completed records related to their organisation.';
$string['reportbuildercontentmode'] = 'Content Mode';
$string['reportbuildercontentmode_help'] = '
Content controls allow you to restrict the records and information that are available when a report is viewed.

**Report content** allows you to select the overall content control settings for this report:

When **Show all records** is selected, every available record for this source will be shown and no restrictions will be placed on the content available.

When **Show records matching any of the checked criteria** is selected the report will display records that match any of the criteria set below.

**Note:** If no criteria is set the report will display no records.

When **Show records matching all of the checked criteria** is selected the report will display records that match all the criteria set below.
**Note:** If no criteria is set the report will display no records.';
$string['reportbuildercontext'] = 'Restrict Access by Role';
$string['reportbuildercontext_help'] = '
Context is the location or level within the system that the user has access to. For example a Site Administrator would have System level access (context), while a learner may only have Course level access (context).

**Context** allows you to set the context in which a user has been assigned a role to view the report.

A user can be assigned a role at the system level giving them site wide access or just within a particular context. For instance a trainer may only be assigned the role at the course level.

When **Users must have role in the system context** is selected the user must be assigned the role at a system level (i.e. at a site-wide level) to be able to view the report.

When **User may have role in any context** is selected a user can view the report when they have been assigned the selected role anywhere in the system.';
$string['reportbuildercurrentorg'] = 'Show by Current Organisation';
$string['reportbuildercurrentorg_help'] = '
When **Show records from staff in the user\'s organisation** is selected the report displays different results depending on the organisation the user has been assigned to. (A user is assigned an organisation in their \'User Profile\' on the \'Positions\' tab).

When **Include records from child organisations** is set to:

*   **Yes** the user viewing the report will be able to view records related to their organisation and any child organisations of that organisation
*   **No** the user can only view records related to their organisation.';
$string['reportbuildercurrentpos'] = 'Show by Current Position';
$string['reportbuildercurrentpos_help'] = '
When **Show records from staff in the user\'s position** is selected the report will display different records depending on their assigned position (A user is assigned a position in their \'User Profile\' on the \'Positions\' tab).

When **Include records from child positions** is set to:

*   **Yes** the user viewing the report can view records related to their positions and any child positions related to their positions
*   **No** the user viewing the report can only view records related to their position.';
$string['reportbuilderdate'] = 'Show by date';
$string['reportbuilderdate_help'] = '
When **Show records based on the record date** is selected the report only displays records within the selected timeframe.

The **Include records from** options allow you to set the timeframe for the report:

*   When set to **The past** the report only shows records with a date older than the current date.
*   When set to **The future** the report only shows records with a future date set from the current date.
*   When set to **The last 30 days** the report only shows records between the current time and 30 days before.
*   When set to **The next 30 days** the report only shows records between the current time and 30 days into the future.';
$string['reportbuilderdescription'] = 'Description';
$string['reportbuilderdescription_help'] = 'When a report description is created the information displays in a box above the search filters on the report page.';
$string['reportbuilderdialogfilter'] = 'Report Builder: Dialog filter';
$string['reportbuilderdialogfilter_help'] = '
This filter allows you to filter information based on a hierarchy. The filter has the following options:

*   is any value - this option disables the filter (i.e. all information is accepted by this filter)
*   is equal to - this option allows only information that is equal to the value selected from the list
*   is not equal to - this option allows only information that is different from the value selected from the list

Once a framework item has been selected you can use the \'Include children?\' checkbox to choose whether to match only that item, or match that item and any sub-items belonging to that item.';
$string['reportbuilderexportoptions'] = 'Report Export Settings';
$string['reportbuilderexportoptions_help'] = 'Report export settings allows a user with the appropriate permissions to specify the export options that are available for users at the bottom of a report page. This setting affects all Report builder reports.

When multiple options are selected the user can choose their preferred options from the export dropdown menu.

When no options are selected the export function is disabled.';
$string['reportbuilderexporttofilesystem'] = 'Enable exporting to file system';
$string['reportbuilderexporttofilesystem_help'] = 'Exporting to file system allows reports to be saved to a directory on the web server\'s file system, instead of only emailing the report to the user scheduling the report.

This can be useful when the report needs to be accessed by an external system automation, and the report directory might have SFTP access enabled.

Reports saved to the filesystem are saved as **\'Export file system root path\'**/username/report.ext where *username* is an internal username of a user who owns the scheduled report, *report* is the name of the scheduled report with non alpha-numeric characters removed, and *ext* is the appropriate export file name extension.';
$string['reportbuilderfilters'] = 'Search Options (Filters)';
$string['reportbuilderfilters_help'] = '
**Search Options** allows you to customise the filters that appear on your report. The available filters are determined by the **Source** of the report. Each report source has a set of default filters.

Filters can be added, sorted and removed.

**Adding filters:** To add a new filter to the report choose the required filter from the \'Add another filter...\' dropdown menu and click **Save changes**. When **Advanced** is checked the filter will not appear in the \'Search by\' box by default, you can click **Show advanced** when viewing a report to see these filters.

**Moving filters:** The filters will appear in the \'Search by\' box in the order they are listed. Use the up and down arrows to change the order.

**Deleting filters:** Click the **Delete** button (the cross icon) to the right of the report filter to remove that filter from the report.

**Changing multiple filter types:** You can modify multiple filter types at the same time by selecting a different filter from the dropdown menu and clicking **Save changes**.';
$string['reportbuilderfinancialyear'] = 'Report Financial Year Settings';
$string['reportbuilderfinancialyear_help'] = 'This setting allows to set the start date of the financial year which is used in the reports content controls.';
$string['reportbuilderfullname'] = 'Report Name';
$string['reportbuilderfullname_help'] = 'This is the name that will appear at the top of your report page and in the \'Report Manager\' block.';
$string['reportbuilderglobalsettings'] = 'Report Builder Global Settings';
$string['reportbuildergroupname'] = 'Report Builder: Group Name';
$string['reportbuildergroupname_help'] = '
The name of the group. This will allow you to identify the group when you want to create a new report based on it. Look for the name in the report source pulldown menu.';
$string['reportbuildergrouptag'] = 'Report Builder: Group Tag';
$string['reportbuildergrouptag_help'] = '
When you create a group using a tag, any activities that are tagged with the official tag specified automatically form part of the group. If you add or remove tags from an activity, the group will be updated to include/exclude that activity.';
$string['reportbuilderhidden'] = 'Hide in My Reports';
$string['reportbuilderhidden_help'] = '
When **Hide in My Reports** is checked the report will not appear on the \'My Reports\' page for any logged in users.

**Note:** The **Hide in My Reports** option only hides the link to the report. Users with the correct access permissions may still access the report using the URL.';
$string['reportbuilderinitcache'] = 'Cache Status (User Time)';
$string['reportbuilderrecordsperpage'] = 'Number of Records per Page';
$string['reportbuilderrecordsperpage_help'] = '
**Number of records per page** allows you define how many records display on a report page.

The maximum number of records that can be displayed on a page is 9999. The more records set to display on a page the longer the report pages take to display.

Recommendation is to **limit the number of records per page to 40**.';
$string['reportbuilderrolesaccess'] = 'Roles with Access';
$string['reportbuilderrolesaccess_help'] = '
When **Restrict access** is set to **Only certain users can view this report** you can specify which roles can view the report using **Roles with permission to view the report**.

You can select one or multiple roles from the list.

When **Restrict access** is set to **All users can view this report** these options will be disabled.';
$string['reportbuildershortname'] = 'Report Builder: Unique name';
$string['reportbuildershortname_help'] = '
The shortname is used by moodle to keep track of this report. No two reports can be given the same shortname, even if they are based on the same source. Avoid using special characters in this field (text, numbers and underscores are okay).';
$string['reportbuildersorting'] = 'Sorting';
$string['reportbuildersorting_help'] = '
**Sorting** allows you to set a default column and sort order on a report.

A user is still able to manually sort a report while viewing it. The users preferences will be saved during the active session. When they finish the session the report will return to the default sort settings set here.';
$string['reportbuildersource'] = 'Source';
$string['reportbuildersource_help'] = '
The **Source** of a report defines the primary type of data used. Further filtering options are available once you start editing the report.

Once saved, the report source cannot be changed.

**Note:** If no options are available in the **Source** field, or the source you require does not appear you will need your Totara installation to be configured to include the source data you require (This cannot be done via the Totara interface).';
$string['reportbuildertag'] = 'Report Builder: Show by tag';
$string['reportbuildertag_help'] = '
This criteria is enabled by selecting the \'Show records by tag\' checkbox. If selected, the report will show results based on whether the record belongs to an item that is tagged with particular tags.

If any tags in the \'Include records tagged with\' section are selected, only records belonging to an item tagged with all the selected tags will be shown. Records belonging to items with no tags will **not** be shown.

If any tags in the \'Exclude records tagged with\' section are selected, records belonging to a coures tagged with the selected tags will **not** be shown. All records belonging to items without any tags will be shown.

It is possible to include and exclude tags at the same time, but a single tag cannot be both included and excluded.';
$string['reportbuildertrainer'] = 'Report Builder: Show by trainer';
$string['reportbuildertrainer_help'] = '
This criteria is enabled by selecting the \'Show records by trainer\' checkbox. If selected, then the report will show different records depending on who the face-to-face trainer was for the feedback being given.

If \'Show records where the user is the trainer\' is selected, the report will show feedback for sessions where the user viewing the report was the trainer.

If \'Records where one of the user\'s direct reports is the trainer\' is selected, then the report will show records for sessions trained by staff of the person viewing the report.

If \'Both\' is selected, then both of the above records will be shown.';
$string['reportbuilderuser'] = 'Show by User';
$string['reportbuilderuser_help'] = '
When **Show records by user** is selected the report will show different records depending on the user viewing the report and their relationship to other users.

**Include records from a particular user** controls what records a user viewing the report can see:

*   When **A user\'s own records** is checked the user can see their own records.
*   When **Records for user\'s direct reports** is checked the user can see the records belonging to any user who reports to them (A user is assigned a manager in their user profile on the \'Positions tab\').
*   When **Records for user\'s indirect reports** is checked the user can see the records belonging to any user who reports any user below them in the management hierarchy, excluding their direct reports.

If multiple options are selected the user sees records that match any of the selected options.';
$string['reportcachingdisabled'] = 'Report caching is disabled. You can enable it <a href="{$a}">here</a>';
$string['reportcolumns'] = 'Report Columns';
$string['reportconfirmdelete'] = 'Are you sure you want to delete this report?';
$string['reportconfirmreload'] = 'This is an embedded report so you cannot delete it (that must be done by your site developer). You can choose to reset the report settings to their original values. Do you want to continue?';
$string['reportcontents'] = 'This report contains records matching the following criteria:';
$string['reportcount'] = '{$a} report(s) based on this group:';
$string['reportmustbedefined'] = 'Report must be defined';
$string['reportname'] = 'Report Name';
$string['reportperformance'] = 'Performance settings';
$string['reports'] = 'Reports';
$string['reportsdirectlyto'] = 'reports directly to';
$string['reportsindirectlyto'] = 'reports indirectly to';
$string['reportsettings'] = 'Report Settings';
$string['reportshortname'] = 'Short Name';
$string['reportshortnamemustbedefined'] = 'Report shortname must be defined';
$string['reporttitle'] = 'Report Title';
$string['reporttype'] = 'Report type';
$string['reportupdated'] = 'Report Updated';
$string['reportwithidnotfound'] = 'Report with id of \'{$a}\' not found in database.';
$string['restoredefaults'] = 'Restore Default Settings';
$string['restrictaccess'] = 'Restrict access';
$string['restrictcontent'] = 'Report content';
$string['restriction'] = 'Restriction';
$string['restrictionswarning'] = '<strong>Warning:</strong> If none of these boxes are checked, all users will be able to view all available records from this source.';
$string['resultsfromfeedback'] = 'Results from <strong>{$a}</strong> completed feedback(s).';
$string['roleswithaccess'] = 'Roles with permission to view this report';
$string['savedsearch'] = 'Saved Search';
$string['savedsearchconfirmdelete'] = 'Are you sure you want to delete this saved search  \'{$a}\'?';
$string['savedsearchdeleted'] = 'Saved search deleted';
$string['savedsearchdesc'] = 'By giving this search a name you will be able to easily access it later or save it to your bookmarks.';
$string['savedsearches'] = 'Saved Searches';
$string['savedsearchinscheduleddelete'] = 'This saved search is currently being used in the following scheduled reports: <br/> {$a} <br/> Deleting this saved search will delete these scheduled reports.';
$string['savedsearchmessage'] = 'Only the data matching the \'{$a}\' search is included.';
$string['savedsearchnotfoundornotpublic'] = 'Saved search not found or search is not public';
$string['savesearch'] = 'Save this search';
$string['saving'] = 'Saving...';
$string['schedule'] = 'Schedule';
$string['scheduledaily'] = 'Daily';
$string['scheduledreportmessage'] = 'Attached is a copy of the \'{$a->reportname}\' report in {$a->exporttype}. {$a->savedtext}

You can also view this report online at:

{$a->reporturl}

You are scheduled to receive this report {$a->schedule}.
To delete or update your scheduled report settings, visit:

{$a->scheduledreportsindex}';
$string['scheduledreports'] = 'Scheduled Reports';
$string['scheduledreportsettings'] = 'Scheduled report settings';
$string['schedulemonthly'] = 'Monthly';
$string['scheduleneedssavedfilters'] = 'This report cannot be scheduled without a saved search.
To view the report, click <a href="{$a}">here</a>';
$string['schedulenotset'] = 'Schedule not set';
$string['scheduleweekly'] = 'Weekly';
$string['search'] = 'Search';
$string['searchby'] = 'Search by';
$string['searchcolumndeleted']=  'Search column deleted';
$string['searchfield'] = 'Search Field';
$string['searchname'] = 'Search Name';
$string['searchoptions'] = 'Report Search Options';
$string['selectitem'] = 'Select item';
$string['selectsource'] = 'Select a source...';
$string['settings'] = 'Settings';
$string['shortnametaken'] = 'That shortname is already in use';
$string['show'] = 'Show';
$string['showbasedonx'] = 'Show records based on {$a}';
$string['showbycompletedorg'] = 'Show by completed organisation';
$string['showbycurrentorg'] = 'Show by current organisation';
$string['showbycurrentpos'] = 'Show by current position';
$string['showbydate'] = 'Show by date';
$string['showbytag'] = 'Show by tag';
$string['showbytrainer'] = 'Show by trainer';
$string['showbyuser'] = 'Show by user';
$string['showbyx'] = 'Show by {$a}';
$string['showhidecolumns'] = 'Show/Hide Columns';
$string['showing'] = 'Showing';
$string['showrecordsbeloworgonly'] = 'Just staff below the user\'s organisation';
$string['showrecordsbelowposonly'] = 'Just staff below the user\'s position';
$string['showrecordsinorg'] = 'Just staff in the user\'s organisation';
$string['showrecordsinorgandbelow'] = 'Staff at or below the user\'s organisation';
$string['showrecordsinpos'] = 'Just staff in the user\'s position';
$string['showrecordsinposandbelow'] = 'Staff at or below the user\'s position';
$string['sidebarfilter'] = 'Sidebar filter options';
$string['sidebarfilterdesc'] = 'The choices below determine which filters appear to the side of the report and how they are labelled.';
$string['sidebarfilter_help'] = '
**Sidebar filter options** allows you to customise the filters that appear to the side of your report. Sidebar filters have
instant filtering enabled - each change made to a filter will automatically refresh the report data (if certain system
requirements are met). The available filters are determined by the **Source** of the report. Only some types of filters can
be placed in the sidebar, so not all standard filters can be placed there. Each report source has a set of default filters.

A filter can appear in either the standard filter area or the sidebar filter area, but not both. Filters can be added, sorted
and removed.

**Adding filters:** To add a new filter to the report choose the required filter from the \'Add another filter...\' dropdown
menu and click **Save changes**. When **Advanced** is checked the filter will not appear in the \'Search by\' box by default,
you can click **Show advanced** when viewing a report to see these filters.

**Moving filters:** The filters will appear in the \'Search by\' box in the order they are listed. Use the up and down arrows
to change the order.

**Deleting filters:** Click the **Delete** button (the cross icon) to the right of the report filter to remove that filter
from the report.

**Changing multiple filter types:** You can modify multiple filter types at the same time by selecting a different filter
from the dropdown menu and clicking **Save changes**.';
$string['sorting'] = 'Sorting';
$string['source'] = 'Source';
$string['standardfilter'] = 'Standard filter options.';
$string['standardfilterdesc'] = 'The choices below determine which filter will appear above the report and how they are labelled.';
$string['standardfilter_help'] = '
**Standard filter options** allows you to customise the filters that appear above your report. The available filters are
determined by the **Source** of the report. Each report source has a set of default filters.

A filter can appear in either the standard filter area or the sidebar filter area, but not both. Filters can be added, sorted
and removed.

**Adding filters:** To add a new filter to the report choose the required filter from the \'Add another filter...\' dropdown
menu and click **Save changes**. When **Advanced** is checked the filter will not appear in the \'Search by\' box by default,
you can click **Show advanced** when viewing a report to see these filters.

**Moving filters:** The filters will appear in the \'Search by\' box in the order they are listed. Use the up and down arrows
to change the order.

**Deleting filters:** Click the **Delete** button (the cross icon) to the right of the report filter to remove that filter
from the report.

**Changing multiple filter types:** You can modify multiple filter types at the same time by selecting a different filter
from the dropdown menu and clicking **Save changes**.';
$string['suspendedonly'] = 'Suspended users only';
$string['suspendeduser'] = 'Suspended user';
$string['systemcontext'] = 'Users must have role in the system context';
$string['tagenable'] = 'Show records by tag';
$string['taggedx'] = 'Tagged \'{$a}\'';
$string['tagids'] = 'Tag IDs';
$string['tags'] = 'Tags';
$string['thefuture'] = 'The future';
$string['thepast'] = 'The past';
$string['toolbarsearch'] = 'Toolbar search box';
$string['toolbarsearch_help'] = '
**Toolbar search box** allows you to customise the fields that will be searched when using the search box in the report header.
The available filters are determined by the **Source** of the report. Each report source has a set of default fields. If no
fields are specified then the search box is not displayed.

You can specify that a field is searched, even if it is not included as a column in the report, although this may cause
confusion for users if they cannot see why a particular record is included in their search results.

**Adding search fields:** To add a new search field to the report choose the required field from the \'Add another search
field...\' dropdown menu and click **Save changes**.

**Delete search fields:** Click the **Delete** button (the cross icon) to the right of the report field to remove that
search field.

**Changing multiple search fields:** You can modify multiple search fields at the same time by selecting a different field
from the dropdown menu and clicking **Save changes**.';
$string['toolbarsearchdesc'] = 'The choices below determine which fields will be searched when a user enters text in the toolbar search box.';
$string['toolbarsearchdisabled'] = 'Disable toolbar search box';
$string['toolbarsearchdisabled_help'] = 'Checking this box will prevent the search box from appearing in the header of the
report. This has the same result as removing all search fields.';
$string['toolbarsearchtextiscontainedinsingle'] = '"{$a->searchtext}" is contained in the column "{$a->field}"';
$string['toolbarsearchtextiscontainedinmultiple'] = '"{$a}" is contained in one or more of the following columns: ';
$string['trainerownrecords'] = 'Show records where the user is the trainer';
$string['trainerstaffrecords'] = 'Records where one of the user\'s direct reports is the trainer';
$string['transformtypeday_heading'] = '{$a} - day of month';
$string['transformtypeday_name'] = 'Day of month';
$string['transformtypedayyear_heading'] = '{$a} - day of year';
$string['transformtypedayyear_name'] = 'Day of year';
$string['transformtypehour_heading'] = '{$a} - hour of day';
$string['transformtypehour_name'] = 'Hour of day';
$string['transformtypemonth_heading'] = '{$a} - month of year';
$string['transformtypemonth_name'] = 'Month of year';
$string['transformtypequarter_heading'] = '{$a} - quarter of year';
$string['transformtypequarter_name'] = 'Quarter of year';
$string['transformtypeweekday_heading'] = '{$a} - week day';
$string['transformtypeweekday_name'] = 'Week day';
$string['transformtypeyear_heading'] = '{$a}';
$string['transformtypeyear_name'] = 'Date YYYY';
$string['transformtypeyearmonth_heading'] = '{$a}';
$string['transformtypeyearmonth_name'] = 'Date YYYY-MM';
$string['transformtypeyearmonthday_heading'] = '{$a}';
$string['transformtypeyearmonthday_name'] = 'Date YYYY-MM-DD';
$string['transformtypeyearquarter_heading'] = '{$a} - year quarter';
$string['transformtypeyearquarter_name'] = 'Date YYYY-Q';
$string['type'] = 'Type';
$string['type_cohort'] = 'Audience';
$string['type_comp_type'] = 'Competency custom fields';
$string['type_course'] = 'Course';
$string['type_course_category'] = 'Category';
$string['type_course_custom_fields'] = 'Course Custom Fields';
$string['type_facetoface'] = 'Face to face';
$string['type_org_type'] = 'Organisation custom fields';
$string['type_pos_type'] = 'Position custom fields';
$string['type_prog'] = 'Program';
$string['type_statistics'] = 'Statistics';
$string['type_tags'] = 'Tags';
$string['type_user'] = 'User';
$string['type_user_profile'] = 'User Profile';
$string['uniquename'] = 'Unique Name';
$string['unknown'] = 'Unknown';
$string['unknownlanguage'] = 'Unknown Language ({$a})';
$string['uninstalledlanguage'] = 'Uninstalled Language {$a->name} ({$a->code})';
$string['updatescheduledreport'] = 'Successfully updated Scheduled Report';
$string['useralternatename'] = 'User Alternate Name';
$string['useraddress'] = 'User\'s Address';
$string['usercity'] = 'User\'s City';
$string['usercohortids'] = 'User audience IDs';
$string['usercountry'] = 'User\'s Country';
$string['userdepartment'] = 'User\'s Department';
$string['userdirectreports'] = 'Records for user\'s direct reports';
$string['useremail'] = 'User\'s Email';
$string['useremailprivate'] = 'Email is private';
$string['useremailunobscured'] = 'User\'s Email (ignoring user display setting)';
$string['userfirstaccess'] = 'User First Access';
$string['userfirstname'] = 'User First Name';
$string['userfirstnamephonetic'] = 'User First Name - phonetic';
$string['userfullname'] = 'User\'s Fullname';
$string['usergenerated'] = 'User generated';
$string['usergeneratedreports'] = 'User generated Reports';
$string['userid'] = 'User ID';
$string['useridnumber'] = 'User ID Number';
$string['userincohort'] = 'User is a member of audience';
$string['userindirectreports'] = 'Records for user\'s indirect reports';
$string['userinstitution'] = 'User\'s Institution';
$string['userlang'] = 'User\'s Preferred Language';
$string['userlastlogin'] = 'User Last Login';
$string['userlastname'] = 'User Last Name';
$string['userlastnamephonetic'] = 'User Last Name - phonetic';
$string['usermiddlename'] = 'User Middle Name';
$string['username'] = 'Username';
$string['usernamelink'] = 'User\'s Fullname (linked to profile)';
$string['usernamelinkicon'] = 'User\'s Fullname (linked to profile with icon)';
$string['userownrecords'] = 'A user\'s own records';
$string['userphone'] = 'User\'s Phone number';
$string['usersjobtitle'] = 'User\'s Job Title';
$string['usersmanagerfirstname'] = 'User\'s Manager\'s First Name';
$string['usersmanagerid'] = 'User\'s Manager ID';
$string['usersmanageridnumber'] = 'User\'s Manager ID Number';
$string['usersmanagerlastname'] = 'User\'s Manager\'s Last Name';
$string['usersmanagername'] = 'User\'s Manager Name';
$string['usersorgid'] = 'User\'s Organisation ID';
$string['usersorgidnumber'] = 'User\'s Organisation ID Number';
$string['usersorgname'] = 'User\'s Organisation Name';
$string['usersorgpathids'] = 'User\'s Organisation Path IDs';
$string['userspos'] = 'User\'s Position';
$string['usersposid'] = 'User\'s Position ID';
$string['usersposidnumber'] = 'User\'s Position ID Number';
$string['userspospathids'] = 'User\'s Position Path IDs';
$string['userstatus'] = 'User Status';
$string['usertempreports'] = 'Records for user\'s temporary reports';
$string['usertimecreated'] = 'User Creation Time';
$string['usertimemodified'] = 'User Last Modified';
$string['value'] = 'Value';
$string['viewreport'] = 'View This Report';
$string['viewsavedsearch'] = 'View a saved search...';
$string['weekly'] = 'Weekly';
$string['withcontentrestrictionall'] = 'Show records matching <strong>all</strong> of the checked criteria below';
$string['withcontentrestrictionany'] = 'Show records matching <strong>any</strong> of the checked criteria below';
$string['withrestriction'] = 'Only certain users can view this report (see below)';
$string['xlsformat'] = 'Excel format';
$string['xofyrecord'] = '{$a->filtered} of {$a->unfiltered} record shown';
$string['xofyrecords'] = '{$a->filtered} of {$a->unfiltered} records shown';
$string['xrecord'] = '{$a} record shown';
$string['xrecords'] = '{$a} records shown';
