<?php

namespace App\Enums;

enum TodoType: string
{
    // WMS
    case CollectArticleMeasurements = 'collect_article_measurements';
    case CollectArticleWeight = 'collect_article_weight';
    case CollectArticleImage = 'collect_article_image';
}
