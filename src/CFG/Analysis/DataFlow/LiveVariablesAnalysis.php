<?php

namespace PhpSema\CFG\Analysis\DataFlow;

use PhpSema\CFG\BBlock;
use PhpSema\CFG\CFG;
use PhpSema\CFG\SymTable;
use PhpSema\Utils\BitSet;

class LiveVariablesAnalysis
{
    /** @var array<int,BitSet> */
    private array $in;

    /** @var array<int,BitSet> */
    private array $out;

    public function __construct(
        CFG    $cfg,
        DefUses $defUses,
    ) {
        $meet = function (BitSet $a, BitSet $b): BitSet {
            return BitSet::union($a, $b);
        };

        $transfer = function (BBlock $block, BitSet $x) use ($defUses): BitSet {
            $defs = $defUses->getBlockDefs($block->getId());
            $uses = $defUses->getBlockUses($block->getId());
            return BitSet::union($uses, BitSet::diff($x, $defs));
        };

        $equals = function (BitSet $a, BitSet $b): bool {
            return $a->equals($b);
        };

        $init = BitSet::empty();
        $boundary = BitSet::empty();

        $bwdAnalysis = new BackwardAnalysis($cfg, $meet, $transfer, $equals, $boundary, $init);

        $this->in = $bwdAnalysis->getIn();
        $this->out = $bwdAnalysis->getOut();
    }

    public static function fromCFG(CFG $cfg): self
    {
        return new self($cfg, new DefUses(SymTable::fromCFG($cfg), $cfg));
    }

    public function getBlockLiveInVariables(BBlock $block): BitSet
    {
        return $this->in[$block->getId()] ?? BitSet::empty();
    }

    public function getBlockLiveOutVariables(BBlock $block): BitSet
    {
        return $this->out[$block->getId()];
    }
}