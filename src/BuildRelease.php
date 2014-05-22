<?php
namespace Guywithnose\ReleaseNotes;

use Gregwar\Cache\Cache;
use Herrera\Version\Builder as VersionBuilder;
use Herrera\Version\Dumper as VersionDumper;
use Herrera\Version\Parser as VersionParser;
use Herrera\Version\Version;
use Nubs\RandomNameGenerator\Vgng;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption('release-name', 'r', InputOption::VALUE_REQUIRED, 'The name to give the release')
            ->addOption('release-version', 'R', InputOption::VALUE_REQUIRED, 'The version number to release')
            ->addOption('access-token', 't', InputOption::VALUE_REQUIRED, 'The access token to use (overrides cache)')
            ->addOption('cache-dir', null, InputOption::VALUE_REQUIRED, 'The access token cache location', dirname(__DIR__))
            ->addOption('token-file', null, InputOption::VALUE_REQUIRED, 'The access token cache filename', '.access_token');
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

        $owner = $input->getArgument('repo-owner');
        $repo = $input->getArgument('repo-name');

        $client = GithubClient::createWithToken($this->_getToken($input, $output), $owner, $repo);

        $tagName = $client->getLatestReleaseTagName();

        $currentVersion = null;
        $commits = [];
        if ($tagName !== null) {
            $currentVersion = VersionParser::toVersion(ltrim($tagName, 'v'));
            $commits = $client->getCommitsSinceTag($tagName);
        } else {
            $currentVersion = new Version();
            $commits = $client->getCommitsOnMaster();
        }

        $pullRequests = $this->_getPullRequests($output, $commits);
        if (empty($pullRequests)) {
            $output->writeln('<error>There were no unreleased pull requests found!</error>');
            return 1;
        }

        $newVersion = $this->_getVersion($input, $output, $currentVersion);
        $preRelease = $this->_isPreRelease($newVersion);
        $releaseName = $this->_getReleaseName($input, $output);
        $releaseNotes = $this->_getReleaseNotes($pullRequests);

        $release = $this->_buildRelease((string)$newVersion, $releaseName, $releaseNotes, $preRelease);
        $this->_submitRelease($output, $client, $release);
    }

    /**
     * Gets an access token from the user, caching via the configured cache file.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @return string The github access token.
     */
    private function _getToken(InputInterface $input, OutputInterface $output)
    {
        $token = $input->getOption('access-token');
        if ($token) {
            return $token;
        }

        $askForToken = function() use($output) {
            return $this->getHelperSet()->get('dialog')->ask($output, '<question>Please enter a github access token</question>: ');
        };

        $cache = new Cache($input->getOption('cache-dir'));
        return $cache->getOrCreate($input->getOption('token-file'), [], $askForToken);
    }

    /**
     * Gets the new version number to use.
     *
     * The user may specify an exact version with major, minor, and patch versions being given as suggestions.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @param \Herrera\Version\Version $currentVersion The current version.
     * @return \Herrera\Version\Version The new version.
     */
    private function _getVersion(InputInterface $input, OutputInterface $output, Version $currentVersion)
    {
        $version = $input->getOption('release-version');
        if ($version) {
            return VersionParser::toVersion($version);
        }

        $builder = VersionBuilder::create()->importVersion($currentVersion);
        $builder->clearBuild();
        $builder->clearPreRelease();

        $autoComplete = [
            $builder->incrementPatch()->getVersion(),
            $builder->incrementMinor()->getVersion(),
            $builder->incrementMajor()->getVersion(),
        ];

        $dialog = $this->getHelperSet()->get('dialog');
        $version = $dialog->ask(
            $output,
            "<question>Version Number</question> <info>(current: {$currentVersion}) (default: {$autoComplete[0]})</info>: ",
            $autoComplete[0],
            $autoComplete
        );

        return VersionParser::toVersion($version);
    }

    /**
     * Checks whether the given version is a stable 1.0+ version.
     *
     * @param \Herrera\Version\Version $version The version to check.
     * @return bool True if the version is a "prerelease", false otherwise.
     */
    private function _isPreRelease(Version $version)
    {
        return $version->getMajor() === 0 || !$version->isStable();
    }

    /**
     * Filters a list of commits down to just the pull requests and extracts the pull request info.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @param array $commits The commits.
     * @return array The pull requests, separated by type, where each pull request has a PR `number` and a commit `message`.
     */
    private function _getPullRequests(OutputInterface $output, array $commits)
    {
        $results = [
            'Backwards Compatibility Breakers' => [],
            'Major Features' => [],
            'Minor Features' => [],
            'Bug Fixes' => [],
            'Developer Changes' => [],
        ];
        $types = array_keys($results);
        $dialog = $this->getHelperSet()->get('dialog');
        $formatter = $this->getHelperSet()->get('formatter');

        foreach ($commits as $commit) {
            if (
                count($commit['parents']) === 2 &&
                preg_match('/Merge pull request #([0-9]*)[^\n]*\n[^\n]*\n(.*)/s', $commit['commit']['message'], $matches)
            ) {
                $lines = array_merge(["Pull Request #{$matches[1]}", ''], explode("\n", $matches[2]));
                $formattedNotes = $formatter->formatBlock($lines, 'info', true);

                $typeIndex = $dialog->select(
                    $output,
                    "{$formattedNotes}\n<question>What type of change is this PR?</question> <info>(default: 2 \"{$types[2]}\")</info> ",
                    $types,
                    2
                );

                $type = $types[$typeIndex];
                $results[$type][] = ['number' => $matches[1], 'message' => $matches[2]];
            }
        }

        return array_filter($results);
    }

    /**
     * Formats the pull requests (as returned by _getPullRequests) into the release notes.
     *
     * @param array $pullRequests The pull requests.
     * @return string The pull request formatted for the release notes.
     */
    private function _getReleaseNotes(array $pullRequests)
    {
        $sections = [];
        foreach ($pullRequests as $sectionTitle => $pulls) {
            $sections[] = "## {$sectionTitle}\n" . implode("\n", array_map([$this, '_formatPullRequest'], $pulls));
        }

        return implode("\n\n", $sections);
    }

    /**
     * Formats a pull request (as returned by _getPullRequests) into a bulleted item for the release notes.
     *
     * @param array $pullRequest The pull request.
     * @return string The pull request formatted for the release notes.
     */
    private function _formatPullRequest(array $pullRequest)
    {
        return "* {$pullRequest['message']}&nbsp;<sup>[PR&nbsp;#{$pullRequest['number']}]</sup>";
    }

    /**
     * Gets a name for the release.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @return string The name for the release.
     */
    private function _getReleaseName(InputInterface $input, OutputInterface $output)
    {
        $releaseName = $input->getOption('release-name');
        if ($releaseName) {
            return $releaseName;
        }

        $dialog = $this->getHelperSet()->get('dialog');
        if ($dialog->askConfirmation($output, '<question>Use a random release name?</question> <info>(default: yes)</info> ', true)) {
            return $this->_selectRandomReleaseName($output);
        }

        return $dialog->ask($output, '<question>Release Name</question>: ');
    }

    /**
     * Continually ask the user if a random release name should be used until they approve one.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @return string The name for the release.
     */
    private function _selectRandomReleaseName(OutputInterface $output)
    {
        $dialog = $this->getHelperSet()->get('dialog');
        $releaseName = null;

        $randomNameGenerator = new Vgng();
        do {
            $releaseName = $randomNameGenerator->getName();

            $useRelease = $dialog->askConfirmation(
                $output,
                "<question>Use release name '<boldquestion>{$releaseName}</boldquestion>'?</question> <info>(default: yes)</info> ",
                true
            );
        } while (!$useRelease);

        return $releaseName;
    }

    /**
     * Builds the full release information to send to github.
     *
     * @param string $version The version number of the release.
     * @param string $releaseName The name of the release.
     * @param string $releaseNotes The formatted release notes.
     * @param bool $preRelease The prerelease flag for github.
     * @return array The data to send to github.
     */
    private function _buildRelease($version, $releaseName, $releaseNotes, $preRelease = false)
    {
        return [
            'tag_name' => "v{$version}",
            'name' => "Version {$version}: {$releaseName}",
            'body' => $releaseNotes,
            'prerelease' => $preRelease,
            'draft' => true,
        ];
    }

    /**
     * Submits the given release to github.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @param \Guywithnose\ReleaseNotes\GithubClient $client The github client.
     * @param array $release The release information (@see $this->_buildRelease()).
     * @return void
     */
    private function _submitRelease(OutputInterface $output, GithubClient $client, array $release)
    {
        $dialog = $this->getHelperSet()->get('dialog');
        $formatter = $this->getHelperSet()->get('formatter');
        $lines = array_merge([$release['name'], ''], explode("\n", $release['body']));
        $formattedNotes = $formatter->formatBlock($lines, 'info', true);

        if (
            $dialog->askConfirmation(
                $output,
                "{$formattedNotes}\n<question>Publish this release as a draft?</question> <info>(default: yes)</info> ",
                true
            )
        ) {
            $client->createRelease($release);
        }
    }
}
