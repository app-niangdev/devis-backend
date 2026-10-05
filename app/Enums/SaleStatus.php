<?php

namespace App\Enums;

enum SaleStatus: string
{
    case VALIDATED = 'VALIDATED';
    case CANCELLED = 'CANCELLED';
}
