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
 * @package    totara
 * @subpackage completionimport
 * @author     Russell England <russell.england@catalyst-eu.net>
 */

$string['blankcompletiondate'] = 'Blank completion date';
$string['blankgrade'] = 'Blank grade';
$string['blankusername'] = 'Blank user name';
$string['cannotcopyfiles'] = 'Cannot copy file from {$a->fromfile} to {$a->tofile}';
$string['cannotcreatetempname'] = 'Cannot create a temporary file name';
$string['cannotcreatetemppath'] = 'Cannot create temporary directory : {$a}';
$string['cannotdeletefile'] = 'Cannot delete file {$a}';
$string['cannotmovefiles'] = 'Cannot move file from {$a->fromfile} to {$a->tofile}';
$string['cannotsaveupload'] = 'Cannot save file to {$a}';
$string['certification_results'] = 'Certification results';
$string['certificationblankrefs'] = 'Blank certification shortname and certification ID number';
$string['certificationexpired'] = 'Import certification expired, skipping importing';
$string['certificationdueforrecert'] = 'Import certification due for renewal, skipping import';
$string['choosefile'] = 'Choose file to upload';
$string['completiondatesame'] = 'Record completion date exists';
$string['completionimport'] = 'Upload Completion Records';
$string['completionimport_certification'] = 'Completion import: Certification status';
$string['completionimport_course'] = 'Completion import: Course status';
$string['completionimport:import'] = 'Completion import';
$string['course_results'] = 'Course results';
$string['courseblankrefs'] = 'Blank course shortname and course ID number';
$string['csvdateformat'] = 'CSV Date format';
$string['csvdelimiter'] = 'CSV Text Delimited with';
$string['csvencoding'] = 'CSV File encoding';
$string['csvimportdone'] = 'CSV import completed';
$string['csvimportfailed'] = 'Failed to import the CSV file';
$string['csvseparator'] = 'CSV Values separated by';
$string['dataimportdone_certification'] = 'Certification data imported successfully';
$string['dataimportdone_course'] = 'Course data imported successfully';
$string['duplicate'] = 'Duplicate';
$string['duplicateidnumber'] = 'Duplicate ID Number';
$string['emptyfile'] = 'File is empty : {$a}';
$string['emptyrow'] = 'Empty row';
$string['error:import_certifications'] = 'Errors while importing the certifications';
$string['error:import_course'] = 'Errors while importing the courses';
$string['error:invalidfilesource'] = 'Invalid file source code passed as a parameter';
$string['erroropeningfile'] = 'Error opening file : {$a}';
$string['evidence_certificationidnumber'] = 'Certification ID number : {$a}';
$string['evidence_certificationshortname'] = 'Certification Short name : {$a}';
$string['evidence_completiondate'] = 'Completion date : {$a}';
$string['evidence_courseidnumber'] = 'Course ID number : {$a}';
$string['evidence_courseshortname'] = 'Course Short name : {$a}';
$string['evidence_grade'] = 'Grade : {$a}';
$string['evidence_importid'] = 'Import ID : {$a}';
$string['evidence_shortname_certification'] = 'Completed certification : {$a}';
$string['evidence_shortname_course'] = 'Completed course : {$a}';
$string['evidence_idnumber_certification'] = 'Completed certification ID : {$a}';
$string['evidence_idnumber_course'] = 'Completed course ID : {$a}';
$string['evidencetype'] = 'Default evidence type';
$string['evidencetype_help'] = 'Any courses or certificates that can\'t be found will be added as evidence in the record of learning.

Please choose the default evidence type you wish to use.';
$string['fieldcountmismatch'] = 'Field count mismatch';
$string['fileisinuse'] = 'File is currently being used elsewhere : {$a}';
$string['sourcefile'] = 'Source file name';
$string['sourcefile_help'] = 'Please enter the file name and full path name to a file on the server.

eg: /var/moodledata/csvimport/course.csv

This option allows you to upload a file externally via FTP rather than using a form via HTTP

Please note the original file will be moved and deleted during the import process';
$string['sourcefilerequired'] = 'Source file name is required';
$string['importcertification'] = '{$a} Records successfully imported as certifications';
$string['importcourse'] = '{$a} Records successfully imported as courses';
$string['importerrors'] = '{$a} Records with data errors - these were ignored';
$string['importevidence'] = '{$a} Records created as evidence';
$string['importing'] = 'Completion history upload - importing {$a}';
$string['importnone'] = 'No records were imported';
$string['importnotready'] = 'Import not ready, please check the errors above';
$string['importresults'] = 'Import results';
$string['importsource'] = 'Import source';
$string['importtotal'] = '{$a} Records in total';
$string['invalidcompletiondate'] = 'Invalid completion date';
$string['invalidfilenames'] = 'These are invalid filenames and will be ignored : {$a}';
$string['invalidfilesource'] = 'Invalid file source setting {$a}';
$string['missingfield'] = 'Missing column \'{$a->columnname}\' in file \'{$a->filename}\'';
$string['missingfields'] = 'These fields are missing, please check the source csv files :';
$string['nomanualenrol'] = 'Course needs to have manual enrol';
$string['nousername'] = 'No user name';
$string['nocourse'] = 'No course';
$string['nothingtoimport'] = 'No pending files to import';
$string['overrideactivecertification'] = 'Override active certifications';
$string['overrideactivecourse'] = 'Override current course completions';
$string['pluginname'] = 'Completion History Import';
$string['report_certification'] = 'Certification import report';
$string['report_course'] = 'Course import report';
$string['resetimport'] = 'Reset report data';
$string['resetcomplete'] = 'Reset report data for {$a} has completed';
$string['resetfailed'] = 'Reset report data for {$a} has failed';
$string['resetconfirm'] = 'Are you sure you want to reset the report data for {$a}?';
$string['resetcourse'] = 'Reset course report data?';
$string['resetcertification'] = 'Reset certification report data?';
$string['resetabove'] = 'Reset selected';
$string['rpl'] = 'Completion history import - imported grade = {$a}';
$string['runimport'] = 'Run the import';
$string['unknownfield'] = 'Unknown column \'{$a->columnname}\' in file \'{$a->filename}\'';
$string['unreadablefile'] = 'File is unreadable : {$a}';
$string['uploadcertification'] = 'Upload certification csv';
$string['uploadcertificationintro'] = 'This will import historical records from a csv file as certifications.
Any certifications that do not exist in the current system will be created as evidence in the record of learning.

The csv file should contain the following columns in the first line of the file

{$a}
';
$string['uploadcourse'] = 'Upload course csv';
$string['uploadcourseintro'] = 'This will import historical completion records from a csv file and enrol users onto the specified courses.
Any courses that do not exist in the current system will be created as evidence in the record of learning.

The csv file should contain the following columns in the first line of the file

{$a}';
$string['uploadfilerequired'] = 'Please select a file to upload';
$string['uploadsuccess'] = 'Uploaded files successfully';
$string['uploadvia_directory'] = 'Alternatively upload csv files via a directory on the server';
$string['uploadvia_form'] = 'Alternatively upload csv files via a form';
$string['usernamenotfound'] = 'User name not found';
$string['validfilenames'] = 'Please note, these are the only valid file names, anything else will be ignored :';
$string['viewreports'] = 'View import errors';
