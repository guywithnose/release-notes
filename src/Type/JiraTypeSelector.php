<?php
namespace Guywithnose\ReleaseNotes\Type;

use JiraRestApi\Issue\IssueService;
use JiraRestApi\JiraException;
use Guywithnose\ReleaseNotes\Change\ChangeInterface;

final class JiraTypeSelector
{
    /**
     * @type TypeManager type manager with the specified types
     */
    private $_typeManager;

    /**
     * @type IssueService issue service object
     */
    private $_issueService;

    /**
     * @type string regular expression pattern to search for
     */
    private $_pattern;

    /**
     * Initialize the change.
     *
     * @param TypeManager  $typeManager  Type name or short description.
     * @param IssueService $issueService Single letter code used for choosing this type in a menu
     * @param string       $pattern      Longer description of type.
     */
    public function __construct(TypeManager $typeManager, IssueService $issueService, string $pattern)
    {
        $this->_typeManager = $typeManager;
        $this->_issueService = $issueService;
        $this->_pattern = $pattern;
    }

    /**
     * @param ChangeInterface $change Change to check for type from Jira
     *
     * @return Type type of commit or default if unable to determine
     */
    public function getChangeType(ChangeInterface $change) : Type
    {
        $text = $change->displayShort();
        $matches = [];
        if (preg_match_all($this->_pattern, $text, $matches)) {
            foreach ($matches[0] as $key) {
                try {
                    $issue = $this->_issueService->get($key);
                    $issuetype = $issue->fields->issuetype->name;
                    $type = $this->_typeManager->getTypeByName($issuetype);
                    if ($type !== null) {
                        return $type;
                    }
                } catch (JiraException $e) {
                    echo "could not find issue" . PHP_EOL;
                }
            }
        }

        return $this->_typeManager->getDefaultType();
    }
}
