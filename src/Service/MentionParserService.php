<?php

namespace Tourze\CommentBundle\Service;

readonly class MentionParserService
{
    public function __construct(
        private string $mentionPattern = '/@([a-zA-Z0-9_]+)/',
        private int $maxMentionsPerComment = 10,
    ) {
    }

    public function replaceMentionsWithLinks(string $content, ?callable $linkGenerator = null): string
    {
        if (null === $linkGenerator) {
            $linkGenerator = function (string $username): string {
                return sprintf('<a href="/user/%s" class="mention">@%s</a>', $username, $username);
            };
        }

        return preg_replace_callback(
            $this->mentionPattern,
            function (array $matches) use ($linkGenerator): string {
                $result = $linkGenerator($matches[1]);

                return is_string($result) ? $result : '';
            },
            $content
        ) ?? $content;
    }

    /**
     * @return list<string>
     */
    public function extractMentionedUsernames(string $content): array
    {
        $mentions = $this->parseMentions($content);

        $usernames = [];
        foreach ($mentions as $mention) {
            if (isset($mention['user_id']) && is_string($mention['user_id'])) {
                $usernames[] = $mention['user_id'];
            }
        }

        return $usernames;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function parseMentions(string $content): array
    {
        $matches = $this->extractMentionMatches($content);
        if ([] === $matches) {
            return [];
        }

        return $this->buildMentionArray($content, $matches);
    }

    /**
     * @return list<array<int, string>>
     */
    private function extractMentionMatches(string $content): array
    {
        $matches = [];
        $matchCount = preg_match_all($this->mentionPattern, $content, $matches, PREG_SET_ORDER);

        if (false === $matchCount || 0 === $matchCount) {
            return [];
        }

        /** @var list<array<int, string>> $matches */
        return $matches;
    }

    /**
     * @param array<int, array<int, string>> $matches
     * @return array<int, array<string, mixed>>
     */
    private function buildMentionArray(string $content, array $matches): array
    {
        $mentions = [];
        $processedUsers = [];
        $count = 0;

        foreach ($matches as $match) {
            if ($this->shouldStopProcessing($count)) {
                break;
            }

            $username = $match[1];
            if ($this->shouldSkipMention($username, $processedUsers)) {
                continue;
            }

            $mention = $this->createMentionData($content, $match, $username);
            if (null !== $mention) {
                $mentions[] = $mention;
                $processedUsers[] = $username;
                ++$count;
            }
        }

        return $mentions;
    }

    private function shouldStopProcessing(int $count): bool
    {
        return $count >= $this->maxMentionsPerComment;
    }

    /**
     * @param array<int, string> $processedUsers
     */
    private function shouldSkipMention(string $username, array $processedUsers): bool
    {
        return in_array($username, $processedUsers, true);
    }

    /**
     * @param array<int, string> $match
     * @return array<string, mixed>|null
     */
    private function createMentionData(string $content, array $match, string $username): ?array
    {
        $position = mb_strpos($content, $match[0]);
        if (false === $position) {
            return null;
        }

        return [
            'user_id' => $username,
            'user_name' => $username,
            'original_text' => $match[0],
            'position' => $position,
        ];
    }

    public function removeMentions(string $content): string
    {
        return preg_replace($this->mentionPattern, '', $content) ?? $content;
    }

    public function hasMentions(string $content): bool
    {
        return $this->countMentions($content) > 0;
    }

    public function countMentions(string $content): int
    {
        return count($this->parseMentions($content));
    }

    public function validateMentionFormat(string $mention): bool
    {
        return 1 === preg_match('/^@[a-zA-Z0-9_]+$/', $mention);
    }

    public function highlightMentions(string $content, string $highlightClass = 'mention-highlight'): string
    {
        return preg_replace(
            $this->mentionPattern,
            '<span class="' . $highlightClass . '">$0</span>',
            $content
        ) ?? $content;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMentionStatistics(string $content): array
    {
        $mentions = $this->parseMentions($content);
        $usernames = array_column($mentions, 'user_id');

        return [
            'total_mentions' => count($mentions),
            'unique_users' => count(array_unique($usernames)),
            'mentioned_users' => array_unique($usernames),
            'mention_positions' => array_column($mentions, 'position'),
            'has_mentions' => count($mentions) > 0,
            'exceeds_limit' => count($mentions) > $this->maxMentionsPerComment,
        ];
    }

    public function normalizeMention(string $mention): string
    {
        // 移除@ 符号，只保留用户名
        $username = ltrim($mention, '@');

        // 转换为小写
        $username = mb_strtolower($username);

        // 移除特殊字符，只保留字母、数字和下划线
        return preg_replace('/[^a-zA-Z0-9_]/', '', $username) ?? '';
    }

    /**
     * @param array<string, mixed> $options
     */
    public function formatMentionForDisplay(string $username, array $options = []): string
    {
        $displayName = is_string($options['display_name'] ?? null) ? $options['display_name'] : $username;
        $url = is_string($options['url'] ?? null) ? $options['url'] : "/user/{$username}";
        $cssClass = is_string($options['css_class'] ?? null) ? $options['css_class'] : 'mention';
        $target = is_string($options['target'] ?? null) ? $options['target'] : '';

        $targetAttr = ('' !== $target) ? " target=\"{$target}\"" : '';

        return sprintf(
            '<a href="%s" class="%s"%s>@%s</a>',
            htmlspecialchars($url),
            htmlspecialchars($cssClass),
            $targetAttr,
            htmlspecialchars($displayName)
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findMentionContext(string $content, string $username, int $contextLength = 50): array
    {
        $contexts = [];
        $mentions = $this->parseMentions($content);

        foreach ($mentions as $mention) {
            if (isset($mention['user_id']) && $mention['user_id'] === $username && isset($mention['position']) && is_int($mention['position'])) {
                $position = $mention['position'];
                $start = max(0, $position - $contextLength);
                $originalText = is_string($mention['original_text']) ? $mention['original_text'] : '';
                $end = min(mb_strlen($content), $position + mb_strlen($originalText) + $contextLength);
                $start = (int) $start;

                $contexts[] = [
                    'text' => mb_substr($content, $start, $end - $start),
                    'mention_position' => $position - $start,
                    'full_position' => $position,
                ];
            }
        }

        return $contexts;
    }
}
