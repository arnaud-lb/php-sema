<?php

namespace PhpSema\CFG\Analysis;

use PhpParser\Comment;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Return_;
use PhpSema\CFG\CFG;
use PhpSema\CFG\NodeUtils;
use PhpSema\CFG\SSA\Node\Phi;
use PhpSema\CFG\SSA\SSASymTable;
use PhpSema\Utils\SideEffectVisitor;
use SplObjectStorage;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class DeadCodeAnalysisSSA
{
    /** @var SplObjectStorage<Node,null> */
    private SplObjectStorage $deadStmts;

    public function __construct(
        private CFG $cfg,
        SSASymTable $symTable,
    )
    {
        $sideEffectVisitor = new SideEffectVisitor();

        /** @var SplObjectStorage<Node,null> */
        $deadStmts = new SplObjectStorage();

        /** @var SplObjectStorage<Node,null> */
        $work = new SplObjectStorage();

        foreach ($cfg->getBBlocks() as $block) {
            foreach ($block->getStmts() as $stmt) {
                if ($sideEffectVisitor->hasSideEffect($stmt) || in_array($stmt, $block->getTerminatorConds(), true)) {
                    $this->processLiveStmt($stmt, $work, $symTable);

                    continue;
                }

                $deadStmts->attach($stmt);
            }
            $terminator = $block->getTerminator();
            if ($terminator instanceof Return_) {
                $this->processLiveStmt($terminator, $work, $symTable);
            } else if ($terminator instanceof Foreach_) {
                $deadStmts->detach($terminator->expr);
                $this->processLiveStmt($terminator->expr, $work, $symTable);
            }
        }

        while (true) {
            $work->rewind();
            if (!$work->valid()) {
                break;
            }

            $stmt = $work->current();
            $work->detach($stmt);

            if (!$deadStmts->contains($stmt)) {
                continue;
            }

            $deadStmts->detach($stmt);
            $this->processLiveStmt($stmt, $work, $symTable);
        }

        $this->deadStmts = $deadStmts;
    }

    /** @return SplObjectStorage<Node,null> */
    public function getDeadStmts(): SplObjectStorage
    {
        return $this->deadStmts;
    }

    /**
     * @return SplObjectStorage<Node,null>
     */
    public function getTopmostDeadStmts(): SplObjectStorage
    {
        $skip = new SplObjectStorage();
        $list = [];

        foreach ($this->cfg->getBBlocks() as $block) {
            foreach (array_reverse($block->getStmts()) as $stmt) {
                if ($skip->contains($stmt)) {
                    foreach (NodeUtils::childNodes($stmt) as $childNode) {
                        $skip->attach($childNode);
                    }
                    continue;
                }
                if ($this->deadStmts->contains($stmt)) {
                    if (!$stmt instanceof Phi && !$stmt instanceof Comment && !$stmt instanceof Nop) {
                        $list[] = $stmt;
                    }
                    foreach (NodeUtils::childNodes($stmt) as $childNode) {
                        $skip->attach($childNode);
                    }
                }
            }
        }

        /** @var SplObjectStorage<Node,null> $store */
        $store = new SplObjectStorage();
        foreach (array_reverse($list) as $elem) {
            $store->attach($elem);
        }

        return $store;
    }

    /** @param SplObjectStorage<Node,null> $work */
    private function processLiveStmt(Node $stmt, SplObjectStorage $work, SSASymTable $symTable): void
    {
        foreach (NodeUtils::childNodes($stmt) as $childNode) {
            $work->attach($childNode);
        }
        if ($stmt instanceof Variable) {
            $def = $symTable->getDef($stmt);
            if ($def !== null) {
                $work->attach($def);
            }
        }
    }
}
