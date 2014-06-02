<?php
namespace Guywithnose\ReleaseNotes\Change;

use Guywithnose\ReleaseNotes\GithubClient;

class ChangeListFactory
{
    /** @type array The change factory. */
    protected $_changeFactory;

    /**
     * Initialize the change list factory.
     *
     * @param ChangeFactory $changeFactory The change factory used to create changes.
     */
    public function __construct(ChangeFactory $changeFactory)
    {
        $this->_changeFactory = $changeFactory;
    }

    /**
     * Fetch the commits from github between the two commits/tags/branches/etc. and create a changelist from the result.
     *
     * @param \Guywithnose\ReleaseNotes\GithubClient $client The github client.
     * @param string|null $startCommitish The beginning commit - excluded from results.  If this is null, all ancestors of $endCommitish will be
     *     returned.
     * @param string $endCommitish The end commit - included in results.
     * @return \Guywithnose\ReleaseNotes\ChangeList The list of changes in the commit range.
     */
    public function createFromGithubRange(GithubClient $client, $startCommitish, $endCommitish)
    {
        if ($startCommitish !== null) {
            return self::createFromCommits($client->getCommitsSinceTag($startCommitish, $endCommitish));
        }

        return self::createFromCommits($client->getCommitsOnBranch($endCommitish));
    }

    /**
     * Creates a changelist from the list of commits (from the github API).
     *
     * @param array $commits The commit representations from the github API.
     * @return \Guywithnose\ReleaseNotes\ChangeList The list of changes in the commit range.
     */
    public function createFromCommits(array $commits)
    {
        $commits = array_filter(array_map([$this->_changeFactory, 'createFromCommit'], $commits));

        return new ChangeList($commits);
    }
}
