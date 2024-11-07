<?php

namespace App\Enums;

enum ShipmentInternalStatus: int
{
    case OPEN = 0;
    case PICKED = 1;
    case PACKED = 2;
    case INVESTIGATE = 3;
}
