<?php

namespace PhpSema\Visualization\CFG;

use Fhaculty\Graph\Graph;
use Graphp\GraphViz\GraphViz;
use PhpParser\Node;
use PhpSema\CFG\BBlock;
use PhpSema\CFG\CFG;
use PhpSema\Visualization\AST\StandardPrettyPrinter;
use PhpSema\Visualization\AST\TerminatorPrettyPrinter;
use PhpSema\Visualization\CFG\Annotator\GraphvizStmtAnnotator;
use SplObjectStorage;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
class GraphvizPrinter
{
    private Graph $graph;

    /**
     * @param array<string,GraphvizStmtAnnotator> $preStmtAnnotators
     * @param array<string,GraphvizStmtAnnotator> $postStmtAnnotators
     */
    public function __construct(
        private CFG $cfg,
        private array $preStmtAnnotators,
        private array $postStmtAnnotators,
    ) {
        $graph = new Graph();

        /** @var SplObjectStorage<Node,array{BBlock,int}> */
        $seen = new SplObjectStorage();
        $prettyPrinter = new StandardPrettyPrinter($seen);
        $terminatorPrinter = new TerminatorPrettyPrinter($seen, $prettyPrinter);

        foreach ($this->cfg->getBBlocks() as $block) {
            $vertex = $graph->createVertex($block->getId());
            $vertex->setAttribute(
                'graphviz.label',
                GraphViz::raw('<'.$this->makeBlockLabel($block, $terminatorPrinter, $prettyPrinter, $seen).'>'),
            );
            $vertex->setAttribute(
                'graphviz.xlabel',
                sprintf('[B%d]', $block->getId()),
            );
            $vertex->setAttribute('graphviz.shape', 'rectangle');
        }

        foreach ($this->cfg->getBBlocks() as $block) {
            $successors = $block->getSuccessors();
            if (isset($successors[1])) {
                $vertex = $graph->getVertex($block->getId());
                $edge = $vertex->createEdgeTo($graph->getVertex($successors[0]->getId()));
                $edge->setAttribute('graphviz.label', 'true');

                $vertex = $graph->getVertex($block->getId());
                $edge = $vertex->createEdgeTo($graph->getVertex($successors[1]->getId()));
                $edge->setAttribute('graphviz.label', 'false');

                continue;
            }
            if (isset($successors[0])) {
                $vertex = $graph->getVertex($block->getId());
                $edge = $vertex->createEdgeTo($graph->getVertex($successors[0]->getId()));

                continue;
            }
            assert(count($successors) === 0);
        }

        $this->graph = $graph;
    }

    /**
     * @param string $format png, pdf, ...
     *
     * @see GraphViz::display()
     */
    public function display(string $format = 'png'): void
    {
        $graphviz = new GraphViz();
        $graphviz->setFormat($format);
        $graphviz->display($this->graph);
    }

    public function getGraph(): Graph
    {
        return $this->graph;
    }

    /**
     * @param SplObjectStorage<Node,array{BBlock,int}> $seen
     *
     * @return string
     */
    private function makeBlockLabel(
        BBlock                  $block,
        TerminatorPrettyPrinter $terminatorPrinter,
        StandardPrettyPrinter   $prettyPrinter,
        SplObjectStorage        $seen,
    ): string {
        $label = [];

        if ($block === $this->cfg->getEntry()) {
            $label[] = '<TR><TD ALIGN="LEFT">' . htmlspecialchars('<entry>') . '</TD></TR>';
        }

        if ($block === $this->cfg->getExit()) {
            $label[] = '<TR><TD ALIGN="LEFT">' . htmlspecialchars('<exit>') . '</TD></TR>';
        }

        if ($block->getLabel() !== null) {
            $label[] = '<TR><TD ALIGN="LEFT">' . htmlspecialchars($block->getLabel()) . '</TD></TR>';
        }

        /*
        $annotations = ($this->annotatePreBlock)($block);
        if ($annotations !== '') {
            $label[] = '<TR><TD ALIGN="LEFT">' . $annotations . '</TD></TR>';
        }
        */
        $stmts = $block->getStmts();
        $terminator = $block->getTerminator();
        if ($terminator !== null) {
            $stmts[] = $terminator;
        }
        if (count($stmts) > 0) {
            $table = '<TR><TD><TABLE ALIGN="LEFT" CELLBORDER="0" CELLPADDING="0" CELLSPACING="0" BORDER="0">';

            $table .= '<TR>';
            foreach ($this->preStmtAnnotators as $key => $annotator) {
                $table .= '<TD ALIGN="LEFT">' . $key . '</TD>';
                $table .= '<TD ALIGN="LEFT">&nbsp;</TD>';
            }
            $table .= '<TD ALIGN="LEFT" COLSPAN="2">StmtNo</TD>';
            foreach ($this->postStmtAnnotators as $key => $annotator) {
                $table .= '<TD ALIGN="LEFT">&nbsp;</TD>';
                $table .= '<TD ALIGN="LEFT">' . $key . '</TD>';
            }
            $table .= '</TR>';

            foreach ($stmts as $i => $stmt) {
                $table .= '<TR>';
                foreach ($this->preStmtAnnotators as $annotator) {
                    $table .= '<TD ALIGN="LEFT">' . ($annotator->getAnnotation($block, $stmt, $i) ?? '') . '</TD>';
                    $table .= '<TD ALIGN="LEFT">&nbsp;</TD>';
                }
                $table .= '<TD ALIGN="LEFT">' . strval($stmt === $block->getTerminator() ? 'T' : $i) . ':&nbsp;</TD>';
                if ($stmt === $block->getTerminator()) {
                    $prettyPrinted = $terminatorPrinter->prettyPrint([$stmt]);
                } else {
                    $prettyPrinted = $prettyPrinter->prettyPrint([$stmt]);
                }
                $seen[$stmt] = [$block, (int)$i];
                $table .= '<TD ALIGN="LEFT"><FONT FACE="monospace">' . addcslashes(htmlspecialchars(trim($prettyPrinted)), '\\') . '</FONT></TD>';
                foreach ($this->postStmtAnnotators as $annotator) {
                    $table .= '<TD ALIGN="LEFT">&nbsp;</TD>';
                    $table .= '<TD ALIGN="LEFT">' . ($annotator->getAnnotation($block, $stmt, $i) ?? '') . '</TD>';
                }
                $table .= '</TR>';
            }
            $table .= '</TABLE></TD></TR>';

            $label[] = $table;
        }

        /*
        $annotations = ($this->annotatePostBlock)($block);
        if ($annotations !== '') {
            $label[] = '<TR><TD ALIGN="LEFT">' . $annotations . '</TD></TR>';
        }
        */

        if (count($label) > 0) {
            $label = [
                '<TABLE ALIGN="LEFT" CELLBORDER="0" CELLPADDING="0" CELLSPACING="0" BORDER="0">',
                ...$label,
                '</TABLE>',
            ];
        }
        return implode('', $label);
    }
}
