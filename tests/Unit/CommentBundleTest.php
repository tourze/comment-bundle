<?php

namespace Tourze\CommentBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\TestCase;
use Tourze\CommentBundle\CommentBundle;
use Tourze\CommentBundle\DependencyInjection\CommentExtension;
use Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle;
use Tourze\DoctrineIpBundle\DoctrineIpBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;

class CommentBundleTest extends TestCase
{
    private CommentBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new CommentBundle();
    }

    public function test_getContainerExtension_returnsCommentExtension(): void
    {
        $extension = $this->bundle->getContainerExtension();
        
        $this->assertInstanceOf(CommentExtension::class, $extension);
    }

    public function test_getBundleDependencies_returnsExpectedDependencies(): void
    {
        $dependencies = CommentBundle::getBundleDependencies();
        
        $this->assertArrayHasKey(DoctrineBundle::class, $dependencies);
        $this->assertArrayHasKey(DoctrineIndexedBundle::class, $dependencies);
        $this->assertArrayHasKey(DoctrineIpBundle::class, $dependencies);
        $this->assertArrayHasKey(DoctrineTimestampBundle::class, $dependencies);
        
        $this->assertEquals(['all' => true], $dependencies[DoctrineBundle::class]);
        $this->assertEquals(['all' => true], $dependencies[DoctrineIndexedBundle::class]);
        $this->assertEquals(['all' => true], $dependencies[DoctrineIpBundle::class]);
        $this->assertEquals(['all' => true], $dependencies[DoctrineTimestampBundle::class]);
    }
}