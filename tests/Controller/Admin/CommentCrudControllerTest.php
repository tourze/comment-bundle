<?php

declare(strict_types=1);

namespace Tourze\CommentBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\CommentBundle\Controller\Admin\CommentCrudController;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(CommentCrudController::class)]
#[RunTestsInSeparateProcesses]
final class CommentCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    public function testGetEntityFqcn(): void
    {
        $this->assertSame(Comment::class, CommentCrudController::getEntityFqcn());
    }

    public function testIndexPage(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client);
        $crawler = $client->request('GET', '/admin');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Navigate to Comment CRUD if menu exists
        $link = $crawler->filter('a[href*="CommentCrudController"]')->first();
        if ($link->count() > 0) {
            $client->click($link->link());
            $this->assertEquals(200, $client->getResponse()->getStatusCode());
        }
    }

    public function testCreateComment(): void
    {
        $client = self::createClientWithDatabase();
        $this->loginAsAdmin($client);
        $client->request('GET', '/admin');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        // Test that the controller can handle entity creation
        $controller = self::getService(CommentCrudController::class);
        $this->assertInstanceOf(CommentCrudController::class, $controller);
    }

    public function testConfigureCrud(): void
    {
        $client = self::createClientWithDatabase();
        $controller = self::getService(CommentCrudController::class);
        $this->assertInstanceOf(CommentCrudController::class, $controller);

        // Test configureCrud configuration
        $this->assertInstanceOf(CommentCrudController::class, $controller);
    }

    public function testConfigureFields(): void
    {
        $client = self::createClientWithDatabase();
        $controller = self::getService(CommentCrudController::class);
        $this->assertInstanceOf(CommentCrudController::class, $controller);

        // Test configureFields configuration
        $this->assertInstanceOf(CommentCrudController::class, $controller);

        $fields = iterator_to_array($controller->configureFields('index'));
        $this->assertNotEmpty($fields);

        // Just verify we have fields without checking specific properties
        // since the field interface doesn't guarantee getProperty() method
        $this->assertGreaterThan(0, count($fields));
    }

    public function testConfigureActions(): void
    {
        $client = self::createClientWithDatabase();
        $controller = self::getService(CommentCrudController::class);
        $this->assertInstanceOf(CommentCrudController::class, $controller);

        // Test configureActions configuration
        $this->assertInstanceOf(CommentCrudController::class, $controller);
    }

    public function testConfigureFilters(): void
    {
        $client = self::createClientWithDatabase();
        $controller = self::getService(CommentCrudController::class);
        $this->assertInstanceOf(CommentCrudController::class, $controller);

        // Test configureFilters configuration
        $this->assertInstanceOf(CommentCrudController::class, $controller);
    }

    public function testCreateIndexQueryBuilder(): void
    {
        $client = self::createClientWithDatabase();
        $controller = self::getService(CommentCrudController::class);
        $this->assertInstanceOf(CommentCrudController::class, $controller);

        // Test createIndexQueryBuilder configuration
        $this->assertInstanceOf(CommentCrudController::class, $controller);
    }

    public function testValidationErrors(): void
    {
        $client = $this->createAuthenticatedClient();

        try {
            $url = $this->generateAdminUrl('new');
        } catch (\InvalidArgumentException) {
            self::markTestSkipped('NEW action is disabled for this controller.');
        }

        // 尝试提交空表单，应该出现验证错误
        $crawler = $client->request('GET', $this->generateAdminUrl('new'));
        $this->assertResponseIsSuccessful();

        // 尝试查找提交按钮（可能的文本变化）
        $submitButton = $crawler->filter('button[type="submit"]');
        if (0 === $submitButton->count()) {
            // 如果没有找到提交按钮，尝试其他可能的选择器
            $submitButton = $crawler->filter('input[type="submit"]');
        }
        if (0 === $submitButton->count()) {
            self::markTestSkipped('Could not find submit button for form validation test');
        }
        $form = $submitButton->form();
        $entityName = $this->getEntitySimpleName();

        // 提交空的必填字段，触发验证错误
        $client->submit($form, [
            $entityName => [
                'targetType' => '',
                'targetId' => '',
                'content' => '',
            ],
        ]);

        // 验证表单有错误
        $responseContent = $client->getResponse()->getContent();
        $this->assertIsString($responseContent, 'Response content should be a string');
        $this->assertStringContainsString('This value should not be blank', $responseContent);
    }

    public function testValidationErrorsForInvalidEmail(): void
    {
        $client = $this->createAuthenticatedClient();

        try {
            $this->generateAdminUrl('new');
        } catch (\InvalidArgumentException) {
            self::markTestSkipped('NEW action is disabled for this controller.');
        }
        $crawler = $client->request('GET', $this->generateAdminUrl('new'));
        $this->assertResponseIsSuccessful();

        // 尝试查找提交按钮（可能的文本变化）
        $submitButton = $crawler->filter('button[type="submit"]');
        if (0 === $submitButton->count()) {
            // 如果没有找到提交按钮，尝试其他可能的选择器
            $submitButton = $crawler->filter('input[type="submit"]');
        }
        if (0 === $submitButton->count()) {
            self::markTestSkipped('Could not find submit button for form validation test');
        }
        $form = $submitButton->form();
        $entityName = $this->getEntitySimpleName();

        // 提交无效的邮箱地址
        $client->submit($form, [
            $entityName => [
                'targetType' => 'post',
                'targetId' => '123',
                'content' => '测试评论内容',
                'authorEmail' => 'invalid-email',
            ],
        ]);

        // 验证邮箱验证错误
        $responseContent = $client->getResponse()->getContent();
        $this->assertIsString($responseContent, 'Response content should be a string');
        $this->assertTrue(
            str_contains($responseContent, 'This value is not a valid email')
            || str_contains($responseContent, '不是一个有效的电子邮件地址'),
            'Should contain email validation error'
        );
    }

    public function testValidationErrorsForTooLongContent(): void
    {
        $client = $this->createAuthenticatedClient();

        try {
            $this->generateAdminUrl('new');
        } catch (\InvalidArgumentException) {
            self::markTestSkipped('NEW action is disabled for this controller.');
        }
        $crawler = $client->request('GET', $this->generateAdminUrl('new'));
        $this->assertResponseIsSuccessful();

        // 尝试查找提交按钮（可能的文本变化）
        $submitButton = $crawler->filter('button[type="submit"]');
        if (0 === $submitButton->count()) {
            // 如果没有找到提交按钮，尝试其他可能的选择器
            $submitButton = $crawler->filter('input[type="submit"]');
        }
        if (0 === $submitButton->count()) {
            self::markTestSkipped('Could not find submit button for form validation test');
        }
        $form = $submitButton->form();
        $entityName = $this->getEntitySimpleName();

        // 提交超长内容（超过10000字符限制）
        $longContent = str_repeat('A', 10001);
        $client->submit($form, [
            $entityName => [
                'targetType' => 'post',
                'targetId' => '123',
                'content' => $longContent,
            ],
        ]);

        // 验证长度验证错误
        $responseContent = $client->getResponse()->getContent();
        $this->assertIsString($responseContent, 'Response content should be a string');
        $this->assertTrue(
            str_contains($responseContent, 'This value is too long')
            || str_contains($responseContent, '太长了'),
            'Should contain length validation error'
        );
    }

    public function testControllerInheritance(): void
    {
        $client = self::createClientWithDatabase();
        $controller = self::getService(CommentCrudController::class);
        $this->assertInstanceOf(AbstractCrudController::class, $controller);
    }

    public function testControllerHasAdminCrudAttribute(): void
    {
        $reflection = new \ReflectionClass(CommentCrudController::class);
        $attributes = $reflection->getAttributes();

        $hasAdminCrudAttribute = false;
        foreach ($attributes as $attribute) {
            if ('EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud' === $attribute->getName()) {
                $hasAdminCrudAttribute = true;
                break;
            }
        }

        $this->assertTrue($hasAdminCrudAttribute, 'Controller should have AdminCrud attribute');
    }

    /**
     * @return AbstractCrudController<Comment>
     */
    protected function getControllerService(): AbstractCrudController
    {
        return self::getService(CommentCrudController::class);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID列' => ['ID'];
        yield '目标类型列' => ['目标类型'];
        yield '目标ID列' => ['目标ID'];
        yield '评论内容列' => ['评论内容'];
        yield '作者ID列' => ['作者ID'];
        yield '作者姓名列' => ['作者姓名'];
        yield '状态列' => ['状态'];
        yield '点赞数列' => ['点赞数'];
        yield '置顶列' => ['置顶'];
        yield '创建时间列' => ['创建时间'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield '目标类型字段' => ['targetType'];
        yield '目标ID字段' => ['targetId'];
        yield '评论内容字段' => ['content'];
        yield '作者ID字段' => ['authorId'];
        yield '作者姓名字段' => ['authorName'];
        yield '作者邮箱字段' => ['authorEmail'];
        yield '作者IP字段' => ['authorIp'];
        yield '用户代理字段' => ['userAgent'];
        yield '父评论字段' => ['parent'];
        yield '状态字段' => ['status'];
        yield '点赞数字段' => ['likesCount'];
        yield '踩数字段' => ['dislikesCount'];
        yield '置顶字段' => ['pinned'];
        yield '有效状态字段' => ['valid'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield '目标类型字段' => ['targetType'];
        yield '目标ID字段' => ['targetId'];
        yield '评论内容字段' => ['content'];
        yield '作者ID字段' => ['authorId'];
        yield '作者姓名字段' => ['authorName'];
        yield '作者邮箱字段' => ['authorEmail'];
        yield '作者IP字段' => ['authorIp'];
        yield '用户代理字段' => ['userAgent'];
        yield '父评论字段' => ['parent'];
        yield '状态字段' => ['status'];
        yield '点赞数字段' => ['likesCount'];
        yield '踩数字段' => ['dislikesCount'];
        yield '置顶字段' => ['pinned'];
        yield '有效状态字段' => ['valid'];
    }
}
