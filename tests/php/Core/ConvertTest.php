<?php

namespace SilverStripe\Core\Tests;

use Exception;
use InvalidArgumentException;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\Deprecation;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\View\Parsers\URLSegmentFilter;
use stdClass;

/**
 * Test various functions on the {@link Convert} class.
 */
class ConvertTest extends SapphireTest
{

    protected $usesDatabase = false;

    private $previousLocaleSetting = null;

    protected function setUp(): void
    {
        parent::setUp();
        // clear the previous locale setting
        $this->previousLocaleSetting = null;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // If a test sets the locale, reset it on teardown
        if ($this->previousLocaleSetting) {
            setlocale(LC_CTYPE, $this->previousLocaleSetting);
        }
    }

    /**
     * Tests {@link Convert::raw2att()}
     */
    public function testRaw2Att()
    {
        $val1 = '<input type="text">';
        $this->assertEquals(
            '&lt;input type=&quot;text&quot;&gt;',
            Convert::raw2att($val1),
            'Special characters are escaped'
        );

        $val2 = 'This is some normal text.';
        $this->assertEquals(
            'This is some normal text.',
            Convert::raw2att($val2),
            'Normal text is not escaped'
        );
    }

    /**
     * Tests {@link Convert::raw2htmlatt()}
     */
    public function testRaw2HtmlAtt()
    {
        $val1 = '<input type="text">';
        $this->assertEquals(
            '&lt;input type=&quot;text&quot;&gt;',
            Convert::raw2htmlatt($val1),
            'Special characters are escaped'
        );

        $val2 = 'This is some normal text.';
        $this->assertEquals(
            'This is some normal text.',
            Convert::raw2htmlatt($val2),
            'Normal text is not escaped'
        );
    }

    /**
     * Tests {@link Convert::html2raw()}
     */
    public function testHtml2raw()
    {
        $val1 = 'This has a <strong>strong tag</strong>.';
        $this->assertEquals(
            'This has a *strong tag*.',
            Convert::html2raw($val1),
            'Strong tags are replaced with asterisks'
        );

        $val1 = 'This has a <b class="test" style="font-weight: bold">b tag with attributes</b>.';
        $this->assertEquals(
            'This has a *b tag with attributes*.',
            Convert::html2raw($val1),
            'B tags with attributes are replaced with asterisks'
        );

        $val2 = 'This has a <strong class="test" style="font-weight: bold">strong tag with attributes</STRONG>.';
        $this->assertEquals(
            'This has a *strong tag with attributes*.',
            Convert::html2raw($val2),
            'Strong tags with attributes are replaced with asterisks'
        );

        $val3 = '<script type="application/javascript">Some really nasty javascript here</script>';
        $this->assertEquals(
            '',
            Convert::html2raw($val3),
            'Script tags are completely removed'
        );

        $val4 = '<style type="text/css">Some really nasty CSS here</style>';
        $this->assertEquals(
            '',
            Convert::html2raw($val4),
            'Style tags are completely removed'
        );

        $val5 = "<script type=\"application/javascript\">Some really nasty\nmultiline javascript here</script>";
        $this->assertEquals(
            '',
            Convert::html2raw($val5),
            'Multiline script tags are completely removed'
        );

        $val6 = "<style type=\"text/css\">Some really nasty\nmultiline CSS here</style>";
        $this->assertEquals(
            '',
            Convert::html2raw($val6),
            'Multiline style tags are completely removed'
        );

        $val7 = '<p>That&#39;s absolutely correct</p>';
        $this->assertEquals(
            "That's absolutely correct",
            Convert::html2raw($val7),
            'Single quotes are decoded correctly'
        );

        $val8 = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor ' . 'incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud ' . 'exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute ' . 'irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla ' . 'pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia ' . 'deserunt mollit anim id est laborum.';
        $this->assertEquals($val8, Convert::html2raw($val8), 'Test long text is unwrapped');
        $this->assertEquals(
            <<<PHP
Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed
do eiusmod tempor incididunt ut labore et dolore magna
aliqua. Ut enim ad minim veniam, quis nostrud exercitation
ullamco laboris nisi ut aliquip ex ea commodo consequat.
Duis aute irure dolor in reprehenderit in voluptate velit
esse cillum dolore eu fugiat nulla pariatur. Excepteur sint
occaecat cupidatat non proident, sunt in culpa qui officia
deserunt mollit anim id est laborum.
PHP
            ,
            Convert::html2raw($val8, false, 60),
            'Test long text is wrapped'
        );
    }

