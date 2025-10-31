<?php

namespace Tourze\CommentBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\CommentBundle\CommentBundle;
use Tourze\CommentBundle\DependencyInjection\CommentExtension;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(CommentExtension::class)]
final class CommentExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    public function testGetAliasReturnsCorrectAlias(): void
    {
        $extension = new CommentExtension();
        $this->assertEquals('comment', $extension->getAlias());
    }

    public function testLoadCreatesContainer(): void
    {
        $extension = new CommentExtension();
        $container = new ContainerBuilder();

        // 添加必要的参数
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.project_dir', __DIR__ . '/../../');

        $extension->load([], $container);

        // 验证一些基础服务被注册
        $this->assertTrue($container->hasDefinition('Tourze\CommentBundle\Service\CommentService'));
        $this->assertTrue($container->hasDefinition('Tourze\CommentBundle\Service\CommentVoteService'));
    }

    public function testBundleHasExtension(): void
    {
        $bundle = new CommentBundle();
        $extension = $bundle->getContainerExtension();
        $this->assertInstanceOf(CommentExtension::class, $extension);
    }
}
