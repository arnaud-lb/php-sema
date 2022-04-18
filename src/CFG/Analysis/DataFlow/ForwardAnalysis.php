<?php

namespace PhpSema\CFG\Analysis\DataFlow;

use PhpSema\CFG\BBlock;
use PhpSema\CFG\CFG;

/**
 * @template V
 *
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class ForwardAnalysis
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
        CFG      $cfg,
        callable $meet,
        callable $transfer,
        callable $equals,
        mixed    $boundary,
        mixed    $init,
    )
    {
        $entry = $cfg->getEntry();
        $basicBlocks = $cfg->getBBlocks();

        $out = array_fill(0, count($basicBlocks), $boundary);
        $out[$entry->getId()] = $init;

        $in = [];

        do {
            $changed = false;
            foreach ($basicBlocks as $id => $bb) {
                if ($bb === $entry) {
                    continue;
                }

                $bbIn = $boundary;
                $bbOut = $out[$id];

                foreach ($bb->getPredecessors() as $pred) {
                    $bbIn = $meet($bbIn, $out[$pred->getId()]);
                }
                $in[$id] = $bbIn;
                $out[$id] = $transfer($bb, $in[$id]);
                $changed = $changed || !$equals($bbOut, $out[$id]);
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