    /**
     * Tests {@link Convert::raw2xml()}
     */
    public function testRaw2Xml()
    {
        $val1 = '<input type="text">';
        $this->assertEquals(
            '&lt;input type=&quot;text&quot;&gt;',
            Convert::raw2xml($val1),
            'Special characters are escaped'
        );

        $val2 = 'This is some normal text.';
        $this->assertEquals(
            'This is some normal text.',
            Convert::raw2xml($val2),
            'Normal text is not escaped'
        );

        $val3 = "This is test\nNow on a new line.";
        $this->assertEquals(
            "This is test\nNow on a new line.",
            Convert::raw2xml($val3),
            'Newlines are retained. They should not be replaced with <br /> as it is not XML valid'
        );
    }

    /**
     * Tests {@link Convert::raw2htmlid()}
     */
    public function testRaw2HtmlID()
    {
        $val1 = 'test test 123';
        $this->assertEquals('test_test_123', Convert::raw2htmlid($val1));

        $val2 = 'test[test][123]';
        $this->assertEquals('test_test_123', Convert::raw2htmlid($val2));

        $val3 = '[test[[test]][123]]';
        $this->assertEquals('test_test_123', Convert::raw2htmlid($val3));

        $val4 = 'A\\Namespaced\\Class';
        $this->assertEquals('A_Namespaced_Class', Convert::raw2htmlid($val4));
    }

    /**
     * Tests {@link Convert::xml2raw()}
     */
    public function testXml2Raw()
    {
        $val1 = '&lt;input type=&quot;text&quot;&gt;';
        $this->assertEquals('<input type="text">', Convert::xml2raw($val1), 'Special characters are escaped');

        $val2 = 'This is some normal text.';
        $this->assertEquals('This is some normal text.', Convert::xml2raw($val2), 'Normal text is not escaped');
    }

    /**
     * Tests {@link Convert::xml2raw()}
     */
    public function testArray2JSON()
    {
        if (Deprecation::isEnabled()) {
            $this->markTestSkipped('Test calls deprecated code');
        }
        $val = [
         'Joe' => 'Bloggs',
         'Tom' => 'Jones',
         'My' => [
          'Complicated' => 'Structure'
         ]
        ];
        $encoded = Convert::array2json($val);
        $this->assertEquals(
            '{"Joe":"Bloggs","Tom":"Jones","My":{"Complicated":"Structure"}}',
            $encoded,
            'Array is encoded in JSON'
        );
    }

    /**
     * Tests {@link Convert::json2array()}
     */
    public function testJSON2Array()
    {
        if (Deprecation::isEnabled()) {
            $this->markTestSkipped('Test calls deprecated code');
        }
        $val = '{"Joe":"Bloggs","Tom":"Jones","My":{"Complicated":"Structure"}}';
        $decoded = Convert::json2array($val);
        $this->assertEquals(3, count($decoded ?? []), '3 items in the decoded array');
        $this->assertContains('Bloggs', $decoded, 'Contains "Bloggs" value in decoded array');
        $this->assertContains('Jones', $decoded, 'Contains "Jones" value in decoded array');
        $this->assertStringContainsString('Structure', $decoded['My']['Complicated']);
    }

    /**
     * Tests {@link Convert::testJSON2Obj()}
     */
    public function testJSON2Obj()
    {
        if (Deprecation::isEnabled()) {
            $this->markTestSkipped('Test calls deprecated code');
        }
        $val = '{"Joe":"Bloggs","Tom":"Jones","My":{"Complicated":"Structure"}}';
        $obj = Convert::json2obj($val);
        $this->assertEquals('Bloggs', $obj->Joe);
        $this->assertEquals('Jones', $obj->Tom);
        $this->assertEquals('Structure', $obj->My->Complicated);
    }

    /**
     * Tests {@link Convert::testRaw2URL()}
     *
     * @todo test toASCII()
     */
    public function testRaw2URL()
    {
        URLSegmentFilter::config()->set('default_allow_multibyte', false);
        $this->assertEquals('foo', Convert::raw2url('foo'));
        $this->assertEquals('foo-and-bar', Convert::raw2url('foo & bar'));
        $this->assertEquals('foo-and-bar', Convert::raw2url('foo &amp; bar!'));
        $this->assertEquals('foos-bar-2', Convert::raw2url('foo\'s [bar] (2)'));
    }

