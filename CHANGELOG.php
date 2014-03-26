<?php
/*

Totara LMS Changelog

Release 2.5.9 (4th March 2014):
==================================================

Improvements:
    T-11874    Enabled multimedia filter in Appraisal stage descriptions and fixed-text questions

Bug Fixes:
    T-11344    Fixed RPL not marking activities as complete
    T-11855    Deleted users are now excluded when certification stages are calculated
    T-11739    Fixed the handling of non UTF-8 encoded file uploads in Totara Sync
    T-11865    Fixed 'In progress' state not being set in certifications when cron is run
    T-11869    Fixed parameter validation for tm_message_send in Messaging
    T-11843    Fixed the wrong date being set in the firstaccess field when users first log in
    T-11886    Fixed the manage feedback link in the site administration block checking appraisals capabilities
    T-11665    Tasks and alerts blocks now respect the "show blocks when empty" setting
    T-11880    Fixed header tabs wrap when screen resolution is between 769px and 1050px
    T-11875    Fixed program assignment completion relative to program enrolment
    T-11831    Fixed PHP notice for Feedback Summary report in Report Builder
    T-11838    Fixed position start date rule in dynamic Audiences
    T-11836    Fixed wrong csv encoding type being set when uploading completion records for Courses/Certifications
    T-11860    Fixed a missing setType in report export form
    T-11857    Fixed Facetoface notification templates not saving 'Manager copy' and 'Status' values
    T-11797    Fixed nullability of positionid field in prog_pos_assignment to prevent errors on upgrade from 1.1 to 2.2
    T-11832    Fixed 'Set time relative to event' option not saving different instances of the same event
    T-11848    Fixed Facetoface room booking when one session finishes and other starts at the same time
    T-11833    Fixed issue where core totara themes weren't referencing image.php correctly
    T-11846    Fixed permissions checks on the Facetoface attendees page


Release 2.5.8 (18th February 2014):
==================================================

Security Fixes:
    T-11765    Set lockout threshold by default to help protect against brute force password attempt

Improvements:
    T-11596    Added capability to filter by some default fields in Facetoface calendar
    T-11780    Marked expired certifications due date as overdue
    T-11828    Avoided ajax call when changing rules in Audience that use radiobuttons
    T-11722    Made org and pos content restrictions consistent across report sources
    T-11786    Improved appearance of tables with headers/toolbars in responsive theme
    T-11506    Added Time signed up to attendees tab for Facetoface

Bug Fixes:
    T-10885    Fixed Learning Plan enrolment plugin to ensure it enrols learners in course
    T-11777    Allow wait-listed users to cancel Facetoface bookings if session is over or in progress
    T-11237    Fixed drop down list forgetting previously saved values in program/certification assignments
    T-11392    Fixed second level tabs overlapping the following text in Kiwifruit theme
    T-11815    Fixed fatal error when grading a quiz triggers completion notification messages
    T-10488    Fixed "reports to manager" Audience rule
    T-11677    Ensure parent page refresh to start completion checks when SCORM opens as popup
    T-11755    Fixed course completion upload when multiple records for one user exist in CSV file
    T-11825    Fixed appearance of popup windows in Kiwifruit theme
    T-11826    Fixed issue where tabs could appear above modal dialogs
    T-11798    Remove suspended users from any prior Facetoface bookings
    T-11791    Fixed issue where menu button didn't display the menu correctly on the iPhone in Totara responsive theme
    T-11308    Made extra-field aliases unique to avoid name collisions in Report Builder
    T-11793    Re-added program administration, course reminders, and course competencies navigation
    T-11784    Fixed issue where the Custom Totara theme wasn't inheriting the base renderer
    T-11802    Fixed Customtotara Theme issue where certain background colours could make headings hard to read
    T-11789    Added missing user fields to RoL Certification and Program report sources
    T-11795    Fixed issue with notice dialog buttons in Totara responsive theme
    T-11790    Fixed incorrect embedded url in RoL Programs Completion History report


Release 2.5.7 (4th February 2014):
==================================================

Security Fixes:
    T-11785    Cleaned modulename parameter before generating coursemodule instances
    T-11783    Added missing validation when exporting calendar
    T-11782    Added missing validation when managing user tags
    T-11766    Prevent admins editing system paths without a specific config setting in new installs only

Improvements:
    T-11746    Changed the totara sync behaviour around user passwords, see here http://community.totaralms.com/mod/forum/discuss.php?d=3708

Bug Fixes:
    T-11787    Fixed typo in facetoface session to show finish date properly
    T-11639    Fixed the display of tab trees containing a second row
    T-11775    Prevent new "Before Session" facetoface notifications from being sent retroactively
    T-11772    Fixed fatal error on completion with multiple dependant courses
    T-6072     Fixed whitespace formatting for CLI installs
    T-11729    Timezone information is now displayed for Facetoface sessions in the calendar
    T-11764    Fixed display of custom field names when multi-language filter is enabled in reportbuilder
    T-8008     Fixed staff manager capabilities on new installs
    T-11366    Fixed delete buttons after adding users to feedback360
    T-11781    Excluded suspended users from facetoface booking selector
    T-11776    Fixed Responsive Theme issue when adding rules to an audience
    T-11778    Fixed language string escaping on Program Assignments tab

API Changes:
    T-11779    Moved tables header code to totaratablelib.php


Release 2.5.6 (21st January 2014):
==================================================

Security Fixes:
    MoodleHQ   http://docs.moodle.org/dev/Moodle_2.5.4_release_notes

Improvements:
    T-10159    Course completion is now turned on by default on an upgrade from Moodle
    T-11736    Made Facetoface session date/time display on course page more logical
    T-11742    Added setting to override current course completions in course imports
    T-11735    Made the view sessions link inactive when the activity is unavailable to learners
    T-11743    Added human-readable text version of hierarchy paths in reportbuilder reports

Bug Fixes:
    T-11641    Fixed hardcoded string in required learning when program uses "or" coursesets
    T-11754    Fixed the display of grades in the Course Completion Report for courses completed via RPL
    T-11748    Fixed open recertification windows always being marked as overdue
    T-11760    Prevent deleted users' assignments from being synced
    T-11758    Fixed facetoface attendees exports and formatting of datetime custom fields
    T-11221    Fixed recurring courses copying grades into a new course
    T-11759    Fixed component pagination in Learning Plans
    T-11699    Fixed capability error when teachers click on Other Users in course enrolment
    T-11757    Fixed custom field textareas in reports when field id is greater than 9
    T-11747    Fixed course backup/restore to include the "completionstartonenrol" setting
    T-10346    Fixed managers email being left blank when exporting Facetoface attendance
    T-11656    Fixed permission warnings on some feedback360 capabilities
    T-11021    Fixed RTL issues on Badges pages

API Changes:
    T-11762    Moved positions navigation code out of Moodle Core


Release 2.5.5 (7th January 2014):
==================================================

Improvements:
    T-10914    Added totara settings to hide certain functionalities

Bug Fixes:
    T-11661    Fixed version number in totara core
    T-11527    Fixed config variable not being set when upgrading from an earlier version of Totara 2.4.8 - Affecting rules based on text in dynamic audiences
    T-11726    Allow suspended field in user source to be left blank in Totara Sync
    T-11729    Timezone information is now displayed for Facetoface sessions in the calendar
    T-11709    Fixed certification completion upload when multiple records for one user exist in CSV file
    T-11696    Fixed discrepancy between Facetoface Attendance and Export Attendance Reports


Release 2.5.4 (24th December 2013):
==================================================

New features:
    T-9788    Added "Program enrollment date" event to the program completion criteria options

Improvements:
    T-11669    Made the "Date Created" field editable for learning plans
    T-11703    Expanded help relating to course and program visibility
    T-11708    Added new optional column "preferred language" to reportbuilder user report source

Bug Fixes:
    MDL-34481  Ensure that completion is recalculated on next cron run following changes to completion settings
    T-11725    Backport MDL-43019 to fix IE11 crashes in SCORM
    T-11231    Fixed an issue with certification completion records missing if user is unassigned
    T-11695    Program report source now conforms to audience visibility
    T-11705    Fixed potential call to nonexistent function while updating program assignments
    T-11719    Fixed broken appraisal activation when secondary or aspirational positions are used
    T-11659    Fixed "Goal Assignments" title duplication when assigning a position, organisation or audience
               competency to a goal
    T-11707    Fixed "Assigned Goals" section duplication when adding goals and competencies to an organisation
    T-11607    Fixed dates when importing certifications using the historic import
    T-11710    Fixed the "CC managers" setting for facetoface notifications
    T-11245    Removed hover style in list of courses/programs
    T-11688    Removed link to blogs when they are disabled
    T-11684    Certification completion date is now used for expiry and recertification window calculations
    T-11702    Minor fixes related to visibility of programs and certifications
    T-11713    Fixed completion import when certification shortname contains an ampersand
    T-11674    Fixed user profile date fields being set to 1970 when disabled
    T-11629    Fixed emails not being sent when manually resolving Program exceptions
    T-5879     Fixed typos in English strings in 360 Feedback, Audiences, and Hierarchy
    T-11731    Fixed broken certification unit tests in MySQL with sql_auto_is_null setting enabled


Release 2.5.3 (10th December 2013):
==================================================

Improvements:
    T-11422    Made the audience visibility column editable for Audiences visible learning tabs
    T-11658    Completions upload reports now show all records instead of just errors
    T-11693    Improved required learning courseset operators to allow for longer string translations
    T-11675    Added additional warnings on the archive completions confirmation page

Database Upgrades:
    T-11682    Changed program/certification completion times to be based off course completion
    T-11670    Added coursename variable to facetoface notifications and fixed the labelling of facetofacename variables
    T-11680    Fixed bug where assigning a manager to a secondary position could affect primary positions

Bug Fixes:
    T-11607    Fixed dates when importing certifications using the historic import
    T-11671    Fixed the handling of blank values for date/time custom fields in Totara Sync
    T-11614    Fixed the framework selector for appraisal and feedback360 assignment dialogs
    T-11622    Fixed goal permissions being applied inconsistently
    T-11690    Fixed a string not being translated correctly on the required learning page
    T-11673    Fixed access to reports when site roles have been renamed
    T-11569    Fixed the required course grade for languages using different characters for the decimal point
    T-11687    Fixed textarea and file custom course fields not displaying in report builder
    T-11629    Fixed programs sending duplicate enrolment messages when changing due date between set and relative
    T-11552    Fixed icals not opening automatically in Outlook 2010
    T-11534    Fixed facetoface calendar entries not taking course visibility into account
    T-11657    Fixed role name missing in security overview report
    T-11650    Fixed access to facetoface approvals page with guest access enabled
    T-11608    Fixed "Restrict initial display" option not being applied in embedded reports
    T-11603    Fixed rendering issue in appraisal snapshots
    T-11685    Fixed facetoface notifications form elements not saving when unchecked
    T-11661    Prevent password reuse if rotation limit setting enabled
    T-11681    Fixed ability to view courses within required learning for learners
    T-11672    Fixed hardcoded string in the completion imports success message

API Changes:
    T-11662    Added a database event to delete related position assignments when a user is deleted


Release 2.5.2 (26th November 2013):
==================================================

API Changes:
    T-10928    See http://community.totaralms.com/mod/forum/discuss.php?d=3507 for more details
    T-11651

Improvements:
    T-11594    Improved hidden courses display in course overview block
    T-11537    Added an option to enable/disable Totara sync field in bulk user actions
    T-11634    Added content restriction options to goal and appraisal report sources
    T-11105    Report builder source files are now available in 'Language customisation'
    T-11566    Made 'My Reports' and 'My Bookings' pages editable so that blocks can be added

Bug Fixes:
    T-11606    Added a check to prevent duplicate certification assignments when uploading completion history
    T-11321    Session info is now included in notification emails when Face-to-face details are updated
    T-11505    Fixed error when inserting more than 1000 records into the cohort_msg_queue table for MSSQL
    T-11546    Fixed exceptions if the user is already assigned to program
    T-11647    Removed confirmation message when signing up for Face-to-face session with 'No email' option
    T-11636    Fixed undefined variable warning on course view when Audience visibility is enabled
    T-11621    Fixed image handling in textarea custom fields
    T-11540    Fixed display of minimum time required on required learning pages
    T-10726    Changed page editing to happen via edit button on 'My Team' page
    T-11619    Fixed 'Completion import: Certification status' report URL
    T-11618    Fixed company goals link redirecting to wrong place on 'My Goals' page
    T-11620    Fixed use of MySQL reserved word in database query in Goals
    T-5879     Fixed leading spaces in reportbuilder source language file


Release 2.5.1 (12th November 2013):
==================================================

Security fixes:
    Fixes from MoodleHQ http://docs.moodle.org/dev/Moodle_2.5.3_release_notes

Improvements:
    T-11456    Improved appearance of heading rows in flexible tables
    T-11449    Improved message when no file selected for custom fields
    T-11604    Moved toolbar above the column headers in totara tables
    T-11581    Only show available databases in Totara Sync external database settings

Bug Fixes:
    T-11616    Fixed goal review questions in appraisals when using postgreSQL
    T-11592    Facetoface session info is shown on the course view page again
    T-11508    Fixed user profile field rules for MSSQL Server
    T-11610    Fixed failing log inputs during program extension requests
    T-11315    Added description and type to the my goals page when showing details
    T-11551    Fixed facetoface signup notification types
    T-10444    Fixed reminderperiod substitution which was not occurring in face to face notifications
    T-11588    Excluded suspended users from all user selectors
    T-11590    Fixed character escaping in feedback360 and appraisals assignments javascript setup
    T-11574    Fixed error when upgrading via command line
    T-11575    Added new completion setting fields to course backup/restore
    T-11558    Deleted users are once again shown on browse list of users page
    T-11579    Fixed permissions for managing course custom fields
    T-11567    Fixed javascript error on notification page
    T-11599    Changed button icon for topics add/remove sections in courses
    T-11582    Fixed 'Standard Totara Responsive' theme problem when using upload completion records options
    T-11559    Prevent people requesting 360 feedback from suspended users
    T-11533    Cleaned up course_modules_completion table when course id deleted
    T-10832    Fix icon picker to account for slash arguments setting
    T-11571    Fixed fatal error on request feedback page when javascript is turned off
    T-11583    Fixed upgrade from Totara 2.4 to Totara 2.5 when a site has external backpacks connected
    T-11479    Only show delimiter selector on CSV sources in Totara Sync
    T-11564    Fixed format hint string next to date pickers on audience editing page

API Changes:
    T-11560    Removed optional $certifpath param from course_set and multi_course_set class constructors


Release 2.5.0 (31st October 2013):
==================================================

Initial release of Totara 2.5

Totara 2.5 introduces the following new features:

* Performance Management
  * Create company wide and personal goals and track progress towards
    meeting those goals.
  * Create custom appraisal forms and assign them to groups of users.
  * Track appraisal progress with summary and detailed reports.
  * Create 360 feedback forms and assign them to groups of users.
  * Allow users to monitor 360 feedback they have received.
    (thanks to GKN and BMI for part funding this work)

* Certifications
  * Ability to create "Certifications", which are Programs that can expire
    after a set time and can be retaken.
  * Manage expiration periods and recertification windows.
  * Supports recertification paths when recertification involves completion
    of a different set of courses to the original certification path.
  * Ability to reset certain activities within courses to support the same
     user taking a course multiple times.
  * A course completion history report which shows previous completion attempts.
  * Course completion import tool for uploading legacy completion data.
    (thanks to BMI for funding this work)

* Audience Visibility
  * Provides an alternative way of managing course and program visibility across
    the whole site.
  * Allows courses and programs to be made visible and accessible to specific
    audiences only.
    (thanks to Kineo US and Kohls for the original patch and Learning Pool for help
    with integration)

* Course catalog changes
  * Changes to the appearance of the course catalog to integrate recent improvements from
    Moodle.

* Manager Delegation
  * Allows a user's manager role to be temporarily delegated to another user.
  * A time limit determines when the temporary assignment is automatically revoked.
    (thanks to Catalyst IT for the original patch)

* Program Progress report source
  * View program completion status and the course completion status of each individual
    course that makes up the program in a single integrated report.
    (thanks to Catalyst IT and Bodo Hoenen from Social Learning Project for the original patch)

* Report builder PDF export
  * All report builder reports now include the option to export to PDF in either
    portrait or landscape mode.
    (thanks to Michael Gwynne from Kineo UK for the original patch)

* Customisable report builder filter names
  * Report creators can customise the names of filters which controls the label
    that appears next to the filter on the report page.

* Relative date support in report builder date filters
  * Report builder date filters now allow relative date ranges such as
   "in the last 3 days".
    (thanks to Jamie Kramer from E-learning Experts for the original patch)

* Instant course and program completion
  * Instead of waiting for the hourly or daily cron, course and program completions
    are now calculated instantly.

* Email notification settings in Totara Sync
  * Administrators can receive emails when there are warnings or errors during
    syncing.

* Experimental Responsive Totara theme for mobile devices
  * A new Totara theme based of the 'bootstrap' base theme designed to scale the
    site to work on any device size.
    This theme is still experimental at this stage, we plan to improve it via 2.5
    point releases and fully support it in Totara 2.6.

* New option to mark face-to-face activity completion based on signup status rather
  than grade. Note: This option also uses the session time as the completion date for
  course and activity completion (rather than the time attendance is marked).

* More minor improvements including:
  * T-11182 New capabilities for managing hierarchy scales.
  * T-11493 New capability for managing write access to idnumber field.
  * T-10930 More fine-grained control over create/update/delete actions within Totara sync.
  * T-11049 Make all program messages optional.
  * T-9833  Improved styling of program management pages.
  * T-11387 Added new web service to store mobile device data for push notifications.

This release updates Totara to be based on Moodle 2.5, which includes the following improvements:

* New admin tool for installing add-ons.
* Transparency and RGB support in the themes colour picker.
* Collapsable form sections to improve the usability of large forms.
* Reduced the size of description fields and simplified the default editor.
* Search the list of users enrolled in a course.
* New assignment settings for handling resubmissions.
* Option to auto-save during quiz attempts.
* Option to drag and drop media files or text onto a course page to create a label.
* Option to display course summary files in course listings.
* View and edit catalog now separated.
* Performance improvements, particularly greater use of the Moodle Universal Cache (MUC).
* Improved security of hashed passwords (Totara contribution to Moodle).
* New user account lockout mechanism.
* Behat test framework integration.
* Progress indicator when dragging files into the filepicker.

For more details on the Moodle changes see:

http://docs.moodle.org/dev/Moodle_2.5_release_notes
http://docs.moodle.org/dev/Moodle_2.5.1_release_notes
http://docs.moodle.org/dev/Moodle_2.5.2_release_notes

See INSTALL.txt for the new system requirements for 2.5.


2.5 Upgrade notes:
==================

2.5 introduces a new, more secure password hashing scheme. If you are upgrading from 2.4 via the web interface ensure
you are logged into you 2.4 site as a site administrator prior to replacing the codebase.

As always make sure you replace the whole code directory rather than copying 2.5 files on top of the 2.4 code. See
UPGRADE.txt for more details.


2.5 API Changes:
================

* Moodle API changes as detailed in http://docs.moodle.org/dev/Moodle_2.5_release_notes#For_developers:_API_changes
* T-11133 Kiwifruit theme will no longer display totara menu bar or breadcrumbs until you are logged in.
* T-11202 Unenrolling users from a course will unenrol them from any future face to face courses they were booked in.
* T-11258 Code changes to the way embedded reports are set up - see comments in the reportbuilder class constructor.
* T-9833  Changes to code to support improved styling of program management. Custom themes may need updating.
* T-11493 Enforcing uniqueness on most 'idnumber' fields (All totara ones plus the user idnumber from Moodle). You
*         will not be able to upgrade to 2.5.0 if you have any duplicates in existing sites.
* T-10878 Removed a number of unused database fields and tables.
*         Removed unused functions organisation_backup() and related functions.
*         Remove unused $exceptiondata argument from insert_exception() and raise_exception() functions.
* T-11182 Added new hierarchy scale capabilities and reworked existing hierarchy capability checks to make them more
*         independent of each other.
* T-10107 New optional arguments in totara_cohort_add_association(), totara_cohort_delete_association() and
*         totara_cohort_get_associations() to support audience visibility.
*         Updated totara_cohort_check_and_update_dynamic_cohort_members() to use progress_trace object instead of
*         boolean verbose flag.
*         Removed unused function totara_print_main_subcategories().
* T-9833  Added optional $fieldset argument to totara_add_icon_picker() to allow icon picker without separate fieldset.
* T-9390  New optional arguments on totara_get_manager() to support more options related to temp managers.
* T-11158 Changed 2nd argument of totara_get_category_item_count() from bool to string to support certifications.
*         Added $certifpath argument to get_total_time_allowance() and get_courseset_groups().
*         Added optional $iscertif and $certifpath arguments to get_content_form_template().
*         New $certificationpath argument to display_edit_assignment_form().
*         Renamed incorrectly named $userpic argument to $user in display_user_message_box().
* T-11182 Add 2 new permission related arguments to customfield_edit_icons().
*         Removed unused 2nd argument from competency_scale_display_table().
* T-11309 Added $type argument to prog_get_programs().
*         Added $type argument and removed two $where arguments from prog_get_programs_search().
* T-11102 Removed unused functions prog_print_programs(), prog_print_program(), prog_print_whole_category_list(),
*         prog_print_category_info(), prog_print_viewtype_selector() and print_program().
* T-11279 get_competency_courses() function visibility changed from private to public.
* T-11049 Add $newprogram boolean argument to prog_messages_manager class constructor.
*
*/
?>
