<?php
namespace Guywithnose\ReleaseNotes\Change;

use Guywithnose\ReleaseNotes\Type\Type;

class PullRequest extends Change
{
    /** @type int The pull request number. */
    protected $_number;

    /**
     * Create the pull request change.
     *
     * @api
     * @param int $number The pull request number.
     * @param string $message The pull request message.
     * @param Type $type The pull request type.
     */
    public function __construct($number, $message, Type $type)
    {
        parent::__construct($message, $type);
        $this->_number = $number;
    }

    /**
     * Returns a short markdown snippet of the pull request for use in release notes.
     *
     * @return string A short representation of the pull request.
     */
    public function displayShort() : string
    {
        return parent::displayShort() . "&nbsp;<sup>[PR&nbsp;#{$this->_number}]</sup>";
    }

    /**
     * Returns a long markdown version of the pull request for use in user display.
     *
     * @return string A long representation of the pull request.
     */
    public function displayFull() : string
    {
        return "### Pull Request #{$this->_number}\n{$this->_message}";
    }
}
