# CommentBundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/comment-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/comment-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/comment-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/comment-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/comment-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/comment-bundle)
[![License](https://img.shields.io/packagist/l/tourze/comment-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/comment-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![Code Coverage](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)

一个功能完善的 Symfony 评论系统，支持审核、投票、@提及和通知功能。

## 目录

- [功能特性](#功能特性)
- [系统要求](#系统要求)
- [安装](#安装)
- [配置](#配置)
- [使用方法](#使用方法)
- [高级用法](#高级用法)
- [事件](#事件)
- [CLI 命令](#cli-命令)
- [测试](#测试)
- [贡献指南](#贡献指南)
- [安全问题](#安全问题)
- [许可证](#许可证)
- [作者](#作者)

## 功能特性

### 核心评论管理
- **增删改查操作**：创建、读取、更新和软删除评论
- **嵌套回复**：支持多级评论回复，可配置嵌套深度限制
- **评论状态**：待审核、已批准、已拒绝、已删除状态管理
- **置顶功能**：将重要评论置顶显示
- **灵活的目标关联**：使用 target_type 和 target_id 关联任意内容类型

### 内容审核与过滤
- **自动内容审核**：敏感词检测和过滤
- **垃圾内容检测**：识别垃圾邮件、广告和违法内容
- **内容质量控制**：
  - 长度验证（1-5000 字符）
  - 过度重复检测
  - 可疑链接检测
- **人工审核**：提供 CLI 工具进行批量评论审核

### 社交互动
- **投票系统**：点赞/踩功能，自动统计投票数
- **@提及功能**：
  - 自动解析用户名
  - 提及通知
  - 可配置提及限制（默认每条评论 10 个）
  - 提及高亮和链接生成
- **通知系统**：
  - 回复通知
  - 审核通过通知
  - @提及通知
  - 新评论管理员通知

### 数据查询与统计
- **灵活的查询 API**：
  - 按目标查询
  - 按作者查询
  - 按 IP 地址查询
  - 内容搜索
  - 热门评论
  - 最新评论
- **统计功能**：
  - 各状态评论总数
  - 点赞/踩总数
  - 审核率统计

## 系统要求

- PHP 8.1+
- Symfony 6.4+
- Doctrine ORM 3.0+

## 安装

```bash
composer require tourze/comment-bundle
```

## 配置

在 `config/bundles.php` 中注册 bundle：

```php
return [
    // ...
    Tourze\CommentBundle\CommentBundle::class => ['all' => true],
];
```

## 使用方法

### 基本评论创建

```php
use Tourze\CommentBundle\Service\CommentService;

// 创建评论
$comment = $commentService->createComment([
    'target_type' => 'article',
    'target_id' => '123',
    'content' => '文章写得很好！',
    'author_id' => 'user456',
    'author_name' => '张三',
    'author_email' => 'zhangsan@example.com',
]);

// 创建回复
$reply = $commentService->createComment([
    'target_type' => 'article',
    'target_id' => '123',
    'content' => '感谢您的反馈！',
    'author_id' => 'user789',
    'parent_id' => $comment->getId(),
]);
```

### 评论审核

```php
// 基于内容自动审核
$isSafe = $contentFilterService->isContentSafe($content);

// 人工审核
$commentService->approveComment($comment);
$commentService->rejectComment($comment);
$commentService->deleteComment($comment, true); // 软删除
```

### 投票功能

```php
use Tourze\CommentBundle\Service\CommentVoteService;

// 点赞评论
$vote = $commentVoteService->vote($comment, 'user123', 'like');

// 更改投票
$vote = $commentVoteService->vote($comment, 'user123', 'dislike');

// 取消投票
$commentVoteService->removeVote($comment, 'user123');
```

### 查询评论

```php
// 获取特定目标的评论
$comments = $commentService->getCommentsByTarget('article', '123', [
    'status' => 'approved',
    'includeReplies' => true,
    'orderBy' => 'createTime',
    'order' => 'DESC',
]);

// 搜索评论
$results = $commentService->searchComments('关键词');

// 获取热门评论
$popular = $commentService->getPopularComments('article', '123', 5);
```

## 高级用法

### 自定义内容过滤

```php
use Tourze\CommentBundle\Service\ContentFilterService;

// 创建自定义过滤器
class CustomContentFilter extends ContentFilterService
{
    public function isContentSafe(string $content): bool
    {
        // 自定义过滤逻辑
        if ($this->containsBannedWords($content)) {
            return false;
        }
        
        return parent::isContentSafe($content);
    }
    
    private function containsBannedWords(string $content): bool
    {
        $bannedWords = ['垃圾', '广告'];
        foreach ($bannedWords as $word) {
            if (stripos($content, $word) !== false) {
                return true;
            }
        }
        return false;
    }
}
```

### 事件监听器

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
        // 发送邮件通知
        // 记录评论创建日志
        // 更新缓存
    }
}
```

### 批量操作

```php
// 批量审核评论
$pendingComments = $commentService->getPendingComments();
foreach ($pendingComments as $comment) {
    if ($this->shouldAutoApprove($comment)) {
        $commentService->approveComment($comment);
    }
}

// 批量删除垃圾评论
$spamComments = $commentService->getCommentsByIp('127.0.0.1');
foreach ($spamComments as $comment) {
    $commentService->deleteComment($comment, false); // 硬删除
}
```

### 统计和分析

```php
// 获取综合统计信息
$stats = $commentService->getStatistics('article', '123');
/*
返回：
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

// 跟踪用户参与度
$userComments = $commentService->getCommentsByAuthor('user123');
$userStats = [
    'comment_count' => count($userComments),
    'average_score' => array_sum(array_map(fn($c) => $c->getScore(), $userComments)) / count($userComments),
];
```

## 事件

该 bundle 会触发以下事件：

- `CommentCreatedEvent`：评论创建时触发
- `CommentUpdatedEvent`：评论更新时触发
- `CommentDeletedEvent`：评论删除时触发
- `CommentApprovedEvent`：评论批准时触发
- `CommentVotedEvent`：评论被投票时触发

## CLI 命令

```bash
# 查看评论统计
php bin/console comment:statistics

# 按目标过滤
php bin/console comment:statistics --target-type=article --target-id=123

# 审核评论
php bin/console comment:moderation --pending
php bin/console comment:moderation --approve 456
php bin/console comment:moderation --auto-approve
```

## 测试

```bash
# 运行测试
./vendor/bin/phpunit packages/comment-bundle/tests

# 运行静态分析
php -d memory_limit=2G ./vendor/bin/phpstan analyse packages/comment-bundle
```

## 贡献指南

详情请参阅 [CONTRIBUTING.md](CONTRIBUTING.md)。

## 安全问题

如果您发现了任何安全相关问题，请发送邮件到 security@tourze.com，而不是使用问题跟踪器。

## 许可证

该 bundle 在 MIT 许可证下发布。详见 [LICENSE](LICENSE) 文件。

## 作者

- [Tourze 团队](https://github.com/tourze)