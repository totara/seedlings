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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package totara
 * @subpackage totara_sync
 */
$string['pluginname'] = 'Totara sync';

$string['sync'] = 'Sync';
$string['totarasync'] = 'Totara sync';
$string['totarasync_help'] = 'Enabling Totara syncing will cause the element to be updated/deleted (synced) from an external source (if configured). The idnumber field MUST have a value to enable this field.
See the Sync settings in the Administration menu.';
$string['totara_sync:manage'] = 'Manage Totara sync';
$string['totara_sync:runsync'] = 'Run Totara sync via the web interface';
$string['totara_sync:setfileaccess'] = 'Set Totara sync file access';
$string['totara_sync:manageuser'] = 'Manage Totara sync users';
$string['totara_sync:manageorg'] = 'Manage Totara sync organisations';
$string['totara_sync:managepos'] = 'Manage Totara sync positions';
$string['totara_sync:uploaduser'] = 'Upload Totara sync users';
$string['totara_sync:uploadorg'] = 'Upload Totara sync organisations';
$string['totara_sync:uploadpos'] = 'Upload Totara sync positions';
$string['totara_sync:deletesynclog'] = 'Clear the sync logs';
$string['settingssaved'] = 'Settings saved';
$string['elementenabled'] = 'Element enabled';
$string['elementdisabled'] = 'Element disabled';
$string['uploadsuccess'] = 'Sync files uploaded successfully';
$string['uploaderror'] = 'The was a problem with uploading the file(s)...';
$string['uploadaccessdenied'] = 'Your Totara Sync configuration is set to look for files in a server directory, not to use uploaded files. To change this go {$a}';
$string['uploadaccessdeniedlink'] = 'here';
$string['couldnotmakedirsforx'] = 'Could not make necessary directories for {$a}';
$string['note:syncfilepending'] = 'NOTE: A pending sync file exists. Uploading another file now will overwrite the pending one.';
//
// Elements
//
$string['element'] = 'Element';
$string['elements'] = 'Elements';
$string['elementnotfound'] = 'Element not found';
$string['manageelements'] = 'Manage elements';
$string['managesyncelements'] = 'Manage sync elements';
$string['noenabledelements'] = 'No enabled elements';
$string['elementxnotfound'] = 'Element {$a} not found';
$string['notadirerror'] = 'Directory \'{$a}\' does not exist or not accessible';
$string['readonlyerror'] = 'Directory \'{$a}\' is read-only';
$string['pathformerror'] = 'Path not found';

// Hierarchy items
$string['displayname:org'] = 'Organisation';
$string['settings:org'] = 'Organisation element settings';
$string['displayname:pos'] = 'Position';
$string['settings:pos'] = 'Position element settings';
$string['removeitems'] = 'Remove items';
$string['removeitemsdesc'] = 'Specify what to do with internal items during sync when item was removed from source.';

// User
$string['displayname:user'] = 'User';
$string['settings:user'] = 'User element settings';
$string['deleted'] = 'Deleted';
$string['sourceallrecords'] = 'Source contains all records';
$string['sourceallrecordsdesc'] = 'Does the source provide all sync records, everytime <strong>OR</strong> are only records that need to be updated/deleted provided? If "No" (only records to be updated/deleted), then the source must use the <strong>"delete" flag</strong>.';
$string['allowduplicatedemails'] = 'Allow duplicate emails';
$string['allowduplicatedemailsdesc'] = 'If "Yes" duplicated emails are allowed from the source. If "No" only unique emails are allowed.';
$string['defaultemailaddress'] = 'Default Email Address';
$string['emailsettingsdesc'] = 'If duplicate emails are allowed you can set a default email address that will be used when syncing users with a blank or invalid email. If duplicates are not allowed every user must have a unique email, if they do not they will be skipped.';
$string['ignoreexistingpass'] = 'Only sync new users\' passwords';
$string['ignoreexistingpassdesc'] = 'If "Yes" passwords are only updated for new users, if "No" all users\' passwords are updated';
$string['forcepwchange'] = 'Force password change for new users';
$string['forcepwchangedesc'] = 'If "yes" new users have their password set but are forced to change it on first login';
$string['checkuserconfig'] = 'These settings change the expected <a href=\'{$a}\'>source configuration</a>. You should check the format of your data source matches the new source configuration';
$string['allowedactions'] = 'Allowed sync actions';
$string['create'] = 'Create';
$string['delete'] = 'Delete';
$string['keep'] = 'Keep';
$string['update'] = 'Update';


