<?php

namespace Tourze\CommentBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\CommentStatus;

#[When(env: 'test')]
class CommentFixtures extends Fixture implements FixtureGroupInterface
{
    public const APPROVED_COMMENT_REFERENCE = 'approved-comment';
    public const PENDING_COMMENT_REFERENCE = 'pending-comment';
    public const PARENT_COMMENT_REFERENCE = 'parent-comment';
    public const REPLY_COMMENT_REFERENCE = 'reply-comment';

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();

        $approvedComment = new Comment();
        $approvedComment->setTargetType('article');
        $approvedComment->setTargetId('article-123');
        $approvedComment->setContent('这是一个已审核通过的评论，内容质量很高。');
        $approvedComment->setAuthorId('user-001');
        $approvedComment->setAuthorName('张三');
        $approvedComment->setAuthorEmail('zhangsan@test.local');
        $approvedComment->setAuthorIp('192.168.1.100');
        $approvedComment->setUserAgent('Mozilla/5.0 (compatible test browser)');
        $approvedComment->setStatus(CommentStatus::APPROVED);
        $approvedComment->setLikesCount(15);
        $approvedComment->setDislikesCount(2);
        $approvedComment->setPinned(true);
        $approvedComment->setCreateTime($now->modify('-2 hours'));

        $manager->persist($approvedComment);
        $this->addReference(self::APPROVED_COMMENT_REFERENCE, $approvedComment);

        $pendingComment = new Comment();
        $pendingComment->setTargetType('product');
        $pendingComment->setTargetId('product-456');
        $pendingComment->setContent('这是一个等待审核的评论。');
        $pendingComment->setAuthorId('user-002');
        $pendingComment->setAuthorName('李四');
        $pendingComment->setAuthorEmail('lisi@test.local');
        $pendingComment->setAuthorIp('192.168.1.101');
        $pendingComment->setStatus(CommentStatus::PENDING);
        $pendingComment->setCreateTime($now->modify('-1 hour'));

        $manager->persist($pendingComment);
        $this->addReference(self::PENDING_COMMENT_REFERENCE, $pendingComment);

        $parentComment = new Comment();
        $parentComment->setTargetType('article');
        $parentComment->setTargetId('article-789');
        $parentComment->setContent('这是一个父级评论，会有回复。');
        $parentComment->setAuthorId('user-003');
        $parentComment->setAuthorName('王五');
        $parentComment->setAuthorEmail('wangwu@test.local');
        $parentComment->setAuthorIp('192.168.1.102');
        $parentComment->setStatus(CommentStatus::APPROVED);
        $parentComment->setLikesCount(8);
        $parentComment->setCreateTime($now->modify('-3 hours'));

        $manager->persist($parentComment);
        $this->addReference(self::PARENT_COMMENT_REFERENCE, $parentComment);

        $replyComment = new Comment();
        $replyComment->setTargetType('article');
        $replyComment->setTargetId('article-789');
        $replyComment->setContent('这是对上面评论的回复。');
        $replyComment->setParent($parentComment);
        $replyComment->setStatus(CommentStatus::APPROVED);
        $replyComment->setLikesCount(3);
        $replyComment->setCreateTime($now->modify('-2 hours'));

        $manager->persist($replyComment);
        $this->addReference(self::REPLY_COMMENT_REFERENCE, $replyComment);

        $anonymousComment = new Comment();
        $anonymousComment->setTargetType('blog');
        $anonymousComment->setTargetId('blog-999');
        $anonymousComment->setContent('这是一个匿名评论。');
        $anonymousComment->setAuthorName('匿名用户');
        $anonymousComment->setAuthorIp('192.168.1.103');
        $anonymousComment->setStatus(CommentStatus::APPROVED);
        $anonymousComment->setCreateTime($now->modify('-4 hours'));

        $manager->persist($anonymousComment);

        $deletedComment = new Comment();
        $deletedComment->setTargetType('video');
        $deletedComment->setTargetId('video-111');
        $deletedComment->setContent('这个评论已被删除。');
        $deletedComment->setAuthorId('user-004');
        $deletedComment->setAuthorName('赵六');
        $deletedComment->setStatus(CommentStatus::DELETED);
        $deletedComment->setDeleteTime($now->modify('-1 hour'));
        $deletedComment->setCreateTime($now->modify('-5 hours'));

        $manager->persist($deletedComment);

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['comment-bundle', 'test'];
    }
}
