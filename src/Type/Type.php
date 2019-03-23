<?php
namespace Guywithnose\ReleaseNotes\Type;

final class Type
{
    /**
     * @type string name or short description of type
     */
    private $_name;

    /**
     * @type string single letter code for selecting in a menu
     */
    private $_code;

    /**
     * @type string description of type
     */
    private $_description;

    /**
     * @type int weight of type used for sorting types
     */
    private $_weight;

    /**
     * Initialize the change.
     *
     * @param string $name        Type name or short description.
     * @param string $code        Single letter code used for choosing this type in a menu
     * @param string $description Longer description of type.
     * @param int    $weight      Weight of description for sorting.
     */
    public function __construct(string $name, string $code, string $description, int $weight)
    {
        $this->_name = $name;
        $this->_code = $code;
        $this->_description = $description;
        $this->_weight = $weight;
    }

    /**
     * Returns a short name of the type for use in release notes.
     *
     * @return string A short representation of the type.
     */
    public function getName() : string
    {
        return $this->_name;
    }

    /**
     * Returns a code of the type for use in menu selection.
     *
     * @return string A code representation of the type.
     */
    public function getCode() : string
    {
        return $this->_code;
    }

    /**
     * Returns a longer description of the type for use in user display.
     *
     * @return string A longer representation of the type.
     */
    public function getDescription() : string
    {
        return $this->_description;
    }

    /**
     * Returns the weight of the type for use in sorting.
     *
     * @return int A weight value for sorting.
     */
    public function getWeight() : int
    {
        return $this->_weight;
    }

    /**
     * Used for sorting object using usort.
     *
     * @param Type $a First type object.
     * @param Type $b Second type object.
     *
     * @return int value indicating less than, equal to, or greater than
     */
    public static function cmp(Type $a, Type $b) : int
    {
        $weightCmp = $a->getWeight() <=> $b->getWeight();
        if ($weightCmp === 0) {
            return strcmp($a->getName(), $b->getName());
        }

        return $weightCmp;
    }
}
