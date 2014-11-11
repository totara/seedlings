<?php // $Id$
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
 * @subpackage totara_hierarchy
 */

// This file defines settingpages and externalpages under the "hierarchies" category


    // Positions.
    $ADMIN->add('hierarchies', new admin_category('positions', get_string('positions', 'totara_hierarchy')));

    $ADMIN->add('positions', new admin_externalpage('positionmanage', get_string('positionmanage', 'totara_hierarchy'),
            "{$CFG->wwwroot}/totara/hierarchy/framework/index.php?prefix=position",
            array('totara/hierarchy:createpositionframeworks', 'totara/hierarchy:updatepositionframeworks', 'totara/hierarchy:deletepositionframeworks',
                  'totara/hierarchy:createposition', 'totara/hierarchy:updateposition', 'totara/hierarchy:deleteposition')));

    $ADMIN->add('positions', new admin_externalpage('positiontypemanage', get_string('managepositiontypes', 'totara_hierarchy'),
            "{$CFG->wwwroot}/totara/hierarchy/type/index.php?prefix=position",
            array('totara/hierarchy:createpositiontype', 'totara/hierarchy:updatepositiontype', 'totara/hierarchy:deletepositiontype')));

    $ADMIN->add('positions', new admin_externalpage('positionsettings', get_string('settings'),
            "{$CFG->wwwroot}/totara/hierarchy/prefix/position/settings.php",
            array('moodle/site:config')));


    // Organisations.
    $ADMIN->add('hierarchies', new admin_category('organisations', get_string('organisations', 'totara_hierarchy')));

    $ADMIN->add('organisations', new admin_externalpage('organisationmanage', get_string('organisationmanage', 'totara_hierarchy'),
            "{$CFG->wwwroot}/totara/hierarchy/framework/index.php?prefix=organisation",
            array('totara/hierarchy:createorganisationframeworks', 'totara/hierarchy:updateorganisationframeworks', 'totara/hierarchy:deleteorganisationframeworks',
                  'totara/hierarchy:createorganisation', 'totara/hierarchy:updateorganisation', 'totara/hierarchy:deleteorganisation')));

    $ADMIN->add('organisations', new admin_externalpage('organisationtypemanage', get_string('manageorganisationtypes', 'totara_hierarchy'),
            "{$CFG->wwwroot}/totara/hierarchy/type/index.php?prefix=organisation",
            array('totara/hierarchy:createorganisationtype', 'totara/hierarchy:updateorganisationtype', 'totara/hierarchy:deleteorganisationtype')));


    // Competencies.
    $ADMIN->add('hierarchies', new admin_category('competencies', get_string('competencies', 'totara_hierarchy')));

    $ADMIN->add('competencies', new admin_externalpage('competencymanage', get_string('competencymanage', 'totara_hierarchy'),
            "{$CFG->wwwroot}/totara/hierarchy/framework/index.php?prefix=competency",
            array('totara/hierarchy:createcompetencyframeworks', 'totara/hierarchy:updatecompetencyframeworks', 'totara/hierarchy:deletecompetencyframeworks',
                  'totara/hierarchy:createcompetency', 'totara/hierarchy:updatecompetency', 'totara/hierarchy:deletecompetency',
                  'totara/hierarchy:createcompetencyscale', 'totara/hierarchy:updatecompetencyscale', 'totara/hierarchy:deletecompetencyscale')));

    $ADMIN->add('competencies', new admin_externalpage('competencytypemanage', get_string('managecompetencytypes', 'totara_hierarchy'),
            "{$CFG->wwwroot}/totara/hierarchy/type/index.php?prefix=competency",
            array('totara/hierarchy:createcompetencytype', 'totara/hierarchy:updatecompetencytype', 'totara/hierarchy:deletecompetencytype')));

//    $ADMIN->add('competencies', new admin_externalpage('competencyglobalsettings', get_string('globalsettings', 'competency'), "$CFG->wwwroot/hierarchy/prefix/competency/adminsettings.php",
//            array('totara/hierarchy:updatecompetency')));

    // Goals.

    $ADMIN->add('hierarchies', new admin_category('goals', get_string('goals', 'totara_hierarchy'),
        totara_feature_disabled('goals')
    ));

    $ADMIN->add('goals', new admin_externalpage('goalmanage', get_string('goalmanage', 'totara_hierarchy'),
            "{$CFG->wwwroot}/totara/hierarchy/framework/index.php?prefix=goal",
            array('totara/hierarchy:creategoalframeworks', 'totara/hierarchy:updategoalframeworks', 'totara/hierarchy:deletegoalframeworks',
                  'totara/hierarchy:creategoal', 'totara/hierarchy:updategoal', 'totara/hierarchy:deletegoal',
                  'totara/hierarchy:creategoalscale', 'totara/hierarchy:updategoalscale', 'totara/hierarchy:deletegoalscale'),
            totara_feature_disabled('goals')));

    $ADMIN->add('goals', new admin_externalpage('goaltypemanage', get_string('managegoaltypes', 'totara_hierarchy'),
            "{$CFG->wwwroot}/totara/hierarchy/type/index.php?prefix=goal",
            array('totara/hierarchy:creategoaltype', 'totara/hierarchy:updategoaltype', 'totara/hierarchy:deletegoaltype'),
            totara_feature_disabled('goals')));

    $ADMIN->add('goals', new admin_externalpage('goalreport', get_string('goalreports', 'totara_hierarchy'),
            "{$CFG->wwwroot}/totara/hierarchy/prefix/goal/reports.php",
            array('totara/hierarchy:viewgoalreport'), totara_feature_disabled('goals')));
