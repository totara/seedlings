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
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @author Eugene Venter <eugene@catalyst.net.nz>
 * @author Aaron Barnes <aaron.barnes@totaralms.com>
 * @author Dave Wallace <dave.wallace@kineo.co.nz>
 * @package totara
 * @subpackage totara_core
 */
M.totara_positionuser = M.totara_positionuser || {

    Y: null,
    // optional php params and defaults defined here, args passed to init method
    // below will override these values
    config: {},

    /**
     * module initialisation method called by php js_init_call()
     *
     * @param object    YUI instance
     * @param string    args supplied in JSON format
     */
    init: function(Y, args){
        // save a reference to the Y instance (all of its dependencies included)
        this.Y = Y;

        // if defined, parse args into this module's config object
        if (args) {
            var jargs = Y.JSON.parse(args);
            for (var a in jargs) {
                if (Y.Object.owns(jargs, a)) {
                    this.config[a] = jargs[a];
                }
            }
        }

        // check jQuery dependency is available
        if (typeof $ === 'undefined') {
            throw new Error('M.totara_positionuser.init()-> jQuery dependency required for this module to function.');
        }

        ///
        /// Position dialog
        ///
        (function() {
            var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/position/assign/';
            totaraSingleSelectDialog(
                'position',
                M.util.get_string('chooseposition', 'totara_hierarchy') + M.totara_positionuser.config.dialog_display_position,
                url+'position.php?',
                'positionid',
                'positiontitle',
                undefined,
                M.totara_positionuser.config.can_edit           //Make selection deletable
            );
        })();

        ///
        /// Organisation dialog
        ///
        (function() {
            var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/organisation/assign/';
            totaraSingleSelectDialog(
                'organisation',
                M.util.get_string('chooseorganisation', 'totara_hierarchy') + M.totara_positionuser.config.dialog_display_organisation,
                url+'find.php?',
                'organisationid',
                'organisationtitle',
                undefined,
                M.totara_positionuser.config.can_edit            // Make selection deletable
            );
        })();

        ///
        /// Manager dialog
        ///
        (function() {
            var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/position/assign/';

            totaraSingleSelectDialog(
                'manager',
                M.util.get_string('choosemanager', 'totara_hierarchy') + M.totara_positionuser.config.dialog_display_manager,
                url+'manager.php?userid='+M.totara_positionuser.config.userid,
                'managerid',
                'managertitle',
                undefined,
                M.totara_positionuser.config.can_edit            // Make selection deletable
            );
        })();

        ///
        /// Temporary manager dialog
        ///
        (function() {
            var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/position/assign/';

            totaraSingleSelectDialog(
                'tempmanager',
                M.util.get_string('choosetempmanager', 'totara_core') + M.totara_positionuser.config.dialog_display_tempmanager,
                url+'tempmanager.php?userid='+M.totara_positionuser.config.userid,
                'tempmanagerid',
                'tempmanagertitle',
                undefined,
                M.totara_positionuser.config.can_edit_tempmanager            // Make selection deletable
            );
        })();

        ///
        /// Appraiser dialog
        ///
        (function() {
            var url = M.cfg.wwwroot+'/totara/hierarchy/prefix/position/assign/';

            totaraSingleSelectDialog(
                'appraiser',
                M.util.get_string('chooseappraiser', 'totara_hierarchy') + M.totara_positionuser.config.dialog_display_appraiser,
                url+'manager.php?userid='+M.totara_positionuser.config.userid,
                'appraiserid',
                'appraisertitle',
                undefined,
                M.totara_positionuser.config.can_edit            // Make selection deletable
            );
        })();

    }
};
