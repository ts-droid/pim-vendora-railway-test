<?php

namespace App\Enums;

enum LaravelQueues: string
{
    case DEFAULT = 'default';
    case LOW = 'low';
    case HIGH = 'high';
    case MAIN = 'main';
    case ARTICLE_SYNC = 'article-sync';
    case PRIORITY = 'priority';
    case MAIL = 'mail';
    case GENERATION = 'generation';
}
