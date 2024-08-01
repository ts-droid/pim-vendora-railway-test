<?php

namespace App\Enums;

enum ArticleType: string
{
    case NonStockItem = 'NonStockItem';
    case LaborItem = 'LaborItem';
    case ServiceItem = 'ServiceItem';
    case ChargeItem = 'ChargeItem';
    case ExpenseItem = 'ExpenseItem';
    case FinishedGoodItem = 'FinishedGoodItem';
    case ComponentPartItem = 'ComponentPartItem';
    case SubassemblyItem = 'SubassemblyItem';
}
