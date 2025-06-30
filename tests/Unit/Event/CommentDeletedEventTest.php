<?php

namespace Tourze\CommentBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Event\CommentDeletedEvent;

class CommentDeletedEventTest extends TestCase
{
    public function test_construct_setsComment(): void
    {
        $comment = new Comment();
        $event = new CommentDeletedEvent($comment);

        $this->assertSame($comment, $event->getComment());
    }

    public function test_getComment_returnsCorrectComment(): void
    {
        $comment = new Comment();
        $comment->setContent('Deleted comment');
        
        $event = new CommentDeletedEvent($comment);
        
        $this->assertSame($comment, $event->getComment());
        $this->assertEquals('Deleted comment', $event->getComment()->getContent());
    }
}