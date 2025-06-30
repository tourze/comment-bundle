<?php

namespace Tourze\CommentBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Event\CommentCreatedEvent;

class CommentCreatedEventTest extends TestCase
{
    public function test_construct_setsComment(): void
    {
        $comment = new Comment();
        $event = new CommentCreatedEvent($comment);

        $this->assertSame($comment, $event->getComment());
    }

    public function test_getComment_returnsCorrectComment(): void
    {
        $comment = new Comment();
        $comment->setContent('New comment');
        
        $event = new CommentCreatedEvent($comment);
        
        $this->assertSame($comment, $event->getComment());
        $this->assertEquals('New comment', $event->getComment()->getContent());
    }
}