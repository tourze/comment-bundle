<?php

namespace Tourze\CommentBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\CommentBundle\DependencyInjection\CommentExtension;
use Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle;
use Tourze\DoctrineIpBundle\DoctrineIpBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;

class CommentBundle extends Bundle implements BundleDependencyInterface
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new CommentExtension();
    }

    public static function getBundleDependencies(): array
    {
        return [
            DoctrineBundle::class => ['all' => true],
            DoctrineIndexedBundle::class => ['all' => true],
            DoctrineIpBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            // DoctrineUserBundle brings in SecurityBundle which requires router
            // For tests, we'll load this manually if needed
        ];
    }
}
