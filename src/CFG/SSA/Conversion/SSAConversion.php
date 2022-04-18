<?php

namespace PhpSema\CFG\SSA\Conversion;

use PhpParser\Node\Expr\Variable;
use PhpSema\CFG\Analysis\Dominance;
use PhpSema\CFG\BBlock;
use PhpSema\CFG\CFG;
use PhpSema\CFG\SSA\Node\Phi;
use PhpSema\CFG\SSA\SSASymTable;
use PhpSema\CFG\SymTable;
use PhpSema\Utils\BitSet;

/**
 * Conversion of a CFG to SSA form
 *
 * Implements "Efficiently Computing Static Single Assignment Form and the Control Dependence Graph", Cytron, Ron & Ferrante, Jeanne & Rosen, Barry & Wegman, Mark & Zadeck, Kenneth. (1991)
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class SSAConversion
{
    public function __construct(
        private CFG $cfg,
        private SymTable $symTable,
    ) {
    }

    public static function convert(CFG $cfg): SSASymTable
    {
        $symTable = SymTable::fromCFG($cfg);

        $conversion = new self($cfg, $symTable);

        $defUses = [];
        foreach ($cfg->getBBlocks() as $block) {
            $defUses[$block->getId()] = new BlockDefUse($symTable, $block);
        }

        $dom = new Dominance($cfg);

        $idoms = $dom->immediateDominators();
        $df = $dom->dominanceFrontier($idoms);
        $domTree = $dom->dominatorTree($idoms);

        $conversion->insertPhis($defUses, $df);
        $symTable = $conversion->rename($domTree);

        return $symTable;
    }

    /**
     * Place phi functions
     *
     * @param array<int,BlockDefUse> $defUses
     * @param array<int,BitSet> $df
     */
    public function insertPhis(array $defUses, array $df): void
    {
        $nodes = $this->cfg->getBBlocks();
        $phi = [];
        $variables = $this->symTable->getIdToNameMap();
        $orig = [];
        $defsites = [];
        foreach ($variables as $varId => $varName) {
            $phi[$varId] = BitSet::empty();
            $defsites[$varId] = [];
        }
        foreach ($defUses as $bbId => $defUse) {
            $orig[$bbId] = BitSet::empty();
            foreach ($defUse->getDef()->toArray() as $varId) {
                $orig[$bbId]->set($varId);
                $defsites[$varId][] = $bbId;
            }
        }
        foreach ($variables as $v => $varName) {
            $w = $defsites[$v];
            while (true) {
                $x = array_pop($w);
                if ($x === null) {
                    break;
                }
                if (!isset($df[$x])) {
                    continue;
                }
                foreach ($df[$x]->toArray() as $y) {
                    if ($phi[$v]->isset($y)) {
                        continue;
                    }

                    $varNode = new Variable($varName);
                    $sourceNodes = [];
                    for ($i = 0, $l = count($nodes[$y]->getPredecessors()); $i < $l; $i++) {
                        $sourceNodes[] = new Variable($varName);
                    }
                    $nodes[$y]->prependStmts([
                        $varNode,
                        ...$sourceNodes,
                        new Phi($varNode, $sourceNodes),
                    ]);

                    $phi[$v]->set($y);
                    if (!$orig[$y]->isset($v)) {
                        $w[] = $y;
                    }
                }
            }
        }
    }

    /**
     * SSA rename vars
     *
     * @param array<int,BBlock[]> $domTree
     */
    public function rename(array $domTree): SSASymTable
    {
        $process = new RenameProcess($this->cfg, $this->symTable, $domTree);
        $process->rename();

        return $process->getSymbolTable();
    }
}
