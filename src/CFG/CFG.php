<?php

namespace PhpSema\CFG;

use PhpParser\Node;

/**
 * @author Arnaud Le Blanc <arnaud.lb@gmail.com>
 */
final class CFG
{
    private int $nextNodeId;

    /** @var array<int,BBlock> */
    private array $bblocks;

    private BBlock $entry;

    private BBlock $exit;

    /** @var array<int,int[]> */
    private array $edges;

    /** @var array<int,int[]> */
    private array $reverseEdges;

    public function __construct()
    {
        $this->nextNodeId = 0;
        $this->bblocks = [];
        $this->edges = [];
        $this->reverseEdges = [];
        $this->entry = $this->createBlock();
        $this->exit = $this->createBlock();
        $this->entry->setSuccessor($this->exit);
    }

    public function getEntry(): BBlock
    {
        return $this->entry;
    }

    public function getExit(): BBlock
    {
        return $this->exit;
    }

    public function createBlock(): BBlock
    {
        $id = $this->nextNodeId++;
        $block = new BBlock($id, $this);

        $this->bblocks[$id] = $block;

        return $block;
    }

    /** @return BBlock[] */
    public function getBBlocks(): array
    {
        return $this->bblocks;
    }

    /** @return BBlock[] */
    public function getSuccessorsById(int $id): array
    {
        $succs = [];
        if (!isset($this->edges[$id])) {
            return $succs;
        }

        foreach ($this->edges[$id] as $succId) {
            $succs[] = $this->bblocks[$succId];
        }

        return $succs;
    }

    /** @return BBlock[] */
    public function getPredecessorsById(int $id): array
    {
        $preds = [];
        if (!isset($this->reverseEdges[$id])) {
            return $preds;
        }

        foreach ($this->reverseEdges[$id] as $predId) {
            $preds[] = $this->bblocks[$predId];
        }

        return $preds;
    }

    /** @param int[] $succs */
    public function setSuccessorIds(int $pred, array $succs): void
    {
        if (isset($this->edges[$pred])) {
            foreach ($this->edges[$pred] as $succ) {
                $this->reverseEdges[$succ] = array_diff($this->reverseEdges[$succ], [$pred]);
            }
        }

        $this->edges[$pred] = $succs;

        foreach ($succs as $succ) {
            $this->reverseEdges[$succ][] = $pred;
        }
    }

    /** @param array<int,int> $order */
    private function getReversePostOrderImpl(int $blockId, array &$order, int &$index): void
    {
        if (isset($order[$blockId])) {
            return;
        }

        $order[$blockId] = $index++;

        if (isset($this->edges[$blockId])) {
            foreach ($this->edges[$blockId] as $succ) {
                $this->getReversePostOrderImpl($succ, $order, $index);
            }
        }

    }

    /** @return array<int,int> blockId -> order */
    public function getReversePostOrder(): array
    {
        $order = [];
        $index = 0;
        $this->getReversePostOrderImpl($this->entry->getId(), $order, $index);

        return $order;
    }

    /** @param array<int,int> $order */
    private function getPostOrderImpl(int $blockId, array &$order, int &$index): void
    {
        if (isset($order[$blockId])) {
            return;
        }

        $order[$blockId] = -1; // visiting

        if (isset($this->edges[$blockId])) {
            foreach ($this->edges[$blockId] as $succ) {
                $this->getPostOrderImpl($succ, $order, $index);
            }
        }

        $order[$blockId] = $index++;
    }

    /** @return array<int,int> blockId -> order */
    public function getPostOrder(): array
    {
        $order = [];
        $index = 0;
        $this->getPostOrderImpl($this->entry->getId(), $order, $index);

        return $order;
    }
}
