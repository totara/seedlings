<?php
/*

Totara LMS Changelog

Release 2.6.11 (7th October 2014):
==================================================

Database Upgrades:
    T-13169        Fixed incorrect column type string in Record Of Learning Certifications report

Bug Fixes:
    T-13199        Fixed the handling of program exceptions missing the linked user assignment
    T-12878        Fixed course enrolment checks to take audience visiblity into account
    T-13278        Fixed nested audiences being updated even when they are unavailable
    T-13054        Fixed overdue warning incorrectly appearing on completed programs and certifications
    T-12660        Fixed capability checks for Audience dialog on course settings page
    T-13102        Fixed border on totara/custom menu drop downs on older browsers
    T-13282        Fixed managing courses and categories page on older browsers
    T-13229        Fixed RPL course completion records being deleted when an activities settings are updated
    T-12942        Fixed display issue in appraisals where visible to overlaps entry field
    T-13137        Fixed capability checks in course visibility for current user
    T-12987        Fixed overwriting a users existing 'Auth' field with totara sync
    T-13118        Removed the docking block functionality on older browsers
    T-13056        Fixed the ordering of html tags on the user profile page when a field is empty
    T-13046        Fixed bulleted and numbered lists in TinyMCE editors for RTL languages
    T-13197        Fixed certification link in ROL pointing to required learning
    T-13178        Fixed column grade not showing in courses report when uploading course completion records
    T-13289        Prevent errors in calendar from Facetoface with sessions with the same start time
    T-12486        Fixed the focus for text boxes in modal dialogs
    T-13196        Changed reportbuilder capability check to use the user:viewalldetails capability
    T-13174        Fixed fatal error on Record Of Learning : Certifications report source when user content restrictions are enabled
    T-13194        Fixed uniqueness of param keys for audience rules
    T-12682        Fixed reportbuilder export to PDF compatibility with IOS devices.
    T-13205        Fixed Program exceptions count methods incorrectly including deleted users


Release 2.6.10 (23rd September 2014):
==================================================

Security issues:
    T-12620        Fixed Facetoface access control issues

Improvements:
    T-13017        Added help button to body field in Facetoface notifications to explain placeholders
    T-12475        Improved Program enrolment message for Single Activity format course
    T-12606        Added a default user email address setting to Totara Sync

Bug Fixes:
    T-12140        Fixed undefined offset errors on the SCORM Interaction report
    T-12748        Fixed temporary manager restriction default value
    T-12848        Fixed course availability through programs after program expires
    T-12977        Fixed Facetoface notification emails when there are scheduling conflicts
    T-12980        Fixed allowing upper case values in auth field in Totara Sync
    T-12973        Fixed alignment of the user table in right-to-left languages
    T-12481        Fixed format for custom user profile fields in bulk user actions download
    T-13155        Fixed launch of SCORMs using simple popup display mode in certain languages
    T-13107        Fixed sending of Facetoface notification emails to cancelled users
    T-13148        Fixed scalability of appraisals and Facetoface upgrade script for large numbers of deleted users
    T-13128        Fixed multilang support when menu and multiselect course custom fields are used as reportbuilder filters
    T-13054        Fixed incorrect overdue status being displayed on completed programs and certifications
    T-13007        Fixed creation of dynamic audience rules with empty parameters
    T-13085        Fixed formatting of course details section when managing courses and categories in older browsers
    T-13082        Fixed formatting help icons in middle of text for older browsers
    T-13005        Fixed recording which user has booked other users to a Facetoface session
    T-13130        Fixed exception when updating course categories with an empty ID Number
    T-13063        Removed excess obsolete entries from cohort rule table
    T-13081        Fixed Totara Sync failure when deleting users if duplicate ID Numbers exist


Release 2.6.9 (9th September 2014):
==================================================

Improvements:
    T-13001        Improved multilingual support for View Activity completion criteria

Bug Fixes:
    T-12872        Fixed appraisal stages so they can be completed on the final due day
    T-12978        Fixed label field in Feedback items to accept non-English characters
    T-12975        Fixed MSSQL error when viewing courses with linked evidence in a Learning Plan
    T-12669        Fixed ability of manager to add a previously-declined user to a Facetoface session
    T-13024        Fixed reportbuilder strings to meet AMOS requirements
    T-12985        Fixed setting of default user for course badge creators when restoring a course backup
    T-13010        Fixed the user join in Facetoface attendance exports
    T-12940        Fixed behaviour of Recipient Groups and Recipients when sending messages to users on a Facetoface session
    T-12988        Fixed Totara Sync incorrectly deleting existing users when the CSV source has invalid values
    T-12906        Fixed links to embedded reports in My Reports
    T-13014        Fixed missing type strings for translation in Facetoface session summary report source
    T-12893        Fixed the TinyMCE Editor fullscreen mode on IE8
    T-12989        Fixed reportbuilder caching for reports containing columns with incorrectly formatted date/time data
    T-12888        Fixed display of dates for Facetoface sessions on the Upcoming Events block
    T-12976        Fixed position of labels in right to left languages
    T-12946        Fixed the "Unlock and delete existing completion data" button to ensure criteria are unlocked
    T-12965        Fixed schema differences on upgrade from 2.5 to 2.6 with MSSQL


Release 2.6.8 (26th August 2014):
==================================================

Improvements:
    T-12943        Improved debugging on Audience rules tab

Database Upgrades:
    T-12581        Fixed database differences when upgrading from Totara 2.2 to 2.6

Bug Fixes:
    T-12910        Fixed required parameter checks on the edit scheduled reports page
    T-12950        Fixed Report builder caching so it doesn't break on MSSQL
    T-12917        Fixed wording of breadcrumbs when viewing learner details as a manager
    T-12767        Fixed backup and restore of Facetoface sessions without rooms
    T-12912        Fixed display of linked courses after adding a course to a competency
    T-12635        Fixed installation recovery if installation of Totara MSSQL module fails
    T-12776        Fixed incorrect error message being shown in Totara sync when an invalid source is selected
    T-12880        Fixed database error when deleting Programs
    T-12163        Fixed hover state on some form buttons in responsive themes
    T-12725        Fixed filtering by dates before January 1970 in Reportbuilder
    T-12511        Fixed get_roles_involved sql query when previewing an appraisal
    T-12895        Fixed missing username fields when viewing pending face-to-face session approvals
    T-12890        Fixed Totara Sync to allow spaces in directory paths
    T-12202        Fixed incorrect modal behaviour in dialogs when help icons are selected
    T-12938        Fixed Reportbuilder upgrade error when using MySQL
    T-12391        Fixed focus order of controls in the Calendar
    T-12904        Fixed Totara Sync to allow @ in directory paths

API Changes:
    T-12713        Enforce unique property if set when importing user custom profile fields with Sync


Release 2.6.7 (12th August 2014):
==================================================

Improvements:
    T-12735        Improved scalability for the program management page
    T-12311        Added checks for Program availability to the Program catalog
    T-12785        Added capability check to hide facetoface session attendees add/remove dropdown depending on permissions
    T-12876        Added forced cache purge on every upgrade

Bug Fixes:
    T-12778        Fixed Face-to-face calendar prev/next month display
    T-12502        Fixed sidebar filters in enhanced catalog when not logged in
    T-12224        Fixed alignment of framework dropdown in totara dialogs in the standard reponsive theme
    T-12464        Added error messages when importing course completion records with different grade on same day, user and course
    T-12799        Fixed filtering of course name through multilang filter for Certificates
    T-12694        Stopped reservation info being shown on session list page if reservations are turned off
    T-12874        Stopped notices being displayed on attendees page if no attendees are selected when add dialog is submitted
    T-12845        Fixed hardcoded column/filter section headings in Reportbuilder
    T-12835        Fixed quiz activities sending blank messages
    T-12786        Fixed course completion not working properly when another course is a prerequisite for completion
    T-12872        Fixed Appraisal Stages so they can be completed on the final due day
    T-12775        Disabled incorrect trust text usage
    T-12768        Fixed incorrect capacity and places totals in the Facetoface Summary report source
    T-12824        Fixed Certification id being incorrectly set in creation event objects
    T-12829        Fixed error on cron when a certification does not contain any courses


Release 2.6.6 (29th July 2014):
==================================================

Security issues:
    T-12619        Improved sesskey checks throughout the system
    T-12745        Improved capability checks around Reportbuilder scheduled reports
    T-12634        Fixed an issue with file downloads in Feedback360 and Appraisals
    T-12633        Fixed an issue with the session data when viewing/downloading a Certificate
    T-12632        Fixed an issue with token access for external Feedback360 requests

Improvements:
    T-12677        Improved error messages for Totara Sync
    T-12693        Improved validation checks around retrieving Programs within a category
    T-12099        Improved developer debugging in Reportbuilder
    T-12771        Added a SVG icon for Facetoface activities
    T-12561        Increased the maximum length of Hierarchy scale names and values for use with the multi-lang filter

Bug Fixes:
    T-12487        Fixed the type of assignment set when uploading completion records for Certifications
    T-12780        Fixed the formatting of dates when viewing a Badge
    T-12761        Fixed an undefined timezone issue in Reportbuilder caching
    T-12668        Fixed Programs potentially appearing multiple times in a user's Required Learning
    T-12403        Fixed the empty duration label when creating new events
    T-12720        Fixed an issue with filtering messages by icon in the Alerts block
    T-12576        Fixed the handling of epoch date for Reportbuilder date/time filters
    T-12621        Fixed the creation of file attachments in Facetoface
    T-11556        Fixed resolving Program exceptions through setting a realistic time
    T-12730        Fixed missing strings in Customtotara and Customtotararesponsive Themes
    T-12515        Fixed parameter names for manager rules in Dynamic Audiences
    T-12445        Fixed URL encoding in Hierarchies
    T-12283        Fixed docking for the Kiwifruitresponsive Theme
    T-12284        Fixed docking for the Kiwifruit Theme
    T-12675        Fixed the ordering of completion criteria for course completion reports
    T-12742        Fixed the downloading of a Badge image
    T-12717        Fixed an issue with the My Team report when adding temporary reports
    T-12731        Fixed the hardcoded 'Participants' string in Appraisals
    T-12737        Fixed the description for the Enable Audience-based Visibility setting
    T-12760        Added the default database collation in all tables including temporary tables


Release 2.6.5 (16th July 2014):
==================================================

Security issues:
    MoodleHQ       http://docs.moodle.org/dev/Moodle_2.6.4_release_notes
    T-12579        Fixed potential security risk in Totara Sync when using database sources

Improvements:
    T-12497        Improved internationalisation for the display of audience rules
    T-12547        Added validity checks to the position assignments form
    T-12591        Backported MDL-45985 new database schema checking script from Moodle 2.8
    T-10684        Added checks to prevent downgrades from a higher version of Moodle

Bug Fixes:
    T-12521        Fixed dynamic audiences not updating if the cohort enrolment plugin is disabled
    T-12203        Fixed reaggregation of Competencies when the aggregation type is changed
    T-12672        Fixed Totara Sync deleting users with no idnumber set
    T-12658        Fixed capabilities of Site Manager to enable them to create hierarchy frameworks
    T-11447        Fixed error on upgrade from Moodle to Totara
    T-12691        Fixed the sending of Stage Due messages in the Appraisal cron
    T-12567        Fixed the starting of new attempts for completed SCORMs which open in a new window
    T-12676        Fixed Totara Sync database source connections with non-alphanumeric passwords
    T-12636        Fixed addition of user middle name columns in Reportbuilder sources
    T-12524        Fixed the default facetoface reminder notifications failing to send
    T-12674        Fixed error when a user tries to show/hide columns in an embedded report
    T-12678        Fixed errors when using Totara Sync with database sources when position dates are text fields
    T-12710        Fixed display of users with no email addresses
    T-12588        Fixed Excel exports failing on some versions of PHP
    T-12299        Fixed appearance of docks in RTL languages
    T-11883        Fixed the multilang filter for goal and competency scales
    T-12623        Fixed the "view all" link in the record of learning and required learning sidebar
    T-12324        Fixed the formatting of date fields in Excel exports
    T-12545        Fixed deletion of associated data when deleting a facetoface notification
    T-12657        Fixed the padding for the body element in Internet Explorer
    T-12489        Fixed an issue with expanding a SCORM activity from the navigation block in a course


Release 2.6.4 (1st July 2014):
==================================================

Improvements:
    T-12605        Added logic to serve older versions of jquery to older versions of IE
    T-12497        Improved internationalisation for the display of audience rules
    T-12527        Added username of creator to Facetoface report and improved logging of attendees actions

Database Upgrades:
    T-12578        Added the ability to continue appraisals with missing roles
    T-11887        Fixed display of appraisals after a user has been deleted

Bug Fixes:
    T-12521        Fixed dynamic audiences not updating if the cohort enrolment plugin is disabled
    T-12538        Fixed category drop down selector not working correctly when creating programs
    T-12570        Fixed the sending of Program messages when completion is set relative to an action
    T-12479        Fixed the activate link incorrectly showing while viewing closed feedback360
    T-12509        Fixed historical course completion records not showing on the my team tab
    T-12563        Changed the default "temporary manager restrict selection" setting to "all users" for new installs
    T-12572        Added check to ensure generator columns can not be added to the same report multiple times
    T-12498        Fixed the display of custom field names for audience rules
    T-12156        Fixed cancellation message when F2F activity email notifications are turned off
    T-12571        Fixed the view hidden courses capability in the enhanced catalog
    T-12488        Fixed dynamic audiences showing on the 'add to audiences' option in bulk user actions
    T-12465        Fixed duplicate records issue when importing more than 250 course completion records
    T-12500        Fixed the incorrect use of urldecode function on page parameters
    T-12372        Fixed learning plan comments linking to the wrong components
    T-12387        Fixed the page title for program/certification searches
    T-12531        Fixed the formatting of the heading for facetoface attendance exports


Release 2.6.3 (17th June 2014):
==================================================

Database Upgrades:
    T-12541    Removed unused categoryid column from table prog_info_field
    T-12034    Fixed sending of Facetoface notifications where messages were not sent to every user in a session

Improvements:
    T-12466    Added 'Asia/Kolkota' lang string to timezone language pack
    T-12385    Added content filter to user reports to allow temporary managers to see their staff
    T-12530    Added room filter to Facetoface session view page
    T-12544    Added admin page to check current role capabilities against the installation defaults
    T-12494    Added ability to edit/delete evidence items created through course completion upload - Requires role with totara/plan:accessanyplan or totara/plan:editsiteevidence capabilities

Bug Fixes:
    T-12303    Fixed duplicated text on upgrade screen
    T-12431    Fixed setup of Totara-specific roles on new installs
    T-12263    Fixed Audience Visible Learning tab type selector
    T-12510    Fixed Audience language strings where cohort was still being used
    T-12162    Fixed custom fields from being both required and locked
    T-12534    Fixed sending of duplicated notifications without variable substitution in Program messages
    T-12491    Fixed Program Overview report to show correct Manager info
    T-12097    Fixed behaviour of Program content tab when javascript is disabled
    T-12519    Fixed certification pagination wrongly linking to programs
    T-12480    Fixed assigning of incorrect course IDs when approving a Learning Plan competency linked to a course
    T-12505    Fixed alignment of navigation elements in RTL languages in Kiwifruitresponsive theme
    T-12493    Fixed display of menu in RTL languages in Standardtotararesponsive theme
    T-12506    Fixed RTL arrow image on My Learning page in Kiwifruitresponsive theme
    T-12501    Fixed deprecated function warning when closing an Appraisal
    T-12513    Fixed display of Appraisal status code in Appraisal Summary report
    T-12512    Fixed column options in Appraisal Details report
    T-12492    Fixed Record of Learning Evidence report when using "show records based on users" option
    T-12526    Fixed PHP undefined property error in Record of Learning Evidence report
    T-12242    Fixed file saving on scheduled reports when "Export to filesystem" is disabled at site level
    T-12525    Fixed errors with Facetoface attendance report export to CSV
    T-12320    Fixed Facetoface iCal attachment line breaks in long descriptions
    T-11816    Fixed display of Articulate Storyline SCORMS in iPads - use new display setting of "New Window (simple)"


Release 2.6.2 (3rd June 2014):
==================================================

Security Fixes:
    T-12441    Fixed potential XSS vulnerability in quicklinks block

Improvements:
    T-11961    Added ability to assign Audience members based on position & organisation types
    T-12326    Extended execution time on completion reaggregation script
    T-12483    Added new alternate name fields when importing users with totara_sync
    T-12364    Improved contrast on Hierarchy selected items to meet Accessibility guidelines

Bug Fixes:
    T-12467    Fixed display of SCORM packages on secure HTTPS sites
    T-12463    Fixed critical SCORM error where subsequent attempts after an initial failed attempt are not recorded
    T-12471    Fixed display of grades in Course Completion Report for grades uploaded by completion import tool
    T-12444    Fixed course completion import report sometimes returning zero records
    T-12469    Fixed sending of notifications when a Facetoface booking date/time is changed
    T-12277    Fixed Face-to-face reminders still being sent to users who have cancelled from a session
    T-12121    Fixed transaction error when quiz completion triggers sending of messages
    T-12307    Fixed days not being translated in weekly scheduled reports
    T-12327    Fixed issue with dialog boxes being too wide for some screens
    T-12179    Fixed choosing of position on email self-registration when Javascript is disabled
    T-12263    Fixed Javascript for type filter dropdown in Audience Visibility
    T-12451    Fixed sort order of dependent courses in Course Completion settings
    T-12461    Fixed display of move and settings admin options for Quicklinks block
    T-12184    Fixed capitalisation of Program and Certification columns in Course Catalog
    T-12455    Fixed changing of visibility of a Certification on Audience Visible Learning tab
    T-12368    Fixed hidden labels in Hierarchy search dialog
    T-12371    Fixed alt attribute on course icons
    T-12362    Fixed alt and title attributes on competency icons
    T-12376    Fixed labels when creating a scheduled report in ReportBuilder
    T-12379    Fixed page title when deleting scheduled report
    T-12349    Fixed page title when deleting a Learning Plan
    T-12348    Fixed table column header on list of Learning Plans
    T-12237    Fixed HTML table in Alerts information popup dialog
    T-12473    Removed redundant get_totara_menu function in totara_core
    T-12478    Removed blink tag from element library


Release 2.6.1 (20th May 2014):
==================================================

Security Fixes:
    MoodleHQ    http://docs.moodle.org/dev/Moodle_2.6.3_release_notes

Improvements:
    T-12195    Improved error handling in F2F bulk add attendees
    T-12238    The alerts block is now a list instead of a table
    T-12313    Removed request approval button in Learning Plans while request is pending
    T-12375    Improved accessibility by combining links under My Reports
    T-12399    Improved look of the events filter on the calendar page
    T-12433    Show participants in appraisal overview page and pdf snapshots
    T-12201    Improved clarity of Audience Visibility language strings

Bug Fixes:
    T-12307    Fixed days not being translated in weekly scheduled reports
    T-12306    Added styling back into the program assignments page
    T-12017    Fixed alternate name fields for external badges
    T-12017    Fixed alternate name fields for trainer roles in face to face
    T-12017    Fixed alternate name fields on manager rules
    T-12234    Fixed highlight effect on Kiwifruit themes
    T-12446    Fixed display issue where save search button was overlaying column headers
    T-12326    Recover activity completion, grade and previous course completion data
    T-12246    Fixed course completion data reset for all users when a course is used as content in a certification
    T-12314    Fixed unknown column error when creating a program with multi_select custom field
    T-12434    The search and clear button on the find courses now are hidden immediately
    T-12278    Fixed facetoface attendance export not showing data if a users do not have a manager assigned to them
    T-12254    Fixed sort order of Facetoface attendees and requested users in Feedback360
    T-12248    Fixed SCORM redirect when it is opened in a new window
    T-12318    Fixed issue where custom field menus did not work as expected in responsive themes
    T-12310    Fixed display of custom field images in the enhanced catalog
    T-12153    Fixed the setting of users timecreated field when new users are created by Totara Sync
    T-12160    Fixed breadcrumbs when viewing staffs record of learning
    T-12204    Fixed incorrect error message being displayed when uploading huge files


Release 2.6.0.1 (7th May 2014):
==================================================

Bug Fixes:

    T-12880    Fix critical error causing deletion of course completion criteria data
    T-12149    Fix navigation menu when adding course custom fields


Release 2.6.0 (5th May 2014):
==================================================

New features:

T-7865    Allow recursive searches down the management hierarchy.
T-8592    Option to allow users to select their own organisation/position/manager during self-registration.
T-9736    Improve saved search interface.
T-9783    Allow manager to add a reason when declining/accepting learning plan and program extension requests.
T-10226   New report source for displaying face to face session information.
T-10239   Additional variables available in program messages.
T-10347   Relative date support for dynamic audience course/program completion rules.
T-10850   Ability to turn off face to face notifications at the site level.
T-10914   Ability for administrators to disable or hide certain functionality.
T-11067   Ability to assign system roles to all members of an audience.
T-11112   Totara sync now supports importing the 'emailstop' field.
T-11497   Ability to upload custom course/program icons.
T-11593   Enhanced Catalog with faceted search.
T-11593   Program custom fields now available.
T-11593   Report builder now supports sidebar filters, automatic results reloading and simple toolbar search options.
T-11593   New multi-select custom field type for hierarchy and course custom fields.
T-11597   Ability to mark face to face attendance in bulk.
T-11722   Organisation and position content restrictions added to appraisal reports.
T-11741   Managers can now reserve spaces in face to face sessions without naming the attendees. Thanks to Xtractor and Synergy Learning.
T-11752   New session start and end filters for the face to face sessions report source.
T-11879   Ability to force password changes for new users in Totara sync.
T-11988   Add report builder support to enrolment plugins. Thanks to Phil Lello from Catalyst EU.
T-11999   Add report builder embedded report support to plugins. Thanks to Phil Lello from Catalyst EU.
T-12109   Add links to completed stages on appraisal summary page.


2.6 Database schema changes:
============================

New tables:

Bug ID      New table name
--------------------------
T-11067     cohort_role
T-11593     course_info_data_param
T-11593     comp_info_data_param
T-11593     pos_info_data_param
T-11593     org_info_data_param
T-11593     goal_info_data_param
T-11593     prog_info_field
T-11593     prog_info_data
T-11593     prog_info_data_param
T-11593     report_builder_search_cols

New fields:

Bug ID      Table name                  New field name
------------------------------------------------------
T-9783      dp_plan_history             reasonfordecision
T-9783      dp_plan_competency_assign   reasonfordecision
T-9783      dp_plan_course_assign       reasonfordecision
T-9783      dp_plan_program_assign      reasonfordecision
T-9783      dp_plan_objective_assign    reasonfordecision
T-9783      prog_extension              reasonfordecision
T-11593     report_builder              toolbarsearch
T-11593     report_builder_filters      region

Other database changes:

T-11166     Report builder exportoptions converted from bitwise to comma separated list.
T-7865      Report builder settings updated: 'user_content', 'who' value switched from string to bitwise integer constant.
T-10914     Totara advanced feature settings migrated to new format.
T-11593     MSSQL group concat extension added. Due to requirement to install group concat plugin, MSSQL DB user requires additional permissions during install/upgrade: ALTER SETTINGS(SERVER)


2.6 API Changes:
================

== Enhanced catalog (T-11593) ==

* display_table() should now be always called, even if there are no rows in the results. This
  function will display a message if there are no rows to display. Remove "if ($countfiltered>0)"
  from embedded pages. This was done because the toolbar search is built into the display table
  header.

* Capability checks should be moved from embedded pages to is_capable() function in embedded
  classes. This function is called during the report constructor of embedded reports. If the
  is_capable method is not implemented then report builder assumes that the capabilities have
  not yet been recoded and will disable instant filters (instant filters go directly to the
  embedded class and bypass the embedded page, which is why the capability checks had to be moved).
  is_capable is passed the report object which can be used to access params, if required.

* rb_filter_type constructor and get_filter have been changed to include a region parameter. If any
  custom filter types have been added which define their own constructor method then they need to
  be updated to accept the additional parameter and pass it to the parent constructor. Any call
  get_filter must be updated (there are unlikely to be any custom calls to get_filter).

* get_extrabuttons() is a new function for embedded report sources that lets you specify a button or
  buttons to go in the top right of the table's toolbar. Simply override the inherited function in
  the desired report source and make it return the rendered output of any buttons you want to add.
  See the embedded catalog report sources for an example.

== Indirect reports patch (T-7865) ==

* The rb_content_option constructor method now accepts either a string or an array for the 3rd
  argument (previously it was just a string). The argument in
  totara/reportbuilder/classes/rb_content_option.php has changed from $field to $fields.

* To maintain backward compatibility, content options will still work with strings, so any custom
  content restrictions _do not_ need to be updated.

* However, the 'user' content option has been updated to pass additional information so any report
  sources that use the 'user' content option need to update the code.

Previously the code would look something like this:

             new rb_content_option(
                 'user',
                 get_string('users'),
                 '[TABLENAME].[FIELDNAME]'
             ),

Whereas now it must look like this:

             new rb_content_option(
                 'user',
                 get_string('users'),
                 array(
                     'userid' => '[TABLENAME].[FIELDNAME]',
                     'managerid' => 'position_assignment.managerid',
                     'managerpath' => 'position_assignment.managerpath',
                     'postype' => 'position_assignment.type',
                 ),
                 'position_assignment'
             ),

Where [TABLENAME] and [FIELDNAME] are typically something like 'base' and 'userid'.

The two key changes are the 3rd argument (where the string is replaced with the array with extra
data), and the 4th argument (where 'position_assignment' is added as a join). In the example above
there were no other joins (the 4th argument was empty). If there are already one or more join
options you will need to convert the 4th argument to an array and add 'position_assignment'. So if
the fourth argument was this:

'dp'

You would need to update to be:

array('dp', 'position_assignment')

Finally you need to make sure that the 'position_assignment' join is available. This can be done
with a line like this:

 $this->add_position_tables_to_joinlist($joinlist, 'base', 'userid');

in the define_joinlist() method. The 2nd and third arguments should reference a table and field used
above for [TABLENAME] and [FIELDNAME].


== Changes to Totara email user function (T-12077) ==

The function totara_generate_email_user() is now deprecated. Update references to use:
\totara_core\totara_user::get_external_user() instead.

== Deprecation of 'standardtotara' theme ==

In Totara 2.6 the 'standardtotara' theme is deprecated in favour of 'standardtotararesponsive'.
'standardtotara' is still present in 2.6 but will be removed in 2.7.

See this guide for how to migrate your 2.5 theme to 2.6:

http://community.totaralms.com/mod/resource/view.php?id=1869

== MSSQL only ==

Now require additional permissions to install:

MSSQL DB user required additional permissions: ALTER SETTINGS(SERVER)

This is due to requirement to install group concat plugin.

*/
?>
