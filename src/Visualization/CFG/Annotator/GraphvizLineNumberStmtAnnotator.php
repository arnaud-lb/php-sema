<?php

namespace PhpSema\Visualization\CFG\Annotator;

use PhpParser\Node;
use PhpSema\CFG\BBlock;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class GraphvizLineNumberStmtAnnotator implements GraphvizStmtAnnotator
{
    private ?BBlock $prevBlock = null;

    private ?int $prevLine = null;

    public function getAnnotation(BBlock $block, Node $stmt, int $nodeIndex): ?string
    {
        if ($this->prevBlock === $block && $this->prevLine === $stmt->getLine()) {
            return null;
        }

        $this->prevBlock = $block;
        $this->prevLine = $stmt->getLine();

        return (string) $stmt->getLine();
    }
}
