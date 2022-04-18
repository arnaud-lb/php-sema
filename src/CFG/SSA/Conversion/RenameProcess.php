<?php

namespace PhpSema\CFG\SSA\Conversion;

use PhpParser\Node;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\Variable;
use PhpSema\CFG\BBlock;
use PhpSema\CFG\CFG;
use PhpSema\CFG\NodeUtils;
use PhpSema\CFG\SSA\Node\Phi;
use PhpSema\CFG\SSA\SSASymTable;
use PhpSema\CFG\SymTable;
use RuntimeException;
use SplObjectStorage;

/**
 * Renaming step of an SSA conversion
 *
 * Implements "Efficiently Computing Static Single Assignment Form and the Control Dependence Graph", Cytron, Ron & Ferrante, Jeanne & Rosen, Barry & Wegman, Mark & Zadeck, Kenneth. (1991)
 *
 * @see SSAConversion
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class RenameProcess
{
    /** @var array<string,int[]> */
    private array $stacks;

    /** @var array<string,int> */
    private array $stackPos;

    private SSASymTable $ssaSymTable;

    /** @var SplObjectStorage<Node,Node> */
    private SplObjectStorage $defs;

    /** @param array<int,BBlock[]> $domTree */
    public function __construct(
        private CFG      $cfg,
        private SymTable $symTable,
        private array    $domTree,
    )
    {
        $this->stacks = [];
        $this->stackPos = [];
        $this->ssaSymTable = new SSASymTable();
        $this->defs = new SplObjectStorage();
    }

    public function rename(): void
    {
        foreach ($this->symTable->getIdToNameMap() as $varName) {
            $this->addImplicitVar($varName);
        }
        $this->visitBlock($this->cfg->getEntry());
    }

    public function getSymbolTable(): SSASymTable
    {
        return $this->ssaSymTable;
    }

    private function visitBlock(BBlock $bb): void
    {
        $stackPos = $this->stackPos;
        foreach ($bb->getStmts() as $stmt) {
            foreach (NodeUtils::definedVariables($stmt) as $node) {
                $this->defs->attach($node, $stmt);
            }
        }
        foreach ($bb->getStmts() as $stmt) {
            $this->visitStmt($stmt);
        }
        foreach ($bb->getSuccessors() as $y) {
            $j = null;
            foreach ($y->getStmts() as $stmt) {
                if (!$stmt instanceof Phi) {
                    if (!$stmt instanceof Variable) {
                        break;
                    }
                    continue;
                }
                if ($j === null) {
                    $j = $this->whichPred($y, $bb);
                }
                assert(is_string($stmt->var->name));
                $id = $this->stackTop($stmt->var->name);
                $stmt->sources[$j]->setAttribute('ssa_var', $id);
            }
        }
        // FIXME: we nay not need this if we ignore unreachable blocks
        if (isset($this->domTree[$bb->getId()])) {
            foreach ($this->domTree[$bb->getId()] as $child) {
                $this->visitBlock($child);
            }
        }
        $this->stackPos = $stackPos;
    }

    private function whichPred(BBlock $x, BBlock $y): int
    {
        foreach ($x->getPredecessors() as $j => $p) {
            if ($p === $y) {
                return $j;
            }
        }

        throw new RuntimeException('Should not happen');
    }

    private function visitStmt(Node $stmt): void
    {
        if (!$stmt instanceof Variable) {
            return;
        }

        assert(is_string($stmt->name));
        $def = $this->defs[$stmt] ?? null;

        if ($def !== null) {
            if ($def instanceof PreInc || $def instanceof PreDec || $def instanceof PostInc || $def instanceof PostDec) {
                $id = $this->stackTop($stmt->name);
                $stmt->setAttribute('ssa_var', $id);
                $this->addVar($stmt->name, $stmt);

                return;
            }

            $id = $this->addVar($stmt->name, $stmt);
            $stmt->setAttribute('ssa_var', $id);

            return;
        }

        $id = $this->stackTop($stmt->name);
        $stmt->setAttribute('ssa_var', $id);
    }

    private function stackTop(string $varName): int
    {
        if (!isset($this->stackPos[$varName])) {
            throw new \Exception(sprintf('Undefined variable "%s"', $varName));
        }
        return $this->stacks[$varName][$this->stackPos[$varName]] ?? 0;
    }

    private function stackPush(string $varName, int $id): void
    {
        $stackPos = ($this->stackPos[$varName] ?? -1) + 1;
        $this->stackPos[$varName] = $stackPos;
        $this->stacks[$varName][$stackPos] = $id;
    }

    private function addVar(string $varName, Node $def): int
    {
        $id = $this->ssaSymTable->addDef($def);
        $this->stackPush($varName, $id);

        return $id;
    }

    private function addImplicitVar(string $varName): void
    {
        $id = $this->ssaSymTable->addImplicitDef();
        $this->stackPush($varName, $id);
    }

}
