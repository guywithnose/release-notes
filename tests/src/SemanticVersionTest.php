<?php
namespace Guywithnose\ReleaseNotes\Tests;

use Guywithnose\ReleaseNotes\SemanticVersion;
use PHPUnit\Framework\TestCase;

class SemanticVersionTest extends TestCase
{
    /**
     * @param mixed $versionString The version
     * @param array $expectedIncrements An array with expected patch, minor, and major numbers
     * @dataProvider provideSemanticIncrementExamples()
     */
    public function testGetSemanticIncrements($versionString, array $expectedIncrements)
    {
        $version = new SemanticVersion($versionString);
        $this->assertSame($expectedIncrements, $version->getSemanticIncrements());
    }

    /**
     * @return array
     */
    public function provideSemanticIncrementExamples()
    {
        return [
            [null, ['patch' => '0.0.1', 'minor' => '0.1.0', 'major' => '1.0.0']],
            ['0.0.0', ['patch' => '0.0.1', 'minor' => '0.1.0', 'major' => '1.0.0']],
            ['1.2.3', ['patch' => '1.2.4', 'minor' => '1.3.0', 'major' => '2.0.0']],
            ['2.0.0', ['patch' => '2.0.1', 'minor' => '2.1.0', 'major' => '3.0.0']],
            ['3.0.1', ['patch' => '3.0.2', 'minor' => '3.1.0', 'major' => '4.0.0']],
            ['v3.2.1', ['patch' => '3.2.2', 'minor' => '3.3.0', 'major' => '4.0.0']],
            [3, []],
        ];
    }

    /**
     * @param mixed $versionString The version
     * @param bool $expectedPreReleaseStatus The expected release status of the version
     * @dataProvider providePreReleaseExamples()
     */
    public function testIsPreRelease($versionString, $expectedPreReleaseStatus)
    {
        $version = new SemanticVersion($versionString);
        $this->assertSame($expectedPreReleaseStatus, $version->isPreRelease());
    }

    /**
     * @return array
     */
    public function providePreReleaseExamples()
    {
        return [
            [null, true],
            ['0.0.0', true],
            ['0.2.3', true],
            ['1.2.3', false],
            ['2.1.0', false],
            ['v5.0.34', false],
            [3, true],
        ];
    }

    /**
     * @param mixed $versionString The version
     * @param bool $expectedVersionString The expected version string
     * @dataProvider provideVersionStringExamples()
     */
    public function testToString($versionString, $expectedVersionString)
    {
        $version = new SemanticVersion($versionString);
        $this->assertSame($expectedVersionString, (string)$version);
    }

    /**
     * @param mixed $versionString The version
     * @param bool $expectedVersionString The expected version string
     * @dataProvider provideVersionStringExamples()
     */
    public function testTagName($versionString, $expectedVersionString)
    {
        $version = new SemanticVersion($versionString);
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
        $version = new SemanticVersion($versionString);
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
