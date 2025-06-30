<?php

namespace Tourze\CommentBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Event\CommentApprovedEvent;

class CommentApprovedEventTest extends TestCase
{
    public function test_construct_setsComment(): void
    {
        $comment = new Comment();
        $event = new CommentApprovedEvent($comment);

        $this->assertSame($comment, $event->getComment());
    }

    public function test_getComment_returnsCorrectComment(): void
    {
        $comment = new Comment();
        $comment->setContent('Test comment');
        
        $event = new CommentApprovedEvent($comment);
        
        $this->assertSame($comment, $event->getComment());
        $this->assertEquals('Test comment', $event->getComment()->getContent());
    }
}