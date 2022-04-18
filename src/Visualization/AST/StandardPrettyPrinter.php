<?php

namespace PhpSema\Visualization\AST;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\PrettyPrinter\Standard;
use PhpSema\CFG\BBlock;
use PhpSema\CFG\SSA\Node\Phi;
use SplObjectStorage;

/**
 * A standard pretty printer with support for our custom nodes
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class StandardPrettyPrinter extends Standard
{
    /** @var SplObjectStorage<Node,array{BBlock,int}> */
    private SplObjectStorage $seen;

    /** @param SplObjectStorage<Node,array{BBlock,int}>|null $seen */
    public function __construct(SplObjectStorage $seen = null)
    {
        if ($seen === null) {
            /** @var SplObjectStorage<Node,array{BBlock,int}> */
            $seen = new SplObjectStorage();
        }
        $this->seen = $seen;

        parent::__construct();
    }

    protected function p(Node $node, $parentFormatPreserved = false): string
    {
        $seen = $this->seen[$node] ?? null;
        if ($seen !== null) {
            return sprintf('[B%d.%d]', $seen[0]->getId(), $seen[1]);
        }

        $p = parent::p($node, $parentFormatPreserved);

        if (mb_strlen($p) > 100) {
            $p = mb_substr($p, 0, 100) . '...';
        }

        return $p;
    }

    public function pStmt_Phi(Phi $node): string
    {
        return $this->p($node->var) . ' = Φ(' . implode(', ', array_map($this->p(...), $node->sources)) . ');';
    }

    public function pExpr_Variable(Variable $node): string
    {
        if ($node->name instanceof Expr) {
            return '${' . $this->p($node->name) . '}';
        } else {
            if ($node->name[0] === '.') {
                return $node->name;
            }
            $var = $node->getAttribute('ssa_var');
            if ($var !== null) {
                assert(is_int($var));
                $subscript = $this->toSubscript($var);
            } else {
                $subscript = '';
            }
            return '$' . $node->name . $subscript;
        }
    }

    public function pExpr_Closure(Closure $node): string
    {
        return ($node->static ? 'static ' : '')
            . 'function ' . ($node->byRef ? '&' : '')
            . '(...)'
            . (!empty($node->uses) ? ' use(' . $this->pCommaSeparated($node->uses) . ')' : '')
            . (null !== $node->returnType ? ' : ' . $this->p($node->returnType) : '')
            . ' { ... }';
    }

    public function pStmt_ClassMethod(ClassMethod $node): string {
        return $this->pModifiers($node->flags)
            . 'function ' . ($node->byRef ? '&' : '') . $node->name
            . '(...)'
            . (null !== $node->returnType ? ' : ' . $this->p($node->returnType) : '')
            . (null !== $node->stmts
                ? $this->nl . '{ ... }'
                : ';');
    }

    public function pStmt_Function(Function_ $node): string {
        return 'function ' . ($node->byRef ? '&' : '') . $node->name
            . '(...)'
            . (null !== $node->returnType ? ' : ' . $this->p($node->returnType) : '')
            . $this->nl . '{ ... }';
    }
    public function pStmt_Switch(Switch_ $node): string
    {
        return sprintf(
            'switch (%s)',
            $this->p($node->cond),
        );
    }

    /** @param string $afterClassToken */
    protected function pClassCommon(Class_ $node, $afterClassToken): string {
        return $this->pModifiers($node->flags)
            . 'class' . $afterClassToken
            . (null !== $node->extends ? ' extends ' . $this->p($node->extends) : '')
            . (!empty($node->implements) ? ' implements ' . $this->pCommaSeparated($node->implements) : '')
            . $this->nl . '{ ... }';
    }

    private function toSubscript(int $s): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['₀', '₁', '₂', '₃', '₄', '₅', '₆', '₇', '₈', '₉'],
            (string)$s,
        );
    }
}
