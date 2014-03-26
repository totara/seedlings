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
 * @author Mark Webster <mark.webster@catalyst-eu.net>
 * @package totara
 * @subpackage reportbuilder
 */

/**
 * Javascript file containing JQuery bindings for totara appraisals
 */

M.totara_appraisal_stage = M.totara_appraisal_stage || {

  Y : null,

  // optional php params and defaults defined here, args passed to init method
  // below will override these values
  config: {},

  /**
   * module initialisation method called by php js_init_call()
   *
   * @param object    YUI instance
   * @param string    args supplied in JSON format
   */
  init : function(Y, args) {
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
    if ( typeof $ === 'undefined') {
      throw new Error('M.totara_appraisal_stage.init()-> jQuery dependency required for this module to function.');
    }

    /**
     * Function to turn a {key:value} object into a string for use in a URL
     * @param {Object} obj Javascript Object
     */
    function objToString (obj) {
      var str = '';
      var first = true;
      for (var p in obj) {
        if (obj.hasOwnProperty(p)) {
          if (!first) {
            str += '&';
          } else {
            first = false;
          }
          str += p + '=' + obj[p];
        }
      }
      return str;
    }

    /**
     * Set the location hash in the address bar of the browser
     * @param {Object} obj Object containing key:value pairs
     * @param {String} name The name of the key to set
     * @param {String} val The value to set
     */
    function setLocObj(obj, name, val) {
      obj[name] = val;
      document.location.hash = objToString(qString);
    }

    $apTable = $('table.appraisal-stages');
    var stageID = $apTable.find('tr.selected').find('a.appraisal-stagelink').attr('data-id');
    var pageID = null;
    var qString = {};

    document.location.hash.replace(/\#?(?:([^=]+)=([^&]*)&?)/g, function () {
      function decode(s) {
        return decodeURIComponent(s.split("+").join(" "));
      }
      qString[decode(arguments[1])] = decode(arguments[2]);
    });

    /**
     * ajax loading of pages upon clicking a stage
     */
    $apTable.find('a.appraisal-stagelink').on('click', function() {
      var $this = $(this);
      if ($this.attr('data-id') != stageID) {
        loadPages($this.attr('data-id'), $this);
      }
      return false;
    });

    function loadPages(sID, stageLink, clearQS, newPage) {
      var $stageLink = stageLink || $apTable.find('a.appraisal-stagelink[data-id="' + sID + '"]');
      var clearQS = clearQS || false;
      var newPage = newPage || false;
      stageID = sID;

      if (clearQS || (sID != qString['id'])) {
        qString = {};
        setLocObj(qString, 'id', sID);
      }

      $.get(M.cfg.wwwroot + '/totara/appraisal/ajax/stage.php', {
        id : sID
      }).done(function(data) {

        $('#appraisalstagecontainer').html($(data).html());

        if ($stageLink) {
          $apTable.find('tr.selected').removeClass('selected');
          $stageLink.closest('tr').addClass('selected');
        }

        attachEvents();

        if (newPage) {
          $('#appraisalstagecontainer').find('a.appraisal-page-list-name').last().click();
        } else if (qString['appraisalstagepageid']) {
          $('#appraisalstagecontainer').find('a[data-pageid="' + qString['appraisalstagepageid'] + '"]').click();
        } else {
          $('#appraisalstagecontainer').find('a.appraisal-page-list-name').eq(0).click();
        }

      });
    }

    /**
     * Modal popup for generic single stage form. Requires the existence of standard mform with buttons #id_submitbutton and #id_cancel
     * @param content The desired contents of the panel
     * @param {String} func optional Function to be called upon submitting the form
     * @param {Array} args optional Array of arguments to be sent to the optional function
     */
    function modalForm(content, func, args) {
      var func = func || false;
      var args = args || false;
      this.Y.use('panel', function(Y) {
        var panel = new Y.Panel({
          headerContent: null,
          bodyContent: content,
          width        : 500,
          zIndex       : 5,
          centered     : true,
          modal        : true,
          render       : true,
        });
        var $content = $('#' + panel.get('id'));
        $content.find('input[type="text"]').eq(0).focus();
        $content.find('#id_submitbutton').on('click', function() {
          var $theFrm = $content.find('form.mform');
          var apprObj = $theFrm.serialize();
          apprObj += ('&submitbutton=' + $(this).attr('value'));
          $.post($theFrm.attr('action'), apprObj).done(function(data){
            if (data == 'success') {
              if(func) {
                var theFunc = eval('(' + func + ')');
                theFunc.apply(null, Array.prototype.slice.call(args));
              }
              panel.destroy(true);
            } else {
              panel.destroy(true);
              modalForm(data, func, args);
            }
          });
          return false;
        });
        $content.find('#id_cancel').on('click', function() {
          panel.destroy(true);
          return false;
        });
        panel.show();
      });
    }

    /**
     * Modal error message
     * @param {String} msg The error message to display
     */
    function modalError(msg) {
      var msg = msg || "An unknown error occurred.";
      this.Y.use('panel', function(Y) {
        var panel = new Y.Panel({
          bodyContent: msg,
          width        : 300,
          zIndex       : 5,
          centered     : true,
          modal        : true,
          render       : true,
          buttons: [
            {
              name: "confirm",
              value  : M.util.get_string('ok','moodle'),
              section: Y.WidgetStdMod.FOOTER,
              action : function (e) {
                e.preventDefault();
                panel.destroy(true);
              }
            }
          ]
        });
        panel.getButton("confirm").removeClass("yui3-button");
        panel.show();
      });
    }

    function updateRoles(sID) {
      $.get(M.cfg.wwwroot + '/totara/appraisal/ajax/stage.php', {
        id : sID,
        action : 'getroles'
      }).done(function(data) {
        for (var i = 0; i < $(data).length; i++) {
          var $stage = $($(data)[i]);
          var $id = $stage.attr('id');
          if (typeof $id !== 'undefined' && $id !== false) {
            var $row = $('table.appraisal-stages a.appraisal-stagelink[data-id="' + $id + '"]').closest('tr');
            $row.find('span.appraisal-rolescananswer').html($stage.find('.cananswer'));
            $row.find('span.appraisal-rolescanview').html($stage.find('.canview'));
          }
        }
      });
    }

    /**
     * Modal poup for adding a question to a page
     * @param content The form to display
     * @param {String} url The URL to load into the content area upon success
     */
    function modalAddEditQuestion(content, url) {
      this.Y.use('panel', function(Y){
        var panel = new Y.Panel({
          headerContent: null,
          bodyContent  : content,
          width        : 800,
          zIndex       : 5,
          centered     : true,
          modal        : true,
          focusOn      : []
        });
        panel.render();
        panel.show();
        panel.on('visibleChange', function(e) {
          if (e.newVal == false) {
            panel.destroy();
          }
        });
        if (panel.get("y") < 10) {
          panel.set("y", 10);
        }
        var $content = $('#' + panel.get('id'));
        $content.find('script').each(function() {
            $.globalEval($(this).html());
        });
        $content.find('input[type="text"]').eq(0).focus();
        if (prevRoles.length) {
          $roles = $content.find('input[id^="id_roles_"]');
          $content.find('#id_cloneprevroles').on('click', function() {
            $roles.each(function(){
              var $this = $(this);
              $this.off('click.props');
              if (prevRoles.indexOf($this.attr('id')) != -1) {
                if (!$this.prop('checked')) {
                  $this.click();
                  $this.prop('checked', true);
                }
              } else {
                if ($this.prop('checked')) {
                  $this.click();
                  $this.prop('checked', false);
                }
              }
              $this.on('click.props', function(){
                $content.find('#id_cloneprevroles').prop('checked', false);
              });
            });
          });
        }
        $content.find('#id_submitbutton').on('click', function() {
          if (tinymce.activeEditor) {
            tinymce.activeEditor.save();
          }
          var $theFrm = $content.find('form.mform');

          // Save all tinyMCE editors.
          // TODO: T-11236 Find way for event propagation.
          if (tinyMCE) {
            for (edId in tinyMCE.editors) {
              tinyMCE.editors[edId].save();
            }
          }
          var apprObj = $theFrm.serialize();
          apprObj += ('&submitbutton=' + $(this).attr('value'));
          $.post($theFrm.attr('action'), apprObj).done(function(data){
            if (data == 'success') {
              pageContent(url);
              updateRoles(stageID);
              panel.destroy(true);
            } else {
              panel.destroy(true);
              modalAddEditQuestion(data, url);
            }
          });
          return false;
        });
        $content.find('#id_cancel').on('click', function() {
          panel.destroy(true);
          return false;
        });
      });
    }

    /**
     * modal popup for deleting an item
     * @param {String} url The URL to get to delete the item.
     * @param {Object} el optional The DOM element being deleted, for fancy removal from the display.
     */
    function modalDelete(url, el, func, args) {
      var $el = el || false;
      var theFunc;
      this.Y.use('panel', function(Y) {
          var bodyContent;
          var hasRedisplay = url.match(/hasredisplay=([^&]+)/);
          if (hasRedisplay) {
              bodyContent = M.util.get_string('confirmdeleteitemwithredisplay', 'totara_appraisal');
          } else {
              bodyContent = M.util.get_string('confirmdeleteitem', 'totara_appraisal');
          }
          var panel = new Y.Panel({
          bodyContent  : bodyContent,
          width        : 300,
          zIndex       : 5,
          centered     : true,
          modal        : true,
          render       : true,
          buttons: [
            {
              name: "confirm",
              value  : M.util.get_string('yes','moodle'),
              section: Y.WidgetStdMod.FOOTER,
              action : function (e) {
                e.preventDefault();
                $.get(url, {sesskey: M.totara_appraisal_stage.config.sesskey, confirm: 1}).done(function(data){
                  if (data == 'success') {
                    if ($el) {
                      $el.slideUp(250, function(){
                        $el.remove();
                        if(func) {
                          theFunc = eval('(' + func + ')');
                          theFunc.apply(null, Array.prototype.slice.call(args));
                        }
                      });
                    } else {
                      if(func) {
                        theFunc = eval('(' + func + ')');
                        theFunc.apply(null, Array.prototype.slice.call(args));
                      }
                    }
                    updateRoles(stageID);
                  } else {
                    modalError(M.util.get_string('error:cannotdelete','totara_appraisal'));
                  }
                }).fail(function(){
                  modalError(M.util.get_string('error:cannotdelete','totara_appraisal'));
                });
                panel.destroy(true);
              }
            },
            {
              name: "deny",
              value  : M.util.get_string('no','moodle'),
              section: Y.WidgetStdMod.FOOTER,
              action : function (e) {
                e.preventDefault();
                panel.destroy(true);
              }
            }
          ]
        });
        panel.getButton("confirm").removeClass("yui3-button");
        panel.getButton("deny").removeClass("yui3-button");
        panel.show();

      });
    }

    /**
     * Load page content into the appropriate area
     * @param {String} url The URL of the content to load
     */
    function pageContent(url, pageLink) {
      var $pageLink = pageLink || false;
      $('#appraisal-questions').load(url, function(){
        var $appQuestions = $('#appraisal-questions');

        // button to add a new question
        $('#id_submitbutton').on('click', function(e) {
          e.preventDefault();
          var apprObj = $appQuestions.find('form.mform').serialize();
          $.post($appQuestions.find('form.mform').attr('action'), apprObj).done(function(data){
            modalAddEditQuestion(data, url);
          });
        });

        // if this was tiggered by someone clicking on a page link, swap the selected class to it
        if ($pageLink) {
          $('#appraisal-page-list').find('li.selected').removeClass('selected');
          $pageLink.closest('li').addClass('selected');
          pageID = $pageLink.attr('data-pageid');
        }

        if (!qString['id']) {
          setLocObj(qString, 'id', stageID);
        }

        setLocObj(qString, 'appraisalstagepageid', pageID);

        // edit question action-icon link
        $('a.action-icon.edit', '#appraisal-quest-list').on('click', function() {
          $.get($(this).attr('href')).done(function(data){
            modalAddEditQuestion(data, M.cfg.wwwroot + '/totara/appraisal/ajax/question.php?appraisalstagepageid=' + pageID);
          });
          return false;
        });

        // delete question action-icon link
        $('a.action-icon.delete', '#appraisal-quest-list').on('click', function() {
          modalDelete($(this).attr('href'), $(this).closest('li'));
          return false;
        });

        // duplicate question action-icon link
        $('a.action-icon.copy', '#appraisal-quest-list').on('click', function() {
          $.get($(this).attr('href') + '&sesskey=' + M.totara_appraisal_stage.config.sesskey, function(data) {
            if (data == 'success') {
              pageContent(url);
            }
          });
          return false;
        });

        if ($('#appraisal-page-list').find('img.move').length) {
          makeDraggable(M.cfg.wwwroot + '/totara/appraisal/ajax/question.php', '#appraisal-quest-list', 'li', [{id:'#appraisal-page-list', type:'li'},{id:'.appraisal-stages', type:'tr'}]);
        }
      });
    }

    function makeDraggable(url, elID, elType, s) {
      var elType = elType || 'li';
      var sType = sType || 'li';
      // drag and drop (shamelessly stolen from http://yuilibrary.com/yui/docs/dd/scroll-list.html with modifications to add an ajax call on drop)
      YUI().use('dd', function(Y) {

        var lis = Y.all(elID + ' ' + elType);
        var ul = Y.one(elID);
        var slis = [];
        for (var i in s) {
          slis[i] = Y.all(s[i].id + ' ' + s[i].type);
        }

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
          if (node.getData('moved')) {
              newPos = 0;
          }
          $.get(url, {
            sesskey: M.totara_appraisal_stage.config.sesskey,
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

        for (var i in slis) {
          slis[i].each(function(v, k){
            if (!v.hasClass('selected')) {
              var drop = v.plug(Y.Plugin.Drop);

              drop.drop.on('drop:enter', function(e) {
                e.drop.get('node').addClass('move-target');
              });

              drop.drop.on('drop:exit', function(e) {
                e.drop.get('node').removeClass('move-target');
              });

              drop.drop.on('drop:hit', function(e){
                var drop = e.drop.get('node'), drag = e.drag.get('node');
                drop.removeClass('move-target');
                drag.setData('moved', 'resetpos');
                $.get(url, {
                  sesskey: M.totara_appraisal_stage.config.sesskey,
                  action: 'move',
                  id: drag.getData('pageid') || drag.getData('questid'),
                  target: drop.getData('pageid') || drop.one('a.appraisal-stagelink').getData('id'),
                  type: drop.getData('type') || drop.one('a.appraisal-stagelink').getData('type'),
                  dataType: 'json'
                }).done(function(data){
                  if (data == 'success') {
                    drag.remove();
                    updateRoles(stageID);
                    if (drop.one('a.appraisal-stagelink')) {
                      updateRoles(drop.one('a.appraisal-stagelink').getData('id'));
                    }
                  } else {
                    data = $.parseJSON(data);
                    if (data.error) {
                      modalError(data.error);
                    } else {
                      modalError();
                    }
                  }
                });
              });
            }
          });
        }

      });
    }

    /**
     *  Attaches mouse events to the loaded content.
     */
    function attachEvents() {
      // add new page button
      $('#appraisal-add-page').on('click', function(){
        $.get($(this).attr('href'), function(data){
          modalForm(data, 'loadPages', [stageID, null, false, true]);
        });
        return false;
      });

      // page title link to display page content in right hand panel
      $('a.appraisal-page-list-name', '#appraisal-page-list').on('click', function(){
        if (!$(this).parent().hasClass('selected')) {
          pageContent($(this).attr('href'), $(this));
        }
        return false;
      });

      // edit page action-icon link
      $('a.action-icon.edit', '#appraisal-page-list').on('click', function(){
        $.get($(this).attr('href'), function(data){
          modalForm(data, 'loadPages', [stageID]);
        });
        return false;
      });

      // delete page action-icon link
      $('a.action-icon.delete', '#appraisal-page-list').on('click', function() {
        var func = null;
        if ($(this).closest('li').hasClass('selected')) {
          func = 'loadPages';
        }
        modalDelete($(this).attr('href'), $(this).closest('li'), func, [stageID, null, true]);
        return false;
      });

      if ($('#appraisal-page-list').find('img.move').length) {
        makeDraggable(M.cfg.wwwroot + '/totara/appraisal/ajax/page.php?sesskey=' + M.totara_appraisal_stage.config.sesskey,
                '#appraisal-page-list', 'li', [{id:'.appraisal-stages', type:'tr'}]);
      }

    }

    attachEvents();

    if (qString['id']) {
      loadPages(qString['id'], null);
    } else {
      $('#appraisalstagecontainer').find('a.appraisal-page-list-name').eq(0).click();
    }

  }
}
