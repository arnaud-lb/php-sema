<?php

namespace PhpSema\CFG\Analysis\DataFlow;

enum UndefinedVariableStatus
{
    case Unkonwn;
    case Undefined;
    case Defined;
    case MaybeUndefined;
}