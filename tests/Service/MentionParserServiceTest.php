<?php

namespace Tourze\CommentBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Service\MentionParserService;

/**
 * @internal
 */
#[CoversClass(MentionParserService::class)]
final class MentionParserServiceTest extends TestCase
{
    private MentionParserService $mentionParser;

    public function testParseMentionsWithSingleMention(): void
    {
        $content = 'Hello @john, how are you?';
        $mentions = $this->mentionParser->parseMentions($content);

        $this->assertCount(1, $mentions);
        $this->assertEquals('john', $mentions[0]['user_id']);
        $this->assertEquals('john', $mentions[0]['user_name']);
        $this->assertEquals('@john', $mentions[0]['original_text']);
        $this->assertEquals(6, $mentions[0]['position']);
    }

    public function testParseMentionsWithMultipleMentions(): void
    {
        $content = 'Hey @alice and @bob, check this out @charlie!';
        $mentions = $this->mentionParser->parseMentions($content);

        $this->assertCount(3, $mentions);
        $this->assertEquals('alice', $mentions[0]['user_id']);
        $this->assertEquals('bob', $mentions[1]['user_id']);
        $this->assertEquals('charlie', $mentions[2]['user_id']);
    }

    public function testParseMentionsWithDuplicateMentions(): void
    {
        $content = 'Hey @alice, tell @alice that @alice should check this';
        $mentions = $this->mentionParser->parseMentions($content);

        $this->assertCount(1, $mentions);
        $this->assertEquals('alice', $mentions[0]['user_id']);
    }

    public function testParseMentionsWithNoMentions(): void
    {
        $content = 'This comment has no mentions at all';
        $mentions = $this->mentionParser->parseMentions($content);

        $this->assertEmpty($mentions);
    }

    public function testParseMentionsRespectsMaxLimit(): void
    {
        $limitedParser = new MentionParserService(maxMentionsPerComment: 2);
        $content = 'Hey @user1 @user2 @user3 @user4 @user5';
        $mentions = $limitedParser->parseMentions($content);

        $this->assertCount(2, $mentions);
    }

    public function testExtractMentionedUsernamesReturnsUsernamesOnly(): void
    {
        $content = 'Hello @john and @jane, nice to meet you @bob!';
        $usernames = $this->mentionParser->extractMentionedUsernames($content);

        $this->assertEquals(['john', 'jane', 'bob'], $usernames);
    }

    public function testReplaceMentionsWithLinksUsesDefaultLinkGenerator(): void
    {
        $content = 'Hello @john!';
        $result = $this->mentionParser->replaceMentionsWithLinks($content);

        $expected = 'Hello <a href="/user/john" class="mention">@john</a>!';
        $this->assertEquals($expected, $result);
    }

    public function testReplaceMentionsWithLinksUsesCustomLinkGenerator(): void
    {
        $content = 'Hello @john!';
        $linkGenerator = function (string $username): string {
            return sprintf('<span class="user" data-user="%s">@%s</span>', $username, $username);
        };

        $result = $this->mentionParser->replaceMentionsWithLinks($content, $linkGenerator);

        $expected = 'Hello <span class="user" data-user="john">@john</span>!';
        $this->assertEquals($expected, $result);
    }

    public function testRemoveMentionsRemovesAllMentions(): void
    {
        $content = 'Hello @john and @jane!';
        $result = $this->mentionParser->removeMentions($content);

        $this->assertEquals('Hello  and !', $result);
    }

    public function testCountMentionsReturnsCorrectCount(): void
    {
        $testCases = [
            ['No mentions here', 0],
            ['Hello @john', 1],
            ['Hey @alice and @bob', 2],
            ['@a @b @c @d @e', 5],
        ];

        foreach ($testCases as [$content, $expectedCount]) {
            $this->assertEquals($expectedCount, $this->mentionParser->countMentions($content));
        }
    }

    public function testHasMentionsDetectsMentions(): void
    {
        $this->assertFalse($this->mentionParser->hasMentions('No mentions here'));
        $this->assertTrue($this->mentionParser->hasMentions('Hello @john'));
    }

    public function testValidateMentionFormatValidatesCorrectly(): void
    {
        $validMentions = ['@john', '@alice123', '@user_name', '@123valid'];
        $invalidMentions = ['john', '@', '@user-name', '@user name'];

        foreach ($validMentions as $mention) {
            $this->assertTrue($this->mentionParser->validateMentionFormat($mention));
        }

        foreach ($invalidMentions as $mention) {
            $this->assertFalse($this->mentionParser->validateMentionFormat($mention));
        }
    }

    public function testHighlightMentionsAddsHighlightClass(): void
    {
        $content = 'Hello @john and @jane!';
        $result = $this->mentionParser->highlightMentions($content);

        $expected = 'Hello <span class="mention-highlight">@john</span> and <span class="mention-highlight">@jane</span>!';
        $this->assertEquals($expected, $result);
    }

    public function testHighlightMentionsUsesCustomClass(): void
    {
        $content = 'Hello @john!';
        $result = $this->mentionParser->highlightMentions($content, 'custom-highlight');

        $expected = 'Hello <span class="custom-highlight">@john</span>!';
        $this->assertEquals($expected, $result);
    }

    public function testGetMentionStatisticsReturnsCompleteStats(): void
    {
        $content = 'Hey @alice and @bob, tell @alice about this';
        $stats = $this->mentionParser->getMentionStatistics($content);

        $this->assertEquals(2, $stats['total_mentions']);
        $this->assertEquals(2, $stats['unique_users']);
        $this->assertEquals(['alice', 'bob'], $stats['mentioned_users']);
        $this->assertTrue($stats['has_mentions']);
        $this->assertFalse($stats['exceeds_limit']);
        $this->assertIsArray($stats['mention_positions']);
    }

    public function testNormalizeMentionNormalizesProperly(): void
    {
        $testCases = [
            ['@JohnDoe', 'johndoe'],
            ['@ALICE_123', 'alice_123'],
            ['@user-name!', 'username'],
            ['john', 'john'],
            ['@user name', 'username'],
        ];

        foreach ($testCases as [$input, $expected]) {
            $this->assertEquals($expected, $this->mentionParser->normalizeMention($input));
        }
    }

    public function testFormatMentionForDisplayFormatsCorrectly(): void
    {
        $username = 'john';
        $result = $this->mentionParser->formatMentionForDisplay($username);

        $expected = '<a href="/user/john" class="mention">@john</a>';
        $this->assertEquals($expected, $result);
    }

    public function testFormatMentionForDisplayUsesCustomOptions(): void
    {
        $username = 'john';
        $options = [
            'display_name' => 'John Doe',
            'url' => '/profile/john',
            'css_class' => 'user-link',
            'target' => '_blank',
        ];
        $result = $this->mentionParser->formatMentionForDisplay($username, $options);

        $expected = '<a href="/profile/john" class="user-link" target="_blank">@John Doe</a>';
        $this->assertEquals($expected, $result);
    }

    public function testFindMentionContextFindsContext(): void
    {
        $content = 'This is a long comment where we mention @john in the middle of the text and continue writing';
        $contexts = $this->mentionParser->findMentionContext($content, 'john', 20);

        $this->assertCount(1, $contexts);
        $this->assertIsString($contexts[0]['text']);
        $this->assertStringContainsString('@john', $contexts[0]['text']);
        $this->assertIsInt($contexts[0]['mention_position']);
        $this->assertIsInt($contexts[0]['full_position']);
    }

    protected function setUp(): void
    {
        $this->mentionParser = new MentionParserService();
    }
}
