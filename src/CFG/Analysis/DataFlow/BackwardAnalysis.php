<?php

namespace PhpSema\CFG\Analysis\DataFlow;

use PhpSema\CFG\BBlock;
use PhpSema\CFG\CFG;

/**
 * @template V
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class BackwardAnalysis
{
    /** @var array<int,V> */
    private array $in;

    /** @var array<int,V> */
    private array $out;

    /**
     * @param callable(V,V):V $meet
     * @param callable(BBlock,V):V $transfer
     * @param callable(V,V):bool $equals
     * @param V $boundary
     * @param V $init
     */
    public function __construct(
        CFG $cfg,
            $meet,
            $transfer,
            $equals,
            $boundary,
            $init,
    )
    {
        $exit = $cfg->getExit();
        $basicBlocks = $cfg->getBBlocks();

        $in = array_fill(0, count($basicBlocks), $boundary);
        $in[$exit->getId()] = $init;

        $out = [];

        do {
            $changed = false;
            foreach ($basicBlocks as $id => $bb) {
                if ($bb === $exit) {
                    continue;
                }

                $bbOut = $boundary;
                $bbIn = $in[$id];

                foreach ($bb->getSuccessors() as $succ) {
                    $bbOut = $meet($bbOut, $in[$succ->getId()]);
                }
                $out[$id] = $bbOut;
                $in[$id] = $transfer($bb, $bbOut);
                $changed = $changed || !$equals($bbIn, $in[$id]);
            }
        } while ($changed);

        $this->in = $in;
        $this->out = $out;
    }

    /** @return array<int,V> */
    public function getIn(): array
    {
        return $this->in;
    }

    /** @return array<int,V> */
    public function getOut(): array
    {
        return $this->out;
    }
}
