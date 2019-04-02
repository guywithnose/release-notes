<?php
namespace Guywithnose\ReleaseNotes\Change;

use Guywithnose\ReleaseNotes\Type\Type;

class Merge extends Change
{
    /** @type string The branch name. */
    protected $_branch;

    /**
     * Create the merge change.
     *
     * @api
     * @param string $branch The merged branch.
     * @param string $message The merge message.
     * @param Type $type The merge type.
     */
    public function __construct($branch, $message, Type $type)
    {
        parent::__construct($message, $type);
        $this->_branch = $branch;
    }

    /**
     * Returns a short markdown snippet of the merge for use in release notes.
     *
     * @return string A short representation of the merge.
     */
    public function displayShort() : string
    {
        return parent::displayShort() . "&nbsp;<sup>[{$this->_branch}]</sup>";
    }

    /**
     * Returns a long markdown version of the merge for use in user display.
     *
     * @return string A long representation of the merge.
     */
    public function displayFull() : string
    {
        return "### {$this->_branch}\n{$this->_message}";
    }
}
