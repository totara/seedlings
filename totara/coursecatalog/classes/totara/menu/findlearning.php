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
 * Totara navigation edit page.
 *
 * @package    totara
 * @subpackage navigation
 * @author     Oleg Demeshev <oleg.demeshev@totaralms.com>
 */

namespace totara_coursecatalog\totara\menu;

use \totara_core\totara\menu\menu as menu;

class findlearning extends \totara_core\totara\menu\item {

    protected function get_default_title() {
        return get_string('findlearning', 'totara_coursecatalog');
    }

    protected function get_default_url() {
        global $CFG;

        if (!empty($CFG->enhancedcatalog)) {
            return '/totara/coursecatalog/courses.php';
        } else {
            return '/course/index.php';
        }
    }

    public function get_default_sortorder() {
        return 60000;
    }

    public function get_default_visibility() {
        return menu::SHOW_WHEN_REQUIRED;
    }
}
