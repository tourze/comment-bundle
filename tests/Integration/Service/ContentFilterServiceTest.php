<?php

namespace Tourze\CommentBundle\Tests\Integration\Service;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Service\ContentFilterService;

class ContentFilterServiceTest extends TestCase
{
    private ContentFilterService $contentFilter;

    public function test_isContentSafe_withValidContent(): void
    {
        $content = 'This is a perfectly normal comment.';

        $this->assertTrue($this->contentFilter->isContentSafe($content));
    }

    public function test_isContentSafe_withTooShortContent(): void
    {
        $content = '';

        $this->assertFalse($this->contentFilter->isContentSafe($content));
    }

    public function test_isContentSafe_withTooLongContent(): void
    {
        $content = str_repeat('a', 6000);

        $this->assertFalse($this->contentFilter->isContentSafe($content));
    }

    public function test_isContentSafe_withSpamWords(): void
    {
        $spamContents = [
            '这是垃圾邮件',
            'This is spam content',
            '快速赚钱的方法',
            'Easy money making opportunity'
        ];

        foreach ($spamContents as $content) {
            $this->assertFalse($this->contentFilter->isContentSafe($content));
        }
    }

    public function test_isContentSafe_withProfanity(): void
    {
        $profaneContents = [
            '你真是个傻逼',
            'This is fucking terrible',
            '草你妈的',
            'What a bitch'
        ];

        foreach ($profaneContents as $content) {
            $this->assertFalse($this->contentFilter->isContentSafe($content));
        }
    }

    public function test_isContentSafe_withExcessiveRepetition(): void
    {
        $repetitiveContents = [
            'aaaaaaaaaaaaa',
            'hahaha hahaha hahaha hahaha hahaha hahaha hahaha hahaha hahaha hahaha hahaha',
            'test test test test test test test test test test test test'
        ];

        foreach ($repetitiveContents as $content) {
            $this->assertFalse($this->contentFilter->isContentSafe($content));
        }
    }

    public function test_isContentSafe_withSuspiciousLinks(): void
    {
        $suspiciousContents = [
            'Check out these links: http://bit.ly/test http://tinyurl.com/test http://goo.gl/test http://ow.ly/test',
            'Visit my website: http://bit.ly/scam-link'
        ];

        foreach ($suspiciousContents as $content) {
            $this->assertFalse($this->contentFilter->isContentSafe($content));
        }
    }

    public function test_filterContent_removesProfanity(): void
    {
        $content = '你真是个傻逼，this is fucking great!';
        $filtered = $this->contentFilter->filterContent($content);

        $this->assertStringContainsString('**', $filtered);
        $this->assertStringNotContainsString('傻逼', $filtered);
        $this->assertStringNotContainsString('fucking', $filtered);
    }

    public function test_filterContent_normalizesWhitespace(): void
    {
        $content = "This   has    multiple     spaces\n\n\nand   newlines";
        $filtered = $this->contentFilter->filterContent($content);

        $this->assertStringNotContainsString('   ', $filtered);
        $this->assertEquals('This has multiple spaces and newlines', $filtered);
    }

    public function test_getFilteredReason_returnsCorrectReason(): void
    {
        $testCases = [
            ['', '内容长度不符合要求'],
            [str_repeat('a', 6000), '内容长度不符合要求'],
            ['这是垃圾邮件', '检测到垃圾内容'],
            ['你是傻逼', '包含不当词汇'],
            ['aaaaaaaaaaaa', '包含过多重复字符'],
            ['Visit http://bit.ly/test', '包含可疑链接'],
            ['This is fine', null]
        ];

        foreach ($testCases as [$content, $expectedReason]) {
            $this->assertEquals($expectedReason, $this->contentFilter->getFilteredReason($content));
        }
    }

    public function test_analyzeContent_returnsCompleteAnalysis(): void
    {
        $content = 'This is a test comment @user123 with a link http://example.com';
        $analysis = $this->contentFilter->analyzeContent($content);

        $this->assertArrayHasKey('length', $analysis);
        $this->assertArrayHasKey('is_length_valid', $analysis);
        $this->assertArrayHasKey('contains_spam', $analysis);
        $this->assertArrayHasKey('contains_profanity', $analysis);
        $this->assertArrayHasKey('has_excessive_repetition', $analysis);
        $this->assertArrayHasKey('has_suspicious_links', $analysis);
        $this->assertArrayHasKey('is_safe', $analysis);
        $this->assertArrayHasKey('filtered_reason', $analysis);
        $this->assertArrayHasKey('link_count', $analysis);
        $this->assertArrayHasKey('mention_count', $analysis);

        $this->assertEquals(mb_strlen($content), $analysis['length']);
        $this->assertTrue($analysis['is_length_valid']);
        $this->assertFalse($analysis['contains_spam']);
        $this->assertFalse($analysis['contains_profanity']);
        $this->assertFalse($analysis['has_excessive_repetition']);
        $this->assertFalse($analysis['has_suspicious_links']);
        $this->assertTrue($analysis['is_safe']);
        $this->assertNull($analysis['filtered_reason']);
    }

    public function test_customConfiguration_works(): void
    {
        $customFilter = new ContentFilterService(
            customSpamWords: ['badword'],
            customProfanityWords: ['badlanguage'],
            enableSpamFilter: true,
            enableProfanityFilter: true,
            maxLength: 100,
            minLength: 5
        );

        $this->assertFalse($customFilter->isContentSafe('This contains badword'));
        $this->assertFalse($customFilter->isContentSafe('This has badlanguage'));
        $this->assertFalse($customFilter->isContentSafe('Hi')); // too short
        $this->assertFalse($customFilter->isContentSafe(str_repeat('a', 101))); // too long
    }

    public function test_disabledFilters_allowContent(): void
    {
        $permissiveFilter = new ContentFilterService(
            enableSpamFilter: false,
            enableProfanityFilter: false
        );

        $this->assertTrue($permissiveFilter->isContentSafe('This is spam content'));
        $this->assertTrue($permissiveFilter->isContentSafe('This is fucking terrible'));
    }

    protected function setUp(): void
    {
        $this->contentFilter = new ContentFilterService();
    }
}