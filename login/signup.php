<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * user signup page.
 *
 * @package    core
 * @subpackage auth
 * @copyright  1999 onwards Martin Dougiamas  http://dougiamas.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../config.php');
require_once($CFG->dirroot . '/user/editlib.php');
require_once($CFG->dirroot . '/totara/core/js/lib/setup.php');

// Try to prevent searching for sites that allow sign-up.
if (!isset($CFG->additionalhtmlhead)) {
    $CFG->additionalhtmlhead = '';
}
$CFG->additionalhtmlhead .= '<meta name="robots" content="noindex" />';

if (empty($CFG->registerauth)) {
    print_error('notlocalisederrormessage', 'error', '', 'Sorry, you may not use this page.');
}
$authplugin = get_auth_plugin($CFG->registerauth);

if (!$authplugin->can_signup()) {
    print_error('notlocalisederrormessage', 'error', '', 'Sorry, you may not use this page.');
}

//HTTPS is required in this page when $CFG->loginhttps enabled
$PAGE->https_required();

$PAGE->set_url('/login/signup.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('login');

$mform_signup = $authplugin->signup_form();

if ($mform_signup->is_cancelled()) {
    redirect(get_login_url());

} else if ($user = $mform_signup->get_data()) {
    $user->confirmed   = 0;
    $user->lang        = current_language();
    $user->firstaccess = time();
    $user->timecreated = time();
    $user->mnethostid  = $CFG->mnet_localhost_id;
    $user->secret      = random_string(15);
    $user->auth        = $CFG->registerauth;
    // Initialize alternate name fields to empty strings.
    $namefields = array_diff(get_all_user_name_fields(), useredit_get_required_name_fields());
    foreach ($namefields as $namefield) {
        $user->$namefield = '';
    }

    $authplugin->user_signup($user, true); // prints notice and link to login/index.php
    exit; //never reached
}

// make sure we really are on the https page when https login required
$PAGE->verify_https_required();

// Setup custom javascript
local_js(array(
    TOTARA_JS_DIALOG,
    TOTARA_JS_TREEVIEW,
    TOTARA_JS_DATEPICKER,
    TOTARA_JS_PLACEHOLDER
));
$PAGE->requires->strings_for_js(array('chooseposition', 'choosemanager', 'chooseorganisation', 'currentlyselected'), 'totara_hierarchy');
$PAGE->requires->strings_for_js(array('error:positionnotselected', 'error:organisationnotselected', 'error:managernotselected'), 'totara_core');
$jsmodule = array(
    'name' => 'totara_positionuser',
    'fullpath' => '/totara/core/js/position.user.js',
    'requires' => array('json'));

$selected_position = json_encode(dialog_display_currently_selected(get_string('selected', 'totara_hierarchy'), 'position'));
$selected_organisation = json_encode(dialog_display_currently_selected(get_string('selected', 'totara_hierarchy'), 'organisation'));
$selected_manager = json_encode(dialog_display_currently_selected(get_string('selected', 'totara_hierarchy'), 'manager'));
$js_can_edit = 'true';
$user = 0;

$args = array('args'=>'{"userid":' . $user . ',' .
    '"can_edit":' . $js_can_edit . ','.
    '"dialog_display_position":' . $selected_position . ',' .
    '"dialog_display_organisation":' . $selected_organisation . ',' .
    '"dialog_display_manager":' . $selected_manager . '}');

$PAGE->requires->js_init_call('M.totara_positionuser.init', $args, false, $jsmodule);

$newaccount = get_string('newaccount');
$login      = get_string('login');

$PAGE->navbar->add($login);
$PAGE->navbar->add($newaccount);

$PAGE->set_title($newaccount);
$PAGE->set_heading($SITE->fullname);

echo $OUTPUT->header();
$mform_signup->display();
echo $OUTPUT->footer();
