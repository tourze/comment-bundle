# CommentBundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/comment-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/comment-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/comment-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/comment-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/comment-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/comment-bundle)
[![License](https://img.shields.io/packagist/l/tourze/comment-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/comment-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)

A comprehensive comment system for Symfony applications with moderation, voting, mentions, and notification features.

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Advanced Usage](#advanced-usage)
- [Events](#events)
- [CLI Commands](#cli-commands)
- [Testing](#testing)
- [Contributing](#contributing)
- [Security](#security)
- [License](#license)
- [Authors](#authors)

## Features

### Core Comment Management
- **CRUD Operations**: Create, read, update, and soft delete comments
- **Nested Replies**: Support for multi-level comment replies with configurable depth limits
- **Comment Status**: Pending, approved, rejected, and deleted status management
- **Pinning**: Pin important comments to the top
- **Flexible Targeting**: Associate comments with any content type using target_type and target_id

### Content Moderation & Filtering
- **Automatic Content Moderation**: Sensitive word detection and filtering
- **Spam Detection**: Identify spam, ads, and illegal content
- **Content Quality Control**:
  - Length validation (1-5000 characters)
  - Excessive repetition detection
  - Suspicious link detection
- **Manual Moderation**: CLI tools for bulk comment moderation

### Social Interaction
- **Voting System**: Like/dislike functionality with automatic vote counting
- **@Mentions**:
  - Automatic username parsing
  - Mention notifications
  - Configurable mention limits (default: 10 per comment)
  - Mention highlighting and link generation
- **Notification System**:
  - Reply notifications
  - Approval notifications
  - Mention notifications
  - Admin notifications for new comments

### Data Querying & Statistics
- **Flexible Query APIs**:
  - Query by target
  - Query by author
  - Query by IP address
  - Content search
  - Popular comments
  - Recent comments
- **Statistics**:
  - Total comments by status
  - Total likes/dislikes
  - Approval rate statistics

## Requirements

- PHP 8.1+
- Symfony 6.4+
- Doctrine ORM 3.0+

## Installation

```bash
composer require tourze/comment-bundle
```

## Configuration

Register the bundle in your `config/bundles.php`:

```php
return [
    // ...
    Tourze\CommentBundle\CommentBundle::class => ['all' => true],
];
```

## Usage

### Basic Comment Creation

```php
use Tourze\CommentBundle\Service\CommentService;

// Create a comment
$comment = $commentService->createComment([
    'target_type' => 'article',
    'target_id' => '123',
    'content' => 'Great article!',
    'author_id' => 'user456',
    'author_name' => 'John Doe',
    'author_email' => 'john@example.com',
]);

// Create a reply
$reply = $commentService->createComment([
    'target_type' => 'article',
    'target_id' => '123',
    'content' => 'Thanks for your feedback!',
    'author_id' => 'user789',
    'parent_id' => $comment->getId(),
]);
```

### Comment Moderation

```php
// Auto-moderate based on content
$isSafe = $contentFilterService->isContentSafe($content);

// Manual moderation
$commentService->approveComment($comment);
$commentService->rejectComment($comment);
$commentService->deleteComment($comment, true); // soft delete
```

### Voting

```php
use Tourze\CommentBundle\Service\CommentVoteService;

// Like a comment
$vote = $commentVoteService->vote($comment, 'user123', 'like');

// Change vote
$vote = $commentVoteService->vote($comment, 'user123', 'dislike');

// Remove vote
$commentVoteService->removeVote($comment, 'user123');
```

### Querying Comments

```php
// Get comments for a specific target
$comments = $commentService->getCommentsByTarget('article', '123', [
    'status' => 'approved',
    'includeReplies' => true,
    'orderBy' => 'createTime',
    'order' => 'DESC',
]);

// Search comments
$results = $commentService->searchComments('keyword');

// Get popular comments
$popular = $commentService->getPopularComments('article', '123', 5);
```

## Advanced Usage

### Custom Content Filtering

```php
use Tourze\CommentBundle\Service\ContentFilterService;

// Create custom filter
class CustomContentFilter extends ContentFilterService
{
    public function isContentSafe(string $content): bool
    {
        // Custom filtering logic
        if ($this->containsBannedWords($content)) {
            return false;
        }
        
        return parent::isContentSafe($content);
    }
    
    private function containsBannedWords(string $content): bool
    {
        $bannedWords = ['spam', 'scam'];
        foreach ($bannedWords as $word) {
            if (stripos($content, $word) !== false) {
                return true;
            }
        }
        return false;
    }
}
```

### Event Listeners

```php
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\CommentBundle\Event\CommentCreatedEvent;

class CommentEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CommentCreatedEvent::NAME => 'onCommentCreated',
        ];
    }
    
    public function onCommentCreated(CommentCreatedEvent $event): void
    {
        $comment = $event->getComment();
        // Send email notification
        // Log comment creation
        // Update cache
    }
}
```

### Bulk Operations

```php
// Bulk approve comments
$pendingComments = $commentService->getPendingComments();
foreach ($pendingComments as $comment) {
    if ($this->shouldAutoApprove($comment)) {
        $commentService->approveComment($comment);
    }
}

// Bulk delete spam comments
$spamComments = $commentService->getCommentsByIp('127.0.0.1');
foreach ($spamComments as $comment) {
    $commentService->deleteComment($comment, false); // hard delete
}
```

### Statistics and Analytics

```php
// Get comprehensive statistics
$stats = $commentService->getStatistics('article', '123');
/*
Returns:
[
    'total' => 150,
    'approved' => 145,
    'pending' => 3,
    'rejected' => 2,
    'total_likes' => 1250,
    'total_dislikes' => 50,
    'approval_rate' => 96.67
]
*/

// Track user engagement
$userComments = $commentService->getCommentsByAuthor('user123');
$userStats = [
    'comment_count' => count($userComments),
    'average_score' => array_sum(array_map(fn($c) => $c->getScore(), $userComments)) / count($userComments),
];
```

## Events

The bundle dispatches the following events:

- `CommentCreatedEvent`: When a comment is created
- `CommentUpdatedEvent`: When a comment is updated
- `CommentDeletedEvent`: When a comment is deleted
- `CommentApprovedEvent`: When a comment is approved
- `CommentVotedEvent`: When a comment is voted on

## CLI Commands

```bash
# View comment statistics
php bin/console comment:statistics

# Filter by target
php bin/console comment:statistics --target-type=article --target-id=123

# Moderate comments
php bin/console comment:moderation --pending
php bin/console comment:moderation --approve 456
php bin/console comment:moderation --auto-approve
```

## Testing

```bash
# Run tests
./vendor/bin/phpunit packages/comment-bundle/tests

# Run static analysis
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/comment-bundle
```

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email security@tourze.com instead of using the issue tracker.

## License

This bundle is released under the MIT license. See the [LICENSE](LICENSE) file for details.

## Authors

- [Tourze Team](https://github.com/tourze)