<?php

use PhpSema\Utils\FunctionUtils;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;

require __DIR__ . '/../vendor/autoload.php';

$input = new ArgvInput(null, new InputDefinition([
    new InputArgument('filename', InputArgument::REQUIRED),
    new InputArgument('function', InputArgument::OPTIONAL),
]));

$parser = (new \PhpParser\ParserFactory())->create(\PhpParser\ParserFactory::PREFER_PHP7);
$ast = $parser->parse(file_get_contents($input->getArgument('filename')));
assert($ast !== null);

$finder = new \PhpParser\NodeFinder();
$nodes = $finder->find($ast, function (\PhpParser\Node $node) use ($input) {
    return match (true) {
        $node instanceof \PhpParser\Node\FunctionLike => $input->getArgument('function') !== null
            ? $input->getArgument('function') === FunctionUtils::getName($node)
            : true,
        default => false,
    };
});

if (count($nodes) === 0) {
    fprintf(STDERR, "Found no functions or methods\n");
    exit(1);
}

foreach ($nodes as $node) {
    assert($node instanceof \PhpParser\Node\FunctionLike);

    fprintf(STDERR, "Analyzing %s\n", FunctionUtils::getName($node) ?? 'anon function');

    $start = microtime(true);

    $cfg = (new \PhpSema\CFG\CFGBuilder())->buildFunction($node);
    $ssaSymTable = \PhpSema\CFG\SSA\Conversion\SSAConversion::convert($cfg);
    $deadCodeAnalysis = new \PhpSema\CFG\Analysis\DeadCodeAnalysisSSA($cfg, $ssaSymTable);

    $end = microtime(true);

    fprintf(STDERR, "Analysis done (%.03fms). Displaying CFG\n", ($end-$start)*1000);

    \PhpSema\Visualization\CFG\GraphvizPrinterBuilder::create($cfg)
        ->withPreStmtAnnotator(
            'Line',
            new \PhpSema\Visualization\CFG\Annotator\GraphvizLineNumberStmtAnnotator(),
        )
        ->withPreStmtAnnotator(
            'Dead',
            new \PhpSema\Visualization\CFG\Annotator\GraphvizDeadStmtAnnotator($deadCodeAnalysis),
        )
        ->withPostStmtAnnotator(
            'Class', new \PhpSema\Visualization\CFG\Annotator\GraphvizStmtClassAnnotator(),
        )
        ->printer()
        ->display();
}