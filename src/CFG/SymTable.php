<?php

namespace PhpSema\CFG;

use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Foreach_;

final class SymTable
{
    /** @var array<int,string> */
    private array $idToName;

    /** @var array<string,int> */
    private array $nameToId;

    public function __construct()
    {
        $this->idToName = [];
        $this->nameToId = [];
    }

    /** @return int Variable id */
    public function addVar(string $name): int
    {
        if (isset($this->nameToId[$name])) {
            return $this->nameToId[$name];
        }

        $id = count($this->idToName);
        $this->idToName[] = $name;
        $this->nameToId[$name] = $id;

        return $id;
    }

    public function getId(string $name): int
    {
        if (!isset($this->nameToId[$name])) {
            throw new \Exception(sprintf(
                'Unknown var: "%s"',
                $name,
            ));
        }

        return $this->nameToId[$name];
    }

    public function getName(int $id): string
    {
        if (!isset($this->idToName[$id])) {
            throw new \Exception(sprintf(
                'Unknown var id: %d',
                $id,
            ));
        }

        return $this->idToName[$id];
    }

    /** @return array<int,string> */
    public function getIdToNameMap(): array
    {
        return $this->idToName;
    }

    /** @return array<string,int> */
    public function getNameToIdMap(): array
    {
        return $this->nameToId;
    }

    public static function fromCFG(CFG $cfg): SymTable
    {
        $symTable = new SymTable();

        foreach ($cfg->getBBlocks() as $block) {
            $terminator = $block->getTerminator();
            if ($terminator instanceof Foreach_) {
                $var = $terminator->keyVar;
                if ($var instanceof Variable && is_string($var->name)) {
                    $symTable->addVar($var->name);
                }
                $var = $terminator->valueVar;
                if ($var instanceof Variable && is_string($var->name)) {
                    $symTable->addVar($var->name);
                }
            }
            foreach ($block->getStmts() as $stmt) {
                if ($stmt instanceof Variable) {
                    if (is_string($stmt->name)) {
                        $symTable->addVar($stmt->name);
                    }
                }
            }
        }

        return $symTable;
    }
}