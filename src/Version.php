<?php
namespace Guywithnose\ReleaseNotes;

use Herrera\Version\Builder as VersionBuilder;
use Herrera\Version\Dumper as VersionDumper;
use Herrera\Version\Parser as VersionParser;
use Herrera\Version\Version as HerreraVersion;
use Herrera\Version\Exception\InvalidStringRepresentationException;

class Version
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
        if (!$this->_isSemantic) {
            return [];
        }

        $builder = VersionBuilder::create()->importVersion($this->_version);
        $builder->clearBuild();
        $builder->clearPreRelease();

        return [
            'patch' => (string)$builder->incrementPatch()->getVersion(),
            'minor' => (string)$builder->incrementMinor()->getVersion(),
            'major' => (string)$builder->incrementMajor()->getVersion(),
        ];
    }

    /**
     * Check to see if the version is a pre-release.
     *
     * @return bool True for prereleases, false for stable releases.
     */
    public function isPreRelease()
    {
        return !$this->_isSemantic || !$this->_version->isStable();
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
        return $this->_isSemantic ? (string)$this->_version : $this->_versionString;
    }
}
