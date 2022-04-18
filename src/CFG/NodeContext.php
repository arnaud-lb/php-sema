<?php

namespace PhpSema\CFG;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Foreach_;
use PhpSema\CFG\SSA\Node\Phi;
use SplObjectStorage;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class NodeContext
{
    private const READ = 1<<0;
    private const WRITE = 1<<1;

    /** @param SplObjectStorage<Node,int> $context */
    private function __construct(
        private SplObjectStorage $context
    ) {
    }

    public static function fromCFG(CFG $cfg): self
    {
        /** @var SplObjectStorage<Node,int> $context */
        $context = new SplObjectStorage();

        foreach ($cfg->getBBlocks() as $block) {
            self::addBlock($context, $block);
        }

        return new self($context);
    }

    public static function fromBlock(BBlock $block): self
    {
        /** @var SplObjectStorage<Node,int> $context */
        $context = new SplObjectStorage();

        self::addBlock($context, $block);

        return new self($context);
    }

    public function isWrite(Node $node): bool
    {
        return (($this->context[$node] ?? 0) & self::WRITE) !== 0;
    }

    /** @param SplObjectStorage<Node,int> $context */
    private static function addBlock(SplObjectStorage $context, BBlock $block): void
    {
        $terminator = $block->getTerminator();
        if ($terminator instanceof Foreach_) {
            if ($terminator->keyVar !== null) {
                $context->attach($terminator->keyVar, self::WRITE);
            }
            $context->attach($terminator->valueVar, self::WRITE);
        }

        foreach (array_reverse($block->getStmts()) as $stmt) {
            switch (true) {
                case $stmt instanceof Assign:
                    $context->attach($stmt->var, self::WRITE);
                    break;
                case $stmt instanceof Phi:
                    $context->attach($stmt->var, self::WRITE);
                    break;
                case $stmt instanceof PostInc || $stmt instanceof PostDec || $stmt instanceof PreInc || $stmt instanceof PreDec:
                    $context->attach($stmt->var, self::WRITE | self::READ);
                    break;
                case $stmt instanceof Param:
                    $context->attach($stmt->var, self::WRITE);
                // TODO
            }
        }
    }
}
