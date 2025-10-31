<?php

namespace Tourze\CommentBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\CommentBundle\Command\CommentStatisticsCommand;
use Tourze\CommentBundle\Service\CommentService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(CommentStatisticsCommand::class)]
#[RunTestsInSeparateProcesses]
final class CommentStatisticsCommandTest extends AbstractCommandTestCase
{
    protected function onSetUp(): void
    {
        // AbstractCommandTestCase 会自动清理数据库
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getContainer()->get(CommentStatisticsCommand::class);
        $this->assertInstanceOf(CommentStatisticsCommand::class, $command);

        return new CommandTester($command);
    }

    public function testConfigureSetsCorrectCommandName(): void
    {
        $command = self::getContainer()->get(CommentStatisticsCommand::class);
        $this->assertInstanceOf(CommentStatisticsCommand::class, $command);
        $this->assertEquals('comment:statistics', $command->getName());
    }

    public function testExecuteShowsStatistics(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testOptionTargetType(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '--target-type' => 'article',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $this->assertStringContainsString('评论系统统计信息 (article)', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testOptionTargetId(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '--target-type' => 'article',
            '--target-id' => '123',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $this->assertStringContainsString('评论系统统计信息 (article:123)', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testOptionRecent(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '--recent' => '5',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