///
/// Sources
///
$string['source'] = 'Source';
$string['sources'] = 'Sources';
$string['sourcenotfound'] = 'Source not found';
$string['sourcesettings'] = 'Source settings';
$string['configuresource'] = 'Configure source';
$string['nosources'] = 'No sources';
$string['filedetails'] = 'File details';
$string['nameandloc'] = 'Name and location';
$string['fieldmappings'] = 'Field mappings';
$string['uploadsyncfiles'] = 'Upload sync files';
$string['sourcedoesnotusefiles'] = 'Source does not use files';
$string['nosourceconfig'] = 'No source configuration';
$string['sourceconfigured'] = 'Source has configuration';
$string['uploadfilelink'] = 'Files can be uploaded <a href=\'{$a}\'>here</a>';

// Hierarchy items
$string['displayname:totara_sync_source_org_csv'] = 'CSV';
$string['displayname:totara_sync_source_org_database'] = 'External Database';
$string['displayname:totara_sync_source_pos_csv'] = 'CSV';
$string['displayname:totara_sync_source_pos_database'] = 'External Database';
$string['settings:totara_sync_source_org_csv'] = 'Organisation - CSV source settings';
$string['settings:totara_sync_source_org_database'] = 'Organisation - external database source settings';
$string['settings:totara_sync_source_pos_csv'] = 'Position - CSV source settings';
$string['settings:totara_sync_source_pos_database'] = 'Position - external database source settings';

// User
$string['displayname:totara_sync_source_user_csv'] = 'CSV';
$string['displayname:totara_sync_source_user_database'] = 'External Database';
$string['settings:totara_sync_source_user_csv'] = 'User - CSV source settings';
$string['settings:totara_sync_source_user_database'] = 'User - external database source settings';
$string['importfields'] = 'Fields to import';
$string['firstname'] = 'First name';
$string['lastname'] = 'Last name';
$string['firstnamephonetic'] = 'First name phonetic';
$string['lastnamephonetic'] = 'Last name Phonetic';
$string['middlename'] = 'Middle name';
$string['alternatename'] = 'Alternate name';
$string['email'] = 'Email';
$string['city'] = 'City';
$string['country'] = 'Country';
$string['timezone'] = 'Timezone';
$string['lang'] = 'Language';
$string['description'] = 'Description';
$string['url'] = 'URL';
$string['institution'] = 'Institution';
$string['department'] = 'Department';
$string['phone1'] = 'Phone 1';
$string['phone2'] = 'Phone 2';
$string['address'] = 'Address';
$string['orgidnumber'] = 'Organisation';
$string['postitle'] = 'Position title';
$string['posidnumber'] = 'Position';
$string['posstartdate'] = 'Position start date';
$string['posenddate'] = 'Position end date';
$string['manageridnumber'] = 'Manager';
$string['appraiseridnumber'] = 'Appraiser';
$string['auth'] = 'Auth';
$string['password'] = 'Password';
$string['suspended'] = 'Suspended';
$string['emailstop'] = 'Turn email off';
$string['customfields'] = 'Custom fields';
$string['csvimportfilestructinfo'] = 'The current config requires a CSV file with the following structure:<br><pre>{$a}<br>...<br>...<br>...</pre>';

// Organisation
$string['shortname'] = 'Shortname';
$string['parentidnumber'] = 'Parent';
$string['typeidnumber'] = 'Type';

