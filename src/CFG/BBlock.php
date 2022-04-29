<?php

namespace PhpSema\CFG;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\While_;

/**
 * A CFG Basic Block
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class BBlock
{
    /** @var Node[] */
    private array $stmts;

    private ?string $label;

    private ?Node $terminator;

    public function __construct(
        private int $id,
        private CFG $cfg,
    )
    {
        $this->stmts = [];
        $this->label = null;
        $this->terminator = null;
    }

    /**
     * @return array<Node>
     */
    public function getStmts(): array
    {
        return $this->stmts;
    }

    /**
     * @return array<Node>
     */
    public function getStmtsAndTerminator(): array
    {
        if ($this->terminator === null) {
            return $this->stmts;
        }

        return [...$this->stmts, $this->terminator];
    }

    public function addStmt(Node $stmt): void
    {
        if ($this->terminator !== null) {
            throw new \RuntimeException(sprintf(
                'Can not add statement in terminated block (statement: %s from line %s, terminator: %s from line %s)',
                get_class($stmt),
                $stmt->getLine(),
                get_class($this->terminator),
                $this->terminator->getLine(),
            ));
        }

        $this->stmts[] = $stmt;
    }

    public function prependStmt(Node $stmt): void
    {
        array_unshift($this->stmts, $stmt);
    }

    /** @param array<Node> $stmts */
    public function prependStmts(array $stmts): void
    {
        $this->stmts = [...$stmts, ...$this->stmts];
    }

    public function getId(): int
    {
        return $this->id;
    }

    /** @return BBlock[] */
    public function getSuccessors(): array
    {
        return $this->cfg->getSuccessorsById($this->id);
    }

    /** @return BBlock[] */
    public function getPredecessors(): array
    {
        return $this->cfg->getPredecessorsById($this->id);
    }

    public function setTerminator(Node $terminator, BBlock ...$blocks): void
    {
        if ($this->terminator !== null) {
            throw new \RuntimeException(sprintf(
                'Can not add terminator to terminated block (statement: %s from line %s, terminator: %s from line %s)',
                get_class($terminator),
                $terminator->getLine(),
                get_class($this->terminator),
                $this->terminator->getLine(),
            ));
        }

        $ids = [];
        foreach ($blocks as $block) {
            $ids[] = $block->getId();
        }

        $this->cfg->setSuccessorIds($this->id, $ids);
        $this->terminator = $terminator;
    }

    public function getTerminator(): ?Node
    {
        return $this->terminator;
    }

    /** @return Node[] */
    public function getTerminatorConds(): array
    {
        $terminator = $this->terminator;

        return match (true) {
            $terminator === null => [],
            $terminator instanceof If_ => [$terminator->cond],
            $terminator instanceof For_ => array_slice($terminator->cond, -1),
            $terminator instanceof Foreach_ => [$terminator->expr],
            $terminator instanceof While_ => [$terminator->cond],
            $terminator instanceof Switch_ => [$terminator->cond],
            $terminator instanceof Case_ => $terminator->cond !== null ? [$terminator->cond] : [],
            $terminator instanceof MatchArm => $terminator->conds !== null ? $terminator->conds : [],
            $terminator instanceof BooleanAnd => [$terminator->left],
            $terminator instanceof BooleanOr => [$terminator->left],
            $terminator instanceof LogicalAnd => [$terminator->left],
            $terminator instanceof LogicalOr => [$terminator->left],
            $terminator instanceof Ternary => [$terminator->cond],
            default => [],
        };
    }

    public function setSuccessor(BBlock $successor): void
    {
        $this->cfg->setSuccessorIds($this->id, [$successor->getId()]);
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getName(): string
    {
        return sprintf('node_%s', $this->id);
    }

    /** @return iterable<BBlock> */
    public function reversePostOrderIterator(): iterable
    {
        foreach (array_reverse($this->getSuccessors()) as $succ) {
            yield from $succ->reversePostOrderIterator();
        }

        yield $this;
    }
}
