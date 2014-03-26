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
M.totara_feedback360_content = M.totara_feedback360_content || {

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

    // check jQuery dependency is available
    if ( typeof $ === 'undefined') {
      throw new Error('M.totara_feedback360_content.init()-> jQuery dependency required for this module to function.');
    }
    makeDraggable(M.cfg.wwwroot + '/totara/feedback360/content.php', '#feedback360-quest-list', 'li');


    function makeDraggable(url, elID, elType) {
      var elType = elType || 'li';
      YUI().use('dd', function(Y) {

        var lis = Y.all(elID + ' ' + elType);
        var ul = Y.one(elID);
        /*var slis = Y.all(sID + ' ' + sType);*/

        Y.DD.DDM.on('drop:over', function(e) {
          //Get a reference to our drag and drop nodes
          var drag = e.drag.get('node'), drop = e.drop.get('node');

          //Are we in the same parent?
          if ('#' + drop.get('parentNode').get('id') == elID) {
            //Are we dropping on a li node?
            if (drop.get('tagName').toLowerCase() === 'li') {
              //Are we not going up?
              if (!goingUp) {
                drop = drop.get('nextSibling');
              }
              //Add the node to this list
              e.drop.get('node').get('parentNode').insertBefore(drag, drop);
              //Set the new parentScroll on the nodescroll plugin
              e.drag.nodescroll.set('parentScroll', e.drop.get('node').get('parentNode'));
              //Resize this nodes shim, so we can drop on it later.
              e.drop.sizeShim();
            }
          }
        });
        //Listen for all drag:drag events
        Y.DD.DDM.on('drag:drag', function(e) {
          //Get the last y point
          var y = e.target.lastXY[1];
          //is it greater than the lastY var?
          if (y < lastY) {
            //We are going up
            goingUp = true;
          } else {
            //We are going down.
            goingUp = false;
          }
          //Cache for next check
          lastY = y;
          Y.DD.DDM.syncActiveShims(true);
        });
        //Listen for all drag:start events
        Y.DD.DDM.on('drag:start', function(e) {
          //Get our drag object
          var drag = e.target;
          //Set some styles here
          drag.get('node').setStyle('opacity', '.25');
          drag.get('dragNode').set('innerHTML', drag.get('node').get('innerHTML'));
          drag.get('dragNode').setStyles({
            opacity : '.5',
            borderColor : 'transparent',
            height : drag.get('node').getComputedStyle('height'),
            width : drag.get('node').getComputedStyle('width'),
            backgroundColor : drag.get('node').getStyle('backgroundColor')
          });
        });
        //Listen for a drag:end events
        Y.DD.DDM.on('drag:end', function(e) {
          var drag = e.target;
          var node = drag.get('node');
          var list = Y.all(elID + ' ' + elType);
          var newPos = list.indexOf(node);
          $.get(url, {
            action: 'pos',
            id: node.getData('pageid') || node.getData('questid'),
            pos: newPos
          });
          //Put our styles back
          node.setStyles({
            visibility : '',
            opacity : '1'
          });
        });

        //Static Vars
        var goingUp = false, lastY = 0;

        lis.each(function(v, k) {
          var dd = new Y.DD.Drag({
            node : v,
            //Make it Drop target and pass this config to the Drop constructor
            target : {
              padding : '0 0 0 20'
            }
          }).plug(Y.Plugin.DDProxy, {
            //Don't move the node at the end of the drag
            moveOnEnd : false
          }).plug(Y.Plugin.DDNodeScroll, {
            node: v.get('parentNode')
          });

        });
      });
    }
  },
}