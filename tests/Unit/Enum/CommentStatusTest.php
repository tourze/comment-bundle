<?php

namespace Tourze\CommentBundle\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Enum\CommentStatus;

class CommentStatusTest extends TestCase
{
    public function test_enum_hasCorrectValues(): void
    {
        $this->assertEquals('pending', CommentStatus::PENDING->value);
        $this->assertEquals('approved', CommentStatus::APPROVED->value);
        $this->assertEquals('rejected', CommentStatus::REJECTED->value);
        $this->assertEquals('deleted', CommentStatus::DELETED->value);
    }

    public function test_getLabel_returnsCorrectLabels(): void
    {
        $this->assertEquals('待审核', CommentStatus::PENDING->getLabel());
        $this->assertEquals('已通过', CommentStatus::APPROVED->getLabel());
        $this->assertEquals('已拒绝', CommentStatus::REJECTED->getLabel());
        $this->assertEquals('已删除', CommentStatus::DELETED->getLabel());
    }

    public function test_label_isDeprecatedAliasForGetLabel(): void
    {
        $this->assertEquals(CommentStatus::PENDING->getLabel(), CommentStatus::PENDING->label());
        $this->assertEquals(CommentStatus::APPROVED->getLabel(), CommentStatus::APPROVED->label());
        $this->assertEquals(CommentStatus::REJECTED->getLabel(), CommentStatus::REJECTED->label());
        $this->assertEquals(CommentStatus::DELETED->getLabel(), CommentStatus::DELETED->label());
    }

    public function test_isPublicVisible_onlyApprovedIsVisible(): void
    {
        $this->assertFalse(CommentStatus::PENDING->isPublicVisible());
        $this->assertTrue(CommentStatus::APPROVED->isPublicVisible());
        $this->assertFalse(CommentStatus::REJECTED->isPublicVisible());
        $this->assertFalse(CommentStatus::DELETED->isPublicVisible());
    }

    public function test_canBeModified_onlyPendingAndApprovedCanBeModified(): void
    {
        $this->assertTrue(CommentStatus::PENDING->canBeModified());
        $this->assertTrue(CommentStatus::APPROVED->canBeModified());
        $this->assertFalse(CommentStatus::REJECTED->canBeModified());
        $this->assertFalse(CommentStatus::DELETED->canBeModified());
    }
}