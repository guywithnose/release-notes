<?php
namespace Guywithnose\ReleaseNotes\Tests\Change;

use Guywithnose\ReleaseNotes\Change\Change;
use Guywithnose\ReleaseNotes\Change\ChangeFactory;
use Guywithnose\ReleaseNotes\Change\ChangeList;
use Guywithnose\ReleaseNotes\Change\ChangeListFactory;
use Guywithnose\ReleaseNotes\Type\TypeManager;
use PHPUnit\Framework\TestCase;

class ChangeListFactoryTest extends TestCase
{
    public function testCreateFromCommits()
    {
        $typeManager = TypeManager::getSemanticTypeManager();
        $changeFactory = new ChangeFactory($typeManager);
        $commit = json_decode(file_get_contents('tests/data/commit.json'), true);
        $expectedChangeList = new ChangeList($typeManager, [new Change($commit['commit']['message'], $typeManager->getDefaultType())]);
        $changeListFactory = new ChangeListFactory($changeFactory, $typeManager);
        $actualChangeList = $changeListFactory->createFromCommits([$commit]);
        $this->assertEquals($expectedChangeList, $actualChangeList);
    }
}
