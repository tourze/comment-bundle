<?php

namespace Tourze\CommentBundle\Enum;

enum CommentStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case DELETED = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待审核',
            self::APPROVED => '已通过',
            self::REJECTED => '已拒绝',
            self::DELETED => '已删除',
        };
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