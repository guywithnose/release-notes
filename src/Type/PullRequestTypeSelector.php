<?php
namespace Guywithnose\ReleaseNotes\Type;

use Guywithnose\ReleaseNotes\Change\Change;
use Guywithnose\ReleaseNotes\Change\ChangeInterface;
use Guywithnose\ReleaseNotes\Change\PullRequest;

final class PullRequestTypeSelector
{
    /**
     * @type TypeManager type manager with the specified types
     */
    private $_typeManager;

    /**
     * @type callable Type selector callable to call when change is a Pull Request
     */
    private $_typeSelector;

    /**
     * Initialize the change.
     *
     * @param TypeManager $typeManager  Type name or short description.
     * @param callable    $typeSelector Type selector callable to call when change is a Pull Request
     */
    public function __construct(TypeManager $typeManager, callable $typeSelector = null)
    {
        $this->_typeManager = $typeManager;
        $this->_typeSelector = $typeSelector;
    }

    /**
     * @param ChangeInterface $change Change to check for type from Jira
     *
     * @return Type type of commit or default if unable to determine
     */
    public function getChangeType(ChangeInterface $change) : Type
    {
        if (!$change instanceof PullRequest) {
            return $this->_typeManager->getTypeByCode(Change::TYPE_IGNORE);
        }

        if ($this->_typeSelector) {
            return $this->_typeSelector($change);
        }

        return $change->getType();
    }
}
