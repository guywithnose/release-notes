<?php
namespace Guywithnose\ReleaseNotes;

use Fhaculty\Graph\Graph;

class GithubCommitGraph
{
    /** @type \Fhaculty\Graph\Graph The commit graph by sha. */
    private $_graph;

    /**
     * Initialize the commit graph using the github API commit representation.
     *
     * @param array $commits The commits as returned from the github API.
     */
    public function __construct(array $commits)
    {
        $this->_graph = $this->_buildGraph($commits);
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
        while ($current !== null && $current->getAttribute('commit')) {
            $result[] = $current->getAttribute('commit');
            $parents = $current->getVerticesEdgeTo();
            if ($parents->isEmpty()) {
                break;
            }

            $current = $parents->getVertexFirst();
        }

        return $result;
    }

    /**
     * Creates the graph from the commits.
     *
     * The graph represents the parent-child relationship.  Each node in the graph has the id of the commit hash.
     *
     * @param array $commits The commits.
     * @return \Fhaculty\Graph\Graph The graph of commits.
     */
    private function _buildGraph(array $commits)
    {
        $graph = new Graph();

        foreach ($commits as $commit) {
            $vertex = $graph->createVertex($commit['sha'], true);
            $vertex->setAttribute('commit', $commit);
            foreach ($commit['parents'] as $parent) {
                $vertex->createEdgeTo($graph->createVertex($parent['sha'], true));
            }
        }

        return $graph;
    }

    /**
     * Searches the graph for the base commit node.
     *
     * @return \Fhaculty\Graph\Vertex|null The base commit node or null if there isn't exactly one.
     */
    private function _baseCommitNode()
    {
        $noChildren = function($vertex) {
            return $vertex->getEdgesIn()->isEmpty();
        };

        $baseNodes = $this->_graph->getVertices()->getVerticesMatch($noChildren);

        return $baseNodes->count() === 1 ? $baseNodes->getVertexFirst() : null;
    }
}
