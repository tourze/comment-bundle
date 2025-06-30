<?php

namespace Tourze\CommentBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\CommentBundle\DependencyInjection\CommentExtension;

class CommentExtensionTest extends TestCase
{
    private CommentExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new CommentExtension();
        $this->container = new ContainerBuilder();
    }

    public function test_load_loadsServicesConfiguration(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务是否被正确加载（使用自动配置，所以使用类名作为服务ID）
        $this->assertTrue($this->container->hasDefinition('Tourze\CommentBundle\Service\CommentService'));
        $this->assertTrue($this->container->hasDefinition('Tourze\CommentBundle\Service\CommentVoteService'));
        $this->assertTrue($this->container->hasDefinition('Tourze\CommentBundle\Service\ContentFilterService'));
        $this->assertTrue($this->container->hasDefinition('Tourze\CommentBundle\Service\MentionParserService'));
        $this->assertTrue($this->container->hasDefinition('Tourze\CommentBundle\Service\NotificationService'));
    }

    public function test_load_registersRepositories(): void
    {
        $this->extension->load([], $this->container);

        // 验证仓储是否被正确注册
        $this->assertTrue($this->container->hasDefinition('Tourze\CommentBundle\Repository\CommentRepository'));
        $this->assertTrue($this->container->hasDefinition('Tourze\CommentBundle\Repository\CommentVoteRepository'));
    }

    public function test_load_registersCommands(): void
    {
        $this->extension->load([], $this->container);

        // 验证命令是否被正确注册
        $this->assertTrue($this->container->hasDefinition('Tourze\CommentBundle\Command\CommentModerationCommand'));
        $this->assertTrue($this->container->hasDefinition('Tourze\CommentBundle\Command\CommentStatisticsCommand'));
    }

    public function test_getAlias_returnsCorrectAlias(): void
    {
        $this->assertEquals('comment', $this->extension->getAlias());
    }
}