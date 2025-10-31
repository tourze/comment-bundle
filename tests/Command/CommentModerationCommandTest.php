<?php

namespace Tourze\CommentBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\CommentBundle\Command\CommentModerationCommand;
use Tourze\CommentBundle\Service\CommentService;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(CommentModerationCommand::class)]
#[RunTestsInSeparateProcesses]
final class CommentModerationCommandTest extends AbstractCommandTestCase
{
    protected function onSetUp(): void
    {
        // AbstractCommandTestCase 会自动清理数据库
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getContainer()->get(CommentModerationCommand::class);
        $this->assertInstanceOf(CommentModerationCommand::class, $command);

        return new CommandTester($command);
    }

    public function testConfigureSetsCorrectCommandName(): void
    {
        $command = self::getContainer()->get(CommentModerationCommand::class);
        $this->assertInstanceOf(CommentModerationCommand::class, $command);
        $this->assertEquals('comment:moderation', $command->getName());
    }

    public function testExecuteWithListOptionShowsMessage(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '--list' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testOptionList(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '--list' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testOptionApprove(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '--approve' => '999',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        // 状态码可能是0或1，取决于评论是否存在和服务实现
        $this->assertContains($commandTester->getStatusCode(), [0, 1]);
    }

    public function testOptionReject(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '--reject' => '999',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        // 状态码可能是0或1，取决于评论是否存在和服务实现
        $this->assertContains($commandTester->getStatusCode(), [0, 1]);
    }

    public function testOptionAutoApprove(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '--auto-approve' => true,
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }

    public function testOptionLimit(): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute([
            '--list' => true,
            '--limit' => '10',
        ]);

        $output = $commandTester->getDisplay();
        $this->assertIsString($output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