// Database sources
$string['dbtype'] = 'Database type';
$string['dbname'] = 'Database name';
$string['dbuser'] = 'Database user';
$string['dbpass'] = 'Database password';
$string['dbhost'] = 'Database hostname';
$string['dbtable'] = 'Database table';

$string['databaseconnectfail'] = 'Failed to connect to database';
$string['cannotconnectdbsettings'] = 'Cannot connect to database, please check settings';
$string['dbmissingcolumnx'] = 'Remote database table does not contain field "{$a}"';
$string['dbtestconnection'] = 'Test database connection';
$string['dbtestconnectsuccess'] = 'Successfully connected to database';
$string['dbtestconnectfail'] = 'Failed to connect to database';

$string['dbconnectiondetails'] = 'Please enter database connection details.';
$string['selectfieldsdb'] = 'Please select some fields to sync by checking the boxes below.';
$string['tablemustincludexdb'] = 'The table "{$a}" must contain the following fields:';

///
/// Log messages
///
$string['syncnotconfigured'] = 'There are problems with your sync configuration. Please fix the issues before running sync.';
$string['temptableprepfail'] = 'temp table preparation failed';
$string['temptablecreatefail'] = 'error creating temp table';
$string['nocsvfilepath'] = 'no CSV filepath specified';
$string['nofilesdir'] = 'No sync files directory configured';
$string['nofiletosync'] = 'No file to sync (file path: {$a})';
$string['nofileuploaded'] = 'No file was uploaded for {$a} sync';
$string['nochangesskippingsync'] = 'no changes, skipping sync';
$string['cannotopenx'] = 'cannot open {$a}';
$string['cannotreadx'] = 'cannot read {$a}';
$string['csvnotvalidmissingfieldx'] = 'CSV file not valid, missing field "{$a}"';
$string['csvnotvalidmissingfieldxmappingx'] = 'CSV file not valid, missing field "{$a->mapping}" (mapping for "{$a->field}")';
$string['csvnotvalidinvalidchars'] = 'CSV file not valid. It contains invalid characters ("{$a->invalidchars}"). Fields in a CSV file must be separated by a selected delimiter ("{$a->delimiter}").';
$string['couldnotimportallrecords'] = 'could not import all records';
$string['lengthlimitexceeded'] = 'value "{$a->value}" is too long for "{$a->field}" field. It cannot be longer than {$a->length} characters. Skipped {$a->source} {$a->idnumber}';
$string['syncstarted'] = 'sync started';
$string['syncfinished'] = 'sync finished';
$string['couldnotgetsourcetable'] = 'could not get source table, aborting...';
$string['couldnotcreateclonetable'] = 'could not create clone table, aborting...';
$string['sanitycheckfailed'] = 'sanity check failed, aborting...';
$string['cannotdeletex'] = 'cannot delete {$a} (might already be deleted)';
$string['deletedx'] = 'deleted {$a}';
$string['frameworkxnotfound'] = 'framework {$a} not found...';
$string['parentxnotfound'] = 'parent {$a} not found...';
$string['cannotsyncitemparent'] = 'cannot sync item\'s parent {$a}';
$string['cannotcreatex'] = 'cannot create {$a}';
$string['cannotcreatedirx'] = 'cannot create directory: {$a}';
$string['createdx'] = 'created {$a}';
$string['cannotupdatex'] = 'cannot update {$a}';
$string['updatedx'] = 'updated {$a}';
$string['frameworkxnotexist'] = 'framework {$a} does not exist';
$string['parentxnotexistinfile'] = 'parent {$a} does not exist in sync file';
$string['typexnotexist'] = 'type {$a} does not exist';
$string['circularreferror'] = 'circular reference error between items {$a->naughtynodes}';
$string['customfieldsnotype'] = 'custom fields specified, but no type {$a}';
$string['typexnotfound'] = 'type {$a} not found...';
$string['customfieldnotexist'] = 'custom field {$a->shortname} does not exist (type:{$a->typeidnumber})';
$string['cannotdeleteuserx'] = 'cannot delete user {$a}';
$string['deleteduserx'] = 'deleted user {$a}';
$string['syncaborted'] = 'sync aborted';
$string['cannotupdateuserx'] = 'cannot update user {$a}';
$string['cannotsetuserpassword'] = 'cannot set user password (user:{$a})';
$string['cannotsetuserpasswordnoauthsupport'] = 'cannot set user password (user:{$a}), auth plugin does not support password changes';
$string['updateduserx'] = 'updated user {$a}';
$string['reviveduserx'] = 'revived user {$a}';
$string['cannotreviveuserx'] = 'cannot revive user {$a}';
$string['cannotcreateuserassignments'] = 'cannot create user assignments (user: {$a})';
$string['createduserx'] = 'created user {$a}';
$string['cannotcreateuserx'] = 'cannot create user {$a}';
$string['orgxnotexist'] = 'Organisation {$a->orgidnumber} does not exist. Skipped user {$a->idnumber}';
$string['posxnotexist'] = 'Position {$a->posidnumber} does not exist. Skipped user {$a->idnumber}';
$string['managerxnotexist'] = 'Manager {$a->manageridnumber} does not exist. Skipped user {$a->idnumber}';
$string['appraiserxnotexist'] = 'Appraiser {$a->appraiseridnumber} does not exist. Skipped user {$a->idnumber}';
$string['selfassignedmanagerx'] = 'User {$a->idnumber} cannot be their own manager. Skipped user {$a->idnumber}';
$string['selfassignedappraiserx'] = 'User {$a->idnumber} cannot be their own appraiser. Skipped user {$a->idnumber}';
$string['optionxnotexist'] = 'Option \'{$a->option}\' does not exist for {$a->fieldname} field. Skipped user {$a->idnumber}';
$string['fieldrequired'] = '{$a->fieldname} is a required field and must have a value. Skipped user {$a->idnumber}';
$string['fieldduplicated'] = 'The value \'{$a->value}\' for {$a->fieldname} is a dupliicate of existing data and must be unique. Skipped user {$a->idnumber}';
$string['fieldmustbeunique'] = 'The value \'{$a->value}\' for {$a->fieldname} is duplicated in the uploaded data and must be unique. Skipped user {$a->idnumber}';
$string['nosourceconfigured'] = 'No source configured, please set configuration <a href=\'{$a}\'>here</a>';
$string['duplicateuserswithidnumberx'] = 'Duplicate users with idnumber {$a->idnumber}. Skipped user {$a->idnumber}';
$string['duplicateuserswithusernamex'] = 'Duplicate users with username {$a->username}. Skipped user {$a->idnumber}';
$string['duplicateuserswithemailx'] = 'Duplicate users with email {$a->email}. Skipped user {$a->idnumber}';
$string['duplicateusernamexdb'] = 'Username {$a->username} is already registered. Skipped user {$a->idnumber}';
$string['duplicateusersemailxdb'] = 'Email {$a->email} is already registered. Skipped user {$a->idnumber}';
$string['duplicateidnumberx'] = 'Duplicate idnumber {$a}';
$string['emptyvalueidnumberx'] = 'Idnumber cannot be empty. Skipped user {$a->idnumber}';
$string['emptyvalueusernamex'] = 'Username cannot be empty. Skipped user {$a->idnumber}';
$string['emptyvalueemailx'] = 'Email cannot be empty. Skipped user {$a->idnumber}';
$string['emptyvaluepasswordx'] = 'Password cannot be empty. Skipped user {$a->idnumber}';
$string['fieldcountmismatch'] = 'Skipping row {$a->rownum} in CSV file - {$a->fieldcount} fields found but {$a->headercount} fields expected. Please make sure fields are separated by a selected delimiter ("{$a->delimiter}").';
$string['nosynctablemethodforsourcex'] = 'Source {$a} has no get_sync_table method. This needs to be fixed by a programmer.';
$string['sourcefilexnotfound'] = 'Source file {$a} not found.';
$string['sourceclassxnotfound'] = 'Source class {$a} not found. This must be fixed by a programmer.';
$string['nosourceenabled'] = 'No source enabled for this element.';

