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
 * @package totara_core
 *
 * Unit tests for phpseclib
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/totara/core/totara.php');

/**
 * Test phpseclib for encryption-decryption
 */
class totara_core_phpseclib_testcase extends advanced_testcase {
    /**
     * Test totara encryption.
     */
    public function test_encrypt_data() {
        // NOTE: do not include the RSA directly here!

        // Encrypt using public Totara key.
        $ciphertext = encrypt_data('secret');

        // Pretty much any result is ok, we need to detect notices and errors here,
        // the actual encryption is tested next.
        $this->assertSame(128, strlen($ciphertext));
    }

    /**
     * Test key creation, encryption and decryption
     */
    public function test_rsa() {
        global $CFG;

        // Delay this include so that we test we have the include in encrypt_data() working.
        require_once($CFG->dirroot . '/totara/core/lib/phpseclib/Crypt/RSA.php');

        $rsa = new Crypt_RSA();
        $keys = $rsa->createKey();
        $privatekey = $keys['privatekey'];
        $publickey = $keys['publickey'];

        $data = array('site' => 'Super site', 'data' => 'my data', 3 => 5, '6' => '9', 'test');
        $sdata = json_encode($data);
        $format_private = '/-----BEGIN RSA PRIVATE KEY-----[A-Z0-9\\n\\r-\\/+=]+-----END RSA PRIVATE KEY-----/im';
        $format_public = '/-----BEGIN PUBLIC KEY-----[A-Z0-9\\n\\r-\\/+=]+-----END PUBLIC KEY-----/im';

        $this->assertEquals(1, preg_match($format_private, $privatekey));
        $this->assertEquals(1, preg_match($format_public, $publickey));

        $ciphertext = encrypt_data($sdata, $publickey);
        $this->assertNotEmpty($ciphertext);
        $newsdata = self::decrypt_data($ciphertext, $privatekey);
        $newdata = json_decode($newsdata, true);
        $this->assertNotEmpty($newdata);
        $this->assertEquals($data, $newdata);
    }

    /**
     * Decrypt previously encrypted text
     *
     * @param string $ciphertext
     * @param string $privatekey
     * @return string
     */
    protected static function decrypt_data($ciphertext, $privatekey) {
        $rsa = new Crypt_RSA();
        $rsa->loadKey($privatekey);
        $rsa->setEncryptionMode(CRYPT_RSA_ENCRYPTION_PKCS1);
        $plaintext = $rsa->decrypt($ciphertext);
        return $plaintext;
    }
}
