<?php

declare(strict_types=1);

namespace Tourze\CommentBundle\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Entity\CommentMention;
use Tourze\CommentBundle\Repository\CommentRepository;

/**
 * @extends AbstractCrudController<CommentMention>
 */
#[AdminCrud(
    routePath: '/comment/mention',
    routeName: 'comment_mention'
)]
final class CommentMentionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CommentMention::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('评论提及')
            ->setEntityLabelInPlural('评论提及管理')
            ->setPageTitle(Crud::PAGE_INDEX, '评论提及列表')
            ->setPageTitle(Crud::PAGE_NEW, '新建评论提及')
            ->setPageTitle(Crud::PAGE_EDIT, '编辑评论提及')
            ->setPageTitle(Crud::PAGE_DETAIL, '评论提及详情')
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setSearchFields(['mentionedUserId', 'mentionedUserName', 'comment.content', 'comment.authorName'])
            ->setPaginatorPageSize(20)
            ->showEntityActionsInlined()
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig'])
            ->setHelp('index', '管理评论中用户提及信息，显示被提及的用户和通知状态')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions->add(Crud::PAGE_INDEX, Action::DETAIL);

        // 安全地更新 NEW action
        try {
            $actions->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action->setIcon('fa fa-plus')->setLabel('新建提及');
            });
        } catch (\InvalidArgumentException) {
            $actions->add(Crud::PAGE_INDEX, Action::NEW)
                ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                    return $action->setIcon('fa fa-plus')->setLabel('新建提及');
                })
            ;
        }

        // 安全地更新 EDIT action
        try {
            $actions->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action->setIcon('fa fa-edit')->setLabel('编辑');
            });
        } catch (\InvalidArgumentException) {
            $actions->add(Crud::PAGE_INDEX, Action::EDIT)
                ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                    return $action->setIcon('fa fa-edit')->setLabel('编辑');
                })
            ;
        }

        // 安全地更新 DELETE action
        try {
            $actions->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action->setIcon('fa fa-trash')->setLabel('删除')
                    ->displayAsForm()
                    ->setCssClass('action-delete text-danger btn btn-link p-0 border-0')
                    ->setHtmlAttributes(['title' => '删除'])
                ;
            });
        } catch (\InvalidArgumentException) {
            $actions->add(Crud::PAGE_INDEX, Action::DELETE)
                ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                    return $action->setIcon('fa fa-trash')->setLabel('删除')
                        ->displayAsForm()
                        ->setCssClass('action-delete text-danger btn btn-link p-0 border-0')
                        ->setHtmlAttributes(['title' => '删除'])
                    ;
                })
            ;
        }

        $actions->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
            return $action->setIcon('fa fa-eye')->setLabel('查看');
        });

        return $actions;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex()
            ->setColumns('col-md-2')
        ;

        yield AssociationField::new('comment', '关联评论')
            ->setColumns('col-md-6')
            ->setRequired(true)
            ->setFormTypeOptions([
                'choice_label' => function (Comment $comment) {
                    return sprintf(
                        '#%d - %s',
                        $comment->getId() ?? 0,
                        mb_substr($comment->getContent(), 0, 50)
                    );
                },
                'query_builder' => function (CommentRepository $repository) {
                    return $repository->createQueryBuilder('c')
                        ->where('c.valid = true')
                        ->orderBy('c.createTime', 'DESC')
                    ;
                },
            ])
            ->formatValue(function ($value, $entity) {
                if (!$value instanceof Comment) {
                    return '无';
                }

                $content = mb_substr($value->getContent(), 0, 80);
                $author = $value->getAuthorName() ?? '匿名用户';

                return sprintf(
                    '<div class="text-truncate" style="max-width: 400px;" title="评论内容: %s">
                        <strong>评论 #%d</strong><br>
                        <small class="text-muted">作者: %s</small><br>
                        <span>%s</span>
                    </div>',
                    htmlspecialchars($value->getContent()),
                    $value->getId() ?? 0,
                    htmlspecialchars($author),
                    htmlspecialchars($content)
                );
            })
            ->setHelp('选择相关的评论记录')
        ;

        yield TextField::new('mentionedUserId', '被提及用户ID')
            ->setColumns('col-md-3')
            ->setRequired(true)
            ->setMaxLength(100)
            ->setHelp('被提及用户的唯一标识符')
        ;

        yield TextField::new('mentionedUserName', '被提及用户名')
            ->setColumns('col-md-3')
            ->setRequired(false)
            ->setMaxLength(100)
            ->setHelp('被提及用户的显示名称（可选）')
        ;

        yield BooleanField::new('notified', '是否已通知')
            ->setColumns('col-md-3')
            ->setHelp('标记是否已向被提及用户发送通知')
            ->renderAsSwitch(false)
        ;

        yield DateTimeField::new('notifyTime', '通知时间')
            ->setColumns('col-md-3')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setHelp('发送通知的时间，通常在设置已通知时自动填入')
            ->hideOnIndex()
        ;

        yield BooleanField::new('valid', '是否有效')
            ->setColumns('col-md-3')
            ->setHelp('标记此提及记录是否有效，无效的记录不会显示或处理')
            ->renderAsSwitch(false)
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->onlyOnIndex()
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->setColumns('col-md-3')
        ;

        // 详情页额外显示字段
        if (Crud::PAGE_DETAIL === $pageName) {
            yield TextField::new('comment.targetType', '评论目标类型')
                ->setHelp('被评论对象的类型')
            ;

            yield TextField::new('comment.targetId', '评论目标ID')
                ->setHelp('被评论对象的ID')
            ;

            yield TextField::new('comment.authorId', '评论作者ID')
                ->setHelp('评论作者的用户ID')
            ;

            yield TextField::new('comment.authorName', '评论作者姓名')
                ->setHelp('评论作者的显示名称')
            ;
        }
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('comment', '关联评论'))
            ->add(TextFilter::new('mentionedUserId', '被提及用户ID'))
            ->add(TextFilter::new('mentionedUserName', '被提及用户名'))
            ->add(BooleanFilter::new('notified', '是否已通知'))
            ->add(BooleanFilter::new('valid', '是否有效'))
            ->add(DateTimeFilter::new('notifyTime', '通知时间'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
        ;
    }
}
