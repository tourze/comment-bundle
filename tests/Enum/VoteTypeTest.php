<?php

namespace Tourze\CommentBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\CommentBundle\Enum\VoteType;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(VoteType::class)]
final class VoteTypeTest extends AbstractEnumTestCase
{
    #[TestWith(['like', '点赞'])]
    #[TestWith(['dislike', '踩'])]
    public function testEnumHasCorrectValuesAndLabels(string $value, string $label): void
    {
        $case = VoteType::from($value);
        $this->assertEquals($value, $case->value);
        $this->assertEquals($label, $case->getLabel());
    }

    public function testIsPositiveOnlyLikeIsPositive(): void
    {
        $this->assertTrue(VoteType::LIKE->isPositive());
        $this->assertFalse(VoteType::DISLIKE->isPositive());
    }

    public function testIsNegativeOnlyDislikeIsNegative(): void
    {
        $this->assertFalse(VoteType::LIKE->isNegative());
        $this->assertTrue(VoteType::DISLIKE->isNegative());
    }

    public function testFromThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        VoteType::from('invalid_vote_type');
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(VoteType::tryFrom('invalid_vote_type'));
    }

    public function testTryFromReturnsCaseForValidValue(): void
    {
        $this->assertEquals(VoteType::LIKE, VoteType::tryFrom('like'));
        $this->assertEquals(VoteType::DISLIKE, VoteType::tryFrom('dislike'));
    }

    public function testAllValuesAreUnique(): void
    {
        $values = array_map(fn ($case) => $case->value, VoteType::cases());
        $uniqueValues = array_unique($values);
        $this->assertCount(count($values), $uniqueValues, 'All enum values must be unique');
    }

    public function testAllLabelsAreUnique(): void
    {
        $labels = array_map(fn ($case) => $case->getLabel(), VoteType::cases());
        $uniqueLabels = array_unique($labels);
        $this->assertCount(count($labels), $uniqueLabels, 'All enum labels must be unique');
    }

    #[TestWith(['LIKE'])]
    #[TestWith(['DISLIKE'])]
    public function testLabelIsDeprecatedAliasForGetLabel(string $caseName): void
    {
        $case = constant('Tourze\CommentBundle\Enum\VoteType::' . $caseName);
        $this->assertInstanceOf(VoteType::class, $case);
        $this->assertEquals($case->getLabel(), $case->label());
    }

    public function testToArrayReturnsCorrectArray(): void
    {
        $expected = ['value' => 'like', 'label' => '点赞'];
        $this->assertEquals($expected, VoteType::LIKE->toArray());

        $expected = ['value' => 'dislike', 'label' => '踩'];
        $this->assertEquals($expected, VoteType::DISLIKE->toArray());
    }

    public function testToSelectItemReturnsCorrectSelectItems(): void
    {
        $likeItem = VoteType::LIKE->toSelectItem();
        $this->assertArrayHasKey('value', $likeItem);
        $this->assertArrayHasKey('label', $likeItem);
        $this->assertEquals('like', $likeItem['value']);
        $this->assertEquals('点赞', $likeItem['label']);

        $dislikeItem = VoteType::DISLIKE->toSelectItem();
        $this->assertEquals('dislike', $dislikeItem['value']);
        $this->assertEquals('踩', $dislikeItem['label']);
    }
}
