<?php

namespace PhpSema\Utils;

use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\Exit_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Include_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Print_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Echo_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\InlineHTML;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class SideEffectVisitor
{
    public function hasSideEffect(Node $stmt): bool
    {
        return match (true) {
            $stmt instanceof Echo_ => true,
            $stmt instanceof Return_ => true,
            $stmt instanceof Assign => match (true) {
                $stmt->var instanceof Variable => false,
                $stmt->var instanceof PropertyFetch => true,
                $stmt->var instanceof ArrayDimFetch => true,
                default => false,
            },
            $stmt instanceof InlineHTML => true,
            $stmt instanceof Print_ => true,
            $stmt instanceof Exit_ => true,
            $stmt instanceof FuncCall => true,
            $stmt instanceof MethodCall => true,
            $stmt instanceof New_ => true,
            $stmt instanceof PropertyFetch => true,
            $stmt instanceof ArrayDimFetch => true,
            $stmt instanceof Include_ => true,

            $stmt instanceof Use_ => true,
            $stmt instanceof UseUse => true,
            $stmt instanceof Namespace_ => true,

            $stmt instanceof Class_ => true,
            $stmt instanceof Function_ => true,
            $stmt instanceof ClassMethod => true,
            $stmt instanceof Property => true,

            // TODO
            default => false,
        };
    }
}
