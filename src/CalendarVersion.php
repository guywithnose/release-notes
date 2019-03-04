<?php
namespace Guywithnose\ReleaseNotes;

use Herrera\Version\Parser as VersionParser;
use Herrera\Version\Exception\InvalidStringRepresentationException;

class CalendarVersion implements VersionInterface
{
    /** @type string The version as a string. */
    protected $_versionString;

    /** @type \Herrera\Version\Version The version. */
    protected $_version;

    /** @type boolean Whether the version is a semantic version. */
    protected $_isSemantic;

    /**
     * Initialize the version.
     *
     * If the version is not given, a 0.0.0 version is assumed.
     *
     * @param string The version.
     */
    public function __construct($version = null)
    {
        $this->_versionString = $version ?: 'v0.0.0';

        try {
            $this->_version = VersionParser::toVersion(ltrim($this->_versionString, 'v'));
            $this->_isSemantic = true;
        } catch (InvalidStringRepresentationException $e) {
            $this->_isSemantic = false;
        }
    }

    /**
     * Get the possible ways to increment the version using semantic versioning.
     *
     * @return array The semantic version number increments.  Will be empty for non-semantic versions.
     */
    public function getSemanticIncrements()
    {
        $now = new \DateTime();

        $increment = 0;

        if ($this->_isSemantic) {
            $y = $this->_version->getMajor();
            $n = $this->_version->getMinor();
            $i = $this->_version->getPatch();

            if ("{$y}.{$n}" === $now->format('y.n')) {
                $increment = $i + 1;
            }
        }

        return [$now->format('\vy.n.') . $increment];
    }

    /**
     * Check to see if the version is a pre-release.
     *
     * @return bool True for prereleases, false for stable releases.
     */
    public function isPreRelease()
    {
        return false;
    }

    /**
     * Converts the version to an appropriate tag name.
     *
     * @return string A tag name for the version.
     */
    public function tagName()
    {
        return "v{$this}";
    }

    /**
     * Converts the version directly to a string.
     *
     * @return string The string representation of the verison.
     */
    public function __toString()
    {
        return $this->_isSemantic ? (string)$this->_version : (string)$this->_versionString;
    }

    /**
     * Returns the unprocessed version string directly.
     *
     * @return string The version string used to construct this version.
     */
    public function unprocessed()
    {
        return $this->_versionString;
    }
}
