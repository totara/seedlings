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
 * @author Valerii Kuznetsov <valerii.kuznetsov@totaralms.com>
 * @package totara
 * @subpackage phpseclib
 *
 * Unit tests for totara/reportbuilder/lib.php
 */
if (!defined('MOODLE_INTERNAL') && (PHP_SAPI != 'cli')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

// Separate process reinitialization
if (!defined('PHPUNIT_TEST')) {
    define('CLI_SCRIPT', 1);
    require_once(dirname(dirname(dirname(__DIR__))) . '/config.php');
}

global $CFG;
require_once($CFG->dirroot . '/totara/core/totara.php');
require_once($CFG->libdir . '/phpseclib/Crypt/RSA.php');

/**
 * Test phpseclib for encryption-decryption
 */
class phpseclib_rsa_test extends PHPUnit_Framework_TestCase {
    protected $public = '';
    protected $private = '';

    public function setUp() {
        $rsa = new Crypt_RSA();
        $keys = $rsa->createKey();
        $this->private = $keys['privatekey'];
        $this->public = $keys['publickey'];
    }

    /**
     * Test key creation, encryption and decryption
     */
    public function test_rsa() {
        // This test requires process isolation, Otherwise it leads to segmentation fault during
        // all tests suite execution.
        // Possible workaraounds related to @runInSeparateProcess and @preserveGlobalState disabled
        // lead to process kill in jenkins.
        // Further investigation required.
        $this->markTestSkipped('Skip this test untill --process-isolation is enabled in build system');
        $data = array('site' => 'Super site', 'data' => 'my data', 3 => 5, '6' => '9', 'test');
        $sdata = json_encode($data);
        $format_private = '/-----BEGIN RSA PRIVATE KEY-----[A-Z0-9\\n\\r-\\/+=]+-----END RSA PRIVATE KEY-----/im';
        $format_public = '/-----BEGIN PUBLIC KEY-----[A-Z0-9\\n\\r-\\/+=]+-----END PUBLIC KEY-----/im';

        $this->assertEquals(1, preg_match($format_private, $this->private));
        $this->assertEquals(1, preg_match($format_public, $this->public));

        $ciphertext = encrypt_data($sdata, $this->public);
        $this->assertNotEmpty($ciphertext);
        $newsdata = $this->decrypt_data($ciphertext);
        $newdata = json_decode($newsdata, true);
        $this->assertNotEmpty($newdata);
        $this->assertEquals($data, $newdata);
    }

    /**
     * Decrypt previously encrypted text
     *
     * @param string $ciphertext
     * @return string
     */
    protected function decrypt_data($ciphertext) {
        $rsa = new Crypt_RSA();
        $rsa->loadKey($this->private);
        $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
        $plaintext = $rsa->decrypt($ciphertext);
        return $plaintext;
    }
}