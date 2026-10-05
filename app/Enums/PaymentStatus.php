<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'PENDING';
    case PARTIAL = 'PARTIAL';
    case PAID = 'PAID';
}
