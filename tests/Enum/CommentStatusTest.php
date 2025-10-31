<?php

namespace Tourze\CommentBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(CommentStatus::class)]
final class CommentStatusTest extends AbstractEnumTestCase
{
    #[TestWith(['pending', '待审核'])]
    #[TestWith(['approved', '已通过'])]
    #[TestWith(['rejected', '已拒绝'])]
    #[TestWith(['deleted', '已删除'])]
    public function testEnumHasCorrectValuesAndLabels(string $value, string $label): void
    {
        $case = CommentStatus::from($value);
        $this->assertEquals($value, $case->value);
        $this->assertEquals($label, $case->getLabel());
    }

    public function testFromThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        CommentStatus::from('invalid_status');
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(CommentStatus::tryFrom('invalid_status'));
    }

    public function testTryFromReturnsCaseForValidValue(): void
    {
        $this->assertEquals(CommentStatus::PENDING, CommentStatus::tryFrom('pending'));
        $this->assertEquals(CommentStatus::APPROVED, CommentStatus::tryFrom('approved'));
        $this->assertEquals(CommentStatus::REJECTED, CommentStatus::tryFrom('rejected'));
        $this->assertEquals(CommentStatus::DELETED, CommentStatus::tryFrom('deleted'));
    }

    public function testAllValuesAreUnique(): void
    {
        $values = array_map(fn ($case) => $case->value, CommentStatus::cases());
        $uniqueValues = array_unique($values);
        $this->assertCount(count($values), $uniqueValues, 'All enum values must be unique');
    }

    public function testAllLabelsAreUnique(): void
    {
        $labels = array_map(fn ($case) => $case->getLabel(), CommentStatus::cases());
        $uniqueLabels = array_unique($labels);
        $this->assertCount(count($labels), $uniqueLabels, 'All enum labels must be unique');
    }

    #[TestWith(['PENDING'])]
    #[TestWith(['APPROVED'])]
    #[TestWith(['REJECTED'])]
    #[TestWith(['DELETED'])]
    public function testLabelIsDeprecatedAliasForGetLabel(string $caseName): void
    {
        $case = constant('Tourze\CommentBundle\Enum\CommentStatus::' . $caseName);
        $this->assertInstanceOf(CommentStatus::class, $case);
        $this->assertEquals($case->getLabel(), $case->label());
    }

    public function testIsPublicVisibleOnlyApprovedIsVisible(): void
    {
        $this->assertFalse(CommentStatus::PENDING->isPublicVisible());
        $this->assertTrue(CommentStatus::APPROVED->isPublicVisible());
        $this->assertFalse(CommentStatus::REJECTED->isPublicVisible());
        $this->assertFalse(CommentStatus::DELETED->isPublicVisible());
    }

    public function testCanBeModifiedOnlyPendingAndApprovedCanBeModified(): void
    {
        $this->assertTrue(CommentStatus::PENDING->canBeModified());
        $this->assertTrue(CommentStatus::APPROVED->canBeModified());
        $this->assertFalse(CommentStatus::REJECTED->canBeModified());
        $this->assertFalse(CommentStatus::DELETED->canBeModified());
    }

    public function testToArrayReturnsCorrectArray(): void
    {
        $expected = ['value' => 'pending', 'label' => '待审核'];
        $this->assertEquals($expected, CommentStatus::PENDING->toArray());

        $expected = ['value' => 'approved', 'label' => '已通过'];
        $this->assertEquals($expected, CommentStatus::APPROVED->toArray());
    }

    public function testToSelectItemReturnsCorrectSelectItems(): void
    {
        $pendingItem = CommentStatus::PENDING->toSelectItem();
        $this->assertArrayHasKey('value', $pendingItem);
        $this->assertArrayHasKey('label', $pendingItem);
        $this->assertEquals('pending', $pendingItem['value']);
        $this->assertEquals('待审核', $pendingItem['label']);

        $approvedItem = CommentStatus::APPROVED->toSelectItem();
        $this->assertEquals('approved', $approvedItem['value']);
        $this->assertEquals('已通过', $approvedItem['label']);
    }
}
