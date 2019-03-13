<?php
namespace Guywithnose\ReleaseNotes;

use FlexyProject\GitHub\Client;
use Github\ResultPager;

class GithubClient
{
    /** @type \Github\Client The github client. */
    protected $_repoReceiver;

    /**
     * Initialize the github client wrapper for the repository.
     *
     * @param \Github\Client The github client.
     * @param string $owner The owner name of the github repository.
     * @param string $repo The name of the github repository.
     */
    public function __construct(Client $client, $owner, $repo)
    {
        $this->_repoReceiver = $client->getReceiver(\FlexyProject\GitHub\Client::REPOSITORIES);
        $this->_repoReceiver->setOwner($owner);
        $this->_repoReceiver->setRepo($repo);
    }

    /**
     * Create a github client wrapper with automated token-based authentication.
     *
     * @param string $token The API token to authenticate with.
     * @param string $owner The owner name of the github repository.
     * @param string $repo The name of the github repository.
     * @param string $apiUrl The base url to the github API if different from the main github site (i.e., GitHub Enterprise).
     * @return self The github client wrapper, authenticated against the API.
     * @throws \Exception if the token is invalid
     */
    public static function createWithToken($token, $owner, $repo, $apiUrl = null)
    {
        $client = new Client();

        if ($apiUrl !== null) {
            $client->setApiUrl($apiUrl);
        }

        $client->setToken($token);

        // Verify that the token works
        $users = $client->getReceiver(\FlexyProject\GitHub\Client::USERS);
        $authenticatedUser = $users->getUser();
        if (isset($authenticatedUser['message']) && $authenticatedUser['message'] === 'Bad credentials') {
            throw new \Exception('Bad credentials');
        }

        return new static($client, $owner, $repo);
    }

    /**
     * Get the latest release's tag name for the repo.
     *
     * @param string $releaseBranch The branch to find releases on, or null to find tag from any branch.
     * @return string|null The release's tag name if one exists.
     */
    public function getLatestReleaseTagName($releaseBranch = null)
    {
        $releasesReceiver = $this->_repoReceiver->getReceiver(\FlexyProject\GitHub\Receiver\Repositories::RELEASES);
        $releases = $releasesReceiver->listReleases();

        foreach ($releases as $release) {
            if ($releaseBranch === null || $release['target_commitish'] === $releaseBranch) {
                return $release['tag_name'];
            }
        }

        return null;
    }

    /**
     * Fetch the commits from github between the two commits/tags/branches/etc.
     *
     * @param string|null $startCommitish The beginning commit - excluded from results.  If this is null, all ancestors of $endCommitish will be
     *     returned.
     * @param string $endCommitish The end commit - included in results.
     * @return array The list of changes in the commit range.
     */
    public function getCommitsInRange($startCommitish, $endCommitish)
    {
        if ($startCommitish !== null) {
            return $this->getCommitsSinceTag($startCommitish, $endCommitish);
        }

        return $this->getCommitsOnBranch($endCommitish);
    }

    /**
     * Fetches the commits to the repo since the given tag.
     *
     * @param string $tagName The old tag.
     * @param string $branch The branch to check
     * @return array The commits made to the repository since the old tag.
     */
    public function getCommitsSinceTag($tagName, $branch = 'masterBranch')
    {
        $commitsReceiver = $this->_repoReceiver->getReceiver(\FlexyProject\GitHub\Receiver\Repositories::COMMITS);
        return $commitsReceiver->compareTwoCommits($tagName, $branch)['commits'];
    }

    /**
     * Fetch the commits for the repo's branch.
     *
     * @param string $branch The branch to check
     * @return array The commits made to the repository's branch.
     */
    public function getCommitsOnBranch($branch = 'master')
    {
        $commitsReceiver = $this->_repoReceiver->getReceiver(\FlexyProject\GitHub\Receiver\Repositories::COMMITS);
        return $commitsReceiver->listCommits($branch);
    }

    /**
     * Submits the given release to github.
     *
     * @param array $release The release information.
     * @return void
     */
    public function createRelease(array $release)
    {
        $releasesReceiver = $this->_repoReceiver->getReceiver(\FlexyProject\GitHub\Receiver\Repositories::RELEASES);
        $releasesReceiver->createRelease(
            $release['tag_name'],
            $release['target_commitish'],
            $release['name'],
            $release['body'],
            $release['draft'],
            $release['prerelease']
        );
    }
}
