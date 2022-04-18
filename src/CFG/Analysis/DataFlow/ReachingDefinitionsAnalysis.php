<?php

namespace PhpSema\CFG\Analysis\DataFlow;

use PhpParser\Node;
use PhpSema\CFG\BBlock;
use PhpSema\CFG\CFG;
use PhpSema\CFG\SymTable;
use PhpSema\Utils\BitSet;
use SplObjectStorage;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class ReachingDefinitionsAnalysis
{
    /** @var array<int,BitSet> */
    private array $in;

    /** @var array<int,BitSet> */
    private array $out;

    public function __construct(
        CFG $cfg,
        private DefinitionGenKills $genKills,
        BitSet $init = null,
    ) {
        $meet = function (BitSet $a, BitSet $b): BitSet {
            return BitSet::union($a, $b);
        };

        $transfer = function (BBlock $block, BitSet $x) use ($genKills): BitSet {
            $gens = $genKills->getBlockGens($block);
            $kills = $genKills->getBlockKills($block);
            return BitSet::union($gens, BitSet::diff($x, $kills));
        };

        $equals = function (BitSet $a, BitSet $b): bool {
            return $a->equals($b);
        };

        $init = $init ?? BitSet::empty();
        $boundary = BitSet::empty();

        $fwdAnalysis = new ForwardAnalysis($cfg, $meet, $transfer, $equals, $boundary, $init);

        $this->in = $fwdAnalysis->getIn();
        $this->out = $fwdAnalysis->getOut();
    }

    public static function fromCFG(CFG $cfg): self
    {
        return new self($cfg, new DefinitionGenKills(SymTable::fromCFG($cfg), $cfg, addEntryDefinitions: false));
    }

    public function getBlockReachingInDefinitions(BBlock $block): BitSet
    {
        return $this->in[$block->getId()] ?? BitSet::empty();
    }

    public function getBlockReachingOutDefinitions(BBlock $block): BitSet
    {
        return $this->out[$block->getId()];
    }

    /** @return SplObjectStorage<Node,BitSet> */
    public function getStmtsReachingInDefinitions(BBlock $block): SplObjectStorage
    {
        $in = $this->in[$block->getId()] ?? null;

        if ($in === null) {
            /** @var SplObjectStorage<Node,BitSet> */
            $empty = new SplObjectStorage();
            return $empty;
        }

        /** @var SplObjectStorage<Node,BitSet> */
        $perStmt = new SplObjectStorage();

        foreach ($block->getStmts() as $stmt) {
            $lastStmt = $stmt;
            $perStmt[$stmt] = $in;
            $in = BitSet::union(
                BitSet::diff($in, $this->genKills->getStmtKills($stmt)),
                $this->genKills->getStmtGens($stmt),
            );
        }

        return $perStmt;
    }
}
