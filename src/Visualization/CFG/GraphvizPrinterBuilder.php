<?php

namespace PhpSema\Visualization\CFG;

use PhpSema\CFG\CFG;
use PhpSema\Visualization\CFG\Annotator\GraphvizStmtAnnotator;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class GraphvizPrinterBuilder
{
    /** @var GraphvizStmtAnnotator[] */
    private array $preStmtAnnotators = [];

    /** @var GraphvizStmtAnnotator[] */
    private array $postStmtAnnotators = [];

    public function __construct(
        private CFG $cfg,
    ) {}

    public static function create(CFG $cfg): self
    {
        return new self($cfg);
    }

    public function printer(): GraphvizPrinter
    {
        return new GraphvizPrinter(
            $this->cfg,
            $this->preStmtAnnotators,
            $this->postStmtAnnotators,
        );
    }

    public function withPreStmtAnnotator(string $label, GraphvizStmtAnnotator $annotator): self
    {
        $clone = clone $this;
        $clone->preStmtAnnotators[$label] = $annotator;

        return $clone;
    }

    public function withPostStmtAnnotator(string $label, GraphvizStmtAnnotator $annotator): self
    {
        $clone = clone $this;
        $clone->postStmtAnnotators[$label] = $annotator;

        return $clone;
    }
}
