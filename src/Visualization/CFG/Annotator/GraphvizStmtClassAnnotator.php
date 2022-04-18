<?php

namespace PhpSema\Visualization\CFG\Annotator;

use PhpParser\Node;
use PhpSema\CFG\BBlock;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class GraphvizStmtClassAnnotator implements GraphvizStmtAnnotator
{
    public function getAnnotation(BBlock $block, Node $stmt, int $nodeIndex): ?string
    {
        return addcslashes(get_class($stmt), '\\');
    }
}
