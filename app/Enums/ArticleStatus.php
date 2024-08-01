<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Active = 'Active';
    case NoSales = 'NoSales';
    case NoPurchases = 'NoPurchases';
    case NoRequest = 'NoRequest';
    case Inactive = 'Inactive';
    case MarkedForDeletion = 'MarkedForDeletion';
}
