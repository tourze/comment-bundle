<?php

namespace Tourze\CommentBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentMention;

#[When(env: 'test')]
class CommentMentionFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const MENTION_NOTIFIED_REFERENCE = 'mention-notified';
    public const MENTION_PENDING_REFERENCE = 'mention-pending';

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();

        $approvedComment = $this->getReference(CommentFixtures::APPROVED_COMMENT_REFERENCE, Comment::class);
        $parentComment = $this->getReference(CommentFixtures::PARENT_COMMENT_REFERENCE, Comment::class);

        $notifiedMention = new CommentMention();
        $notifiedMention->setComment($approvedComment);
        $notifiedMention->setMentionedUserId('user-007');
        $notifiedMention->setMentionedUserName('小明');
        $notifiedMention->setNotified(true);
        $notifiedMention->setNotifyTime($now->modify('-1 hour'));
        $notifiedMention->setCreateTime($now->modify('-2 hours'));

        $manager->persist($notifiedMention);
        $this->addReference(self::MENTION_NOTIFIED_REFERENCE, $notifiedMention);

        $pendingMention = new CommentMention();
        $pendingMention->setComment($parentComment);
        $pendingMention->setMentionedUserId('user-008');
        $pendingMention->setMentionedUserName('小红');
        $pendingMention->setNotified(false);
        $pendingMention->setCreateTime($now->modify('-30 minutes'));

        $manager->persist($pendingMention);
        $this->addReference(self::MENTION_PENDING_REFERENCE, $pendingMention);

        $multipleMention1 = new CommentMention();
        $multipleMention1->setComment($approvedComment);
        $multipleMention1->setMentionedUserId('user-009');
        $multipleMention1->setMentionedUserName('小李');
        $multipleMention1->setNotified(true);
        $multipleMention1->setNotifyTime($now->modify('-30 minutes'));
        $multipleMention1->setCreateTime($now->modify('-2 hours'));

        $manager->persist($multipleMention1);

        $multipleMention2 = new CommentMention();
        $multipleMention2->setComment($approvedComment);
        $multipleMention2->setMentionedUserId('user-010');
        $multipleMention2->setMentionedUserName('小王');
        $multipleMention2->setNotified(false);
        $multipleMention2->setCreateTime($now->modify('-2 hours'));

        $manager->persist($multipleMention2);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CommentFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['comment-bundle', 'test'];
    }
}
