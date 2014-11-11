Google Fusion Tables export for Grades
-------------------------------------------------------------------------------
license: http://www.gnu.org/copyleft/gpl.html GNU Public License

Changes:
- 2012-10    : Created by Piers Harding

Requirements:
- None

Notes:

Install instructions:
  1. unpack this archive into the / directory as you would for any Moodle
     auth module (http://docs.moodle.org/en/Installing_contributed_modules_or_plugins).
  2. To configure grade/export/fusion you need to navigate to Site Administration -> Grades -> Export Settings -> Fusion Table Export
    or
       <your moodle root>/admin/settings.php?section=gradeexportfusion
       Follow the instructions about setting up the Google API  - ensure
       that the Google API configuration allows the Fusion Tables access

generic help on OAuth for Moodle is available here:
http://docs.moodle.org/25/en/Google_OAuth_2.0_setup

The Google API configuration workbench is here:
https://code.google.com/apis/console#access

Ensure that the redirects URI is set to:
<Your Moodle base URL>/admin/oauth2callback.php

