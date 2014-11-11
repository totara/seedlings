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
 * @author Jon Sharp <jon.sharp@catalyst-eu.net>
 * @package totara
 * @subpackage certification
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/totara/certification/lib.php');

/**
 * Certificate module PHPUnit archive test class
 *
 * To test, run this from the command line from the $CFG->dirroot
 * vendor/bin/phpunit --verbose totara_certification_recertdates_testcase totara/certification/tests/recertdates_test.php
 *
 * @package    totara_certifications
 * @category   phpunit
 * @group      totara_certifications
 * @author     Jon Sharp <jonathans@catalyst-eu.net>
 * @copyright  Catalyst IT Ltd 2013 <http://catalyst-eu.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class totara_certification_recertdates_testcase extends advanced_testcase {

    public function test_recertdates() {

        // Expiry.
        $activeperiod = '1 year';
        $windowperiod = '1 month';

        $curtimeexpires = strtotime('3-May-2013 08:14');
        $timecompleted = strtotime('15-April-2013 12:01');

        $expnewtimeexpires = strtotime('3-May-2014 08:14');
        $exptimewindowopens = strtotime('3-Apr-2014 08:14');

        $base = get_certiftimebase(CERTIFRECERT_EXPIRY, $curtimeexpires, $timecompleted);
        $newtimeexpires = get_timeexpires($base, $activeperiod);
        $timewindowopens = get_timewindowopens($newtimeexpires, $windowperiod);

        $this->assertEquals($newtimeexpires, $expnewtimeexpires);
        $this->assertEquals($timewindowopens, $exptimewindowopens);

        // Completion.
        $expnewtimeexpires = strtotime('15-April-2014 12:01');
        $exptimewindowopens = strtotime('15-March-2014 12:01');

        $base = get_certiftimebase(CERTIFRECERT_COMPLETION, $curtimeexpires, $timecompleted);
        $newtimeexpires = get_timeexpires($base, $activeperiod);
        $timewindowopens = get_timewindowopens($newtimeexpires, $windowperiod);

        $this->assertEquals($newtimeexpires, $expnewtimeexpires);
        $this->assertEquals($timewindowopens, $exptimewindowopens);

        // Completion (window period is weeks).
        $expnewtimeexpires = strtotime('15-April-2014 12:01');
        $exptimewindowopens = strtotime('25-March-2014 12:01');

        $windowperiod = '3 week';

        $base = get_certiftimebase(CERTIFRECERT_COMPLETION, $curtimeexpires, $timecompleted);
        $newtimeexpires = get_timeexpires($base, $activeperiod);
        $timewindowopens = get_timewindowopens($newtimeexpires, $windowperiod);

        $this->assertEquals($newtimeexpires, $expnewtimeexpires);
        $this->assertEquals($timewindowopens, $exptimewindowopens);
    }
}
