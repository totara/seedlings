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
 * @author  Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage plan
 */

/**
 * Add learning plans administration menu settings
 */
defined('MOODLE_INTERNAL') || die;

    $ADMIN->add('totara_plan',
        new admin_externalpage('managetemplates',
            new lang_string('managetemplates', 'totara_plan'),
            "$CFG->wwwroot/totara/plan/template/index.php",
            array('totara/plan:configureplans'),
            totara_feature_disabled('learningplans')
        )
    );

    $ADMIN->add('totara_plan',
        new admin_externalpage('priorityscales',
            new lang_string('priorityscales', 'totara_plan'),
            "$CFG->wwwroot/totara/plan/priorityscales/index.php",
            array('totara/plan:configureplans'),
            totara_feature_disabled('learningplans')
        )
    );

    $ADMIN->add('totara_plan',
        new admin_externalpage('objectivescales',
            new lang_string('objectivescales', 'totara_plan'),
            "$CFG->wwwroot/totara/plan/objectivescales/index.php",
            array('totara/plan:configureplans'),
            totara_feature_disabled('learningplans')
        )
    );

    $ADMIN->add('totara_plan',
        new admin_externalpage('evidencetypes',
            new lang_string('evidencetypes', 'totara_plan'),
            "$CFG->wwwroot/totara/plan/evidencetypes/index.php",
            array('totara/plan:configureplans'),
            totara_feature_disabled('learningplans')
        )
    );

