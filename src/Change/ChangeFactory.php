<?php
namespace Guywithnose\ReleaseNotes\Change;

class ChangeFactory
{
    /** @type callable A function that selects the type for a given change. */
    protected $_typeSelector;

    /**
     * Initialize the change factory with the type selector.
     *
     * The type selector is a function that is passed the change and must return a valid type (@see \Guywithnose\ReleaseNotes\Change::types) or
     * null to use the default type.
     *
     * @param callable $typeSelector The type selector function.
     */
    public function __construct(callable $typeSelector = null)
    {
        $this->_typeSelector = $typeSelector ?: function(Change $change) {
            return $change->getType();
        };
    }

    /**
     * Create the change from a github API commit representation.
     *
     * @param array $commit The commit representation from the github API.
     * @return \Guywithnose\ReleaseNotes\Change|null The change object for the commit, or null if none could be found.
     */
    public function createFromCommit(array $commit)
    {
        $typeSelector = $this->_typeSelector;
        $change = null;

        if (count($commit['parents']) > 1) {
            $change = null;
            if (preg_match('/Merge pull request #([0-9]*)[^\n]*\n[^\n]*\n(.*)/s', $commit['commit']['message'], $matches)) {
                $change = new PullRequest((int)$matches[1], $matches[2]);
            } elseif (preg_match('/Merge branch \'([^\']*)\'[^\n]*\n[^\n]*\n(.*)/s', $commit['commit']['message'], $matches)) {
                $change = new Merge($matches[1], $matches[2]);
            } else {
                $change = new Change($commit['commit']['message']);
            }
        } else {
            $change = new Change($commit['commit']['message']);
        }

        $type = $typeSelector($change);
        if ($type === Change::TYPE_IGNORE) {
            return null;
        }

        $change->setType($type);

        return $change;

        return null;
    }
}
