<?php

namespace PhpSema\CFG\SSA;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class SSASymTable
{
    /** @var array<int,?Node> */
    private array $idToDef;

    public function __construct()
    {
        $this->idToDef = [];
    }

    /**
     * Add a variable and return its unique id
     *
     * @param Node $def The definition node (e.g. an Assign node)
     */
    public function addDef(Node $def): int
    {
        $id = count($this->idToDef);
        $this->idToDef[] = $def;

        return $id;
    }

    public function addImplicitDef(): int
    {
        $id = count($this->idToDef);
        $this->idToDef[] = null;

        return $id;
    }

    /**
     * Returns the definition node of a variable (e.g. an Assign node) or null if the variable is undefined
     */
    public function getDef(int|Variable $var): ?Node
    {
        if ($var instanceof Variable) {
            $var = SSAUtils::varId($var);
        }

        if (!array_key_exists($var, $this->idToDef)) {
            throw new \Exception(sprintf(
                'Invalid variable %d',
                $var,
            ));
        }

        return $this->idToDef[$var];
    }
}
