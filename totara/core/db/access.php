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
 * @author Jonathan Newman <jonathan.newman@catalyst.net.nz>
 * @author Ciaran Irvine <ciaran.irvine@totaralms.com>
 * @package totara
 * @subpackage totara_core
 */

/*
 * The capabilities are loaded into the database table when the module is
 * installed or updated. Whenever the capability definitions are updated,
 * the module version number should be bumped up.
 *
 * The system has four possible values for a capability:
 * CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT, and inherit (not set).
*/

$capabilities = array(

    // Managing course custom fields
    'totara/core:createcoursecustomfield' => array(
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
    ),
    'totara/core:updatecoursecustomfield' => array(
        'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
    ),
    'totara/core:deletecoursecustomfield' => array(
        'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
    ),
    // Managing program custom fields.
    'totara/core:createprogramcustomfield' => array(
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'totara/core:createcoursecustomfield'
    ),
    'totara/core:updateprogramcustomfield' => array(
        'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'totara/core:updatecoursecustomfield'
    ),
    'totara/core:deleteprogramcustomfield' => array(
        'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'totara/core:deletecoursecustomfield'
    ),
    'totara/core:undeleteuser' => array(
        'riskbitmask'   => RISK_CONFIG,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes'    => array(
            'manager'   => CAP_ALLOW
        )
    ),
    'totara/core:seedeletedusers' => array(
        'riskbitmask'   => RISK_PERSONAL | RISK_CONFIG,
        'captype'       => 'read',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes'    => array(
            'manager' => CAP_ALLOW
        )
    ),
    'totara/core:appearance' => array(
        'riskbitmask'   => RISK_CONFIG,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes'    => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/site:config'
    ),

    // Unlock course completion.
    'moodle/course:unlockcompletion' => array(
        'riskbitmask' => RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/course:update'
    ),

    // Manage audience visibility.
    'totara/coursecatalog:manageaudiencevisibility' => array(
        'riskbitmask'  => RISK_CONFIG | RISK_SPAM,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => array(
            'manager' => CAP_ALLOW
        )
    ),

    // Assign own temporary manager.
    'totara/core:delegateownmanager' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_USER,
        'archetypes'   => array(
            'manager'       => CAP_ALLOW
        ),
        'clonepermissionsfrom' => ' totara/hierarchy:assignselfposition'
    ),
    // Assign temporary manager to users.
    'totara/core:delegateusersmanager' => array(
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_USER,
        'archetypes'   => array(
            'manager'       => CAP_ALLOW,
        )
    ),
    // Update user ID number.
    'totara/core:updateuseridnumber' => array(
        'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
    ),
    // View Record of Learning for other users.
    'totara/core:viewrecordoflearning' => array(
        'riskbitmask'   => RISK_PERSONAL,
        'captype'       => 'read',
        'contextlevel'  => CONTEXT_USER,
        'archetypes' => array(
            'staffmanager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'totara/plan:accessanyplan'
    ),
    // Customise the main navigation menu.
    'totara/core:editmainmenu' => array(
        'riskbitmask'   => RISK_CONFIG,
        'captype'       => 'write',
        'contextlevel'  => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
    ),
);
