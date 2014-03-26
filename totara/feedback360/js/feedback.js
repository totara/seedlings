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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage totara_feedback360
 */
M.totara_feedback360_feedback = M.totara_feedback360_feedback || {

  Y : null,

  /**
   * module initialisation method called by php js_init_call()
   *
   * @param object    YUI instance
   * @param string    args supplied in JSON format
   */
  init : function(Y, args) {
    // save a reference to the Y instance (all of its dependencies included)
    this.Y = Y;
    formid = '#' + args;

    // check jQuery dependency is available
    if ( typeof $ === 'undefined') {
      throw new Error('M.totara_feedback360_feedback.init()-> jQuery dependency required for this module to function.');
    }
    moveScroller();

    $('#saveprogress').on('submit', function(e){
      window.onbeforeunload = null; // Prevent leaving page warning.
      e.preventDefault();
      $('input[name=action]', $(formid)).attr('value', 'saveprogress')
      $(formid).submit();
    });

    function moveScroller() {
        var move = function() {
            var st = $(window).scrollTop();
            var sa = $("#feedbackhead-anchor");
            var ot = sa.offset().top;
            var s = $("#feedbackhead");
            if(st > ot) {
                s.css({
                    position: "fixed",
                    top: "0px",
                    left: $("#feedbackhead-anchor").offset().left,
                    width: $("#feedbackhead-anchor").width() - parseInt(s.css("padding-left")) - parseInt(s.css("padding-right")),
                    "z-index": "2"
                });
                sa.height(s.outerHeight());
            } else {
                if(st <= ot) {
                    s.css({
                        position: "relative",
                        top: "",
                        left: "",
                        width:""
                    });
                }
                sa.height(0);
            }
        };
        $(window).scroll(move);
        move();
    }
  }
}