$string['syncexecute'] = 'Run Sync';
$string['runsynccronstart'] = 'Running totara_sync cron...';
$string['runsynccronend'] = 'Done!';
$string['runsynccronendwithproblem'] = 'However, there have been some problems';
$string['deleteallsynclog'] = 'Clear all records';
$string['deletepartialsynclog'] = 'Clear all except latest records';
$string['deleteallsynclogcheck'] = 'Are you absolutely sure you want to delete all the Totara Sync log records?';
$string['deletepartialsynclogcheck'] = 'Are you absolutely sure you want to delete all the Totara Sync log records except for the most recent run?';
$string['error:deletesynclogpermission'] = 'You do not have permission to delete sync log records!';

///
/// Totara sync log reports
///
$string['synclog'] = 'Sync log';
$string['viewsynclog'] = 'View the results in the Sync Log <a href=\'{$a}\'>here</a>';
$string['sourcetitle'] = 'Totara Sync Log';
$string['datetime'] = 'Date/Time';
$string['logtype'] = 'Log type';
$string['error'] = 'Error';
$string['info'] = 'Info';
$string['warn'] = 'Warning';
$string['action'] = 'Action';
$string['info'] = 'Info';
$string['id'] = 'ID';
$string['runid'] = 'Run ID';
$string['datetime'] = 'Date/Time';
$string['element'] = 'Element';
$string['action'] = 'Action';
$string['info'] = 'Info';

