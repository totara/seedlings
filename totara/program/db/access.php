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
 * @package totara
 * @subpackage program
 */

//
// Capability definitions for the program module.
//
// The capabilities are loaded into the database table when the module is
// installed or updated. Whenever the capability definitions are updated,
// the module version number should be bumped up.
//
// The system has four possible values for a capability:
// CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT, and inherit (not set).
//
//
// CAPABILITY NAMING CONVENTION
//
// It is important that capability names are unique. The naming convention
// for capabilities that are specific to modules and blocks is as follows:
//   [mod/block]/<component_name>:<capabilityname>
//
// component_name should be the same as the directory name of the mod or block.
//
// For local modules the naming convention is:
//   local/<component_name>:<capabilityname>
//
// Core moodle capabilities are defined thus:
//    moodle/<capabilityclass>:<capabilityname>
//
// Examples: mod/forum:viewpost
//           block/recent_activity:view
//           moodle/site:deleteuser
//
// The variable name for the capability definitions array follows the format
//   $<componenttype>_<component_name>_capabilities
//
// For the core capabilities, the variable is $moodle_capabilities.
//
// For local modules, the variable is $capabilities in 2.0 and
// $local_<component_name>_capabilities in 1.9.

$capabilities = array(

    // View a program
    'totara/program:viewprogram' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
            'user' => CAP_ALLOW,
        )
    ),

    // View hidden programs
    'totara/program:viewhiddenprograms' => array(
        'captype' => 'read',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'coursecreator' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        )
    ),

    // Access any user's programs
    'totara/program:accessanyprogram' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    // Create new programs
    'totara/program:createprogram' => array(
        'riskbitmask' => RISK_XSS | RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        )
    ),

    // Delete programs
    'totara/program:deleteprogram' => array(
        'riskbitmask' => RISK_DATALOSS |
            RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        ),
        'clonepermissionsfrom' => 'totara/program:createprogram'
    ),
    // Ability to edit aspects of a program - specific abilities governed by further capabilities below

    // Ability to edit the program details tab
    'totara/program:configuredetails' => array(
        'riskbitmask' => RISK_DATALOSS | RISK_XSS |
            RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        )
    ),


    // Ability to add and remove program content and configure the flow of content
    'totara/program:configurecontent' => array(
        'riskbitmask' => RISK_DATALOSS |
            RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        )
    ),

    // Ability to add and remove program assignments
    'totara/program:configureassignments' => array(
        'riskbitmask' => RISK_DATALOSS |
            RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        )
    ),

    // Ability to add and remove program messages
    'totara/program:configuremessages' => array(
        'riskbitmask' => RISK_DATALOSS |
            RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        )
    ),

    // Ability to view exception reports and handle exceptions
    'totara/program:handleexceptions' => array(
        'riskbitmask' => RISK_DATALOSS |
            RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
        )
    ),

    'totara/program:visibility' => array(
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_PROGRAM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        )
    ),

    'totara/program:markstaffcoursecomplete' => array(
        'riskbitmask' => RISK_DATALOSS || RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_USER,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
            'staffmanager' => CAP_ALLOW
        )
    ),

);
