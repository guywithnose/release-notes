<?php
namespace Guywithnose\ReleaseNotes;

use Gregwar\Cache\Cache;
use Guywithnose\ReleaseNotes\Change\Change;
use Guywithnose\ReleaseNotes\Change\ChangeFactory;
use Guywithnose\ReleaseNotes\Change\ChangeList;
use Guywithnose\ReleaseNotes\Change\ChangeListFactory;
use Guywithnose\ReleaseNotes\Prompt\PromptFactory;
use Nubs\RandomNameGenerator\Vgng;
use Nubs\Sensible\CommandFactory\EditorFactory;
use Nubs\Sensible\Editor;
use Nubs\Which\LocatorFactory\PlatformLocatorFactory as WhichLocatorFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

class BuildRelease extends Command
{
    /**
     * Configures the command's options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('buildRelease')->setDescription('Prepare release notes for a github repository')->addArgument(
            'repo-owner',
            InputArgument::REQUIRED,
            'The github repository owner'
        )->addArgument('repo-name', InputArgument::REQUIRED, 'The github repository name')->addOption(
            'target-branch',
            null,
            InputOption::VALUE_REQUIRED,
            'The name of the target branch',
            'master'
        )->addOption('previous-tag-name', null, InputOption::VALUE_REQUIRED, 'The name of the previous tag')->addOption(
            'release-name',
            'r',
            InputOption::VALUE_REQUIRED,
            'The name to give the release'
        )->addOption('release-version', 'R', InputOption::VALUE_REQUIRED, 'The version number to release')->addOption(
            'access-token',
            't',
            InputOption::VALUE_REQUIRED,
            'The access token to use (overrides cache)'
        )->addOption(
            'publish',
            'p',
            InputOption::VALUE_NONE,
            'Immediately publish the release (instead of leaving as draft)'
        )->addOption('cache-dir', null, InputOption::VALUE_REQUIRED, 'The access token cache location', dirname(__DIR__))->addOption(
            'token-file',
            null,
            InputOption::VALUE_REQUIRED,
            'The access token cache filename',
            '.access_token'
        )->addOption('github-api', null, InputOption::VALUE_REQUIRED, 'The base url to the GitHub API');
    }

    /**
     * Prepares release notes for the requested repository.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $promptFactory = new PromptFactory($input, $output, $this->getHelperSet()->get('question'), $this->getHelperSet()->get('formatter'));

        $client = GithubClient::createWithToken(
            $this->_getToken($input, $promptFactory),
            $input->getArgument('repo-owner'),
            $input->getArgument('repo-name'),
            $input->getOption('github-api')
        );

        $targetBranch = $input->getOption('target-branch');
        $baseTagName = $this->_getBaseTagName($input, $client, $targetBranch);
        $release = $this->_buildRelease($input, $client, $targetBranch, $baseTagName);

        $defaultChoice = $input->getOption('publish') ? 'p' : 'd';
        $choices = [
            'b' => 'Change Target Branch',
            't' => 'Change Base Tag',
            'c' => 'Categorize Changes',
            'v' => 'Change Version',
            'n' => 'Change Release Name',
            'r' => 'Randomize Release Name',
            'e' => 'Edit Release Notes',
            'd' => 'Submit Draft Release',
            'p' => 'Publish Release',
            'q' => 'Cancel and Quit',
        ];

        $done = false;
        while (!$done) {
            $choice = $promptFactory->invoke('What would you like to do?', $defaultChoice, $choices, $release->previewFormat());
            $result = $this->_handleUserInput($release, $promptFactory, $client, $input, $choice);
            if ($result === true) {
                $done = true;
            } elseif ($result === false) {
                return;
            } elseif ($result !== null) {
                $release = $result;
            }
        }

        $this->_submitRelease($promptFactory, $client, $release);
    }

    /**
     * Handle user input
     *
     * @param
     */
    private function _handleUserInput($release, $promptFactory, $client, $input, $choice)
    {
        $targetBranch = $baseTagName = null;
        switch ($choice) {
            case 'b':
                $targetBranch = $promptFactory->invoke('Please enter the target branch');
                $baseTagName = $this->_getBaseTagName($input, $client, $targetBranch);
                return $this->_buildRelease($input, $client, $targetBranch, $baseTagName);
                break;
            case 't':
                $targetBranch = $release->targetCommitish;
                $baseTagName = $promptFactory->invoke('Please enter the base tag', 'v' . $release->currentVersion);
                return $this->_buildRelease($input, $client, $targetBranch, $baseTagName);
                break;
            case 'c':
                $selectTypeForChange = function(Change $change) use($promptFactory) {
                    return $promptFactory->invoke(
                        'What type of change is this PR?',
                        $change->getType(),
                        $change::types(),
                        $change->displayFull()
                    );
                };
                $release->changes = $this->_getChangesInRange(
                    $client,
                    $release->currentVersion->unprocessed(),
                    $release->targetCommitish,
                    $selectTypeForChange
                );
                $release->version = $this->_getVersion($input, $release->currentVersion, $release->changes);
                $release->notes = $release->changes->display();
                break;
            case 'v':
                $suggestedVersions = $this->_getSuggestedNewVersions($release->currentVersion, $release->changes);
                $currentVersion = $release->currentVersion;
                $defaultVersion = $release->version;
                $release->version = new Version(
                    $promptFactory->invoke("Version Number (current: {$currentVersion})", $defaultVersion, $suggestedVersions, null, false)
                );
                break;
            case 'n':
                $release->name = $promptFactory->invoke('Release Name', $release->name);
                break;
            case 'r':
                $randomNameGenerator = new Vgng();
                $release->name = $randomNameGenerator->getName();
                break;
            case 'e':
                $release->notes = $this->_amendReleaseNotes($input, $release->notes);
                break;
            case 'd':
                $release->isDraft = true;
                return true;
            case 'p':
                $release->isDraft = false;
                return true;
            case 'q':
                return false;
        }

        return null;
    }

