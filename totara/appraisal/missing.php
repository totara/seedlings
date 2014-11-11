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
 * @author David Curry <david.curry@totaralms.com>
 * @package totara
 * @subpackage appraisal
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot . '/totara/appraisal/lib.php');

// Check if Appraisals are enabled.
appraisal::check_feature_enabled();

// Get the appraisal id.
$appraisalid = required_param('appraisalid', PARAM_INT);

// Capability checks.
$systemcontext = context_system::instance();
$canassign = has_capability('totara/appraisal:assignappraisaltogroup', $systemcontext);
$canviewusers = has_capability('totara/appraisal:viewassignedusers', $systemcontext);
$appraisal = new appraisal($appraisalid);

admin_externalpage_setup('manageappraisals');
$PAGE->set_heading($appraisal->name);
$PAGE->set_title(get_string('missingrolestitle', 'totara_appraisal', $appraisal->name));

$output = $PAGE->get_renderer('totara_appraisal');
echo $output->header();
echo $output->heading($appraisal->name);

if ($canviewusers || $canassign) {
    $errors = $appraisal->validate_roles(true);
    if (!empty($errors)) {
        $explaination = get_string('missingroles', 'totara_appraisal');
        $explaination .= get_string('missingrolesbelow', 'totara_appraisal');
        echo html_writer::tag('p', $explaination);

        $warndesc = array();
        foreach ($errors as $warn) {
            $warndesc[] = html_writer::tag('li', $warn);
        }
        echo html_writer::tag('ul', implode('', $warndesc), array('class' => 'appraisalerrorlist'));

    } else {
        // Looks like all the roles are filled.
        echo html_writer::tag('p', get_string('missingrolesnone', 'totara_appraisal'));

    }
} else {
    // You should not be viewing this page.
    print_error('error:pagepermissions', 'totara_appraisal');
}

echo $output->footer();
