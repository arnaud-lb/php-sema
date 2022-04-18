<?php

require 'vendor/autoload.php';

$parser = (new \PhpParser\ParserFactory())->create(\PhpParser\ParserFactory::PREFER_PHP7);
$ast = $parser->parse(file_get_contents($argv[1]));
assert($ast !== null);

$cfg = (new \PhpSema\CFG\CFGBuilder())->build($ast);
$ssaSymTable = \PhpSema\CFG\SSA\Conversion\SSAConversion::convert($cfg);

\PhpSema\Visualization\CFG\GraphvizPrinterBuilder::create($cfg)->printer()->display();