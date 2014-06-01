<?php
namespace Guywithnose\ReleaseNotes\Change;

class Change
{
    const TYPE_BC = 'bc';
    const TYPE_MAJOR = 'M';
    const TYPE_MINOR = 'm';
    const TYPE_BUGFIX = 'b';
    const TYPE_DEVELOPER = 'd';
    const TYPE_IGNORE = 'x';

    /** @type string The type of the change.  One of the TYPE_* constants. */
    protected $_type;

    /** @type string A message describing the change. */
    protected $_message;

    /**
     * Initialize the change.
     *
     * @param string $message The pull request message.
     * @param string $type The pull request type.  @see self::types().
     */
    public function __construct($message, $type = null)
    {
        $this->_message = $message;
        $this->setType($type);
    }

    /**
     * Returns the map between change types and a display representation of them.
     *
     * @return array The type map.
     */
    public static function types()
    {
        return [
            static::TYPE_BC => 'Backwards Compatibility Breakers',
            static::TYPE_MAJOR => 'Major Features',
            static::TYPE_MINOR => 'Minor Features',
            static::TYPE_BUGFIX => 'Bug Fixes',
            static::TYPE_DEVELOPER => 'Developer Changes',
            static::TYPE_IGNORE => 'Remove Pull Request from Release Notes',
        ];
    }

    /**
     * Sets the type.
     *
     * @param string|null $type The type - self::TYPE_MINOR used if type is null.
     * @return void
     */
    public function setType($type)
    {
        $this->_type = $type ?: static::TYPE_MINOR;
    }

    /**
     * Get the type.
     *
     * @return string The type code.
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Get the displayable type.
     *
     * @return string The displayable type.
     */
    public function displayType()
    {
        return static::types()[$this->_type];
    }

    /**
     * Returns a short markdown snippet of the change for use in release notes.
     *
     * @return string A short representation of the change.
     */
    public function displayShort()
    {
        return '* ' . strtok($this->_message, "\n");
    }

    /**
     * Returns a long markdown version of the change for use in user display.
     *
     * @return string A long representation of the change.
     */
    public function displayFull()
    {
        return $this->_message;
    }
}
