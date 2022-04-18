<?php

namespace PhpSema\CFG;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Goto_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Label;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class CFGBuilder
{
    private CFG $cfg;

    /** @var BBlock[] */
    private array $finallyStack;

    /** @var BBlock[] */
    private array $breakStack;

    /** @var BBlock[] */
    private array $continueStack;

    /** @var array<string,BBlock> */
    private array $labelBlocks;

    public function __construct()
    {
        $this->cfg = new CFG();
        $this->finallyStack = [];
        $this->breakStack = [];
        $this->continueStack = [];
        $this->labelBlocks = [];
    }

    /** @param Stmt[] $stmts */
    public function build(array $stmts): CFG
    {
        $entry = $this->cfg->getEntry();
        $successor = $this->cfg->getExit();
        $block = $this->createBlock($successor);
        $entry->setSuccessor($block);

        $this->visitList($block, $successor, $stmts);

        return $this->cfg;
    }

    public function buildFunction(FunctionLike $node): CFG
    {
        $entry = $this->cfg->getEntry();
        $successor = $this->cfg->getExit();
        $block = $this->createBlock($successor);
        $entry->setSuccessor($block);

        $block = $this->visitList($block, $successor, $node->getParams());
        if ($node instanceof Closure) {
            $block = $this->visitList($block, $successor, $node->uses);
        }

        $stmts = $node->getStmts();
        if ($stmts !== null) {
            $this->visitList($block, $successor, $stmts);
        }

        return $this->cfg;
    }

    private function createBlock(BBlock $successor): BBlock
    {
        $block = $this->cfg->createBlock();
        $block->setSuccessor($successor);

        return $block;
    }

    private function createBlockWithoutSuccessor(): BBlock
    {
        return $this->cfg->createBlock();
    }

    /** @param Node[] $nodes */
    private function visitList(BBlock $block, BBlock $successor, array $nodes): BBlock
    {
        foreach ($nodes as $node) {
            $block = $this->visit($block, $successor, $node);
        }

        return $block;
    }

    private function visit(BBlock $block, BBlock $successor, Node $node): BBlock
    {
        if ($node instanceof If_) {
            return $this->visitIf($block, $successor, $node);
        }
        if ($node instanceof For_) {
            return $this->visitFor($block, $successor, $node);
        }
        if ($node instanceof Foreach_) {
            return $this->visitForeach($block, $successor, $node);
        }
        if ($node instanceof While_) {
            return $this->visitWhile($block, $successor, $node);
        }
        if ($node instanceof Switch_) {
            return $this->visitSwitch($block, $successor, $node);
        }
        if ($node instanceof Match_) {
            return $this->visitMatch($block, $successor, $node);
        }
        if ($node instanceof Do_) {
            return $this->visitDo($block, $successor, $node);
        }
        if ($node instanceof BooleanAnd || $node instanceof BooleanOr || $node instanceof LogicalAnd || $node instanceof LogicalOr) {
            return $this->visitBooleanOp($block, $successor, $node);
        }
        if ($node instanceof Expression) {
            return $this->visitExpression($block, $successor, $node);
        }
        if ($node instanceof Ternary) {
            return $this->visitTernary($block, $successor, $node);
        }
        if ($node instanceof TryCatch) {
            return $this->visitTryCatch($block, $successor, $node);
        }
        if ($node instanceof Catch_) {
            return $this->visitCatch($block, $successor, $node);
        }
        if ($node instanceof Return_) {
            return $this->visitReturn($block, $successor, $node);
        }
        if ($node instanceof Continue_) {
            return $this->visitContinue($block, $successor, $node);
        }
        if ($node instanceof Break_) {
            return $this->visitBreak($block, $successor, $node);
        }
        if ($node instanceof Goto_) {
            return $this->visitGoto($block, $successor, $node);
        }
        if ($node instanceof Label) {
            return $this->visitLabel($block, $successor, $node);
        }
        if ($node instanceof FuncCall) {
            return $this->visitFuncCall($block, $successor, $node);
        }
        if ($node instanceof MethodCall) {
            return $this->visitMethodCall($block, $successor, $node);
        }
        if ($node instanceof New_) {
            return $this->visitNew($block, $successor, $node);
        }
        if ($node instanceof Closure) {
            return $this->visitClosure($block, $successor, $node);
        }
        if ($node instanceof Function_) {
            return $this->visitFunction($block, $successor, $node);
        }
        if ($node instanceof Class_) {
            return $this->visitClass($block, $successor, $node);
        }

        if ($node instanceof Expr || $node instanceof Stmt || $node instanceof Param) {
            foreach ($node->getSubNodeNames() as $name) {
                $block = $this->visitSubNode($block, $successor, $node->$name);
            }
            $block->addStmt($node);
        }

        return $block;
    }