    /**
     * Helper function for comparing characters with significant whitespaces
     *
     * @param string $expected
     * @param string $actual
     */
    protected function assertEqualsQuoted($expected, $actual)
    {
        $message = sprintf(
            'Expected "%s" but given "%s"',
            addcslashes($expected ?? '', "\r\n"),
            addcslashes($actual ?? '', "\r\n")
        );
        $this->assertEquals($expected, $actual, $message);
    }

    /**
     * Tests {@link Convert::nl2os()}
     */
    public function testNL2OS()
    {

        foreach (["\r\n", "\r", "\n"] as $nl) {
            // Base case: no action
            $this->assertEqualsQuoted(
                'Base case',
                Convert::nl2os('Base case', $nl)
            );

            // Mixed formats
            $this->assertEqualsQuoted(
                "Test{$nl}Text{$nl}Is{$nl}{$nl}Here{$nl}.",
                Convert::nl2os("Test\rText\r\nIs\n\rHere\r\n.", $nl)
            );

            // Test that multiple runs are non-destructive
            $expected = "Test{$nl}Text{$nl}Is{$nl}{$nl}Here{$nl}.";
            $this->assertEqualsQuoted(
                $expected,
                Convert::nl2os($expected, $nl)
            );

            // Check repeated sequence behaves correctly
            $expected = "{$nl}{$nl}{$nl}{$nl}{$nl}{$nl}{$nl}{$nl}";
            $input = "\r\r\n\r\r\n\n\n\n\r";
            $this->assertEqualsQuoted(
                $expected,
                Convert::nl2os($input, $nl)
            );
        }
    }

    /**
     * Tests {@link Convert::raw2js()}
     */
    public function testRaw2JS()
    {
        // Test attempt to break out of string
        $this->assertEquals(
            '\\"; window.location=\\"http://www.google.com',
            Convert::raw2js('"; window.location="http://www.google.com')
        );
        $this->assertEquals(
            '\\\'; window.location=\\\'http://www.google.com',
            Convert::raw2js('\'; window.location=\'http://www.google.com')
        );
        // Test attempt to close script tag
        $this->assertEquals(
            '\\"; \\x3c/script\\x3e\\x3ch1\\x3eHa \\x26amp; Ha\\x3c/h1\\x3e\\x3cscript\\x3e',
            Convert::raw2js('"; </script><h1>Ha &amp; Ha</h1><script>')
        );
        // Test newlines are properly escaped
        $this->assertEquals(
            'New\\nLine\\rReturn',
            Convert::raw2js("New\nLine\rReturn")
        );
        // Check escape of slashes
        $this->assertEquals(
            '\\\\\\"\\x3eClick here',
            Convert::raw2js('\\">Click here')
        );
    }

    /**
     * Tests {@link Convert::raw2json()}
     */
    public function testRaw2JSON()
    {
        if (Deprecation::isEnabled()) {
            $this->markTestSkipped('Test calls deprecated code');
        }

        // Test object
        $input = new stdClass();
        $input->Title = 'My Object';
        $input->Content = '<p>Data</p>';
        $this->assertEquals(
            '{"Title":"My Object","Content":"<p>Data<\/p>"}',
            Convert::raw2json($input)
        );

        // Array
        $array = ['One' => 'Apple', 'Two' => 'Banana'];
        $this->assertEquals(
            '{"One":"Apple","Two":"Banana"}',
            Convert::raw2json($array)
        );

        // String value with already encoded data. Result should be quoted.
        $value = '{"Left": "Value"}';
        $this->assertEquals(
            '"{\\"Left\\": \\"Value\\"}"',
            Convert::raw2json($value)
        );
    }

    /**
     * Test that a context bitmask can be passed through to the json_encode method in {@link Convert::raw2json()}
     * and in {@link Convert::array2json()}
     */
    public function testRaw2JsonWithContext()
    {
        if (Deprecation::isEnabled()) {
            $this->markTestSkipped('Test calls deprecated code');
        }
        $data = ['foo' => 'b"ar'];
        $expected = '{"foo":"b\u0022ar"}';
        $result = Convert::raw2json($data, JSON_HEX_QUOT);
        $this->assertSame($expected, $result);
        $wrapperResult = Convert::array2json($data, JSON_HEX_QUOT);
        $this->assertSame($expected, $wrapperResult);
    }

    /**
     * Tests {@link Convert::xml2array()}
     */
    public function testXML2Array()
    {
        if (Deprecation::isEnabled()) {
            $this->markTestSkipped('Test calls deprecated code');
        }
        $inputXML = <<<XML
<?xml version="1.0"?>
<!DOCTYPE results [
  <!ENTITY long "SOME_SUPER_LONG_STRING">
]>
<results>
    <result>My para</result>
    <result>Ampersand &amp; is retained and not double encoded</result>
</results>
XML
        ;
        $expected = [
            'result' => [
                'My para',
                'Ampersand & is retained and not double encoded'
            ]
        ];
        $actual = Convert::xml2array($inputXML, false);
        $this->assertEquals($expected, $actual);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('XML Doctype parsing disabled');
        Convert::xml2array($inputXML, true);
    }

