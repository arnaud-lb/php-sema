<?php

namespace PhpSema\CFG\Analysis\DataFlow;

use PhpParser\Node\Expr\Variable;
use PhpSema\CFG\CFG;
use PhpSema\CFG\NodeUtils;
use PhpSema\CFG\SymTable;
use PhpSema\Utils\BitSet;

class DefUses
{
    /** @var array<int,BitSet> */
    private array $blockDefs;

    /** @var array<int,BitSet> */
    private array $blockUses;

    public function __construct(
        SymTable $symTable,
        CFG $cfg,
    ) {
        $this->blockDefs = [];
        $this->blockUses = [];

        $nameToId = $symTable->getNameToIdMap();
        $isDef = new \SplObjectStorage();

        foreach ($cfg->getBBlocks() as $block) {
            foreach ($block->getStmtsAndTerminator() as $stmt) {
                foreach (NodeUtils::definedVariables($stmt) as $var) {
                    $isDef->attach($var);
                }
            }
        }

        foreach ($cfg->getBBlocks() as $block) {
            $defs = BitSet::empty();
            $uses = BitSet::empty();
            foreach ($block->getStmtsAndTerminator() as $stmt) {
                if ($stmt instanceof Variable) {
                    if (!is_string($stmt->name)) {
                        continue;
                    }
                    $varId = $nameToId[$stmt->name];
                    if ($isDef->contains($stmt)) {
                        $defs->set($varId);
                        continue;
                    }
                    if (!$defs->isset($varId)) {
                        $uses->set($varId);
                    }
                }
            }
            $this->blockDefs[$block->getId()] = $defs;
            $this->blockUses[$block->getId()] = $uses;
        }

        /*
        foreach ($cfg->getBBlocks() as $blockId => $_) {
            printf("Block %s:\n", $blockId);
            foreach ($this->blockDefs[$blockId]->toArray() as $varId) {
                printf("def: %s\n", $symTable->getName($varId));
            }
        }
        */
    }

    public static function fromCFG(CFG $cfg): self
    {
        return new self(SymTable::fromCFG($cfg), $cfg);
    }

    public function getBlockDefs(int $blockId): BitSet
    {
        return $this->blockDefs[$blockId];
    }

    public function getBlockUses(int $blockId): BitSet
    {
        return $this->blockUses[$blockId];
    }
}
