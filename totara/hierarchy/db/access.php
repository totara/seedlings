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
 * @author Ciaran Irvine <ciaran@catalyst.net.nz>
 * @package totara
 * @subpackage hierarchy
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

        // Viewing and managing a competency.
        'totara/hierarchy:viewcompetency' => array(
            'riskbitmask' => RISK_PERSONAL,
            'captype' => 'read',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW,
                'student' => CAP_ALLOW,
                'user' => CAP_ALLOW
                ),
            ),
        'totara/hierarchy:createcompetency' => array(
            'captype'       => 'write',
            'contextlevel'  => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW
                ),
            ),
        'totara/hierarchy:updatecompetency' => array(
            'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
            'captype'       => 'write',
            'contextlevel'  => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW
                ),
            ),
        'totara/hierarchy:deletecompetency' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createcompetencytype' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updatecompetencytype' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deletecompetencytype' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createcompetencyframeworks' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updatecompetencyframeworks' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deletecompetencyframeworks' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createcompetencytemplate' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updatecompetencytemplate' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deletecompetencytemplate' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createcompetencycustomfield' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updatecompetencycustomfield' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deletecompetencycustomfield' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:viewcompetencyscale' => array(
                'captype'       => 'read',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                'clonepermissionsfrom' => 'totara/hierarchy:viewcompetencyframeworks'
                ),
        'totara/hierarchy:createcompetencyscale' => array(
                'riskbitmask'   => RISK_SPAM,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                'clonepermissionsfrom' => 'totara/hierarchy:createcompetencyframeworks'
                ),
        'totara/hierarchy:updatecompetencyscale' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                'clonepermissionsfrom' => 'totara/hierarchy:updatecompetencyframeworks'
                ),
        'totara/hierarchy:deletecompetencyscale' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                'clonepermissionsfrom' => 'totara/hierarchy:deletecompetencyframeworks'
                ),

        // Viewing and managing positions.
        'totara/hierarchy:viewposition' => array(
                'riskbitmask' => RISK_PERSONAL,
                'captype'      => 'read',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW,
                    'student' => CAP_ALLOW,
                    'user' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createposition' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updateposition' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deleteposition' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createpositiontype' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updatepositiontype' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deletepositiontype' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createpositionframeworks' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updatepositionframeworks' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deletepositionframeworks' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createpositioncustomfield' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updatepositioncustomfield' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deletepositioncustomfield' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),

        // Viewing and managing organisations.
        'totara/hierarchy:vieworganisation' => array(
                'riskbitmask' => RISK_PERSONAL,
                'captype'      => 'read',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW,
                    'student' => CAP_ALLOW,
                    'user' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createorganisation' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updateorganisation' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deleteorganisation' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createorganisationtype' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updateorganisationtype' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deleteorganisationtype' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createorganisationframeworks' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updateorganisationframeworks' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deleteorganisationframeworks' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:createorganisationcustomfield' => array(
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updateorganisationcustomfield' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deleteorganisationcustomfield' => array(
                'riskbitmask'   => RISK_PERSONAL | RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),

        // Assign a position to yourself.
        'totara/hierarchy:assignselfposition' => array(
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            ),

        // Assign a position to a user.
        'totara/hierarchy:assignuserposition' => array(
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW
                ),
            ),

        // Goals permissions - Management.
        'totara/hierarchy:viewgoal' => array(
            'riskbitmask' => RISK_PERSONAL,
            'captype' => 'read',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW,
                'student' => CAP_ALLOW,
                'user' => CAP_ALLOW
                ),
            'clonepermissionsfrom' => 'totara/hierarchy:viewcompetency'
            ),
        'totara/hierarchy:creategoal' => array(
            'riskbitmask' => RISK_SPAM,
            'captype'       => 'write',
            'contextlevel'  => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW
                ),
            'clonepermissionsfrom' => 'totara/hierarchy:createcompetency'
            ),
        'totara/hierarchy:updategoal' => array(
            'riskbitmask'   => RISK_DATALOSS,
            'captype'       => 'write',
            'contextlevel'  => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW
                ),
            'clonepermissionsfrom' => 'totara/hierarchy:updatecompetency'
            ),
        'totara/hierarchy:deletegoal' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
            'clonepermissionsfrom' => 'totara/hierarchy:deletecompetency'
                ),
        'totara/hierarchy:creategoaltype' => array(
            'riskbitmask' => RISK_SPAM,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
            'clonepermissionsfrom' => 'totara/hierarchy:createcompetencytype'
                ),
        'totara/hierarchy:updategoaltype' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
            'clonepermissionsfrom' => 'totara/hierarchy:updatecompetencytype'
                ),
        'totara/hierarchy:deletegoaltype' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
            'clonepermissionsfrom' => 'totara/hierarchy:deletecompetencytype'
                ),
        'totara/hierarchy:creategoalframeworks' => array(
            'riskbitmask' => RISK_SPAM,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
            'clonepermissionsfrom' => 'totara/hierarchy:createcompetencyframeworks'
                ),
        'totara/hierarchy:updategoalframeworks' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
            'clonepermissionsfrom' => 'totara/hierarchy:updatecompetencyframeworks'
                ),
        'totara/hierarchy:deletegoalframeworks' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'wrireadte',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
            'clonepermissionsfrom' => 'totara/hierarchy:deletecompetencyframeworks'
                ),
        'totara/hierarchy:creategoalcustomfield' => array(
            'riskbitmask' => RISK_SPAM,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
            'clonepermissionsfrom' => 'totara/hierarchy:createcompetencycustomfield'
                ),
        'totara/hierarchy:updategoalcustomfield' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
            'clonepermissionsfrom' => 'totara/hierarchy:updatecompetencycustomfield'
                ),
        'totara/hierarchy:deletegoalcustomfield' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
            'clonepermissionsfrom' => 'totara/hierarchy:deletecompetencycustomfield'
                ),
        'totara/hierarchy:viewgoalscale' => array(
                'captype'       => 'read',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:creategoalscale' => array(
                'riskbitmask'   => RISK_SPAM,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:updategoalscale' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:deletegoalscale' => array(
                'riskbitmask'   => RISK_DATALOSS,
                'captype'       => 'write',
                'contextlevel'  => CONTEXT_SYSTEM,
                'archetypes' => array(
                    'manager' => CAP_ALLOW
                    ),
                ),
        'totara/hierarchy:viewgoalreport' => array(
                'riskbitmask' => RISK_PERSONAL,
                'captype' => 'read',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => array('manager' => CAP_ALLOW),
                'clonepermissionsfrom' => 'totara/hierarchy:viewgoal'
        ),
        'totara/hierarchy:editgoalreport' => array(
                'riskbitmask' => RISK_PERSONAL | RISK_CONFIG,
                'captype' => 'write',
                'contextlevel' => CONTEXT_SYSTEM,
                'archetypes' => array('manager' => CAP_ALLOW),
                'clonepermissionsfrom' => 'totara/hierarchy:updategoal'
        ),

        // User goals self management permissions.
        'totara/hierarchy:viewownpersonalgoal' => array(
            'captype' => 'read',
            'contextlevel' => CONTEXT_USER,
            'archetypes' => array(
                'user' => CAP_ALLOW
                )
            ),
        'totara/hierarchy:viewowncompanygoal' => array(
            'captype' => 'read',
            'contextlevel' => CONTEXT_USER,
            'archetypes' => array(
                'user' => CAP_ALLOW
                )
            ),
        'totara/hierarchy:manageownpersonalgoal' => array(
            'captype' => 'write',
            'contextlevel' => CONTEXT_USER,
            'archetypes' => array(
                'user' => CAP_ALLOW
                )
            ),
        'totara/hierarchy:manageowncompanygoal' => array(
            'captype' => 'write',
            'contextlevel' => CONTEXT_USER,
            'archetypes' => array(
                'user' => CAP_ALLOW
                )
            ),

        // Manager team goal management permissions.
        'totara/hierarchy:viewstaffpersonalgoal' => array(
            'riskbitmask'   => RISK_PERSONAL,
            'captype' => 'read',
            'contextlevel' => CONTEXT_USER,
                'archetypes' => array(
                    'staffmanager' => CAP_ALLOW
                    ),
            ),
        'totara/hierarchy:viewstaffcompanygoal' => array(
            'riskbitmask'   => RISK_PERSONAL,
            'captype' => 'read',
            'contextlevel' => CONTEXT_USER,
                'archetypes' => array(
                    'staffmanager' => CAP_ALLOW
                    ),
            ),
        'totara/hierarchy:managestaffpersonalgoal' => array(
            'riskbitmask'   => RISK_PERSONAL | RISK_SPAM | RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_USER,
                'archetypes' => array(
                    'staffmanager' => CAP_ALLOW
                    ),
            ),
        'totara/hierarchy:managestaffcompanygoal' => array(
            'riskbitmask'   => RISK_SPAM | RISK_DATALOSS,
            'captype' => 'write',
            'contextlevel' => CONTEXT_USER,
                'archetypes' => array(
                    'staffmanager' => CAP_ALLOW
                    ),
            ),

        // Admin site goal management permissions.
        'totara/hierarchy:managegoalassignments' => array(
            'riskbitmask'   => RISK_SPAM,
            'captype' => 'write',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW
                ),
            ),

        // Additional view framework permissions.
        'totara/hierarchy:viewcompetencyframeworks' => array(
            'captype' => 'read',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW
            ),
            'clonepermissionsfrom' => 'totara/hierarchy:updatecompetencyframeworks'
        ),
        'totara/hierarchy:viewpositionframeworks' => array(
            'captype' => 'read',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW
            ),
            'clonepermissionsfrom' => 'totara/hierarchy:updatepositionframeworks'
        ),
        'totara/hierarchy:vieworganisationframeworks' => array(
            'captype' => 'read',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW
            ),
            'clonepermissionsfrom' => 'totara/hierarchy:updateorganisationframeworks'
        ),

        'totara/hierarchy:viewgoalframeworks' => array(
            'captype' => 'read',
            'contextlevel' => CONTEXT_SYSTEM,
            'archetypes' => array(
                'manager' => CAP_ALLOW,
                'staffmanager' => CAP_ALLOW,
                'user' => CAP_ALLOW
            ),
            'clonepermissionsfrom' => 'totara/hierarchy:updategoalframeworks'
        ),
    );
