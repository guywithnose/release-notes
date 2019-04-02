<?php
namespace Guywithnose\ReleaseNotes\Change;

use Guywithnose\ReleaseNotes\Type\Type;
use Guywithnose\ReleaseNotes\Type\TypeManager;

class ChangeList
{
    /** @type array The changes. */
    protected $_changes;

    /** @type TypeManager Types. */
    protected $_typeManager;

    /**
     * Initialize the change list.
     *
     * @param array $changes The changes
     */
    public function __construct(TypeManager $typeManager, array $changes)
    {
        $this->_typeManager = $typeManager;
        $this->_changes = $changes;
    }

    /**
     * Checks if the change list is empty.
     *
     * @return bool True for empty, false if there is at least one change.
     */
    public function isEmpty()
    {
        return empty($this->_changes);
    }

    /**
     * Finds the largest change, by type, in the change list.
     *
     * @return \Guywithnose\ReleaseNotes\ChangeInterface|null The largest change.
     */
    public function largestChange()
    {
        $largestChange = null;

        foreach ($this->_changes as $change) {
            $changeIndex = $change->getType();
            if ($largestChange === null) {
                $largestChange = $changeIndex;
            }

            if (Type::cmp($changeIndex, $largestChange) < 0) {
                $largestChange = $change->getType();
            }
        }

        return $largestChange;
    }

    /**
     * Returns a markdown representation of the full changelist split into sections by type.
     *
     * @return string The formatted changelist.
     */
    public function display()
    {
        $types = $this->_typeManager->getTypesForCommand();

        $partitions = $this->_partitionByType();
        $sections = [];
        foreach ($partitions as $type => $changes) {
            $changeDescriptions = [];
            foreach ($changes as $change) {
                $changeDescriptions[] = $change->displayShort();
            }

            $sections[] = "## {$types[$type]}\n" . implode("\n", $changeDescriptions);
        }

        return implode("\n\n", $sections);
    }

    /**
     * Returns the changes partitioned by change type.
     *
     * @return array An array of type => array of changes.
     */
    protected function _partitionByType()
    {
        $types = $this->_typeManager->getTypesForCommand();
        $result = array_combine(array_keys($types), array_fill(0, count($types), []));

        foreach ($this->_changes as $change) {
            $result[$change->getType()->getCode()][] = $change;
        }

        return array_filter($result);
    }
}
