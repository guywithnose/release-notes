<?php
namespace Guywithnose\ReleaseNotes;

use Guywithnose\ReleaseNotes\Change\ChangeList;

class Release
{
    /** @type \Guywithnose\ReleaseNotes\Change\ChangeList The changes made for this release. */
    public $changes;

    /** @type \Guywithnose\ReleaseNotes\Version The version of the previous release. */
    public $currentVersion;

    /** @type \Guywithnose\ReleaseNotes\Version The version of the release. */
    public $version;

    /** @type string The name of the release. */
    public $name;

    /** @type string The formatted release notes. */
    public $notes;

    /** @type string The target commit/branch/etc. to tag. */
    public $targetCommitish;

    /** @type boolean Whether the release is a draft or if it should be published immediately. */
    public $isDraft;

    /**
     * Initialize the release.
     *
     * @param \Guywithnose\ReleaseNotes\Change\ChangeList $changes The changes made for this release.
     * @param \Guywithnose\ReleaseNotes\Version $version The version of the previous release.
     * @param \Guywithnose\ReleaseNotes\Version $version The version of the release.
     * @param string $name The name of the release.
     * @param string $notes The formatted release notes.
     * @param string $targetCommitish The target commit/branch/etc. to tag.
     * @param boolean $isDraft Whether the release is a draft or if it should be published immediately.
     */
    public function __construct(ChangeList $changes, Version $currentVersion, Version $version, $name, $notes, $targetCommitish, $isDraft)
    {
        $this->changes = $changes;
        $this->currentVersion = $currentVersion;
        $this->version = $version;
        $this->name = $name;
        $this->notes = $notes;
        $this->targetCommitish = $targetCommitish;
        $this->isDraft = $isDraft;
    }

    /**
     * Builds a preview of the release for display to the user.
     *
     * @return string A preview of the release.
     */
    public function previewFormat()
    {
        return implode([$this->_actionDescription(), $this->_releaseName(), $this->notes], "\n\n");
    }

    /**
     * Builds the release information to send to github.
     *
     * @return array The data to send to github.
     */
    public function githubFormat()
    {
        return [
            'tag_name' => $this->version->tagName(),
            'name' => $this->_releaseName(),
            'body' => $this->notes,
            'prerelease' => $this->version->isPreRelease(),
            'draft' => $this->isDraft,
            'target_commitish' => $this->targetCommitish,
        ];
    }

    /**
     * Returns the formatted name of the GitHub release, including version.
     *
     * @return string The formatted release name.
     */
    protected function _releaseName()
    {
        return "Version {$this->version}" . ($this->name ? ": {$this->name}" : '');
    }

    /**
     * Builds a description of the action being taken by this release.
     *
     * @return string The formatted action description.
     */
    protected function _actionDescription()
    {
        $action = $this->isDraft ? 'Drafting' : 'Publishing';
        $releaseType = $this->version->isPreRelease() ? 'pre-release' : 'release';
        return "{$action} {$releaseType} tag on {$this->targetCommitish}.";
    }
}
