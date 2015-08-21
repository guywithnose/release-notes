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
        $this->setName('buildRelease')
            ->setDescription('Prepare release notes for a github repository')
            ->addArgument('repo-owner', InputArgument::REQUIRED, 'The github repository owner')
            ->addArgument('repo-name', InputArgument::REQUIRED, 'The github repository name')
            ->addOption('target-branch', null, InputOption::VALUE_REQUIRED, 'The name of the target branch', 'master')
            ->addOption('previous-tag-name', null, InputOption::VALUE_REQUIRED, 'The name of the previous tag')
            ->addOption('release-name', 'r', InputOption::VALUE_REQUIRED, 'The name to give the release')
            ->addOption('release-version', 'R', InputOption::VALUE_REQUIRED, 'The version number to release')
            ->addOption('access-token', 't', InputOption::VALUE_REQUIRED, 'The access token to use (overrides cache)')
            ->addOption('publish', 'p', InputOption::VALUE_NONE, 'Immediately publish the release (instead of leaving as draft)')
            ->addOption('cache-dir', null, InputOption::VALUE_REQUIRED, 'The access token cache location', dirname(__DIR__))
            ->addOption('token-file', null, InputOption::VALUE_REQUIRED, 'The access token cache filename', '.access_token')
            ->addOption('github-api', null, InputOption::VALUE_REQUIRED, 'The base url to the GitHub API');
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
        $output->getFormatter()->setStyle('boldquestion', new OutputFormatterStyle('red', 'cyan', ['bold']));
        $promptFactory = new PromptFactory($output, $this->getHelperSet()->get('dialog'), $this->getHelperSet()->get('formatter'));

        $client = GithubClient::createWithToken(
            $this->_getToken($input, $promptFactory),
            $input->getArgument('repo-owner'),
            $input->getArgument('repo-name'),
            $input->getOption('github-api')
        );

        $targetBranch = $input->getOption('target-branch');

        $tagName = $this->_getBaseTagName($input, $promptFactory, $client, $targetBranch);
        $currentVersion = Version::createFromString($tagName);

        $selectTypeForChange = function(Change $change) use($promptFactory) {
            return $this->_selectTypeForChange($promptFactory, $change);
        };

        $commitGraph = new GithubCommitGraph($client->getCommitsInRange($tagName, $targetBranch));
        $leadingCommits = $commitGraph->firstParents();

        $changeListFactory = new ChangeListFactory(new ChangeFactory($selectTypeForChange));
        $changes = $changeListFactory->createFromCommits($leadingCommits);
        if ($changes->isEmpty()) {
            $output->writeln('<error>There were no unreleased changes found!</error>');
            return 1;
        }

        $suggestedVersions = $this->_getSuggestedNewVersions($currentVersion, $changes);
        $newVersion = $this->_getVersion($input, $promptFactory, $currentVersion, $suggestedVersions);
        $releaseName = $this->_getReleaseName($input, $promptFactory);

        $commandLocatorFactory = new WhichLocatorFactory();
        $editorFactory = new EditorFactory($commandLocatorFactory->create());
        $editor = $editorFactory->create();
        $releaseNotes = $this->_amendReleaseNotes($input, $editor, new ProcessBuilder(), $changes->display());

        $isDraft = !$input->getOption('publish');
        $release = $this->_buildRelease($newVersion, $releaseName, $releaseNotes, $targetBranch, $isDraft);
        $this->_submitRelease($promptFactory, $client, $release);
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
     * @param \Guywithnose\ReleaseNotes\Prompt\PromptFactory $promptFactory The prompt factory.
     * @param \Guywithnose\ReleaseNotes\GithubClient $client The github client.
     * @param string $releaseBranch The branch to find releases on, or null to find tag from any branch.
     * @return string The github access token.
     */
    private function _getBaseTagName(InputInterface $input, PromptFactory $promptFactory, GithubClient $client, $releaseBranch)
    {
        $tag = $input->getOption('previous-tag-name');
        if ($tag) {
            return $tag;
        }

        return $promptFactory->invoke('Please enter the base tag', $client->getLatestReleaseTagName($releaseBranch));
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
        $largestChangeType = $changes->largestChange()->getType();
        $increments = $currentVersion->getSemanticIncrements();
        if (empty($increments)) {
            return [];
        }

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
     * @param \Guywithnose\ReleaseNotes\Prompt\PromptFactory $promptFactory The prompt factory.
     * @param \Guywithnose\ReleaseNotes\Version $currentVersion The current version.
     * @param array $suggestedVersions The auto-complete versions for user suggestions.
     * @return \Guywithnose\ReleaseNotes\Version The new version.
     */
    private function _getVersion(InputInterface $input, PromptFactory $promptFactory, Version $currentVersion, array $suggestedVersions)
    {
        $version = $input->getOption('release-version');
        if ($version) {
            return Version::createFromString($version);
        }

        return Version::createFromString(
            $promptFactory->invoke(
                "Version Number (current: {$currentVersion})",
                empty($suggestedVersions) ? null : $suggestedVersions[0],
                $suggestedVersions,
                null,
                false
            )
        );
    }

    /**
     * Gets the change type for this change.
     *
     * @param \Guywithnose\ReleaseNotes\Prompt\PromptFactory $promptFactory The prompt factory.
     * @param \Guywithnose\ReleaseNotes\Change\Change $change The change.
     * @return string The type code of the change.
     */
    private function _selectTypeForChange(PromptFactory $promptFactory, Change $change)
    {
        return $promptFactory->invoke('What type of change is this PR?', $change->getType(), $change::types(), $change->displayFull());
    }

    /**
     * Allows the user to amend the release notes.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Nubs\Sensible\Editor $editor The editor loader for allowing the user to customize the release notes.
     * @param \Symfony\Component\Process\ProcessBuilder $processBuilder The process builder for loading the editor.
     * @param string $releaseNotes The release notes to amend.
     * @return string The amended release notes.
     */
    private function _amendReleaseNotes(InputInterface $input, Editor $editor, ProcessBuilder $processBuilder, $releaseNotes)
    {
        return $input->isInteractive() ? $editor->editData($processBuilder, $releaseNotes) : $releaseNotes;
    }

    /**
     * Gets a name for the release.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Guywithnose\ReleaseNotes\Prompt\PromptFactory $promptFactory The prompt factory.
     * @return string The name for the release.
     */
    private function _getReleaseName(InputInterface $input, PromptFactory $promptFactory)
    {
        $releaseName = $input->getOption('release-name');
        if ($releaseName) {
            return $releaseName;
        }

        if ($promptFactory->invoke('Use a random release name?', true)) {
            return $this->_selectRandomReleaseName($promptFactory);
        }

        return $promptFactory->invoke('Release Name');
    }

    /**
     * Continually ask the user if a random release name should be used until they approve one.
     *
     * @param \Guywithnose\ReleaseNotes\Prompt\PromptFactory $promptFactory The prompt factory.
     * @return string The name for the release.
     */
    private function _selectRandomReleaseName(PromptFactory $promptFactory)
    {
        $releaseName = null;
        $randomNameGenerator = new Vgng();
        do {
            $releaseName = $randomNameGenerator->getName();
        } while (!$promptFactory->invoke("Use release name '<boldquestion>{$releaseName}</boldquestion>'?", true));

        return $releaseName;
    }

    /**
     * Builds the full release information to send to github.
     *
     * @param \Guywithnose\Release\Version $version The version of the release.
     * @param string $releaseName The name of the release.
     * @param string $releaseNotes The formatted release notes.
     * @param string $targetCommitish The target commit/branch/etc. to tag.
     * @param boolean $isDraft Whether the release is a draft or if it should be published immediately.
     * @return array The data to send to github.
     */
    private function _buildRelease(Version $version, $releaseName, $releaseNotes, $targetCommitish = null, $isDraft = true)
    {
        return [
            'tag_name' => $version->tagName(),
            'name' => "Version {$version}" . ($releaseName ? ": {$releaseName}" : ''),
            'body' => $releaseNotes,
            'prerelease' => $version->isPreRelease(),
            'draft' => $isDraft,
            'target_commitish' => $targetCommitish,
        ];
    }

    /**
     * Submits the given release to github.
     *
     * @param \Guywithnose\ReleaseNotes\Prompt\PromptFactory $promptFactory The prompt factory.
     * @param \Guywithnose\ReleaseNotes\GithubClient $client The github client.
     * @param array $release The release information (@see $this->_buildRelease()).
     * @return void
     */
    private function _submitRelease(PromptFactory $promptFactory, GithubClient $client, array $release)
    {
        $prompt = $release['draft'] ? 'Submit draft?' : 'Publish release?';
        if ($promptFactory->invoke($prompt, true, [], "{$release['name']}\n\n{$release['body']}")) {
            $client->createRelease($release);
        }
    }
}
