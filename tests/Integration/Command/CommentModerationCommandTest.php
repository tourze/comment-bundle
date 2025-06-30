<?php

namespace Tourze\CommentBundle\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\CommentBundle\Command\CommentModerationCommand;
use Tourze\CommentBundle\Service\CommentService;

class CommentModerationCommandTest extends TestCase
{
    private CommentModerationCommand $command;
    private CommentService $commentService;

    protected function setUp(): void
    {
        $this->commentService = $this->createMock(CommentService::class);
        $this->command = new CommentModerationCommand($this->commentService);
    }

    public function test_configure_setsCorrectCommandName(): void
    {
        $this->assertEquals('comment:moderation', $this->command->getName());
    }

    public function test_execute_withoutOptions_showsDefaultMessage(): void
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('comment:moderation');
        $commandTester = new CommandTester($command);

        // 当没有选项时，默认执行 list 动作（因为 getOption('list') !== null 总是为真）
        $this->commentService->expects($this->once())
            ->method('getPendingComments')
            ->with(['limit' => 50])
            ->willReturn([]);

        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('没有待审核的评论', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
    
    public function test_execute_withListOption(): void
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('comment:moderation');
        $commandTester = new CommandTester($command);

        $this->commentService->expects($this->once())
            ->method('getPendingComments')
            ->with(['limit' => 50])
            ->willReturn([]);

        $commandTester->execute([
            'command' => $command->getName(),
            '--list' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('没有待审核的评论', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}