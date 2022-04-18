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

\PhpSema\Visualization\CFG\GraphvizPrinterBuilder::create($cfg)
    ->withPreStmtAnnotator(
        'Line',
        new \PhpSema\Visualization\CFG\Annotator\GraphvizLineNumberStmtAnnotator(),
    )
    ->withPostStmtAnnotator(
        'Class', new \PhpSema\Visualization\CFG\Annotator\GraphvizStmtClassAnnotator(),
    )
    ->printer()
    ->display();
