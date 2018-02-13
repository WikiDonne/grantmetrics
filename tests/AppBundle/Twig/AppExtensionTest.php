<?php
/**
 * This file contains only the AppExtensionTest class.
 */

namespace AppBundle\Twig;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use AppBundle\Twig\AppExtension;
use AppBundle\Twig\Extension;
use DateTime;

/**
 * Tests for the AppExtension class.
 * Some code courtesy of the XTools team, released under GPL-3.0: https://github.com/x-tools/xtools
 */
class AppExtensionTest extends WebTestCase
{
    /** @var Container The Symfony container. */
    protected $container;

    /** @var AppBundle\Twig\AppExtension Instance of class */
    protected $appExtension;

    /**
     * Set class instance.
     */
    public function setUp()
    {
        $this->client = static::createClient();
        $this->container = $this->client->getContainer();
        $stack = new RequestStack();
        $session = new Session();
        $this->appExtension = new AppExtension($this->container, $stack, $session);
    }

    /**
     * Format number as a diff size.
     */
    public function testDiffFormat()
    {
        $this->assertEquals(
            "<span class='diff-pos'>3,000</span>",
            $this->appExtension->diffFormat(3000)
        );
        $this->assertEquals(
            "<span class='diff-neg'>-20,000</span>",
            $this->appExtension->diffFormat(-20000)
        );
        $this->assertEquals(
            "<span class='diff-zero'>0</span>",
            $this->appExtension->diffFormat(0)
        );
    }

    /**
     * Format number as a percentage.
     */
    public function testPercentFormat()
    {
        $this->assertEquals('45%', $this->appExtension->percentFormat(45));
        $this->assertEquals('30%', $this->appExtension->percentFormat(30, null, 3));
        $this->assertEquals('33.33%', $this->appExtension->percentFormat(2, 6, 2));
        $this->assertEquals('25%', $this->appExtension->percentFormat(2, 8));
    }

    /**
     * Format a time duration as humanized string.
     */
    public function testFormatDuration()
    {
        $this->assertEquals(
            [30, 'num-seconds'],
            $this->appExtension->formatDuration(30, false)
        );
        $this->assertEquals(
            [1, 'num-minutes'],
            $this->appExtension->formatDuration(70, false)
        );
        $this->assertEquals(
            [50, 'num-minutes'],
            $this->appExtension->formatDuration(3000, false)
        );
        $this->assertEquals(
            [2, 'num-hours'],
            $this->appExtension->formatDuration(7500, false)
        );
        $this->assertEquals(
            [10, 'num-days'],
            $this->appExtension->formatDuration(864000, false)
        );
    }

    /**
     * Format a number.
     */
    public function testNumberFormat()
    {
        $this->assertEquals('1,234', $this->appExtension->numberFormat(1234));
        $this->assertEquals('1,234.32', $this->appExtension->numberFormat(1234.316, 2));
        $this->assertEquals('50', $this->appExtension->numberFormat(50.0000, 4));
    }

    /**
     * Format a date.
     */
    public function testDateFormat()
    {
        $this->assertEquals(
            '2/1/17, 11:45 PM',
            $this->appExtension->dateFormat(new DateTime('2017-02-01 23:45:34'))
        );
        $this->assertEquals(
            '8/12/15, 11:45 AM',
            $this->appExtension->dateFormat('2015-08-12 11:45:50')
        );
    }

    /**
     * Intution methods.
     */
    public function testIntution()
    {
        $this->assertEquals('en', $this->appExtension->getLang());
        $this->assertEquals('English', $this->appExtension->getLangName());

        $allLangs = $this->appExtension->getAllLangs();

        // There should be a bunch.
        $this->assertGreaterThan(20, count($allLangs));

        // Keys should be the language codes, with name as the values.
        $this->assertArraySubset(['en' => 'English'], $allLangs);
        $this->assertArraySubset(['de' => 'Deutsch'], $allLangs);
        $this->assertArraySubset(['es' => 'Español'], $allLangs);

        // Testing if the language is RTL.
        $this->assertFalse($this->appExtension->intuitionIsRTLLang('en'));
        $this->assertTrue($this->appExtension->intuitionIsRTLLang('ar'));
    }

    /**
     * Methods that fetch data about the git repository.
     */
    public function testGitMethods()
    {
        $this->assertEquals(7, strlen($this->appExtension->gitShortHash()));
        $this->assertEquals(40, strlen($this->appExtension->gitHash()));
    }

    /**
     * Capitalizing first letter.
     */
    public function testCapitalizeFirst()
    {
        $this->assertEquals('Foo', $this->appExtension->capitalizeFirst('foo'));
        $this->assertEquals('Bar', $this->appExtension->capitalizeFirst('Bar'));
    }

    /**
     * Wikifying a string.
     */
    public function testWikify()
    {
        $wikitext = '<script>alert("XSS baby")</script> [[test page]]';
        $this->assertEquals(
            "&lt;script&gt;alert(\"XSS baby\")&lt;/script&gt; " .
                "<a target='_blank' href='https://test.example.org/wiki/Test_page'>test page</a>",
            $this->appExtension->wikify($wikitext, 'test.example')
        );

        $wikitext = '/* My section */ Editing a specific section';
        $this->assertEquals(
            "<a target='_blank' href='https://test.example.org/wiki/My_fun_page#My_section'>".
                "&rarr;</a><em class='text-muted'>My section:</em> Editing a specific section",
            $this->appExtension->wikify($wikitext, 'test.example', 'my fun page')
        );
    }
}
