<?php
namespace Guywithnose\ReleaseNotes;

use Gregwar\Cache\Cache;
use Nubs\RandomNameGenerator\Vgng;
use Nubs\Sensible\Editor;
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
        $targetBranch = $input->getOption('target-branch');

        $client = GithubClient::createWithToken($this->_getToken($input, $output), $owner, $repo);

        $tagName = $this->_getBaseTagName($input, $output, $client, $targetBranch);
        $currentVersion = Version::createFromString($tagName);;

        $commits = [];
        if ($tagName !== null) {
            $commits = $client->getCommitsSinceTag($tagName, $targetBranch);
        } else {
            $commits = $client->getCommitsOnBranch($targetBranch);
        }

        $pullRequests = $this->_getPullRequests($output, $commits);
        if (empty($pullRequests)) {
            $output->writeln('<error>There were no unreleased pull requests found!</error>');
            return 1;
        }

        $suggestedVersions = $this->_getSuggestedNewVersions($currentVersion, $pullRequests);
        $newVersion = $this->_getVersion($input, $output, $currentVersion, $suggestedVersions);
        $releaseName = $this->_getReleaseName($input, $output);
        $releaseNotes = $this->_getReleaseNotes($pullRequests);
        $releaseNotes = $this->_amendReleaseNotes($input, new Editor(), new ProcessBuilder(), $releaseNotes);

        $release = $this->_buildRelease($newVersion, $releaseName, $releaseNotes, $targetBranch);
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
     * Gets the tag this release is based off of.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @param \Guywithnose\ReleaseNotes\GithubClient $client The github client.
     * @param string $releaseBranch The branch to find releases on, or null to find tag from any branch.
     * @return string The github access token.
     */
    private function _getBaseTagName(InputInterface $input, OutputInterface $output, GithubClient $client, $releaseBranch)
    {
        $tag = $input->getOption('previous-tag-name');
        if ($tag) {
            return $tag;
        }

        $dialog = $this->getHelperSet()->get('dialog');
        $latestTag = $client->getLatestReleaseTagName($releaseBranch);

        return $dialog->ask($output, "<question>Please enter the base tag</question> <info>(default: {$latestTag})</info>: ", $latestTag);
    }

    /**
     * Get the suggested new versions based off the current version and the given categorized pull requests.
     *
     * The order of the suggestions is driven off of the pull request categories.
     *
     * @param \Guywithnose\ReleaseNotes\Version $currentVersion The current version.
     * @param array $pullRequests The categorized pull requests.
     */
    private function _getSuggestedNewVersions(Version $currentVersion, array $pullRequests)
    {
        $largestChange = array_keys($pullRequests)[0];
        $increments = $currentVersion->getSemanticIncrements();

        if ($largestChange === 'bc') {
            return [$increments['major'], $increments['minor'], $increments['patch']];
        }

        if ($largestChange === 'M') {
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
     * @param \Symfony\Component\Console\Output\OutputInterface $output The command output.
     * @param \Guywithnose\ReleaseNotes\Version $currentVersion The current version.
     * @param array $suggestedVersions The auto-complete versions for user suggestions.
     * @return \Guywithnose\ReleaseNotes\Version The new version.
     */
    private function _getVersion(InputInterface $input, OutputInterface $output, Version $currentVersion, array $suggestedVersions)
    {
        $version = $input->getOption('release-version');
        if ($version) {
            return Version::createFromString($version);
        }

        $dialog = $this->getHelperSet()->get('dialog');
        $version = $dialog->ask(
            $output,
            "<question>Version Number</question> <info>(current: {$currentVersion}) (default: {$suggestedVersions[0]})</info>: ",
            $suggestedVersions[0],
            $suggestedVersions
        );

        return Version::createFromString($version);
    }

    /**
     * Get the different categories of changes that can be used.
     *
     * @return array The types of changes that can be used.
     */
    private function _getChangeTypes()
    {
        return [
            'bc' => 'Backwards Compatibility Breakers',
            'M' => 'Major Features',
            'm' => 'Minor Features',
            'b' => 'Bug Fixes',
            'd' => 'Developer Changes',
            'x' => 'Remove Pull Request from Release Notes',
        ];
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
        $types = $this->_getChangeTypes();
        $results = array_combine(array_keys($types), array_fill(0, count($types), []));
        $dialog = $this->getHelperSet()->get('dialog');
        $formatter = $this->getHelperSet()->get('formatter');

        foreach ($commits as $commit) {
            if (
                count($commit['parents']) === 2 &&
                preg_match('/Merge pull request #([0-9]*)[^\n]*\n[^\n]*\n(.*)/s', $commit['commit']['message'], $matches)
            ) {
                $lines = array_merge(["Pull Request #{$matches[1]}", ''], explode("\n", $matches[2]));
                $formattedNotes = $formatter->formatBlock($lines, 'info', true);

                $type = $dialog->select(
                    $output,
                    "{$formattedNotes}\n<question>What type of change is this PR?</question> <info>(default: m \"{$types['m']}\")</info> ",
                    $types,
                    'm'
                );

                if ($type !== 'x') {
                    $results[$type][] = ['number' => $matches[1], 'message' => $matches[2]];
                }
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
        $types = $this->_getChangeTypes();
        $sections = [];
        foreach ($pullRequests as $type => $pulls) {
            $sections[] = "## {$types[$type]}\n" . implode("\n", array_map([$this, '_formatPullRequest'], $pulls));
        }

        return implode("\n\n", $sections);
    }

    /**
     * Allows the user to amend the release notes.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input The command input.
     * @param \Nubs\Sensible\Editor $editor The editor loader for allowing the user to customize the release notes.
     * @param \Symfony\Component\Process\ProcessBuilder $builder The process builder for loading the editor.
     * @param string $releaseNotes The release notes to amend.
     * @return string The amended release notes.
     */
    private function _amendReleaseNotes(InputInterface $input, Editor $editor, ProcessBuilder $processBuilder, $releaseNotes)
    {
        if (!$input->isInteractive()) {
            return $releaseNotes;
        }

        $releaseNotesFile = tempnam(sys_get_temp_dir(), 'buildRelease');
        file_put_contents($releaseNotesFile, $releaseNotes);

        $proc = $processBuilder->setPrefix($editor->get())->setArguments([$releaseNotesFile])->getProcess();
        $proc->setTty(true)->run();
        if ($proc->isSuccessful()) {
            $releaseNotes = file_get_contents($releaseNotesFile);
        }

        unlink($releaseNotesFile);

        return $releaseNotes;
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
     * @param \Guywithnose\Release\Version  $version The version of the release.
     * @param string $releaseName The name of the release.
     * @param string $releaseNotes The formatted release notes.
     * @param string $targetCommitish The target commit/branch/etc. to tag.
     * @return array The data to send to github.
     */
    private function _buildRelease(Version $version, $releaseName, $releaseNotes, $targetCommitish = null)
    {
        return [
            'tag_name' => $version->tagName(),
            'name' => "Version {$version}" . ($releaseName ? ": {$releaseName}" : ''),
            'body' => $releaseNotes,
            'prerelease' => $version->isPreRelease(),
            'draft' => true,
            'target_commitish' => $targetCommitish,
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

        if ($dialog->askConfirmation($output, "{$formattedNotes}\n<question>Continue?</question> <info>(default: yes)</info> ", true)) {
            $client->createRelease($release);
        }
    }
}
