<?php
namespace Guywithnose\ReleaseNotes\Change;

class ChangeList
{
    /** @type array The changes. */
    protected $_changes;

    /**
     * Initialize the change list.
     *
     * @param array $changes The changes
     */
    public function __construct(array $changes)
    {
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
     * @return \Guywithnose\ReleaseNotes\Change|null The largest change.
     */
    public function largestChange()
    {
        $types = array_keys(Change::types());
        $largestChangeIndex = count($types) - 1;
        $largestChange = null;

        foreach ($this->_changes as $change) {
            $changeIndex = $change->getType();
            if ($changeIndex < $largestChangeIndex) {
                $largestChangeIndex = $changeIndex;
                $largestChange = $change;
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
        $types = Change::types();

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
        $types = Change::types();
        $result = array_combine(array_keys($types), array_fill(0, count($types), []));

        foreach ($this->_changes as $change) {
            $result[$change->getType()][] = $change;
        }

        return array_filter($result);
    }
}
