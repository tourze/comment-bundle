<?php

namespace Tourze\CommentBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\CommentBundle\Entity\Comment;

class CommentDeletedEvent extends Event
{
    public const NAME = 'comment.deleted';

    public function __construct(
        private readonly Comment $comment
    ) {
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }
}