    /**
     * Builds the release without prompts based on command options and default values.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Guywithnose\ReleaseNotes\GithubClient $client The github client.
     * @param string $targetBranch The target branch to build a release for.
     * @param string $baseTagName The tag name of the previous release on the target branch.
     * @return \Guywithnose\ReleaseNotes\Release The release object.
     */
    private function _buildRelease(InputInterface $input, GithubClient $client, $targetBranch, $baseTagName)
    {
        $currentVersion = new Version($baseTagName);

        $changes = $this->_getChangesInRange($client, $baseTagName, $targetBranch);
        $newVersion = $this->_getVersion($input, $currentVersion, $changes);
        $releaseNotes = $changes->display();

        $releaseName = $this->_getReleaseName($input);
        $isDraft = !$input->getOption('publish');

        return new Release($changes, $currentVersion, $newVersion, $releaseName, $releaseNotes, $targetBranch, $isDraft);
    }

    private function _getChangesInRange(GithubClient $client, $startCommitish, $endCommitish, callable $changePrompter = null)
    {
        $commitGraph = new GithubCommitGraph($client->getCommitsInRange($startCommitish, $endCommitish));
        $leadingCommits = $commitGraph->firstParents();
        $changeListFactory = new ChangeListFactory(new ChangeFactory($changePrompter));

        return $changeListFactory->createFromCommits($leadingCommits);
    }

    /**
     * Gets an access token from the user, caching via the configured cache file.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Guywithnose\ReleaseNotes\Prompt\PromptFactory $promptFactory The prompt factory.
     * @return string The github access token.
     */
    private function _getToken(InputInterface $input, PromptFactory $promptFactory)
    {
        $token = $input->getOption('access-token');
        if ($token) {
            return $token;
        }

        $cache = new Cache($input->getOption('cache-dir'));

        return trim($cache->getOrCreate($input->getOption('token-file'), [], $promptFactory->create('Please enter a github access token')));
    }

