<?php
namespace Guywithnose\ReleaseNotes;

use Herrera\Version\Builder as VersionBuilder;
use Herrera\Version\Dumper as VersionDumper;
use Herrera\Version\Parser as VersionParser;
use Herrera\Version\Version as HerreraVersion;

class Version
{
    /** @type \Herrera\Version\Version The version. */
    protected $_version;

    /**
     * Initialize the version.
     *
     * If the version is not given, a 0.0.0 version is assumed.
     *
     * @param \Herrera\Version\Version The version.
     */
    public function __construct(HerreraVersion $version = null)
    {
        $this->_version = $version ?: new HerreraVersion();
    }

    /**
     * Create the version from a string.
     *
     * The string will have any leading 'v' trimmed off of it.
     *
     * @param string $string The version with an optional leading v.
     * @return self The version object.
     */
    public static function createFromString($string)
    {
        $version = $string ? VersionParser::toVersion(ltrim($string, 'v')) : null;

        return new static($version);
    }

    /**
     * Get the possible ways to increment the version using semantic versioning.
     *
     * @return array The semantic version number increments.
     */
    public function getSemanticIncrements()
    {
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
        return !$this->_version->isStable();
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
        return (string)$this->_version;
    }
}
