<?php

namespace Tourze\CommentBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\VoteType;

class CommentVotedEvent extends Event
{
    public const NAME = 'comment.voted';

    public function __construct(
        private readonly Comment $comment,
        private readonly VoteType $voteType,
        private readonly string $action,
        private readonly ?string $voterId = null
    ) {
    }

    public function getComment(): Comment
    {
        return $this->comment;
    }

    public function getVoteType(): VoteType
    {
        return $this->voteType;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getVoterId(): ?string
    {
        return $this->voterId;
    }
}