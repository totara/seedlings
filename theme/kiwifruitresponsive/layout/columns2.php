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
 * @author Brian Barnes <brian.barnes@totaralms.com>
 * @package totara
 * @subpackage theme
 */

if (!empty($PAGE->theme->settings->favicon)) {
    $faviconurl = $PAGE->theme->setting_file_url('favicon', 'favicon');
} else {
    $faviconurl = $OUTPUT->favicon();
}

$custommenu = $OUTPUT->custom_menu();
$hascustommenu = !empty($custommenu);

$haslogininfo = (empty($PAGE->layout_options['nologininfo']));
$showmenu = empty($PAGE->layout_options['nocustommenu']);
$hasfooter = (empty($PAGE->layout_options['nofooter']));

if ($showmenu && !$hascustommenu) {
    // load totara menu
    $menudata = totara_build_menu();
    $totara_core_renderer = $PAGE->get_renderer('totara_core');
    $totaramenu = $totara_core_renderer->print_totara_menu($menudata);
}

$kiwifruitheading = $OUTPUT->kiwifruit_header();

$left = (!right_to_left());  // To know if to add 'pull-right' and 'desktop-first-column' classes in the layout for LTR.
echo $OUTPUT->doctype() ?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $faviconurl; ?>" />
    <?php echo $OUTPUT->standard_head_html() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body <?php echo $OUTPUT->body_attributes('two-column'); ?>>
<?php echo $OUTPUT->standard_top_of_body_html(); ?>
<!-- START OF HEADER -->
<?php echo $kiwifruitheading ?>
<div id="page">
  <div id="wrapper" class="clearfix">

    <div id="navbar" class="clearfix">
        <div class="breadcrumb"><?php echo $OUTPUT->navbar(); ?></div>
        <nav class="breadcrumb-button"><?php echo $OUTPUT->page_heading_button(); ?></nav>
    </div>
    <div id="course-header">
        <?php echo $OUTPUT->course_header(); ?>
    </div>

    <div id="page-content" class="row-fluid">
        <section id="region-main" class="span9<?php if ($left) { echo ' pull-right'; } ?>">
            <?php
            echo $OUTPUT->page_heading();
            echo $OUTPUT->course_content_header();
            echo $OUTPUT->main_content();
            echo $OUTPUT->course_content_footer();
            ?>
        </section>
        <?php
        $classextra = '';
        if ($left) {
            $classextra = ' desktop-first-column';
        }
        echo $OUTPUT->blocks('side-pre', 'span3'.$classextra);
        ?>
    </div>
  </div>
</div>
<?php if (!empty($coursefooter)) { ?>
<div id="course-footer"><?php echo $coursefooter; ?></div>
<?php } ?>

<?php if ($hasfooter) { ?>
  <div id="page-footer">
    <div class="footer-content">
      <?php if ($hascustommenu) { ?>
      <div id="custommenu"><?php echo $custommenu; ?></div>
      <?php } else { ?>
      <div id="totaramenu"><?php echo $totaramenu; ?>
  <div class="clear"></div>
  </div>
      <?php } ?>
      <div class="footer-powered"><a href="http://www.totaralms.com/" target="_blank"><img class="logo" src="<?php echo $CFG->wwwroot.'/theme/'.$PAGE->theme->name ?>/pix/logo-ftr.png" alt="Logo" /></a></div>
    <div class="footer-backtotop"><a href="#">Back to top</a></div>
      <div class="footnote">
        <div class="footer-links">
        </div>
      </div>
      <?php
      echo $OUTPUT->standard_footer_html();
      ?>
    </div>
  <div class="clear"></div>
  </div>
<?php } ?>


    <?php echo $OUTPUT->standard_end_of_body_html() ?>

</body>
</html>
