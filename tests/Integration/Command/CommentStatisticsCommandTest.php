<?php

namespace Tourze\CommentBundle\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\CommentBundle\Command\CommentStatisticsCommand;
use Tourze\CommentBundle\Service\CommentService;

class CommentStatisticsCommandTest extends TestCase
{
    private CommentStatisticsCommand $command;
    private CommentService $commentService;

    protected function setUp(): void
    {
        $this->commentService = $this->createMock(CommentService::class);
        $this->command = new CommentStatisticsCommand($this->commentService);
    }

    public function test_configure_setsCorrectCommandName(): void
    {
        $this->assertEquals('comment:statistics', $this->command->getName());
    }

    public function test_execute_withValidArguments(): void
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find('comment:statistics');
        $commandTester = new CommandTester($command);

        $this->commentService->expects($this->once())
            ->method('getStatistics')
            ->willReturn([
                'total' => 100,
                'approved' => 80,
                'pending' => 15,
                'rejected' => 5
            ]);

        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('评论系统统计信息', $output);
        $this->assertStringContainsString('总评论数', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}