    /**
     * Tests {@link Convert::xml2array()} if an exception the contains a reference to a removed <!ENTITY />
     */
    public function testXML2ArrayEntityException()
    {
        if (Deprecation::isEnabled()) {
            $this->markTestSkipped('Test calls deprecated code');
        }
        $inputXML = <<<XML
        <?xml version="1.0"?>
        <!DOCTYPE results [
            <!ENTITY long "SOME_SUPER_LONG_STRING">
        ]>
        <results>
            <result>Now include &long; lots of times to expand the in-memory size of this XML structure</result>
            <result>&long;&long;&long;</result>
        </results>
        XML;
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('String could not be parsed as XML');
        Convert::xml2array($inputXML);
    }

    /**
     * Tests {@link Convert::xml2array()} if an exception the contains a reference to a multiple removed <!ENTITY />
     */
    public function testXML2ArrayMultipleEntitiesException()
    {
        if (Deprecation::isEnabled()) {
            $this->markTestSkipped('Test calls deprecated code');
        }
        $inputXML = <<<XML
        <?xml version="1.0"?>
        <!DOCTYPE results [<!ENTITY long "SOME_SUPER_LONG_STRING"><!ENTITY short "SHORT_STRING">]>
        <results>
            <result>Now include &long; and &short; lots of times</result>
            <result>&long;&long;&long;&short;&short;&short;</result>
        </results>
        XML;
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('String could not be parsed as XML');
        Convert::xml2array($inputXML);
    }

    /**
     * Tests {@link Convert::xml2array()} if there is a malicious <!ENTITY /> present
     */
    public function testXML2ArrayMaliciousEntityException()
    {
        $inputXML = <<<XML
        <?xml version="1.0"?>
        <!DOCTYPE results [
            <!ENTITY><!<!ENTITY>ENTITY ext SYSTEM "http://evil.com">
        ]>
        <results>
            <result>Evil document</result>
        </results>
        XML;
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Malicious XML entity detected');
        Convert::xml2array($inputXML);
    }

    /**
     * Tests {@link Convert::base64url_encode()} and {@link Convert::base64url_decode()}
     */
    public function testBase64url()
    {
        $data = 'Wëīrð characters ☺ such as ¤Ø¶÷╬';
        // This requires this test file to have UTF-8 character encoding
        $this->assertEquals(
            $data,
            Convert::base64url_decode(Convert::base64url_encode($data))
        );

        $data = 654.423;
        $this->assertEquals(
            $data,
            Convert::base64url_decode(Convert::base64url_encode($data))
        );

        $data = true;
        $this->assertEquals(
            $data,
            Convert::base64url_decode(Convert::base64url_encode($data))
        );

        $data = ['simple','array','¤Ø¶÷╬'];
        $this->assertEquals(
            $data,
            Convert::base64url_decode(Convert::base64url_encode($data))
        );

        $data = [
         'a'  => 'associative',
         4    => 'array',
         '☺' => '¤Ø¶÷╬'
        ];
        $this->assertEquals(
            $data,
            Convert::base64url_decode(Convert::base64url_encode($data))
        );
    }

    public function testValidUtf8()
    {
        // Install a UTF-8 locale
        $this->previousLocaleSetting = setlocale(LC_CTYPE, 0);

        $locales = ['en_US.UTF-8', 'en_NZ.UTF-8', 'de_DE.UTF-8'];
        $localeInstalled = false;
        foreach ($locales as $locale) {
            if ($localeInstalled = setlocale(LC_CTYPE, $locale)) {
                break;
            }
        }

        // If the system doesn't have any of the UTF-8 locales, exit early
        if ($localeInstalled === false) {
            $this->markTestIncomplete('Unable to run this test because of missing locale!');
            return;
        }

        $problematicText = html_entity_decode('<p>This is a&nbsp;Test with non-breaking&nbsp;space!</p>', ENT_COMPAT, 'UTF-8');

        $this->assertTrue(mb_check_encoding(Convert::html2raw($problematicText), 'UTF-8'));
    }

