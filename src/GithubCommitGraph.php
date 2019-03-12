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
     * @param int $depth optional depth of commits to get; default 1
     *
     * @return array The commits along the left-most line of the commit graph.
     */
    public function firstParents(int $depth = 1) : array
    {
        $current = $this->_baseCommitNode();

        return $this->getCommitsToDepth($current, $depth);
    }

    /**
     * Get commits down to the specified depth.
     *
     * @param $baseCommitNode the commit to start processing with
     * @param int $depth Depth of commits to go starting at 1
     *
     * @return array The commits along the left-most line of the commit graph.
     */
    public function getCommitsToDepth($baseCommitNode, int $depth) : array
    {
        if ($depth === 0) {
            return [];
        }

        $result = [];

        $current = $baseCommitNode;
        while ($current !== null && $current->getAttribute('commit')) {
            $commit = $current->getAttribute('commit');
            $result[$commit['sha']] = $current->getAttribute('commit');
            $parents = $current->getVerticesEdgeTo();
            if ($parents->isEmpty()) {
                break;
            }

            if (count($parents) > 1) {
                $result = array_merge($result, $this->getCommitsToDepth($parents->getVertexLast(), $depth - 1));
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
