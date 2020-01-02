<?php
namespace Guywithnose\ReleaseNotes\Tests;

use Guywithnose\ReleaseNotes\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ApplicationTest extends TestCase
{
    /**
     * @var Application
     */
    private $_application;

    public function testMissingArguments()
    {
        try {
            $this->getCommandTester();
        } catch (\Exception $ex) {
            $output = $ex->getMessage();
            $this->assertContains('Not enough arguments (missing: "repo-owner, repo-name")', $output);
        }
    }

    public function testInvalidAccessToken()
    {
        try {
            $this->getCommandTester(
                [
                    'repo-owner' => 'guyithnose',
                    'repo-name' => 'release-notes',
                ],
                'foo\\n'
            );
        } catch (\Exception $ex) {
            $output = $ex->getMessage();
            $this->assertContains('Bad credentials', $output);
        }
    }

    /**
     * @param array $options The command options
     * @param string $input The user input
     * @return CommandTester
     */
    protected function getCommandTester(array $options = [], $input = null)
    {
        $command = $this->getApplication()->find('buildRelease');
        $commandTester = new CommandTester($command);
        if ($input) {
            $helper = $command->getHelper('question');
            $helper->setInputStream($this->getInputStream($input));
        }

        $commandTester->execute($options);
        return $commandTester;
    }

    protected function setUp()
    {
        parent::setUp();
        $this->_application = new Application();
    }

    /**
     * @return Application
     */
    protected function getApplication()
    {
        return $this->_application;
    }

    /**
     * @param string $input The input
     * @return resource
     */
    protected function getInputStream($input)
    {
        $stream = fopen('php://memory', 'r+', false);
        fwrite($stream, $input);
        rewind($stream);

        return $stream;
    }
}
