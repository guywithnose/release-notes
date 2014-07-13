<?php
namespace Guywithnose\ReleaseNotes;

use Fhaculty\Graph\Graph;

class GithubCommitGraph
{
    /** @type array The commits pulled from github. */
    private $_commits;

    /** @type \Fhaculty\Graph\Graph The commit graph by sha. */
    private $_graph;

    /**
     * Initialize the commit graph using the github API commit representation.
     *
     * @param array $commits The commits as returned from the github API.
     */
    public function __construct(array $commits)
    {
        $this->_commits = $this->_prepareCommits($commits);
        $this->_graph = $this->_buildGraph();
    }

    /**
     * Filter the commits to just the first parents (i.e., merge commits).
     *
     * @return array The commits along the left-most line of the commit graph.
     */
    public function firstParents()
    {
        $result = [];

        $current = $this->_baseCommitNode();
        while (isset($this->_commits[$current->getId()])) {
            $result[] = $this->_commits[$current->getId()];
            $current = $current->getVerticesEdgeTo()->getVertexFirst();
        }

        return $result;
    }

    /**
     * Creates the graph from the commits.
     *
     * The graph represents the parent-child relationship.  Each node in the graph has the id of the commit hash.
     *
     * @return \Fhaculty\Graph\Graph The graph of commits.
     */
    private function _buildGraph()
    {
        $graph = new Graph();

        foreach ($this->_commits as $sha => $commit) {
            $vertex = $graph->createVertex($sha, true);
            foreach ($commit['parents'] as $parent) {
                $vertex->createEdgeTo($graph->createVertex($parent['sha'], true));
            }
        }

        return $graph;
    }

    /**
     * Initializes a private commit datastructure in order to more easily look up commits by sha.
     *
     * @param array $commits The commits.
     * @return array The commits with the sha's as the keys.
     */
    private function _prepareCommits(array $commits)
    {
        $result = [];
        foreach ($commits as $commit) {
            $result[$commit['sha']] = $commit;
        }

        return $result;
    }

    /**
     * Searches the graph for the base commit node.
     *
     * @return \Fhaculty\Graph\Vertex The base commit node.
     */
    private function _baseCommitNode()
    {
        $noChildren = function($vertex) {
            return $vertex->getEdgesIn()->isEmpty();
        };

        return $this->_graph->getVertices()->getVertexMatch($noChildren);
    }
}
