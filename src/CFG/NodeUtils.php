<?php

namespace PhpSema\CFG;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignRef;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ClosureUse;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\Node\Stmt\Static_;
use PhpParser\Node\Stmt\Unset_;
use PhpSema\CFG\SSA\Node\Phi;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class NodeUtils
{
    /** @return iterable<Node> */
    public static function childNodes(Node $node): iterable
    {
        yield from match(true) {
            $node instanceof Class_ => [],
            $node instanceof Function_ => [],
            $node instanceof Closure => $node->uses,
            default => self::genericChildNodes($node),
        };
    }

    /** @return iterable<Node> */
    private static function genericChildNodes(Node $node): iterable
    {
        foreach ($node->getSubNodeNames() as $childName) {
            $childNode = $node->$childName;
            if ($childNode instanceof Stmt || $childNode instanceof Expr) {
                yield $childNode;
            }
            if (is_array($childNode)) {
                foreach ($childNode as $elem) {
                    if ($elem instanceof Stmt || $elem instanceof Expr) {
                        yield $elem;
                    }
                    if ($elem instanceof Arg) {
                        yield $elem->value;
                    }
                }
            }
        }
    }

    /** @return iterable<Node> */
    public static function deepChildNodes(Node $node): iterable
    {
        foreach (self::childNodes($node) as $childNode) {

            yield $childNode;
            yield from self::deepChildNodes($childNode);
        }
    }

    /** @return iterable<Variable> */
    public static function definedVariables(Node $stmt): iterable
    {
        return match (true) {
            $stmt instanceof Assign => self::lhsToVariables($stmt->var),
            $stmt instanceof AssignRef => self::lhsToVariables($stmt->var),
            $stmt instanceof Phi => self::lhsToVariables($stmt->var),
            $stmt instanceof PreInc && $stmt->var instanceof Variable => [$stmt->var],
            $stmt instanceof PreDec && $stmt->var instanceof Variable => [$stmt->var],
            $stmt instanceof PostInc && $stmt->var instanceof Variable => [$stmt->var],
            $stmt instanceof PostDec && $stmt->var instanceof Variable => [$stmt->var],
            $stmt instanceof Param && $stmt->var instanceof Variable => [$stmt->var],
            $stmt instanceof ClosureUse => [$stmt->var],
            $stmt instanceof Static_ => array_map(
                fn ($staticVar) => $staticVar->var,
                $stmt->vars,
            ),
            $stmt instanceof Global_ => array_filter(
                $stmt->vars,
                fn ($expr) => $expr instanceof Variable,
            ),
            $stmt instanceof Foreach_ => [
                ...($stmt->keyVar !== null ? self::lhsToVariables($stmt->keyVar) : []),
                ...self::lhsToVariables($stmt->valueVar),
            ],
            $stmt instanceof Unset_ => array_filter(
                $stmt->vars,
                fn ($expr) => $expr instanceof Variable,
            ),
            default => [],
        };
    }

    /**
     * Finds the variables that are defined when the $node is in RHS of an expression
     *
     * @return iterable<Variable>
     */
    public static function lhsToVariables(Node $node): iterable
    {
        return match (true) {
            $node instanceof Variable => [$node],
            $node instanceof PropertyFetch => [],
            $node instanceof ArrayDimFetch => [],
            $node instanceof Array_ => self::lhsArrayToVariables($node),
            $node instanceof StaticPropertyFetch => [],
            default => throw new \Exception(sprintf('TODO: %s', get_class($node))),
        };
    }

    /** @return iterable<Variable> */
    private static function lhsArrayToVariables(Array_ $node): iterable
    {
        foreach ($node->items as $item) {
            if ($item === null) {
                continue;
            }
            yield from self::lhsToVariables($item->value);
        }
    }
}
