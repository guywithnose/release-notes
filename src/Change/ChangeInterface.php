<?php
namespace Guywithnose\ReleaseNotes\Change;

interface ChangeInterface
{
    /**
     * Sets the type.
     *
     * @param Type $type The type
     *
     * @return void
     */
    public function setType(Type $type);

    /**
     * Get the type.
     *
     * @return Type The type code.
     */
    public function getType() : Type;

    /**
     * Returns a short markdown snippet of the change for use in release notes.
     *
     * @return string A short representation of the change.
     */
    public function displayShort() : string;

    /**
     * Returns a long markdown version of the change for use in user display.
     *
     * @return string A long representation of the change.
     */
    public function displayFull() : string;
}
