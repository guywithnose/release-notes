<?php
namespace Guywithnose\ReleaseNotes;

use Github\Client;
use Github\ResultPager;

class GithubClient
{
    /** @type \Github\Client The github client. */
    protected $_client;

    /** @type string The owner name of the github repository. */
    protected $_owner;

    /** @type string The name of the github repository. */
    protected $_repo;

    /**
     * Initialize the github client wrapper for the repository.
     *
     * @param \Github\Client The github client.
     * @param string $owner The owner name of the github repository.
     * @param string $repo The name of the github repository.
     */
    public function __construct(Client $client, $owner, $repo)
    {
        $this->_client = $client;
        $this->_owner = $owner;
        $this->_repo = $repo;
    }

    /**
     * Create a github client wrapper with automated token-based authentication.
     *
     * @param string $token The API token to authenticate with.
     * @param string $owner The owner name of the github repository.
     * @param string $repo The name of the github repository.
     * @return self The github client wrapper, authenticated against the API.
     */
    public static function createWithToken($token, $owner, $repo)
    {
        $client = new Client();
        $client->authenticate($token, null, Client::AUTH_HTTP_TOKEN);

        return new static($client, $owner, $repo);
    }

    /**
     * Get the latest release's tag name for the repo.
     *
     * @return string|null The release's tag name if one exists.
     */
    public function getLatestReleaseTagName()
    {
        $releases = $this->_client->api('repo')->releases()->all($this->_owner, $this->_repo);

        return empty($releases) ? null : $releases[0]['tag_name'];
    }

    /**
     * Fetches the commits to the repo since the given tag.
     *
     * @param string $tagName The old tag.
     * @return array The commits made to the repository since the old tag.
     */
    public function getCommitsSinceTag($tagName)
    {
        return $this->_client->api('repo')->commits()->compare($this->_owner, $this->_repo, $tagName, 'master')['commits'];
    }

    /**
     * Fetch the commits for the repo's master branch.
     *
     * @return array The commits made to the repository's master branch.
     */
    public function getCommitsOnMaster()
    {
        $paginator = new ResultPager($this->_client);

        return $paginator->fetchAll($this->_client->api('repo')->commits(), 'all', [$this->_owner, $this->_repo, ['sha' => 'master']]);
    }

    /**
     * Submits the given release to github.
     *
     * @param array $release The release information.
     * @return void
     */
    public function createRelease(array $release)
    {
        $this->_client->api('repo')->releases()->create($this->_owner, $this->_repo, $release);
    }
}
