<?php
namespace Guywithnose\ReleaseNotes\Tests;

use Guywithnose\ReleaseNotes\SemanticVersion;
use Guywithnose\ReleaseNotes\SemanticVersionFactory;
use PHPUnit\Framework\TestCase;

class SemanticVersionFactoryTest extends TestCase
{
    /**
     * @param mixed $versionString The version
     * @param array $expectedIncrements An array with expected patch, minor, and major numbers
     * @dataProvider provideSemanticVersionExamples()
     */
    public function testCreateVersion($versionString, $expectedVersionString)
    {
        $factory = new SemanticVersionFactory();
        $version = $factory->createVersion($versionString);
        $this->assertInstanceOf(SemanticVersion::class, $version);
        $this->assertSame($expectedVersionString, $version->__toString());
    }

    /**
     * @return array
     */
    public function provideSemanticVersionExamples()
    {
        return [
            [null, '0.0.0'],
            ['0.0.0', '0.0.0'],
            ['1.1.6', '1.1.6'],
            ['4.0.3', '4.0.3'],
            ['3', '3'],
        ];
    }
}
