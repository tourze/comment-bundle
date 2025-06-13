<?php

namespace Tourze\CommentBundle\Enum;

enum VoteType: string
{
    case LIKE = 'like';
    case DISLIKE = 'dislike';

    public function label(): string
    {
        return match ($this) {
            self::LIKE => '点赞',
            self::DISLIKE => '踩',
        };
    }

    public function isPositive(): bool
    {
        return $this === self::LIKE;
    }

    public function isNegative(): bool
    {
        return $this === self::DISLIKE;
    }
}