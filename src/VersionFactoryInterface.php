<?php
namespace Guywithnose\ReleaseNotes;

interface VersionFactoryInterface
{
    /**
     * Create a new version object with the string $version.
     *
     * @param string $version The version.
     *
     * @return VersionInterface new version object
     */
    public function createVersion($version = null) : VersionInterface;
}
