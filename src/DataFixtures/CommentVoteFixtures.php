<?php

namespace Tourze\CommentBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\When;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentVote;
use Tourze\CommentBundle\Enum\VoteType;

#[When(env: 'test')]
class CommentVoteFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const LIKE_VOTE_REFERENCE = 'like-vote';
    public const DISLIKE_VOTE_REFERENCE = 'dislike-vote';
    public const ANONYMOUS_VOTE_REFERENCE = 'anonymous-vote';

    public function load(ObjectManager $manager): void
    {
        $now = new \DateTimeImmutable();

        $approvedComment = $this->getReference(CommentFixtures::APPROVED_COMMENT_REFERENCE, Comment::class);
        $parentComment = $this->getReference(CommentFixtures::PARENT_COMMENT_REFERENCE, Comment::class);
        $replyComment = $this->getReference(CommentFixtures::REPLY_COMMENT_REFERENCE, Comment::class);

        $likeVote = new CommentVote();
        $likeVote->setComment($approvedComment);
        $likeVote->setVoterId('user-005');
        $likeVote->setVoterIp('192.168.1.105');
        $likeVote->setVoteType(VoteType::LIKE);
        $likeVote->setCreateTime($now->modify('-1 hour'));

        $manager->persist($likeVote);
        $this->addReference(self::LIKE_VOTE_REFERENCE, $likeVote);

        $dislikeVote = new CommentVote();
        $dislikeVote->setComment($approvedComment);
        $dislikeVote->setVoterId('user-006');
        $dislikeVote->setVoterIp('192.168.1.106');
        $dislikeVote->setVoteType(VoteType::DISLIKE);
        $dislikeVote->setCreateTime($now->modify('-45 minutes'));

        $manager->persist($dislikeVote);
        $this->addReference(self::DISLIKE_VOTE_REFERENCE, $dislikeVote);

        $anonymousVote = new CommentVote();
        $anonymousVote->setComment($parentComment);
        $anonymousVote->setVoterIp('192.168.1.107');
        $anonymousVote->setVoteType(VoteType::LIKE);
        $anonymousVote->setCreateTime($now->modify('-30 minutes'));

        $manager->persist($anonymousVote);
        $this->addReference(self::ANONYMOUS_VOTE_REFERENCE, $anonymousVote);

        for ($i = 1; $i <= 5; ++$i) {
            $vote = new CommentVote();
            $vote->setComment($parentComment);
            $vote->setVoterId('user-' . str_pad((string) (100 + $i), 3, '0', STR_PAD_LEFT));
            $vote->setVoterIp('192.168.1.' . (110 + $i));
            $vote->setVoteType(VoteType::LIKE);
            $vote->setCreateTime($now->modify('-' . (10 + $i) . ' minutes'));

            $manager->persist($vote);
        }

        for ($i = 1; $i <= 2; ++$i) {
            $vote = new CommentVote();
            $vote->setComment($parentComment);
            $vote->setVoterId('user-' . str_pad((string) (200 + $i), 3, '0', STR_PAD_LEFT));
            $vote->setVoterIp('192.168.1.' . (120 + $i));
            $vote->setVoteType(VoteType::DISLIKE);
            $vote->setCreateTime($now->modify('-' . (5 + $i) . ' minutes'));

            $manager->persist($vote);
        }

        $replyVote1 = new CommentVote();
        $replyVote1->setComment($replyComment);
        $replyVote1->setVoterId('user-011');
        $replyVote1->setVoterIp('192.168.1.111');
        $replyVote1->setVoteType(VoteType::LIKE);
        $replyVote1->setCreateTime($now->modify('-20 minutes'));

        $manager->persist($replyVote1);

        $replyVote2 = new CommentVote();
        $replyVote2->setComment($replyComment);
        $replyVote2->setVoterIp('192.168.1.112');
        $replyVote2->setVoteType(VoteType::LIKE);
        $replyVote2->setCreateTime($now->modify('-15 minutes'));

        $manager->persist($replyVote2);

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
