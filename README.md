# php-sema

Semantic analysis of PHP code.

php-sema implements an AST-based CFG that is suitable for source-level analysis.

Currently, php-sema implements: SSA conversion, generic data flow analysis algorithms, reachable definitions, dead code analysis, undefined variable analysis.

## Use-cases

- Detecting uses of undefined variables
- Detecting dead code (code without effect)
- Detecting unreachable code

## Work in progress

This is a work in progress. There is not enough tests, no support for variable-variables or references, and the API needs improvements.
