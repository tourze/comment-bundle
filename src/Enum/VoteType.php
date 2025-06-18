<?php

namespace Tourze\CommentBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum VoteType: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;
    
    case LIKE = 'like';
    case DISLIKE = 'dislike';

    public function getLabel(): string
    {
        return match ($this) {
            self::LIKE => '点赞',
            self::DISLIKE => '踩',
        };
    }
    
    /**
     * @deprecated Use getLabel() instead
     */
    public function label(): string
    {
        return $this->getLabel();
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