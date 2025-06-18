<?php

namespace Tourze\CommentBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum CommentStatus: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;
    
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case DELETED = 'deleted';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待审核',
            self::APPROVED => '已通过',
            self::REJECTED => '已拒绝',
            self::DELETED => '已删除',
        };
    }
    
    /**
     * @deprecated Use getLabel() instead
     */
    public function label(): string
    {
        return $this->getLabel();
    }

    public function isPublicVisible(): bool
    {
        return $this === self::APPROVED;
    }

    public function canBeModified(): bool
    {
        return in_array($this, [self::PENDING, self::APPROVED], true);
    }
}