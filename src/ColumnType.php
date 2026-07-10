<?php

declare(strict_types=1);

namespace Sumire;

enum ColumnType: string
{
    case DateTime = 'datetime';
    case Json = 'json';
}
