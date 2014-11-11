<?php

require_once(dirname(__FILE__) . '/../SVGGraphJavascript.php');

class SVGGraphJavascriptTest extends PHPUnit_Framework_TestCase
{

  public function testEmulateJsonEncode()
  {
    $data = array(
      null,
      true,
      FALSE,
      -6666,
      33333.33,
      "aaa'aaa\"aaa\naaa\taaa\raaa\x08aaa\faaa\\aaa/aaa\00aaa",
      array('a', 'b', 'c'),
      array(2=>'d', true=>'e', null=>'f', 5=>'g', true=>'h'),
      (object)array("o\n"=>'x', 'p'=>'y', 'z'=>55),
      new stdClass(),
      array(),
      (object)array('a', 'b'),
    );
    $expected = array(
      'null',
      'true',
      'false',
      '-6666',
      '33333.33',
      '"aaa\'aaa\"aaa\naaa\taaa\raaa\baaa\faaa\\\\aaa\/aaa\u0000aaa"',
      '["a","b","c"]',
      '{"2":"d","1":"h","":"f","5":"g"}',
      '{"o\n":"x","p":"y","z":55}',
      '{}',
      '[]',
      '{"0":"a","1":"b"}',
    );

    if (function_exists('json_encode')) {
      // Let's verify the PHP behaves as expected.
      foreach ($data as $k => $v) {
        $this->assertSame($expected[$k], json_encode($v));
      }
    }

    foreach ($data as $k => $v) {
      // Now test the emulation.
      $res = SVGGraphJavascript::emulate_json_encode($v);
      $this->assertSame($expected[$k], $res);
    }
  }
}