    private function visitSubNode(BBlock $block, BBlock $successor, mixed $node): BBlock
    {
        if ($node instanceof Expr || $node instanceof Stmt) {
            return $this->visit($block, $successor, $node);
        }

        if (is_array($node)) {
            foreach ($node as $subNode) {
                $block = $this->visitSubNode($block, $successor, $subNode);
            }

            return $block;
        }

        return $block;
    }

    private function visitIf(BBlock $block, BBlock $successor, If_ $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);
        $trueBlock = $this->createBlock($nextBlock);
        $falseBlock = $this->createBlock($nextBlock);

        $this->visitCondition($block, $trueBlock, $falseBlock, $node->cond, $node);
        $this->visitList($trueBlock, $nextBlock, $node->stmts);

        foreach ($node->elseifs as $elseif) {
            $elseTrueBlock = $this->createBlock($nextBlock);
            $elseFalseBlock = $this->createBlock($nextBlock);
            $this->visitCondition($falseBlock, $elseTrueBlock, $elseFalseBlock, $elseif->cond, $elseif);
            $this->visitList($elseTrueBlock, $nextBlock, $elseif->stmts);
            $falseBlock = $elseFalseBlock;
        }

        if ($node->else !== null) {
            $this->visitList($falseBlock, $nextBlock, $node->else->stmts);
        }

