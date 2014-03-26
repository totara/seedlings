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
 * @author Darko Miletic
 * @package totara
 * @subpackage cron
 */

/**
 *
 * Class that performs effective OS portable script lock
 * Ideal for locking scripts executed from command line
 * @author Darko Miletic <darko.miletic@totaralms.com>
 *
 */
class cron_lockfile {
    /**
     *
     * Enter description here ...
     * @var resource
     */
    private $handle = null;

    /**
     *
     * Class CTOR - specify file to lock
     *
     * @param string $filename - lock filename
     */
    public function __construct($filename) {
        $handle = fopen($filename, 'r');
        if ($handle !== false) {
            $result = flock($handle, LOCK_EX | LOCK_NB);
            if ($result) {
                $this->handle = $handle;
            } else {
                fclose($handle);
            }
        }
    }

    public function __destruct(){
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN | LOCK_NB);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    /**
     *
     * Helper that checks whether the file was
     * locked by us or not
     * @return bool
     */
    public function locked() {
        return ($this->handle !== null);
    }
}
