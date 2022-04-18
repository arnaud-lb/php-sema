<?php

namespace PhpSema\CFG\SSA\Node;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class Phi extends Stmt
{
    public Variable $var;

    /** @var Variable[] */
    public array $sources;

    /** @param array<Variable> $sources */
    public function __construct(Variable $var, array $sources)
    {
        parent::__construct();
        $this->var = $var;
        $this->sources = $sources;
    }

    /** @return string[] */
    public function getSubNodeNames(): array
    {
        return ['var', 'sources'];
    }

    public function getType(): string
    {
        return 'Stmt_Phi';
    }
}