    /**
     * Gets the tag this release is based off of.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Guywithnose\ReleaseNotes\GithubClient $client The github client.
     * @param string $releaseBranch The branch to find releases on, or null to find tag from any branch.
     * @return string The base tag name.
     */
    private function _getBaseTagName(InputInterface $input, GithubClient $client, $releaseBranch)
    {
        $tag = $input->getOption('previous-tag-name');
        if ($tag) {
            return $tag;
        }

        return $client->getLatestReleaseTagName($releaseBranch);
    }

    /**
     * Get the suggested new versions based off the current version and the given change list.
     *
     * The order of the suggestions is driven off of the "largest" change in the change list.
     *
     * @param \Guywithnose\ReleaseNotes\Version $currentVersion The current version.
     * @param \Guywithnose\ReleaseNotes\Change\ChangeList $changes The changes.
     * @return array The semantic versions that work for a new version.
     */
    private function _getSuggestedNewVersions(Version $currentVersion, ChangeList $changes)
    {
        $increments = $currentVersion->getSemanticIncrements();
        if (empty($increments)) {
            return [];
        }

        $largestChange = $changes->largestChange();
        $largestChangeType = $largestChange ? $largestChange->getType() : Change::TYPE_MINOR;

        if ($largestChangeType === Change::TYPE_BC) {
            return [$increments['major'], $increments['minor'], $increments['patch']];
        }

        if ($largestChangeType === Change::TYPE_MAJOR) {
            return [$increments['minor'], $increments['patch'], $increments['major']];
        }

        return [$increments['patch'], $increments['minor'], $increments['major']];
    }

    /**
     * Gets the new version number to use.
     *
     * The user may specify an exact version with the given auto-complete versions being given as suggestions.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Guywithnose\ReleaseNotes\Version $currentVersion The previous release's version.
     * @param \Guywithnose\ReleaseNotes\Change\ChangeList $changes The changes in this release.
     * @return \Guywithnose\ReleaseNotes\Version The new version.
     */
    private function _getVersion(InputInterface $input, Version $currentVersion, ChangeList $changes)
    {
        $suggestedVersions = $this->_getSuggestedNewVersions($currentVersion, $changes);
        $bestSuggested = empty($suggestedVersions) ? null : $suggestedVersions[0];

        return new Version($input->getOption('release-version') ?: $bestSuggested);
    }

    /**
     * Allows the user to amend the release notes.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param string $releaseNotes The release notes to amend.
     * @return string The amended release notes.
     */
    private function _amendReleaseNotes(InputInterface $input, $releaseNotes)
    {
        $commandLocatorFactory = new WhichLocatorFactory();
        $editorFactory = new EditorFactory($commandLocatorFactory->create());
        $editor = $editorFactory->create();
        $processBuilder = new ProcessBuilder();
        return $input->isInteractive() ? $editor->editData($processBuilder, $releaseNotes) : $releaseNotes;
    }

    /**
     * Get a name for the release.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @return string The name for the release.
     */
    private function _getReleaseName(InputInterface $input)
    {
        $releaseName = $input->getOption('release-name');
        if ($releaseName) {
            return $releaseName;
        }

        $randomNameGenerator = new Vgng();

        return $randomNameGenerator->getName();
    }

    /**
     * Submits the given release to github.
     *
     * @param \Guywithnose\ReleaseNotes\Prompt\PromptFactory $promptFactory The prompt factory.
     * @param \Guywithnose\ReleaseNotes\GithubClient $client The github client.
     * @param \Guywithnose\ReleaseNotes\Release $release The release information.
     * @return void
     */
    private function _submitRelease(PromptFactory $promptFactory, GithubClient $client, Release $release)
    {
        if ($promptFactory->invoke('Are you sure?', true, [], $release->previewFormat())) {
            $client->createRelease($release->githubFormat());
        }
    }
}
