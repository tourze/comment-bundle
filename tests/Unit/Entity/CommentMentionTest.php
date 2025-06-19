<?php

namespace Tourze\CommentBundle\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentMention;

class CommentMentionTest extends TestCase
{
    public function test_construct_setsDefaults(): void
    {
        $mention = new CommentMention();

        $this->assertNull($mention->getId());
        $this->assertFalse($mention->isNotified());
        $this->assertInstanceOf(\DateTimeImmutable::class, $mention->getCreateTime());
        $this->assertNull($mention->getNotifyTime());
        $this->assertNull($mention->getMentionedUserName());
    }

    public function test_settersAndGetters_workCorrectly(): void
    {
        $mention = new CommentMention();
        $comment = new Comment();
        $now = new \DateTimeImmutable();

        $mention->setComment($comment);
        $mention->setMentionedUserId('user123');
        $mention->setMentionedUserName('Test User');
        $mention->setCreateTime($now);
        $mention->setNotifyTime($now);

        $this->assertEquals($comment, $mention->getComment());
        $this->assertEquals('user123', $mention->getMentionedUserId());
        $this->assertEquals('Test User', $mention->getMentionedUserName());
        $this->assertEquals($now, $mention->getCreateTime());
        $this->assertEquals($now, $mention->getNotifyTime());
    }

    public function test_setIsNotified_automaticallySetNotifiedAt(): void
    {
        $mention = new CommentMention();

        $this->assertNull($mention->getNotifyTime());
        $this->assertFalse($mention->isNotified());

        $mention->setNotified(true);

        $this->assertTrue($mention->isNotified());
        $this->assertInstanceOf(\DateTimeImmutable::class, $mention->getNotifyTime());
    }

    public function test_setIsNotified_doesNotOverrideExistingNotifiedAt(): void
    {
        $mention = new CommentMention();
        $existingDate = new \DateTimeImmutable('2023-01-01 12:00:00');
        
        $mention->setNotifyTime($existingDate);
        $mention->setNotified(true);

        $this->assertEquals($existingDate, $mention->getNotifyTime());
    }

    public function test_setIsNotified_toFalse_doesNotChangeNotifiedAt(): void
    {
        $mention = new CommentMention();
        $mention->setNotified(true);
        
        $notifiedAt = $mention->getNotifyTime();
        $mention->setNotified(false);

        $this->assertFalse($mention->isNotified());
        $this->assertEquals($notifiedAt, $mention->getNotifyTime());
    }

    public function test_mentionWithComment_relationshipWorks(): void
    {
        $comment = new Comment();
        $comment->setTargetType('article');
        $comment->setTargetId('123');
        $comment->setContent('Hello @user123!');

        $mention = new CommentMention();
        $mention->setComment($comment);
        $mention->setMentionedUserId('user123');
        $mention->setMentionedUserName('John Doe');

        $this->assertEquals($comment, $mention->getComment());
        $this->assertEquals('user123', $mention->getMentionedUserId());
        $this->assertEquals('John Doe', $mention->getMentionedUserName());
    }

    public function test_fluentInterface_worksCorrectly(): void
    {
        $comment = new Comment();
        $now = new \DateTimeImmutable();

        $mention = (new CommentMention())
            ->setComment($comment)
            ->setMentionedUserId('user456')
            ->setMentionedUserName('Jane Doe')
            ->setNotified(false)
            ->setCreateTime($now)
            ->setNotifyTime($now);

        $this->assertEquals($comment, $mention->getComment());
        $this->assertEquals('user456', $mention->getMentionedUserId());
        $this->assertEquals('Jane Doe', $mention->getMentionedUserName());
        $this->assertFalse($mention->isNotified());
        $this->assertEquals($now, $mention->getCreateTime());
        $this->assertEquals($now, $mention->getNotifyTime());
    }

    public function test_mentionNotificationWorkflow(): void
    {
        $comment = new Comment();
        $comment->setContent('Thanks @alice for your help!');

        $mention = new CommentMention();
        $mention->setComment($comment);
        $mention->setMentionedUserId('alice');
        $mention->setMentionedUserName('Alice Smith');

        // 初始状态：未通知
        $this->assertFalse($mention->isNotified());
        $this->assertNull($mention->getNotifyTime());

        // 发送通知
        $mention->setNotified(true);

        // 验证通知状态
        $this->assertTrue($mention->isNotified());
        $this->assertInstanceOf(\DateTimeImmutable::class, $mention->getNotifyTime());
        $this->assertEqualsWithDelta(
            time(),
            $mention->getNotifyTime()->getTimestamp(),
            2 // 允许2秒的误差
        );
    }

    public function test_mentionWithNullUserName_handledCorrectly(): void
    {
        $mention = new CommentMention();
        $mention->setMentionedUserId('user789');
        $mention->setMentionedUserName(null);

        $this->assertEquals('user789', $mention->getMentionedUserId());
        $this->assertNull($mention->getMentionedUserName());
    }

    public function test_createdAtIsImmutable(): void
    {
        $mention = new CommentMention();
        $mention->setCreateTime(new \DateTimeImmutable('2023-01-01 10:00:00'));
        $originalTime = $mention->getCreateTime()->format('Y-m-d H:i:s');

        // DateTimeImmutable 是不可变的，所以修改不会影响原始对象
        $newTime = $mention->getCreateTime()->modify('+1 hour');

        // 验证原时间未被修改
        $this->assertEquals($originalTime, $mention->getCreateTime()->format('Y-m-d H:i:s'));
        $this->assertEquals('2023-01-01 11:00:00', $newTime->format('Y-m-d H:i:s'));
    }

    public function test_multipleMentionsFromSameComment(): void
    {
        $comment = new Comment();
        $comment->setContent('Thanks @alice and @bob for your help!');

        $mention1 = new CommentMention();
        $mention1->setComment($comment);
        $mention1->setMentionedUserId('alice');

        $mention2 = new CommentMention();
        $mention2->setComment($comment);
        $mention2->setMentionedUserId('bob');

        $this->assertEquals($comment, $mention1->getComment());
        $this->assertEquals($comment, $mention2->getComment());
        $this->assertEquals('alice', $mention1->getMentionedUserId());
        $this->assertEquals('bob', $mention2->getMentionedUserId());
        $this->assertNotEquals($mention1->getMentionedUserId(), $mention2->getMentionedUserId());
    }
}