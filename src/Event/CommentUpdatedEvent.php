<?php

namespace Tourze\CommentBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\CommentBundle\Entity\Comment;

class CommentUpdatedEvent extends Event
{
    public const NAME = 'comment.updated';

    public function __construct(
        private readonly Comment $comment
    ) {
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }
}