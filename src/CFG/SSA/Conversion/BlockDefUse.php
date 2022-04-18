<?php

namespace PhpSema\CFG\SSA\Conversion;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Foreach_;
use PhpSema\CFG\BBlock;
use PhpSema\CFG\NodeContext;
use PhpSema\CFG\SymTable;
use PhpSema\Utils\BitSet;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class BlockDefUse
{
    private BitSet $def;

    private BitSet $use;

    public function __construct(
        SymTable $symTable,
        BBlock $block,
    ) {
        $this->def = BitSet::empty();
        $this->use = BitSet::empty();

        $nameToId = $symTable->getNameToIdMap();
        $context = NodeContext::fromBlock($block);

        $terminator = $block->getTerminator();
        if ($terminator instanceof Foreach_) {
            $var = $terminator->keyVar;
            if ($var instanceof Variable && is_string($var->name)) {
                $id = $nameToId[$var->name];
                $this->def->set($id);
            }
            $var = $terminator->valueVar;
            if ($var instanceof Variable && is_string($var->name)) {
                $id = $nameToId[$var->name];
                $this->def->set($id);
            }
        }

        foreach ($block->getStmts() as $stmt) {
            if ($stmt instanceof Variable) {
                if (!is_string($stmt->name)) {
                    continue;
                }
                $id = $nameToId[$stmt->name];
                if ($context->isWrite($stmt)) {
                    $this->def->set($id);
                    continue;
                }
                if (!$this->def->isset($id)) {
                    $this->use->set($id);
                }
            }
        }
    }

    public function getDef(): BitSet
    {
        return $this->def;
    }

    public function getUse(): BitSet
    {
        return $this->use;
    }
}
