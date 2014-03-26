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
 * @author Darko Miletic
 * @author Simon Coggins <simon.coggins@totaralms.com>
 * @package totara
 * @subpackage cron
 */

// Fix for missing bind function in < IE9.
if (Y.UA.ie < 9) {
    if (!Function.prototype.bind) {
      Function.prototype.bind = function (oThis) {
        if (typeof this !== "function") {
          // Closest thing possible to the ECMAScript 5 internal IsCallable function.
          throw new TypeError("Function.prototype.bind - what is trying to be bound is not callable");
        }

        var aArgs = Array.prototype.slice.call(arguments, 1),
            fToBind = this,
            fNOP = function () {},
            fBound = function () {
              return fToBind.apply(this instanceof fNOP && oThis
                                     ? this
                                     : oThis,
                                   aArgs.concat(Array.prototype.slice.call(arguments)));
            };

        fNOP.prototype = this.prototype;
        fBound.prototype = new fNOP();

        return fBound;
      };
    }
}

M.cronsettings = M.cronsettings || {
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

        // bind event handlers
        // need to use bind() to force module scope inside function
        // (by default 'this' in event handlers is the dom object)
        Y.one('#id_cron_execution_watch_cron_refresh').on('click', this.cron_refresh.bind(this));
        Y.one('#id_cron_execution_watch_cron_execute').on('click', this.run_cron.bind(this));

    },

    changeText: function(Y, newtext) {
        var element = Y.one('#cron_execution_status');
        if (element) {
            var nnode = document.createTextNode(newtext);
            element.set('text', newtext);
        }
    },

    ajaxRefresh: function(id, o){
        var doit = (o.responseText !== undefined);
        if (doit) {
            var resp = Y.JSON.parse(o.responseText);
        }

        var msg = "Unable to check cron status!";
        if (doit && (resp !== false)) {
            msg = resp;
        }
        M.cronsettings.changeText(Y, msg);
    },

    cron_refresh: function(Y) {
        var _this = this;
        YUI().use('io', function(Y) {
            Y.io('cron_ajax_refresh.php', {
                on: {success: _this.ajaxRefresh}
            });
        });
    },

    run_cron: function(Y) {
        window.location = this.config.wwwroot+'/admin/cron.php';
    }
};


