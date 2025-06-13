<?php

namespace Tourze\CommentBundle\Service;

class MentionParserService
{
    public function __construct(
        private readonly string $mentionPattern = '/@([a-zA-Z0-9_]+)/',
        private readonly int $maxMentionsPerComment = 10
    ) {
    }

    public function replaceMentionsWithLinks(string $content, callable $linkGenerator = null): string
    {
        if ($linkGenerator === null) {
            $linkGenerator = function (string $username): string {
                return sprintf('<a href="/user/%s" class="mention">@%s</a>', $username, $username);
            };
        }

        return preg_replace_callback(
            $this->mentionPattern,
            function ($matches) use ($linkGenerator) {
                return $linkGenerator($matches[1]);
            },
            $content
        );
    }

    public function extractMentionedUsernames(string $content): array
    {
        $mentions = $this->parseMentions($content);
        return array_column($mentions, 'user_id');
    }

    public function parseMentions(string $content): array
    {
        $mentions = [];
        $matches = [];

        if (preg_match_all($this->mentionPattern, $content, $matches, PREG_SET_ORDER)) {
            $count = 0;
            foreach ($matches as $match) {
                if ($count >= $this->maxMentionsPerComment) {
                    break;
                }

                $username = $match[1];

                // 避免重复提及
                if (!$this->isMentionExists($mentions, $username)) {
                    $mentions[] = [
                        'user_id' => $username,
                        'user_name' => $username,
                        'original_text' => $match[0],
                        'position' => mb_strpos($content, $match[0])
                    ];
                    $count++;
                }
            }
        }

        return $mentions;
    }

    private function isMentionExists(array $mentions, string $username): bool
    {
        foreach ($mentions as $mention) {
            if ($mention['user_id'] === $username) {
                return true;
            }
        }
        return false;
    }

    public function removeMentions(string $content): string
    {
        return preg_replace($this->mentionPattern, '', $content);
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
        return preg_match('/^@[a-zA-Z0-9_]+$/', $mention) === 1;
    }

    public function highlightMentions(string $content, string $highlightClass = 'mention-highlight'): string
    {
        return preg_replace(
            $this->mentionPattern,
            '<span class="' . $highlightClass . '">$0</span>',
            $content
        );
    }

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
            'exceeds_limit' => count($mentions) > $this->maxMentionsPerComment
        ];
    }

    public function normalizeMention(string $mention): string
    {
        // 移除@ 符号，只保留用户名
        $username = ltrim($mention, '@');

        // 转换为小写
        $username = mb_strtolower($username);

        // 移除特殊字符，只保留字母、数字和下划线
        $username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);

        return $username;
    }

    public function formatMentionForDisplay(string $username, array $options = []): string
    {
        $displayName = $options['display_name'] ?? $username;
        $url = $options['url'] ?? "/user/{$username}";
        $cssClass = $options['css_class'] ?? 'mention';
        $target = $options['target'] ?? '';

        $targetAttr = $target ? " target=\"{$target}\"" : '';

        return sprintf(
            '<a href="%s" class="%s"%s>@%s</a>',
            htmlspecialchars($url),
            htmlspecialchars($cssClass),
            $targetAttr,
            htmlspecialchars($displayName)
        );
    }

    public function findMentionContext(string $content, string $username, int $contextLength = 50): array
    {
        $contexts = [];
        $mentions = $this->parseMentions($content);

        foreach ($mentions as $mention) {
            if ($mention['user_id'] === $username) {
                $position = $mention['position'];
                $start = max(0, $position - $contextLength);
                $end = min(mb_strlen($content), $position + mb_strlen($mention['original_text']) + $contextLength);

                $contexts[] = [
                    'text' => mb_substr($content, $start, $end - $start),
                    'mention_position' => $position - $start,
                    'full_position' => $position
                ];
            }
        }

        return $contexts;
    }
}