    public function testUpperCamelToLowerCamel()
    {
        $this->assertEquals(
            'd',
            Convert::upperCamelToLowerCamel('D'),
            'Single character'
        );
        $this->assertEquals(
            'id',
            Convert::upperCamelToLowerCamel('ID'),
            'Multi leading upper without trailing lower'
        );
        $this->assertEquals(
            'id',
            Convert::upperCamelToLowerCamel('Id'),
            'Single leading upper with trailing lower'
        );
        $this->assertEquals(
            'idField',
            Convert::upperCamelToLowerCamel('IdField'),
            'Single leading upper with trailing upper camel'
        );
        $this->assertEquals(
            'idField',
            Convert::upperCamelToLowerCamel('IDField'),
            'Multi leading upper with trailing upper camel'
        );
        $this->assertEquals(
            'iDField',
            Convert::upperCamelToLowerCamel('iDField'),
            'Single leading lower with trailing upper camel'
        );
        $this->assertEquals(
            '_IDField',
            Convert::upperCamelToLowerCamel('_IDField'),
            'Non-alpha leading  with trailing upper camel'
        );
    }

    /**
     * Test that memstring2bytes returns the number of bytes for a PHP ini style size declaration
     *
     * @param string $memString
     * @param int    $expected
     * @dataProvider memString2BytesProvider
     */
    public function testMemString2Bytes($memString, $expected)
    {
        $this->assertSame($expected, Convert::memstring2bytes($memString));
    }

    /**
     * @return array
     */
    public function memString2BytesProvider()
    {
        return [
            'infinite' => ['-1', -1],
            'integer' => ['2048', 2 * 1024],
            'kilo' => ['2k', 2 * 1024],
            'mega' => ['512M', 512 * 1024 * 1024],
            'MiB' => ['512MiB', 512 * 1024 * 1024],
            'mbytes' => ['512 mbytes', 512 * 1024 * 1024],
            'megabytes' => ['512 megabytes', 512 * 1024 * 1024],
            'giga' => ['1024g', 1024 * 1024 * 1024 * 1024],
            'G' => ['1024G', 1024 * 1024 * 1024 * 1024]
        ];
    }

    /**
     * Test that bytes2memstring returns a binary prefixed string representing the number of bytes
     *
     * @param string $bytes
     * @param int    $expected
     * @dataProvider bytes2MemStringProvider
     */
    public function testBytes2MemString($bytes, $expected)
    {
        $this->assertSame($expected, Convert::bytes2memstring($bytes));
    }

    /**
     * @return array
     */
    public function bytes2MemStringProvider()
    {
        return [
            [200, '200B'],
            [2 * 1024, '2K'],
            [512 * 1024 * 1024, '512M'],
            [512 * 1024 * 1024 * 1024, '512G'],
            [512 * 1024 * 1024 * 1024 * 1024, '512T'],
            [512 * 1024 * 1024 * 1024 * 1024 * 1024, '512P']
        ];
    }

    public function providerTestSlashes()
    {
        return [
            ['bob/bob', '/', true, 'bob/bob'],
            ['\\bob/bob\\', '/', true, '/bob/bob/'],
            ['\\bob////bob\\/', '/', true, '/bob/bob/'],
            ['bob/bob', '\\', true, 'bob\\bob'],
            ['\\bob/bob\\', '\\', true, '\\bob\\bob\\'],
            ['\\bob////bob\\/', '\\', true, '\\bob\\bob\\'],
            ['bob/bob', '#', true, 'bob#bob'],
            ['\\bob/bob\\', '#', true, '#bob#bob#'],
            ['\\bob////bob\\/', '#', true, '#bob#bob#'],
            ['bob/bob', '/', false, 'bob/bob'],
            ['\\bob/bob\\', '/', false, '/bob/bob/'],
            ['\\bob////bob\\/', '/', false, '/bob////bob//'],
            ['bob/bob', '\\', false, 'bob\\bob'],
            ['\\bob/bob\\', '\\', false, '\\bob\\bob\\'],
            ['\\bob////bob\\/', '\\', false, '\\bob\\\\\\\\bob\\\\'],
            ['bob/bob', '#', false, 'bob#bob'],
            ['\\bob/bob\\', '#', false, '#bob#bob#'],
            ['\\bob////bob\\/', '#', false, '#bob####bob##'],
        ];
    }

    /**
     * @dataProvider providerTestSlashes
     * @param string $path
     * @param string $separator
     * @param bool $multiple
     * @param string $expected
     */
    public function testSlashes($path, $separator, $multiple, $expected)
    {
        $this->assertEquals($expected, Convert::slashes($path, $separator, $multiple));
    }
}
