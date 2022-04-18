<?php

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

require __DIR__ . '/../vendor/autoload.php';

$input = new ArgvInput(null, new InputDefinition([
    new InputArgument('filename', InputArgument::REQUIRED),
]));

$parser = (new \PhpParser\ParserFactory())->create(\PhpParser\ParserFactory::PREFER_PHP7);
$ast = $parser->parse(file_get_contents($input->getArgument('filename')));
assert($ast !== null);

$cfg = (new \PhpSema\CFG\CFGBuilder())->build($ast);

$analysis = \PhpSema\CFG\Analysis\DataFlow\UndefinedVariableAnalysis::fromCFG($cfg);

foreach ($cfg->getBBlocks() as $block) {
    foreach ($block->getStmts() as $stmt) {
        if (!$stmt instanceof \PhpParser\Node\Expr\Variable) {
            continue;
        }
        if (!is_string($stmt->name)) {
            continue;
        }
        switch ($analysis->getVariableStatus($stmt)) {
            case \PhpSema\CFG\Analysis\DataFlow\UndefinedVariableStatus::Defined:
                break;
            case \PhpSema\CFG\Analysis\DataFlow\UndefinedVariableStatus::Unkonwn:
                break;
            case \PhpSema\CFG\Analysis\DataFlow\UndefinedVariableStatus::MaybeUndefined:
                fprintf(
                    STDERR,
                    "Variable \$%s may be undefined when used at line %d\n",
                    $stmt->name,
                    $stmt->getLine(),
                );
                break;
            case \PhpSema\CFG\Analysis\DataFlow\UndefinedVariableStatus::Undefined:
                fprintf(
                    STDERR,
                    "Variable \$%s is undefined when used at line %d\n",
                    $stmt->name,
                    $stmt->getLine(),
                );
        }
    }
}