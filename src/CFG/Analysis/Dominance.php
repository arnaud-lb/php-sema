<?php

namespace PhpSema\CFG\Analysis;

use PhpSema\CFG\BBlock;
use PhpSema\CFG\CFG;
use PhpSema\Utils\BitSet;

/**
 * "A Simple, Fast Dominance Algorithm", Keith D. Cooper, Timothy J. Harvey, and Ken Kennedy
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class Dominance
{
    private CFG $cfg;

    public function __construct(CFG $cfg)
    {
        $this->cfg = $cfg;
    }

    /**
     * Computes the dominance frontier
     *
     * @param array<int,BBlock> $idoms block id -> immediate dominator
     * @return array<int,BitSet>       block id -> block id set
     */
    public function dominanceFrontier(array $idoms)
    {
        $df = [];

        foreach ($this->cfg->getBBlocks() as $block) {
            $predecessors = $block->getPredecessors();
            if (count($predecessors) >= 2) {
                foreach ($predecessors as $p) {
                    // FIXME: Find an other way to ignore unreachable blocks
                    if (count($p->getPredecessors()) === 0 || !isset($idoms[$p->getId()])) {
                        continue;
                    }
                    $runner = $p;
                    while ($runner !== $idoms[$block->getId()]) {
                        if (!isset($df[$runner->getId()])) {
                            $df[$runner->getId()] = BitSet::unit($block->getId());
                        } else {
                            $df[$runner->getId()]->set($block->getId());
                        }
                        $runner = $idoms[$runner->getId()];
                    }
                }
            }
        }

        return $df;
    }

    /**
     * @param array<int,BBlock> $idoms
     * @return array<int,BBlock[]>
     */
    public function dominatorTree(array $idoms): array
    {
        $dt = [];

        foreach ($this->cfg->getBBlocks() as $id => $node) {
            $idom = $idoms[$id] ?? null;
            if ($idom === null) {
                continue;
            }
            $dt[$idom->getId()][] = $node;
        }

        /*
        $graph = new Graph();
        foreach ($dt as $id => $children) {
            $parent = $graph->createVertex($id, true);
            foreach ($children as $child) {
                $child = $graph->createVertex($child->getId(), true);
                $parent->createEdgeTo($child);
            }
        }
        $graphviz = new GraphViz();
        $graphviz->display($graph);
        */

        return $dt;
    }

    /**
     * Computes immediate dominators
     *
     * @return array<int,BBlock> block id -> immediate dominator
     */
    public function immediateDominators(): array
    {
        // FIXME: check that the orders are right
        $postOrder = $this->cfg->getPostOrder();
        $reversePostOrder = $this->cfg->getReversePostOrder();
        asort($reversePostOrder);

        $blocks = $this->cfg->getBBlocks();
        $entry = $this->cfg->getEntry();

        $doms = [
            $entry->getId() => $entry,
        ];

        do {
            $changed = false;
            foreach ($reversePostOrder as $id => $_) {
                $b = $blocks[$id];
                if ($b === $entry) {
                    continue;
                }
                $newIdom = null;
                foreach ($b->getPredecessors() as $p) {
                    if ($newIdom === null) {
                        if (isset($doms[$p->getId()])) {
                            $newIdom = $p;
                        }
                        continue;
                    }
                    if (isset($doms[$p->getId()])) {
                        while ($newIdom !== $p) {
                            while ($postOrder[$p->getId()] < $postOrder[$newIdom->getId()]) {
                                $p = $doms[$p->getId()];
                            }
                            while ($postOrder[$newIdom->getId()] < $postOrder[$p->getId()]) {
                                $newIdom = $doms[$newIdom->getId()];
                            }
                        }
                    }
                }
                assert($newIdom !== null);
                if (!isset($doms[$b->getId()]) || $doms[$b->getId()] !== $newIdom) {
                    $doms[$b->getId()] = $newIdom;
                    $changed = true;
                }
            }
        } while ($changed);

        unset($doms[$this->cfg->getEntry()->getId()]);

        /*
        $graph = new Graph();
        foreach ($doms as $id => $dom) {
            $parent = $graph->createVertex($id, true);
            $child = $graph->createVertex($dom->getId(), true);
            $parent->createEdgeTo($child);
        }
        $graphviz = new GraphViz();
        $graphviz->display($graph);
        */

        return $doms;
    }
}
