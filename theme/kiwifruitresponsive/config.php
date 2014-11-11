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
 * @author Brian Barnes <brian.barnes@totaralms.com>
 * @package totara
 * @subpackage theme
 */

$THEME->name = 'kiwifruitresponsive';
$THEME->parents = array('standardtotararesponsive', 'bootstrapbase');
$THEME->parents_exclude_sheets = array('standardtotararesponsive', 'admin');
$THEME->sheets = array(
    'fonts', 'core', 'blocks', 'navigation', 'course', 'dock', 'css3', 'ie7', 'custom'
);

if (!(core_useragent::is_ie() && !core_useragent::check_ie_version('10.0'))) {
    $THEME->enable_dock = true;
}
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->csspostprocess = 'theme_kiwifruitresponsive_process_css';
$THEME->javascripts_footer = array(
    'core'
);
