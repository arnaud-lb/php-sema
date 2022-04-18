<?php

namespace PhpSema\CFG\Analysis\DataFlow;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpSema\CFG\BBlock;
use PhpSema\CFG\CFG;
use PhpSema\CFG\NodeUtils;
use PhpSema\CFG\SymTable;
use PhpSema\Utils\BitSet;
use SplObjectStorage;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class DefinitionGenKills
{
    /**
     * All definitions
     *
     * This is a list of Variable nodes found in an LHS position
     *
     * @var Variable[]
     */
    private array $defs;

    private BitSet $entryDefs;

    /**
     * Node -> defId
     *
     * @var SplObjectStorage<Node,int>
     */
    private SplObjectStorage $stmtDefs;

    /**
     * varId -> BitSet<defId>
     *
     * @var array<int,BitSet>
     */
    private array $varDefs;

    /** @var SplObjectStorage<Node,BitSet> */
    private SplObjectStorage $stmtGens;

    /** @var SplObjectStorage<Node,BitSet> */
    private SplObjectStorage $stmtKills;

    /** @var array<int,BitSet> */
    private array $blockGens;

    /** @var array<int,BitSet> */
    private array $blockKills;

    public function __construct(
        private SymTable $symTable,
        CFG $cfg,
        bool $addEntryDefinitions,
    ) {
        $this->defs = [];

        $this->makeDefinitions($cfg, $addEntryDefinitions);
        $this->makeGenKills($cfg);
    }

    public static function fromCFG(CFG $cfg): self
    {
        return new self(SymTable::fromCFG($cfg), $cfg, false);
    }

    public function getDefinition(int $id): Variable
    {
        return $this->defs[$id];
    }

    public function getEntryDefinitions(): BitSet
    {
        return $this->entryDefs;
    }

    public function getStmtDef(Node $stmt): ?int
    {
        return $this->stmtDefs[$stmt] ?? null;
    }

    public function getBlockGens(BBlock $block): BitSet
    {
        return $this->blockGens[$block->getId()];
    }

    public function getBlockKills(BBlock $block): BitSet
    {
        return $this->blockKills[$block->getId()];
    }

    public function getStmtGens(Node $stmt): BitSet
    {
        return $this->stmtGens[$stmt];
    }

    public function getStmtKills(Node $stmt): BitSet
    {
        return $this->stmtKills[$stmt];
    }

    public function getVarDefs(int $varId): BitSet
    {
        return $this->varDefs[$varId];
    }

    public function getSymTable(): SymTable
    {
        return $this->symTable;
    }

    private function makeDefinitions(CFG $cfg, bool $addEntryDefinitions): void
    {
        $varNameToId = $this->symTable->getNameToIdMap();

        $defs = [];
        $entryDefs = BitSet::empty();

        $varDefs = [];
        foreach ($varNameToId as $varName => $varId) {
            if ($addEntryDefinitions) {
                $defId = count($defs);
                $defs[] = new Variable($varName);
                $varDefs[$varId] = BitSet::unit($defId);
                $entryDefs->set($defId);
            } else {
                $varDefs[$varId] = BitSet::empty();
            }
        }

        /** @var SplObjectStorage<Node,int> */
        $stmtDefs = new SplObjectStorage();

        foreach ($cfg->getBBlocks() as $block) {
            foreach ($block->getStmts() as $stmt) {
                foreach (NodeUtils::definedVariables($stmt) as $var) {
                    $defId = count($defs);
                    $defs[] = $var;
                    $stmtDefs[$var] = $defId;
                    if (is_string($var->name)) { // TODO
                        $varId = $varNameToId[$var->name];
                        $varDefs[$varId]->set($defId);
                    }
                }
            }
        }

        $this->defs = $defs;
        $this->entryDefs = $entryDefs;
        $this->varDefs = $varDefs;
        $this->stmtDefs = $stmtDefs;
    }

    private function makeGenKills(CFG $cfg): void
    {
        $defs = $this->defs;
        $varDefs = $this->varDefs;
        $stmtDefs = $this->stmtDefs;
        $varNameToId = $this->symTable->getNameToIdMap();

        /** @var SplObjectStorage<Node,BitSet> */
        $stmtGens = new SplObjectStorage();

        /** @var SplObjectStorage<Node,BitSet> */
        $stmtKills = new SplObjectStorage();

        foreach ($cfg->getBBlocks() as $block) {
            $blockGens = BitSet::empty();
            $blockKills = BitSet::empty();
            foreach ($block->getStmts() as $stmt) {
                $def = $stmtDefs[$stmt] ?? null;
                if ($def === null) {
                    $stmtGens[$stmt] = BitSet::empty();
                    $stmtKills[$stmt] = BitSet::empty();
                    continue;
                }
                $var = $defs[$def];
                assert(is_string($var->name));

                $gens = BitSet::unit($def);
                $kills = BitSet::diff($varDefs[$varNameToId[$var->name]], $gens);

                $stmtGens[$stmt] = $gens;
                $stmtKills[$stmt] = $kills;

                $blockKills = BitSet::union($blockKills, $kills);
                $blockGens = BitSet::union($gens, BitSet::diff($blockGens, $blockKills));
            }
            $this->blockGens[$block->getId()] = $blockGens;
            $this->blockKills[$block->getId()] = $blockKills;
        }

        $this->stmtGens = $stmtGens;
        $this->stmtKills = $stmtKills;
    }
}
