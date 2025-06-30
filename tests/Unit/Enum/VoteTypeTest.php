<?php

namespace Tourze\CommentBundle\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Enum\VoteType;

class VoteTypeTest extends TestCase
{
    public function test_enum_hasCorrectValues(): void
    {
        $this->assertEquals('like', VoteType::LIKE->value);
        $this->assertEquals('dislike', VoteType::DISLIKE->value);
    }

    public function test_getLabel_returnsCorrectLabels(): void
    {
        $this->assertEquals('点赞', VoteType::LIKE->getLabel());
        $this->assertEquals('踩', VoteType::DISLIKE->getLabel());
    }

    public function test_label_isDeprecatedAliasForGetLabel(): void
    {
        $this->assertEquals(VoteType::LIKE->getLabel(), VoteType::LIKE->label());
        $this->assertEquals(VoteType::DISLIKE->getLabel(), VoteType::DISLIKE->label());
    }

    public function test_isPositive_onlyLikeIsPositive(): void
    {
        $this->assertTrue(VoteType::LIKE->isPositive());
        $this->assertFalse(VoteType::DISLIKE->isPositive());
    }

    public function test_isNegative_onlyDislikeIsNegative(): void
    {
        $this->assertFalse(VoteType::LIKE->isNegative());
        $this->assertTrue(VoteType::DISLIKE->isNegative());
    }
}