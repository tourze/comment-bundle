<?php

namespace Tourze\CommentBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\CommentBundle\Entity\Comment;

class CommentCreatedEvent extends Event
{
    public const NAME = 'comment.created';

    public function __construct(
        private readonly Comment $comment
    ) {
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }
}