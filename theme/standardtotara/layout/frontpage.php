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

$hasheading = $OUTPUT->page_heading();
$hasnavbar = (empty($PAGE->layout_options['nonavbar']) && $PAGE->has_navbar());
$hasfooter = (empty($PAGE->layout_options['nofooter']));
$haslogininfo = (empty($PAGE->layout_options['nologininfo']));
$haslangmenu = (!isset($PAGE->layout_options['langmenu']) || $PAGE->layout_options['langmenu'] );

$hassidepre = $PAGE->blocks->region_has_content('side-pre', $OUTPUT);
$hassidepost = $PAGE->blocks->region_has_content('side-post', $OUTPUT);

$showsidepre = $hassidepre && !$PAGE->blocks->region_completely_docked('side-pre', $OUTPUT);
$showsidepost = $hassidepost && !$PAGE->blocks->region_completely_docked('side-post', $OUTPUT);

$custommenu = $OUTPUT->custom_menu();
$hascustommenu = (empty($PAGE->layout_options['nocustommenu']) && !empty($custommenu));

$bodyclasses = array();
if ($showsidepre && !$showsidepost) {
    if (!right_to_left()) {
        $bodyclasses[] = 'side-pre-only';
    } else {
        $bodyclasses[] = 'side-post-only';
    }
} else if ($showsidepost && !$showsidepre) {
    if (!right_to_left()) {
        $bodyclasses[] = 'side-post-only';
    } else {
        $bodyclasses[] = 'side-pre-only';
    }
} else if (!$showsidepost && !$showsidepre) {
    $bodyclasses[] = 'content-only';
}

if (!empty($PAGE->theme->settings->logo)) {
    $logourl = $PAGE->theme->setting_file_url('logo', 'logo');
} else {
    $logourl = $OUTPUT->pix_url('logo', 'theme');
}

if (!empty($PAGE->theme->settings->favicon)) {
    $faviconurl = $PAGE->theme->setting_file_url('favicon', 'favicon');
} else {
    $faviconurl = $OUTPUT->favicon();
}

$sitesummary = isset($SITE->summary) ? $SITE->summary : '';

$hasframe = !isset($PAGE->theme->settings->noframe) || !$PAGE->theme->settings->noframe;


if (!$hascustommenu) {
    $menudata = totara_build_menu();
    $totara_core_renderer = $PAGE->get_renderer('totara_core');
    $totaramenu = $totara_core_renderer->print_totara_menu($menudata);
}

// ensure X-UA-Compatible is before favicon meta tag to ensure compatibility mode is disabled
echo $OUTPUT->doctype() ?>
<html <?php echo $OUTPUT->htmlattributes() ?>>
<head>
<title><?php echo $OUTPUT->page_title() ?></title>
<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
<meta name="generator" content="<?php echo get_string('poweredby', 'totara_core'); ?>" />
<meta name="description" content="<?php p(strip_tags(format_text($sitesummary, FORMAT_HTML))) ?>" />
<link rel="shortcut icon" href="<?php echo $faviconurl; ?>" />
<?php echo $OUTPUT->standard_head_html() ?>
</head>
<body <?php echo $OUTPUT->body_attributes($bodyclasses); ?>>
<?php echo $OUTPUT->standard_top_of_body_html() ?>
<div id="page">
  <div id="wrapper" class="clearfix">

<!-- START OF HEADER -->

    <div id="page-header" class="clearfix">
      <div class="page-header-inner">
        <div id="page-header-wrapper" class="clearfix">
          <?php if ($logourl == NULL) { ?>
          <div id="logo"><a href="<?php echo $CFG->wwwroot; ?>">&nbsp;</a></div>
          <?php } else { ?>
          <div id="logo" class="custom"><a href="<?php echo $CFG->wwwroot; ?>"><img class="logo" src="<?php echo $logourl;?>" alt="Logo" /></a></div>
          <?php } ?>
          <div class="headermenu">
            <?php if ($haslogininfo || $haslangmenu) { ?>
              <div class="profileblock">
                <?php
                if ($haslogininfo) {
                    echo $OUTPUT->login_info();
                }
                if ($haslangmenu) {
                    echo $OUTPUT->lang_menu();
                }
                ?>
              </div>
            <?php } ?>
          </div>
        </div>
        <div id="main_menu" class="clearfix">
          <?php if ($hascustommenu) { ?>
          <div id="custommenu"><?php echo $custommenu; ?></div>
          <?php } else { ?>
          <div id="totaramenu"><?php echo $totaramenu; ?></div>
          <?php } ?>
        </div>
      </div>
    </div>

<!-- END OF HEADER -->

<!-- START OF CONTENT -->

    <div id="page-content-wrapper">
      <div id="page-content">
        <div id="region-main-box">
          <div id="region-post-box">
            <div id="region-main-wrap">
              <div id="region-main">
                <div class="region-content"> <?php echo core_renderer::MAIN_CONTENT_TOKEN ?> </div>
              </div>
            </div>
            <?php if ($hassidepre || (right_to_left() && $hassidepost)) { ?>
            <div id="region-pre" class="block-region">
              <div class="region-content">
                <?php
                    if (!right_to_left()) {
                        echo $OUTPUT->blocks('side-pre');
                    } else if ($hassidepost) {
                        echo $OUTPUT->blocks('side-post');
                    }
                ?>
              </div>
            </div>
            <?php } ?>
            <?php if ($hassidepost || (right_to_left() && $hassidepre)) { ?>
            <div id="region-post" class="block-region">
              <div class="region-content">
                <?php
                    if (!right_to_left()) {
                        echo $OUTPUT->blocks('side-post');
                    } else if ($hassidepre) {
                        echo $OUTPUT->blocks('side-pre');
                    }
                ?>
              </div>
            </div>
            <?php } ?>
          </div>
        </div>
      </div>
    </div>

<!-- END OF CONTENT -->

<!-- START OF FOOTER -->

    <div id="page-footer">
      <div class="footer-content">
        <div class="footer-powered">Powered by <a href="http://www.totaralms.com/" target="_blank">TotaraLMS</a></div>
        <div class="footnote">
          <div class="footer-links">
          </div>
        </div>
        <?php
        echo $OUTPUT->standard_footer_html();
        ?>
      </div>
    </div>

<!-- END OF FOOTER -->
  </div>
</div>
<?php echo $OUTPUT->standard_end_of_body_html() ?>
</body>
</html>