        return $nextBlock;
    }

    private function visitFor(BBlock $block, BBlock $successor, For_ $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);
        $tailBlock = $this->createBlockWithoutSuccessor();

        $this->breakStack[] = $nextBlock;
        $this->continueStack[] = $tailBlock;

        $block = $this->visitList($block, $successor, $node->init);

        $this->visitList($tailBlock, $successor, $node->loop);

        $bodyBlock = $this->createBlock($tailBlock);
        $this->visitList($bodyBlock, $tailBlock, $node->stmts);

        $startBlock = $this->createBlock($bodyBlock);
        $block->setSuccessor($startBlock);
        $tailBlock->setSuccessor($startBlock);

        $block = $this->visitList($startBlock, $successor, array_slice($node->cond, 0, -1));
        $lastCond = end($node->cond);
        if ($lastCond !== false) {
            $this->visitCondition($block, $bodyBlock, $nextBlock, $lastCond, $node);
        }

        array_pop($this->breakStack);
        array_pop($this->continueStack);

        return $nextBlock;
    }

    private function visitForeach(BBlock $block, BBlock $successor, Foreach_ $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);
        $headBlock = $this->createBlockWithoutSuccessor();
        $bodyBlock = $this->createBlock($headBlock);

        $this->breakStack[] = $nextBlock;
        $this->continueStack[] = $bodyBlock;

        $block = $this->visit($block, $headBlock, $node->expr);
        $block->setSuccessor($headBlock);

        $headBlock->setTerminator($node, $bodyBlock, $nextBlock);

        $this->visitList($bodyBlock, $headBlock, $node->stmts);

        array_pop($this->breakStack);
        array_pop($this->continueStack);

        return $nextBlock;
    }

    private function visitWhile(BBlock $block, BBlock $successor, While_ $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);
        $headBlock = $this->createBlockWithoutSuccessor();
        $bodyBlock = $this->createBlock($headBlock);
        $block->setSuccessor($headBlock);

        $this->breakStack[] = $nextBlock;
        $this->continueStack[] = $headBlock;

        $this->visitCondition($headBlock, $bodyBlock, $nextBlock, $node->cond, $node);
        $this->visitList($bodyBlock, $nextBlock, $node->stmts);

        array_pop($this->breakStack);
        array_pop($this->continueStack);

        return $nextBlock;
    }

    private function visitDo(BBlock $block, BBlock $successor, Do_ $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);
        $condBlock = $this->createBlockWithoutSuccessor();
        $bodyBlock = $this->createBlock($condBlock);
        $block->setSuccessor($bodyBlock);

        $this->breakStack[] = $nextBlock;
        $this->continueStack[] = $condBlock;

        $this->visitList($bodyBlock, $condBlock, $node->stmts);
        $this->visitCondition($condBlock, $bodyBlock, $nextBlock, $node->cond, $node);

        array_pop($this->breakStack);
        array_pop($this->continueStack);

        return $nextBlock;
    }

    private function visitSwitch(BBlock $block, BBlock $successor, Switch_ $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);

        $block = $this->visit($block, $successor, $node->cond);

        $this->breakStack[] = $nextBlock;
        $this->continueStack[] = $nextBlock;

        $fallthrough = $nextBlock;
        $nextCase = $nextBlock;
        foreach (array_reverse($node->cases) as $case) {
            $bodyBlock = $this->createBlock($fallthrough);
            $this->visitList($bodyBlock, $fallthrough, $case->stmts);
            $fallthrough = $bodyBlock;

            if ($case->cond !== null) {
                $condBlock = $this->createBlockWithoutSuccessor();
                $this->visitCondition($condBlock, $bodyBlock, $nextCase, $case->cond, $case);
                $nextCase = $condBlock;
            }
        }

        $block->setTerminator($node, $nextCase);

        array_pop($this->breakStack);
        array_pop($this->continueStack);

        return $nextBlock;
    }

    private function visitMatch(BBlock $block, BBlock $successor, Match_ $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);

        $block = $this->visit($block, $successor, $node->cond);

        $nextArm = $nextBlock;
        foreach (array_reverse($node->arms) as $arm) {
            $bodyBlock = $this->createBlock($nextBlock);
            $this->visit($bodyBlock, $nextBlock, $arm->body);

            if ($arm->conds !== null) {
                foreach (array_reverse($arm->conds) as $cond) {
                    $condBlock = $this->createBlockWithoutSuccessor();
                    $this->visitCondition($condBlock, $bodyBlock, $nextArm, $cond, $arm);
                    $nextArm = $condBlock;
                }
            } else {
                $nextArm = $bodyBlock;
            }
        }

        $block->setTerminator($node, $nextArm);

        return $nextBlock;
    }

    // TODO: handle finally blocks
    public function visitContinue(BBlock $block, BBlock $successor, Continue_ $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);

        if ($node->num !== null) {
            $num = $node->num;
            $block = $this->visit($block, $successor, $num);
            if (!$num instanceof LNumber) {
                $block->setTerminator($node, ...$this->continueStack);
                return $nextBlock;
            }

            $continueBlock = $this->continueStack[count($this->continueStack)-$num->value] ?? null;
            if ($continueBlock !== null) {
                $block->setTerminator($node, $continueBlock);
                return $nextBlock;
            }

            $block->setTerminator($node, ...$this->continueStack);
            return $nextBlock;
        }

        $continueBlock = end($this->continueStack);
        if ($continueBlock !== false) {
            $block->setTerminator($node, $continueBlock);
        }

        return $nextBlock;
    }

    // TODO: handle finally blocks
    public function visitBreak(BBlock $block, BBlock $successor, Break_ $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);

        if ($node->num !== null) {
            $num = $node->num;
            $block = $this->visit($block, $successor, $num);
            if (!$num instanceof LNumber) {
                $block->setTerminator($node, ...$this->breakStack);
                return $nextBlock;
            }

            $breakBlock = $this->breakStack[count($this->breakStack)-$num->value] ?? null;
            if ($breakBlock !== null) {
                $block->setTerminator($node, $breakBlock);
                return $nextBlock;
            }

            $block->setTerminator($node, ...$this->breakStack);
            return $nextBlock;
        }

        $breakBlock = end($this->breakStack);
        if ($breakBlock !== false) {
            $block->setTerminator($node, $breakBlock);
        }

        return $nextBlock;
    }

    // TODO: handle finally blocks
    public function visitGoto(BBlock $block, BBlock $successor, Goto_ $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);

        $labelBlock = $this->labelBlocks[$node->name->name] ?? null;
        if ($labelBlock === null) {
            $labelBlock = $this->createBlockWithoutSuccessor();
            $this->labelBlocks[$node->name->name] = $labelBlock;
        }

        $block->setTerminator($node, $labelBlock);

        return $nextBlock;
    }

    public function visitLabel(BBlock $block, BBlock $successor, Label $node): BBlock
    {
        $labelBlock = $this->labelBlocks[$node->name->name] ?? null;
        if ($labelBlock === null) {
            $labelBlock = $this->createBlockWithoutSuccessor();
            $this->labelBlocks[$node->name->name] = $labelBlock;
        }

        $block->setSuccessor($labelBlock);

        $labelBlock->addStmt($node);
        $labelBlock->setSuccessor($successor);

        return $labelBlock;
    }

    public function visitFuncCall(BBlock $block, BBlock $successor, FuncCall $node): BBlock
    {
        if ($node->name instanceof Expr) {
            $block = $this->visit($block, $successor, $node->name);
        }

        foreach ($node->args as $arg) {
            if ($arg instanceof Arg) {
                $block = $this->visit($block, $successor, $arg->value);
            }
        }

        $block->addStmt($node);

        return $block;
    }

    public function visitMethodCall(BBlock $block, BBlock $successor, MethodCall $node): BBlock
    {
        $block = $this->visit($block, $successor, $node->var);
        if ($node->name instanceof Expr) {
            $block = $this->visit($block, $successor, $node->name);
        }

        foreach ($node->args as $arg) {
            if ($arg instanceof Arg) {
                $block = $this->visit($block, $successor, $arg->value);
            }
        }

        $block->addStmt($node);

        return $block;
    }

    public function visitNew(BBlock $block, BBlock $successor, New_ $node): BBlock
    {
        if ($node->class instanceof Expr) {
            $block = $this->visit($block, $successor, $node->class);
        }

        foreach ($node->args as $arg) {
            if ($arg instanceof Arg) {
                $block = $this->visit($block, $successor, $arg->value);
            }
        }

        $block->addStmt($node);

        return $block;
    }

    public function visitClosure(BBlock $block, BBlock $successor, Closure $node): BBlock
    {
        foreach ($node->uses as $use) {
            $block = $this->visit($block, $successor, $use->var);
        }

        $block->addStmt($node);

        return $block;
    }

    public function visitFunction(BBlock $block, BBlock $successor, Function_ $node): BBlock
    {
        $block->addStmt($node);

        return $block;
    }

    public function visitClass(BBlock $block, BBlock $successor, Class_ $node): BBlock
    {
        $block->addStmt($node);

        return $block;
    }

    public function visitBooleanOp(BBlock $block, BBlock $successor, Expr|Stmt $node): BBlock
    {
        $confluenceBlock = $this->createBlock($successor);
        $this->visitShortcuttingOp($block, $confluenceBlock, $confluenceBlock, $node, null);
        $block->addStmt($node);

        return $confluenceBlock;
    }

    public function visitExpression(BBlock $block, BBlock $successor, Expression $node): BBlock
    {
        return $this->visit($block, $successor, $node->expr);
    }

    private function visitTernary(BBlock $block, BBlock $successor, Ternary $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);
        $trueBlock = $node->if !== null ? $this->createBlock($nextBlock) : $nextBlock;
        $falseBlock = $this->createBlock($nextBlock);

        $this->visitCondition($block, $trueBlock, $falseBlock, $node->cond, $node);

        if ($node->if !== null) {
            $this->visit($trueBlock, $nextBlock, $node->if);
        }
        $this->visit($falseBlock, $nextBlock, $node->else);

        $nextBlock->addStmt($node);

        return $nextBlock;
    }

    // TODO: Any statement could jump to all catch blocks. Should we model that
    // in the CFG, or should we have special handling of try/catch in analyses ?
    private function visitTryCatch(BBlock $block, BBlock $successor, TryCatch $node): BBlock
    {
        $nextBlock = $this->createBlock($successor);
        $finallyBlock = null;

        if ($node->finally !== null) {
            $finallyBlock = $this->createBlock($nextBlock);
            $this->visitList($finallyBlock, $nextBlock, $node->finally->stmts);
            $nextBlock = $finallyBlock;
            $this->finallyStack[] = $finallyBlock;
            $this->continueStack[] = $finallyBlock;
            $this->breakStack[] = $finallyBlock;
        }

        $tryBlock = $this->createBlock($nextBlock);
        $this->visitList($tryBlock, $nextBlock, $node->stmts);

        $catchBlocks = [];
        foreach ($node->catches as $catch) {
            $catchBlock = $this->createBlock($successor);
            $this->visit($catchBlock, $nextBlock, $catch);
        }

        if ($finallyBlock !== null) {
            array_pop($this->finallyStack);
            array_pop($this->continueStack);
            array_pop($this->breakStack);
        }

        $block->setTerminator($node, $tryBlock, ...$catchBlocks);

        return $nextBlock;
    }

    private function visitCatch(BBlock $block, BBlock $successor, Catch_ $node): BBlock
    {
        if ($node->var !== null) {
            $block = $this->visit($block, $successor, $node->var);
        }

        $nextBlock = $this->createBlock($successor);
        $catchBlock = $this->createBlock($successor);

        $block->setTerminator($node, $catchBlock, $nextBlock);

        return $this->visitList($catchBlock, $nextBlock, $node->stmts);
    }

    private function visitReturn(BBlock $block, BBlock $successor, Return_ $node): BBlock
    {
        if ($node->expr !== null) {
            $block = $this->visit($block, $successor, $node->expr);
        }

        $finallyBlock = end($this->finallyStack);
        $block->setTerminator($node, $finallyBlock !== false ? $finallyBlock : $this->cfg->getExit());

        return $this->createBlock($successor);
    }

    private function visitCondition(BBlock $block, BBlock $trueBlock, BBlock $falseBlock, Expr|Stmt $node, Node $parent): void
    {
        $this->visitShortcuttingOp($block, $trueBlock, $falseBlock, $node, $parent);
    }

    private function visitShortcuttingOp(BBlock $block, BBlock $trueBlock, BBlock $falseBlock, Expr|Stmt $node, ?Node $parent): void
    {
        if ($node instanceof BooleanAnd || $node instanceof LogicalAnd) {
            $rightBlock = $this->createBlock($trueBlock);
            $this->visitShortcuttingOp($block, $rightBlock, $falseBlock, $node->left, $node);
            $this->visitShortcuttingOp($rightBlock, $trueBlock, $falseBlock, $node->right, $parent);
            return;
        }

        if ($node instanceof BooleanOr || $node instanceof LogicalOr) {
            $rightBlock = $this->createBlock($trueBlock);
            $this->visitShortcuttingOp($block, $trueBlock, $rightBlock, $node->left, $node);
            $this->visitShortcuttingOp($rightBlock, $trueBlock, $falseBlock, $node->right, $parent);
            return;
        }

        $block = $this->visit($block, $block, $node);
        if ($parent !== null) {
            $block->setTerminator($parent, $trueBlock, $falseBlock);
        }
    }

}
