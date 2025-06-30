<?php

namespace Tourze\CommentBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Event\CommentUpdatedEvent;

class CommentUpdatedEventTest extends TestCase
{
    public function test_construct_setsComment(): void
    {
        $comment = new Comment();
        $event = new CommentUpdatedEvent($comment);

        $this->assertSame($comment, $event->getComment());
    }

    public function test_getComment_returnsCorrectComment(): void
    {
        $comment = new Comment();
        $comment->setContent('Updated comment');
        
        $event = new CommentUpdatedEvent($comment);
        
        $this->assertSame($comment, $event->getComment());
        $this->assertEquals('Updated comment', $event->getComment()->getContent());
    }
}