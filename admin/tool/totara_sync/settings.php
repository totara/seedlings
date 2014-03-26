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
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @package tool
 * @subpackage totara_sync
 */

defined('MOODLE_INTERNAL') || die;
$systemcontext = context_system::instance();
if (has_capability('tool/totara_sync:manage', $systemcontext)) {
    require_once($CFG->dirroot.'/admin/tool/totara_sync/lib.php');

    $ADMIN->add('root', new admin_category('tool_totara_sync', get_string('pluginname', 'tool_totara_sync')), 'development');
    $ADMIN->add('tool_totara_sync', new admin_externalpage('totarasyncsettings',
            get_string('generalsettings', 'tool_totara_sync'),
            "$CFG->wwwroot/admin/tool/totara_sync/admin/settings.php", 'tool/totara_sync:manage'));
    $ADMIN->add('tool_totara_sync', new admin_category('syncelements', get_string('elements', 'tool_totara_sync')));
    $ADMIN->add('tool_totara_sync', new admin_category('syncsources', get_string('sources', 'tool_totara_sync')));

    $can_manage_any = false;
    $can_upload_any = false;
    $upload_enabled = get_config('totara_sync', 'fileaccess') == FILE_ACCESS_UPLOAD;
    if ($elements = totara_sync_get_elements()) {
        foreach ($elements as $e) {
            $elname = $e->get_name();
            if (!$can_manage_any) {
                if (has_capability('tool/totara_sync:manage' . $elname, $systemcontext) || has_capability('tool/totara_sync:setfileaccess', $systemcontext)) {
                    $can_manage_any = true;
                    $ADMIN->add('syncelements', new admin_externalpage('managesyncelements', get_string('manageelements', 'tool_totara_sync'), "$CFG->wwwroot/admin/tool/totara_sync/admin/elements.php", 'tool/totara_sync:manage'));
                }
            }

            if ($e->is_enabled()) {
                if (has_capability('tool/totara_sync:upload' . $elname, $systemcontext)) {
                    $can_upload_any = true;
                }

                /// Elements
                $ADMIN->add('syncelements', new admin_externalpage('syncelement'.$elname,
                    get_string('displayname:'.$elname, 'tool_totara_sync'), "$CFG->wwwroot/admin/tool/totara_sync/admin/elementsettings.php?element={$elname}", 'tool/totara_sync:manage' . $elname));

                /// Sources
                if ($sources = $e->get_sources()) {

                    $ADMIN->add('syncsources', new admin_category($elname.'sources', get_string('displayname:'.$elname, 'tool_totara_sync')));
                    foreach ($sources as $s) {
                        if (!$s->has_config()) {
                            continue;
                        }
                        $sname = $s->get_name();
                        $ADMIN->add($elname.'sources', new admin_externalpage($sname,
                            get_string('displayname:'.$sname, 'tool_totara_sync'), "$CFG->wwwroot/admin/tool/totara_sync/admin/sourcesettings.php?element={$elname}&source={$sname}", 'tool/totara_sync:manage' . $elname));
                    }
                }
            }
        }

        if ($can_upload_any && $upload_enabled) {
            $ADMIN->add('syncsources', new admin_externalpage('uploadsyncfiles', get_string('uploadsyncfiles', 'tool_totara_sync'), "$CFG->wwwroot/admin/tool/totara_sync/admin/uploadsourcefiles.php", 'tool/totara_sync:manage'));
        }
        unset($elname);
    }
    if (has_capability('tool/totara_sync:runsync', $systemcontext)) {
        $ADMIN->add('tool_totara_sync', new admin_externalpage('totarasyncexecute', get_string('syncexecute', 'tool_totara_sync'), "$CFG->wwwroot/admin/tool/totara_sync/admin/syncexecute.php", 'tool/totara_sync:runsync'));
    }
    $ADMIN->add('tool_totara_sync', new admin_externalpage('totarasynclog', get_string('synclog', 'tool_totara_sync'), "$CFG->wwwroot/admin/tool/totara_sync/admin/synclog.php", 'tool/totara_sync:manage'));
}
