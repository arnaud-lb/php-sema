<?php

namespace PhpSema\Utils;

use PhpParser\Node\FunctionLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class FunctionUtils
{
    static function getName(FunctionLike $function): ?string
    {
        return match (true) {
            $function instanceof Function_ => $function->name->name,
            $function instanceof ClassMethod => $function->name->name,
            default => null,
        };
    }
}
