<?php
namespace Guywithnose\ReleaseNotes\Change;

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