///
/// Totara sync help strings
///
$string['country_help'] = "This should be formatted within the CSV as the 2 character code of the country. For example 'New Zealand' should be 'NZ'<br />
see <a href=\"http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2\">http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2</a> for details";
$string['fileaccess_help'] = '**Directory**: This option allows you to specify a directory on the server to be checked for sync files automatically

**Upload**: This option requires you to upload files via the \'upload sync files\' page under sources in site administration';
//Delimiter strings
$string['delimiter'] = 'Delimiter';
$string['comma'] = 'Comma (,)';
$string['semicolon'] = 'Semi-colon (;)';
$string['colon'] = 'Colon (:)';
$string['tab'] = 'Tab (\t)';
$string['pipe'] = 'Pipe (|)';

$string['errorplural'] = 'Errors';
$string['notifymessage'] = 'Server time: {$a->time}, Element: {$a->element}, Action: {$a->action}, {$a->logtype}: {$a->info}';
$string['notifymessagestart'] = '{$a->count} new Totara sync log messages ({$a->logtypes}) since {$a->since}. See below for most recent messages:';
$string['notifysubject'] = '{$a} :: Totara sync notification';
$string['syncnotifications'] = 'Totara sync notifications';
$string['viewsyncloghere'] = 'For more information, view the sync log at {$a}';
$string['warnplural'] = 'Warnings';
$string['enablescheduledsync'] = 'Enable scheduled syncing';
$string['files'] = 'Files';
$string['filesdir'] = 'Files directory';
$string['fileaccess'] = 'File Access';
$string['fileaccess_directory'] = 'Directory Check';
$string['fileaccess_upload'] = 'Upload Files';
$string['generalsettings'] = 'General settings';
$string['invalidemailaddress'] = 'Invalid email address \'{$a}\'';
$string['notifications'] = 'Notifications';
$string['notifymailto'] = 'Email notifications to';
$string['notifymailto_help'] = 'A comma-separated list of email addresses so which notifications should be sent.';
$string['notifytypes'] = 'Send notifications for';
$string['schedule'] = 'Schedule';
$string['csvencoding'] = 'CSV file encoding';
?>
