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

    public function isPositive(): bool
    {
        return self::LIKE === $this;
    }

    public function isNegative(): bool
    {
        return self::DISLIKE === $this;
    }

    public function label(): string
    {
        return $this->getLabel();
    }

    /**
     * 获取所有枚举的选项数组（用于下拉列表等）
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function toSelectItems(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[] = [
                'value' => $case->value,
                'label' => $case->getLabel(),
            ];
        }

        return $result;
    }
}
