<?php

namespace PhpSema\Visualization\CFG\Annotator;

use PhpParser\Node;
use PhpSema\CFG\Analysis\DeadCodeAnalysisSSA;
use PhpSema\CFG\BBlock;
use SplObjectStorage;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class GraphvizDeadStmtAnnotator implements GraphvizStmtAnnotator
{
    /** @var SplObjectStorage<Node,null> */
    private $deadStmts;

    public function __construct(
        DeadCodeAnalysisSSA $deadCodeAnalysisSSA,
    ) {
        $this->deadStmts = $deadCodeAnalysisSSA->getDeadStmts();
    }

    public function getAnnotation(BBlock $block, Node $stmt, int $nodeIndex): ?string
    {
        if (!$this->deadStmts->contains($stmt)) {
            return null;
        }

        return "\u{1F480}"; // skull
    }
}
