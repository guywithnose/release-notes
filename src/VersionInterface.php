<?php
namespace Guywithnose\ReleaseNotes;

interface VersionInterface
{
    /**
     * Get the possible ways to increment the version using semantic versioning.
     *
     * @return array The semantic version number increments.  Will be empty for non-semantic versions.
     */
    public function getSemanticIncrements();

    /**
     * Check to see if the version is a pre-release.
     *
     * @return bool True for prereleases, false for stable releases.
     */
    public function isPreRelease();

    /**
     * Converts the version to an appropriate tag name.
     *
     * @return string A tag name for the version.
     */
    public function tagName();

    /**
     * Converts the version directly to a string.
     *
     * @return string The string representation of the verison.
     */
    public function __toString();

    /**
     * Returns the unprocessed version string directly.
     *
     * @return string The version string used to construct this version.
     */
    public function unprocessed();
}
