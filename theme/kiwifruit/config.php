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
 * @author Paul Walker <paul.walker@catalyst-eu.net>
 * @package totara
 * @subpackage theme
 */

$THEME->name = 'kiwifruit';
$THEME->parents = array('standardtotara', 'standard', 'base');
$THEME->parents_exclude_sheets = array('standardtotara' => array('css3'),'standard' => array('css3'));
$THEME->sheets = array(
    'core', 'blocks', 'navigation', 'course', 'dock', 'css3', 'ie7', 'custom'
);

$THEME->layouts = array(
    'base' => array(
        'file' => 'general.php',
        'regions' => array('side-pre', 'side-post'),
        'defaultregion' => 'side-post'
    ),
    'standard' => array(
        'file' => 'general.php',
        'regions' => array('side-pre', 'side-post'),
        'defaultregion' => 'side-post'
    ),
    'frontpage' => array(
        'file' => 'frontpage.php',
        'regions' => array('side-pre', 'side-post', 'middle', 'bottom'),
        'defaultregion' => 'side-post'
    ),
    'mydashboard' => array(
        'file' => 'general.php',
        'regions' => array('side-pre', 'side-post'),
        'defaultregion' => 'side-post'
    ),
    'course' => array(
        'file' => 'general.php',
        'regions' => array('side-pre', 'side-post'),
        'defaultregion' => 'side-post'
    ),
    'incourse' => array(
        'file' => 'general.php',
        'regions' => array('side-pre', 'side-post'),
        'defaultregion' => 'side-post'
    ),
    'admin' => array(
        'file' => 'general.php',
        'regions' => array('side-pre', 'side-post'),
        'defaultregion' => 'side-post'
    ),
    'login' => array(
        'file' => 'login.php',
        'regions' => array(),
        'options' => array('langmenu' => true, 'nologininfo' => true, 'nocustommenu' => true,
            'nonavbar' => true, 'nocourseheaderfooter' => true)
    ),
    'report' => array(
        'file' => 'general.php',
        'regions' => array('side-pre'),
        'options' => array('langmenu' => true),
        'defaultregion' => 'side-pre'
    ),
    'noblocks' => array(
        'file' => 'general.php',
        'regions' => array(),
        'options' => array('noblocks' => true, 'langmenu' => true)
    ),
    'popup' => array(
        'file' => 'popup.php',
        'regions' => array(),
        'options' => array('noblocks' => true, 'noheader' => true, 'nofooter' => true, 'nonavbar' => false, 'nocustommenu' => true, 'nologininfo' => true, 'nocourseheaderfooter' => true)
    ),
    'embedded' => array(
        'file' => 'general.php',
        'regions' => array(),
        'options' => array('noblocks' => true, 'noheader' => true, 'nofooter' => true, 'nonavbar' => false, 'nocustommenu' => true, 'nologininfo' => true, 'nocourseheaderfooter' => true)
    ),
    'redirect' => array(
        'file' => 'general.php',
        'regions' => array(),
        'options' => array('noblocks' => true, 'noheader' => true, 'nofooter' => true, 'nonavbar' => true, 'nocustommenu' => true, 'nologininfo' => true, 'nocourseheaderfooter' => true, 'langmenu' => false)
    ),
);

$THEME->enable_dock = true;
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->csspostprocess = 'theme_kiwifruit_process_css';
