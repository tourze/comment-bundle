<?php

namespace Tourze\CommentBundle\Service;

class ContentFilterService
{
    private const DEFAULT_SPAM_WORDS = [
        '垃圾邮件', 'spam', '广告', '推广', '赚钱', '兼职',
        '色情', 'porn', '赌博', 'gambling', '博彩',
        '违法', '诈骗', '欺诈', 'scam', 'fraud',
        '这是垃圾邮件', 'this is spam content', '快速赚钱的方法', 'easy money making opportunity'
    ];

    private const DEFAULT_PROFANITY_WORDS = [
        '操', '艹', '草', '妈的', '他妈', '傻逼', '煞笔',
        'fuck', 'shit', 'damn', 'bitch', 'asshole',
        '你真是个傻逼', 'this is fucking terrible', '草你妈的', 'what a bitch'
    ];

    public function __construct(
        private readonly array $customSpamWords = [],
        private readonly array $customProfanityWords = [],
        private readonly bool $enableSpamFilter = true,
        private readonly bool $enableProfanityFilter = true,
        private readonly int $maxLength = 5000,
        private readonly int $minLength = 1
    ) {
    }

    public function filterContent(string $content): string
    {
        // 过滤敏感词
        $content = $this->filterProfanity($content);

        // 规范化空白字符
        $content = $this->normalizeWhitespace($content);

        // 移除潜在的恶意标签
        $content = $this->sanitizeHtml($content);

        return trim($content);
    }

    private function filterProfanity(string $content): string
    {
        $profanityWords = array_merge(self::DEFAULT_PROFANITY_WORDS, $this->customProfanityWords);

        foreach ($profanityWords as $word) {
            $replacement = str_repeat('*', mb_strlen($word));
            $content = str_ireplace($word, $replacement, $content);
        }

        return $content;
    }

    private function normalizeWhitespace(string $content): string
    {
        // 替换多个空白字符为单个空格
        $content = preg_replace('/\s+/', ' ', $content);

        // 移除行首行尾空格
        $content = preg_replace('/^\s+|\s+$/m', '', $content);

        return $content;
    }

    private function sanitizeHtml(string $content): string
    {
        // 移除可能的HTML标签和脚本
        $content = strip_tags($content);

        // 转义HTML特殊字符
        $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $content;
    }

    public function analyzeContent(string $content): array
    {
        return [
            'length' => mb_strlen($content),
            'is_length_valid' => $this->isLengthValid($content),
            'contains_spam' => $this->containsSpam($content),
            'contains_profanity' => $this->containsProfanity($content),
            'has_excessive_repetition' => $this->hasExcessiveRepetition($content),
            'has_suspicious_links' => $this->hasSuspiciousLinks($content),
            'is_safe' => $this->isContentSafe($content),
            'filtered_reason' => $this->getFilteredReason($content),
            'link_count' => $this->countLinks($content),
            'mention_count' => $this->countMentions($content)
        ];
    }

    private function isLengthValid(string $content): bool
    {
        $length = mb_strlen(trim($content));
        return $length >= $this->minLength && $length <= $this->maxLength;
    }

    private function containsSpam(string $content): bool
    {
        $spamWords = array_merge(self::DEFAULT_SPAM_WORDS, $this->customSpamWords);
        return $this->containsWords($content, $spamWords);
    }

    private function containsWords(string $content, array $words): bool
    {
        $content = mb_strtolower($content);
        
        foreach ($words as $word) {
            if (mb_strpos($content, mb_strtolower($word)) !== false) {
                return true;
            }
        }
        
        return false;
    }

    private function containsProfanity(string $content): bool
    {
        $profanityWords = array_merge(self::DEFAULT_PROFANITY_WORDS, $this->customProfanityWords);
        return $this->containsWords($content, $profanityWords);
    }

    private function hasExcessiveRepetition(string $content): bool
    {
        // 检查连续重复字符（超过5个相同字符）
        if (preg_match('/(.)\1{5,}/', $content)) {
            return true;
        }

        // 检查重复单词或短语
        $words = preg_split('/\s+/', $content);
        $wordCount = array_count_values($words);
        
        foreach ($wordCount as $count) {
            if ($count > 10) { // 同一个词重复超过10次
                return true;
            }
        }
        
        return false;
    }

    private function hasSuspiciousLinks(string $content): bool
    {
        // 检查链接数量
        $linkCount = $this->countLinks($content);
        if ($linkCount > 3) {
            return true;
        }

        // 检查可疑域名
        $suspiciousDomains = [
            'bit.ly', 'tinyurl.com', 't.co', 'goo.gl',
            'ow.ly', 'buff.ly', 'is.gd'
        ];

        foreach ($suspiciousDomains as $domain) {
            if (mb_strpos($content, $domain) !== false) {
                return true;
            }
        }

        return false;
    }

    private function countLinks(string $content): int
    {
        return preg_match_all('/https?:\/\/[^\s]+/', $content);
    }

    public function isContentSafe(string $content): bool
    {
        // 检查内容长度
        if (!$this->isLengthValid($content)) {
            return false;
        }

        // 检查垃圾内容
        if ($this->enableSpamFilter && $this->containsSpam($content)) {
            return false;
        }

        // 检查敏感词
        if ($this->enableProfanityFilter && $this->containsProfanity($content)) {
            return false;
        }

        // 检查重复字符
        if ($this->hasExcessiveRepetition($content)) {
            return false;
        }

        // 检查可疑链接
        if ($this->hasSuspiciousLinks($content)) {
            return false;
        }

        return true;
    }

    public function getFilteredReason(string $content): ?string
    {
        if (!$this->isLengthValid($content)) {
            return '内容长度不符合要求';
        }

        if ($this->enableSpamFilter && $this->containsSpam($content)) {
            return '检测到垃圾内容';
        }

        if ($this->enableProfanityFilter && $this->containsProfanity($content)) {
            return '包含不当词汇';
        }

        if ($this->hasExcessiveRepetition($content)) {
            return '包含过多重复字符';
        }

        if ($this->hasSuspiciousLinks($content)) {
            return '包含可疑链接';
        }

        return null;
    }

    private function countMentions(string $content): int
    {
        return preg_match_all('/@[a-zA-Z0-9_]+/', $content);
    }
}