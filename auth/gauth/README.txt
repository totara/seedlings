Google OpenId Authentication for Moodle
-------------------------------------------------------------------------------
license: http://www.gnu.org/copyleft/gpl.html GNU Public License

Changes:
- 2011-10    : Created by Piers Harding

Requirements:
- None

Notes:

Install instructions:
  1. unpack this archive into the / directory as you would for any Moodle
     auth module (http://docs.moodle.org/en/Installing_contributed_modules_or_plugins).
  2. Login to Moodle as an administrator, and activate the module by navigating
     Administration -> Plugins -> Authentication -> Manage authentication and clicking on the enable icon.
  3. Configure the settings for the plugin - pay special attention to the
     mapping of the Moodle and Google username attribute.
- 4. If you only want auth/gauth as login option, change login page to point to auth/gauth/index.php
- 5. If you want to use another authentication method together with auth/gauth,
    in parallel, change the 'Instructions' in the 'Common settings' of the
    'Administrations >> Users >> Authentication Options' to contain a link to the
    auth/gauth login page (-- remember to check the href and src paths --):
    <br>Click <a href="auth/gauth/index.php">here</a> to login with SSO
- 6 Save the changes for the 'Common settings'

Multiple Domains:
If you want to allow multiple domains access in, then set domainname to a comma delimited list of the valid domains.
This will not work with the Enable GAPPS domain specific login option.

Country attribute:
is the 2 letter ISO country code, ie NZ for New Zealand.  The default constant value is taken from
the field mappings if the mapping offered is not an available attribute, and 'contact/country/home' is not present.

Problems:

If there is a problem with authentication, then please check:
  * your attribute matching from Google to Moodle
  * Your server clock time is synchronised with an appropriate NTP server (NONCE will be incorrect if you don't)
  * You MUST ALWAYS have the CURL extension active - the POST back verification most likely will not work unless you do.
  * to make CURL work you must have extension=curl.so somewhere in your config (usually in a separate file sunc has /etc/php.d/curl.ini, or /etc/php5/conf.d/curl.so)
  * safe_mode must be Off
  * open_basedir must be unset (seems to come up as a problem with CentOS - use php_admin_value open_basedir none in Apache2 config

 Debugging:
  * you can get quite a lot of extra information in the error.log by editing auth/gauth/index.php and setting:
     define('GAUTH_DEBUG', 1); // this should be 0 to turn off