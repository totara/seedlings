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
 * @subpackage totara_hierarchy
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/config.php');
require_once('../lib.php');
require_once('lib.php');
require_once($CFG->libdir.'/adminlib.php');

///
/// Setup / loading data
///

$sitecontext = context_system::instance();

// Get params
$frameworkid = optional_param('frameworkid', 0, PARAM_INT);
$edit        = optional_param('edit', -1, PARAM_BOOL);
$hide        = optional_param('hide', 0, PARAM_INT);
$show        = optional_param('show', 0, PARAM_INT);
$moveup      = optional_param('moveup', 0, PARAM_INT);
$movedown    = optional_param('movedown', 0, PARAM_INT);

// Get hierarchy object
$hierarchy = new competency();

// Load framework
$framework   = $hierarchy->get_framework($frameworkid, true, true);

// If no frameworks exist
if (!$framework) {
    // Redirect to frameworks page
    redirect($CFG->wwwroot.'/totara/hierarchy/framework/index.php?prefix=competency');
    exit();
}

$frameworkid = $framework->id;

// Cache user capabilities
$can_add = has_capability('totara/hierarchy:create'.$hierarchy->prefix.'template', $sitecontext);
$can_edit = has_capability('totara/hierarchy:update'.$hierarchy->prefix.'template', $sitecontext);
$can_delete = has_capability('totara/hierarchy:delete'.$hierarchy->prefix.'template', $sitecontext);

if ($can_add || $can_edit || $can_delete) {
    $navbaritem = $hierarchy->get_editing_button($edit);
    $editingon = !empty($USER->{$hierarchy->prefix.'editing'});
} else {
    $navbaritem = '';
    $editingon = false;
}

// Setup page and check permissions
admin_externalpage_setup($hierarchy->prefix.'manage', $navbaritem);

echo $OUTPUT->header();

$hierarchy->display_framework_selector('prefix/competency/template/index.php');
$templates = $hierarchy->get_templates();

if ($templates) {
    competency_template_display_table($templates, $frameworkid);
}

echo $OUTPUT->footer();
