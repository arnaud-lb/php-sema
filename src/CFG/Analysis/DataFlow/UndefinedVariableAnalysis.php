<?php

namespace PhpSema\CFG\Analysis\DataFlow;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpSema\CFG\CFG;
use PhpSema\CFG\SymTable;
use PhpSema\Utils\BitSet;
use SplObjectStorage;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class UndefinedVariableAnalysis
{
    /** @var SplObjectStorage<Node,BitSet> */
    private SplObjectStorage $defsByStmt;

    public function __construct(
        CFG $cfg,
        private ReachingDefinitionsAnalysis $reachingDefs,
        private DefinitionGenKills $genKills,
        private SymTable $symTable,
        private BitSet $entryDefs,
    )
    {
        $this->defsByStmt = new SplObjectStorage();

        foreach ($cfg->getBBlocks() as $block) {
            $this->defsByStmt->addAll($this->reachingDefs->getStmtsReachingInDefinitions($block));
        }
    }

    public static function fromCFG(CFG $cfg): self
    {
        $symTable = SymTable::fromCFG($cfg);
        $genKills = new DefinitionGenKills($symTable, $cfg, addEntryDefinitions: true);

        $defs = $genKills->getEntryDefinitions();

        $reachingDefs = new ReachingDefinitionsAnalysis($cfg, $genKills, $defs);

        return new self($cfg, $reachingDefs, $genKills, $symTable, $defs);
    }

    public function getVariableStatus(Variable $var): UndefinedVariableStatus
    {
        if (!is_string($var->name)) {
            return UndefinedVariableStatus::Unkonwn;
        }

        if (!$this->genKills->getStmtGens($var)->isEmpty()) {
            return UndefinedVariableStatus::Unkonwn;
        }

        $defs = $this->defsByStmt[$var];

        $varId = $this->symTable->getId($var->name);

        $defs = BitSet::intersect($defs, $this->genKills->getVarDefs($varId));

        $int = BitSet::intersect($defs, $this->entryDefs);

        if ($int->isEmpty()) {
            return UndefinedVariableStatus::Defined;
        }

        if ($int->count() < $defs->count()) {
            return UndefinedVariableStatus::MaybeUndefined;
        }

        return UndefinedVariableStatus::Undefined;
    }
}
