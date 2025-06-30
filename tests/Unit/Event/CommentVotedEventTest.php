<?php

namespace Tourze\CommentBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\VoteType;
use Tourze\CommentBundle\Event\CommentVotedEvent;

class CommentVotedEventTest extends TestCase
{
    public function test_construct_setsAllProperties(): void
    {
        $comment = new Comment();
        $voteType = VoteType::LIKE;
        $action = 'added';
        $voterId = 'user123';
        
        $event = new CommentVotedEvent($comment, $voteType, $action, $voterId);

        $this->assertSame($comment, $event->getComment());
        $this->assertEquals($voteType, $event->getVoteType());
        $this->assertEquals($action, $event->getAction());
        $this->assertEquals($voterId, $event->getVoterId());
    }

    public function test_construct_withoutVoterId(): void
    {
        $comment = new Comment();
        $voteType = VoteType::DISLIKE;
        $action = 'removed';
        
        $event = new CommentVotedEvent($comment, $voteType, $action);
        
        $this->assertSame($comment, $event->getComment());
        $this->assertEquals($voteType, $event->getVoteType());
        $this->assertEquals($action, $event->getAction());
        $this->assertNull($event->getVoterId());
    }
}