<?php
namespace Guywithnose\ReleaseNotes;

final class CalendarVersionFactory implements VersionFactoryInterface
{
    /**
     * Create a new version object with the string $version.
     *
     * @param string $version The version.
     *
     * @return VersionInterface new version object
     */
    public function createVersion($version = null) : VersionInterface
    {
        return new CalendarVersion($version);
    }
}
