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

$THEME->name = 'standardtotara';
$THEME->parents = array('standard', 'base');
$THEME->sheets = array(
    'core',     /** Must come first**/
    'navigation',
    'admin',
    'blocks',
    'calendar',
    'course',
    'user',
    'dock',
    'grade',
    'message',
    'modules',
    'question',
    'pagelayout',
    'css3',      /** Sets up CSS 3 + browser specific styles **/
    'filemanager'
);

$THEME->layouts = array(
    // we want to show blocks on the default layout
    'base' => array(
        'file' => 'general.php',
        'regions' => array('side-pre', 'side-post'),
        'defaultregion' => 'side-post',
        'options' => array('langmenu' => true),
    ),
    // pages that need the full width of the page - no blocks shown at all
    // this is only used by totara pages
    'noblocks' => array(
        'file' => 'general.php',
        'regions' => array(),
        'options' => array('noblocks'=>true, 'langmenu'=>true),
    ),
    // rather than having a separate layout file for the single sidebar report
    // layout, we re-use general.php and just exclude side-post from the regions list
    'report' => array(
        'file' => 'report.php',
        'regions' => array('side-pre'),
        'defaultregion' => 'side-pre',
        'options' => array('langmenu' => false),
    ),
    // hide the totara nav and login info on the login page as you need to login first
    'login' => array(
        'file' => 'general.php',
        'regions' => array(),
        'options' => array('langmenu'=>true, 'nologininfo' => true, 'nocustommenu' => true, 'nonavbar' => true, 'nocourseheaderfooter' => true),
    ),
    // hide the login info section during maintenance as well
    'maintenance' => array(
        'file' => 'general.php',
        'regions' => array(),
        'options' => array('noblocks' => true, 'nofooter' => true, 'nonavbar' => true, 'nocustommenu' => true, 'nologininfo' => true, 'langmenu' => false, 'nocourseheaderfooter' => true),
    ),
    // also exclude login info on print view
    'print' => array(
        'file' => 'general.php',
        'regions' => array(),
        'options' => array('noblocks'=>true, 'nofooter'=>true, 'nonavbar'=>false, 'nocustommenu'=>true, 'nologininfo' => true, 'langmenu' => false, 'nocourseheaderfooter' => true),
    ),
    // simplify the layout of popups, removing superfluous divs and padding,
    // to make popups work much better with tablets and other devices with limited screen size.
    'popup' => array(
        'file' => 'popup.php',
        'regions' => array(),
        'options' => array('nofooter' => true, 'nonavbar' => true, 'nocustommenu' => true, 'nologininfo' => true, 'nocourseheaderfooter' => true),
    ),
);

$THEME->enable_dock = true;
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
