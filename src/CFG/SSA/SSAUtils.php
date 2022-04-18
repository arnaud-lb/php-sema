<?php

namespace PhpSema\CFG\SSA;

use PhpParser\Node\Expr\Variable;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class SSAUtils
{
    public static function varId(Variable $var): int
    {
        $id = $var->getAttribute('ssa_var');
        if ($id === null) {
            throw new \Exception('Variable has no SSA id');
        }
        if (!is_int($id)) {
            throw new \Exception('SSA id has invalid type');
        }
        return $id;
    }
}
