<?php
namespace Guywithnose\ReleaseNotes\Tests\Change;

use Guywithnose\ReleaseNotes\Change\Change;
use Guywithnose\ReleaseNotes\Change\ChangeFactory;
use Guywithnose\ReleaseNotes\Type\Type;
use Guywithnose\ReleaseNotes\Type\TypeManager;
use PHPUnit\Framework\TestCase;

class ChangeFactoryTest extends TestCase
{
    /**
     * @var array
     */
    private $_commit;

    public function testCreateFromCommit()
    {
        $commit = $this->getCommit();
        $typeManager = TypeManager::getSemanticTypeManager();
        $expectedChange = new Change($commit['commit']['message'], $typeManager->getDefaultType());
        $changeFactory = new ChangeFactory($typeManager);
        $actualChange = $changeFactory->createFromCommit($commit);
        $this->assertEquals($expectedChange, $actualChange);
    }

    protected function setUp()
    {
        $commitJson = file_get_contents('tests/data/commit.json');
        $this->_commit = json_decode($commitJson, true);
    }

    /**
     * @return array
     */
    private function getCommit()
    {
        return $this->_commit;
    }
}
