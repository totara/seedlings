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
 * @author       Simon Coggins <simon.coggins@totaralms.com>
 * @package      theme
 * @subpackage   standardtotara
 * @license      http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$mypagetype = $PAGE->pagetype;
$modname = isset($PAGE->cm->modname) ? $PAGE->cm->modname : 'unknown';
$sitesummary = isset($SITE->summary) ? $SITE->summary : '';
$bodyclasses = array('popup','content-only', $modname);


if (!empty($PAGE->theme->settings->favicon)) {
    $faviconurl = $PAGE->theme->setting_file_url('favicon', 'favicon');
} else {
    $faviconurl = $OUTPUT->favicon();
}

echo $OUTPUT->doctype() ?>
<html <?php echo $OUTPUT->htmlattributes() ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta name="generator" content="<?php echo get_string('poweredby', 'totara_core'); ?>" />
    <meta name="description" content="<?php p(strip_tags(format_text($sitesummary, FORMAT_HTML))) ?>" />
    <link rel="shortcut icon" href="<?php echo $faviconurl; ?>" />
    <?php echo $OUTPUT->standard_head_html(); ?>
</head>

<body <?php echo $OUTPUT->body_attributes($bodyclasses); ?>>
<?php echo $OUTPUT->standard_top_of_body_html() ?>
<?php if ($modname != 'scorm') {echo("<div id='popup-page'>");} ?> <!--for adding additional formatting div for non scorm pages-->
    <!-- END OF HEADER -->
    <div id="popup-content">
            <?php echo $OUTPUT->main_content(); ?>
    </div>
    <!-- START OF FOOTER -->
<?php if ($modname != 'scorm') {echo("</div>");}?> <!--close additional formatting div-->
<?php echo $OUTPUT->standard_end_of_body_html(); ?>
</body>
</html>
