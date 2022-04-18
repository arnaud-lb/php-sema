<?php

namespace PhpSema\Visualization\AST;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Stmt\Case_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\PrettyPrinterAbstract;
use PhpSema\CFG\BBlock;
use SplObjectStorage;

/**
 * A pretty printer for block terminators
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class TerminatorPrettyPrinter extends Standard
{
    /** @param SplObjectStorage<Node,array{BBlock,int}> $seen */
    public function __construct(
        private SplObjectStorage $seen,
        private Standard         $prettyPrinter,
    ) {
        parent::__construct();
    }

    protected function p(Node $node, $parentFormatPreserved = false): string
    {
        $seen = $this->seen[$node] ?? null;
        if ($seen !== null) {
            return sprintf('[B%d.%d]', $seen[0]->getId(), $seen[1]);
        }

        return PrettyPrinterAbstract::p($node, $parentFormatPreserved);
    }

    public function pStmt_If(If_ $node): string
    {
        return sprintf('if (%s)', $this->prettyPrinter->p($node->cond));
    }

    public function pStmt_For(For_ $node): string
    {
        $cond = end($node->cond);

        return sprintf(
            'for (...; %s; ...)',
            $cond !== false ? $this->prettyPrinter->p($cond) : '',
        );
    }

    public function pStmt_Foreach(Foreach_ $node): string
    {
        return sprintf(
            'foreach (%s as ...)',
            $this->prettyPrinter->p($node->expr),
        );
    }

    public function pStmt_While(While_ $node): string
    {
        return sprintf(
            'while (%s)',
            $this->prettyPrinter->p($node->cond),
        );
    }

    public function pStmt_Switch(Switch_ $node): string
    {
        return sprintf(
            'switch (%s)',
            $this->prettyPrinter->p($node->cond),
        );
    }

    public function pStmt_Case(Case_ $node): string
    {
        if ($node->cond === null) {
            return 'default:';
        }

        return sprintf(
            'case %s:',
            $this->prettyPrinter->p($node->cond),
        );
    }

    public function pStmt_Do(Do_ $node): string
    {
        return sprintf(
            'do ... while (%s)',
            $this->prettyPrinter->p($node->cond),
        );
    }

    public function pExpr_Match(Match_ $node): string
    {
        return sprintf(
            'match (%s)',
            $this->prettyPrinter->p($node->cond),
        );
    }

    public function pMatchArm(MatchArm $node): string
    {
        return sprintf(
            'match (...) { %s => ... }',
            '???', // TODO: print cond
        );
    }

    public function pExpr_BinaryOp_BooleanAnd(BooleanAnd $node): string
    {
        return sprintf(
            '%s && ...',
            $this->prettyPrinter->p($node->left),
        );
    }

    public function pExpr_BinaryOp_BooleanOr(BooleanOr $node): string
    {
        return sprintf(
            '%s || ...',
            $this->prettyPrinter->p($node->left),
        );
    }

    public function pExpr_BinaryOp_LogicAnd(LogicalAnd $node): string
    {
        return sprintf(
            '%s and ...',
            $this->prettyPrinter->p($node->left),
        );
    }

    public function pExpr_BinaryOp_LogicOr(LogicalOr $node): string
    {
        return sprintf(
            '%s or ...',
            $this->prettyPrinter->p($node->left),
        );
    }

    public function pExpr_Ternary(Ternary $node): string
    {
        if ($node->if !== null) {
            return sprintf(
                '%s ? ... : ...',
                $this->prettyPrinter->p($node->cond),
            );
        } else {
            return sprintf(
                '%s ?: ...',
                $this->prettyPrinter->p($node->cond),
            );
        }
    }

    public function pStmt_TryCatch(TryCatch $node): string
    {
        return 'try (...)';
    }

    public function pStmt_Catch(Catch_ $node): string
    {
        return 'catch (...)';
    }
}
