<?php
namespace Guywithnose\ReleaseNotes;

use Github\Client as GithubClient;
use Gregwar\Cache\Cache;
use Herrera\Version\Dumper as VersionDumper;
use Herrera\Version\Parser as VersionParser;
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
            ->addArgument('repo_owner', InputArgument::REQUIRED, 'The github repository owner')
            ->addArgument('repo_name', InputArgument::REQUIRED, 'The github repository name')
            ->addOption('release_name', 'r', InputOption::VALUE_REQUIRED, 'The name to give the release')
            ->addOption('access_token', 't', InputOption::VALUE_REQUIRED, 'The access token to use (overrides cache)')
            ->addOption('cache_dir', null, InputOption::VALUE_REQUIRED, 'The access token cache location', dirname(__DIR__))
            ->addOption('token_file', null, InputOption::VALUE_REQUIRED, 'The access token cache filename', '.access_token');
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

        $owner = $input->getArgument('repo_owner');
        $repo = $input->getArgument('repo_name');

        $client = new GithubClient();
        $client->authenticate($this->_getToken($input, $output), null, GithubClient::AUTH_HTTP_TOKEN);

        $tagName = $client->api('repo')->releases()->all($owner, $repo)[0]['tag_name'];

        $commits = $this->_getCommitsSinceTag($client, $owner, $repo, $tagName);
        $releaseNotes = implode("\n", array_map(array($this, '_formatPullRequest'), $this->_getPullRequests($commits)));

        $nextVersionNumber = $this->_incrementVersion(ltrim($tagName, 'v'));
        $releaseName = $this->_getReleaseName($input, $output);

        $client->api('repo')->releases()->create($owner, $repo, $this->_buildRelease($nextVersionNumber, $releaseName, $releaseNotes));
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
        $token = $input->getOption('access_token');
        if ($token) {
            return $token;
        }

        $askForToken = function() use($output) {
            return $this->getHelperSet()->get('dialog')->ask($output, '<question>Please enter a github access token</question>: ');
        };

        $cache = new Cache($input->getOption('cache_dir'));
        return $cache->getOrCreate($input->getOption('token_file'), [], $askForToken);
    }

    /**
     * Increments the given version number patch version.
     *
     * @param string $version The version number.
     * @return string The incremented version number.
     */
    private function _incrementVersion($version)
    {
        $version = VersionParser::toBuilder($version);
        $version->clearBuild();
        $version->clearPreRelease();
        $version->incrementPatch();

        return VersionDumper::toString($version);
    }

    /**
     * Fetches the commits to the given repo since the given tag.
     *
     * @param \Github\Client $client The github client.
     * @param string $owner The repository owner.
     * @param string $repo The repository name.
     * @param string $tagName The old tag.
     * @return array The commits made to the repository since the old tag.
     */
    private function _getCommitsSinceTag(GithubClient $client, $owner, $repo, $tagName)
    {
        return $client->api('repo')->commits()->compare($owner, $repo, $tagName, 'master')['commits'];
    }


    /**
     * Filters a list of commits down to just the pull requests and extracts the pull request info.
     *
     * @param array The commits.
     * @return array The pull requests, where each pull request has a PR `number` and a commit `message`.
     */
    private function _getPullRequests(array $commits)
    {
        $results = [];
        foreach ($commits as $commit) {
            if (
                count($commit['parents']) === 2 &&
                preg_match('/Merge pull request #([0-9]*)[^\n]*\n[^\n]*\n(.*)/s', $commit['commit']['message'], $matches)
            ) {
                $results[] = ['number' => $matches[1], 'message' => $matches[2]];
            }
        }

        return $results;
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
        $releaseName = $input->getOption('release_name');
        if ($releaseName) {
            return $releaseName;
        }

        $dialog = $this->getHelperSet()->get('dialog');
        if ($dialog->askConfirmation($output, '<question>Use a random release name?</question> ', true)) {
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

        do {
            $releaseName = $this->_getRandomReleaseName();

            $useRelease = $dialog->askConfirmation(
                $output,
                "<question>Use release name '<boldquestion>{$releaseName}</boldquestion>'?</question> ",
                true
            );
        } while (!$useRelease);

        return $releaseName;
    }

    /**
     * Gets a fun random name for the release.
     *
     * @return string The name for the release.
     */
    private function _getRandomReleaseName()
    {
        $randomNameDir = dirname(__DIR__) . '/vgng';

        return exec("cd {$randomNameDir}; ./vgng.py");
    }

    /**
     * Builds the full release information to send to github.
     *
     * @param string $version The version number of the release.
     * @param string $releaseName The name of the release.
     * @param string $releaseNotes The formatted release notes.
     * @return array The data to send to github.
     */
    private function _buildRelease($version, $releaseName, $releaseNotes)
    {
        return ['tag_name' => "v{$version}", 'name' => "Version {$version}: {$releaseName}", 'body' => $releaseNotes, 'draft' => true];
    }
}
