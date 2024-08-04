<?php

namespace App\Events;

use App\Models\Article;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Article $article;
    public array $changes;

    public function __construct(Article $article, array $changes)
    {
        $this->article = $article;
        $this->changes = $changes;
    }
}
