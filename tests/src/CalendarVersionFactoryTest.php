<?php
namespace Guywithnose\ReleaseNotes\Tests;

use Guywithnose\ReleaseNotes\CalendarVersion;
use Guywithnose\ReleaseNotes\CalendarVersionFactory;
use PHPUnit\Framework\TestCase;

class CalendarVersionFactoryTest extends TestCase
{
    /**
     * @param mixed $versionString The version
     * @param array $expectedIncrements An array with expected patch, minor, and major numbers
     * @dataProvider provideCalendarVersionExamples()
     */
    public function testCreateVersion($versionString, $expectedVersionString)
    {
        $factory = new CalendarVersionFactory();
        $version = $factory->createVersion($versionString);
        $this->assertInstanceOf(CalendarVersion::class, $version);
        $this->assertSame($expectedVersionString, $version->__toString());
    }

    /**
     * @return array
     */
    public function provideCalendarVersionExamples()
    {
        $now = new \DateTime();
        $y = $now->format('y');
        $n = $now->format('n');
        return [
            [null, '0.0.0'],
            ['0.0.0', '0.0.0'],
            ['1.1.6', '1.1.6'],
            ["{$y}.0.3", "{$y}.0.3"],
            ["{$y}.{$n}.2", "{$y}.{$n}.2"],
        ];
    }
}
