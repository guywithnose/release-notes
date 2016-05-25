<?php
namespace Guywithnose\ReleaseNotes\Tests\Change;

use Guywithnose\ReleaseNotes\Change\Change;
use Guywithnose\ReleaseNotes\Change\ChangeFactory;
use Guywithnose\ReleaseNotes\Change\ChangeList;
use Guywithnose\ReleaseNotes\Change\ChangeListFactory;

class ChangeListFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateFromCommits()
    {
        $changeFactory = new ChangeFactory();
        $commit = json_decode(file_get_contents('tests/data/commit.json'), true);
        $expectedChangeList = new ChangeList([new Change($commit['commit']['message'])]);
        $changeListFactory = new ChangeListFactory($changeFactory);
        $actualChangeList = $changeListFactory->createFromCommits([$commit]);
        $this->assertEquals($expectedChangeList, $actualChangeList);
    }
}
