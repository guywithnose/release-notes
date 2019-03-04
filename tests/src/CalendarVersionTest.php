<?php
namespace Guywithnose\ReleaseNotes\Tests;

use Guywithnose\ReleaseNotes\CalendarVersion;
use PHPUnit\Framework\TestCase;

class CalendarVersionTest extends TestCase
{
    /**
     * @param mixed $versionString The version
     * @param array $expectedIncrements An array with expected patch, minor, and major numbers
     * @dataProvider provideCalendarIncrementExamples()
     */
    public function testGetCalendarIncrements($versionString, array $expectedIncrements)
    {
        $version = new CalendarVersion($versionString);
        $this->assertSame($expectedIncrements, $version->getSemanticIncrements());
    }

    /**
     * @return array
     */
    public function provideCalendarIncrementExamples()
    {
        $now = new \DateTime();
        $y = $now->format('y');
        $n = $now->format('n');
        return [
            [null, ["{$y}.{$n}.0"]],
            ['0.0.0', ["{$y}.{$n}.0"]],
            ['1.1.6', ["{$y}.{$n}.0"]],
            ["{$y}.0.3", ["{$y}.{$n}.0"]],
            ["{$y}.{$n}.2", ["{$y}.{$n}.3"]],
        ];
    }

    /**
     * @param mixed $versionString The version
     * @param bool $expectedPreReleaseStatus The expected release status of the version
     * @dataProvider providePreReleaseExamples()
     */
    public function testIsPreRelease($versionString, $expectedPreReleaseStatus)
    {
        $version = new CalendarVersion($versionString);
        $this->assertSame($expectedPreReleaseStatus, $version->isPreRelease());
    }

    /**
     * @return array
     */
    public function providePreReleaseExamples()
    {
        return [
            [null, false],
            ['0.0.0', false],
            ['0.2.3', false],
            ['1.2.3', false],
            ['2.1.0', false],
            ['v5.0.34', false],
            [3, false],
        ];
    }

    /**
     * @param mixed $versionString The version
     * @param bool $expectedVersionString The expected version string
     * @dataProvider provideVersionStringExamples()
     */
    public function testToString($versionString, $expectedVersionString)
    {
        $version = new CalendarVersion($versionString);
        $this->assertSame($expectedVersionString, (string)$version);
    }

    /**
     * @param mixed $versionString The version
     * @param bool $expectedVersionString The expected version string
     * @dataProvider provideVersionStringExamples()
     */
    public function testTagName($versionString, $expectedVersionString)
    {
        $version = new CalendarVersion($versionString);
        $this->assertSame('v' . $expectedVersionString, $version->tagName());
    }

    /**
     * @return array
     */
    public function provideVersionStringExamples()
    {
        return [
            [null, '0.0.0'],
            ['0.2.3', '0.2.3'],
            ['v2.0.16', '2.0.16'],
            [3, '3'],
        ];
    }

    /**
     * @param mixed $versionString The version
     * @param bool $expectedVersionString The expected version string
     * @dataProvider provideConstructorVersion()
     */
    public function testUnprocessed($versionString, $expectedVersionString)
    {
        $version = new CalendarVersion($versionString);
        $this->assertSame($expectedVersionString, $version->unprocessed());
    }

    /**
     * @return array
     */
    public function provideConstructorVersion()
    {
        return [
            [null, 'v0.0.0'],
            ['0.2.3', '0.2.3'],
            ['v2.0.16', 'v2.0.16'],
            [3, 3],
        ];
    }
}
