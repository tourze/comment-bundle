<?php

declare(strict_types=1);

namespace Tourze\CommentBundle\Controller\Admin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Tourze\CommentBundle\Entity\Comment;
use Tourze\CommentBundle\Enum\CommentStatus;
use Tourze\EasyAdminEnumFieldBundle\Field\EnumField;

/**
 * @extends AbstractCrudController<Comment>
 */
#[AdminCrud(
    routePath: '/comment/comment',
    routeName: 'comment_comment'
)]
final class CommentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Comment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('评论')
            ->setEntityLabelInPlural('评论管理')
            ->setSearchFields(['id', 'targetType', 'targetId', 'content', 'authorName', 'authorEmail', 'authorIp'])
            ->setDefaultSort(['createTime' => 'DESC'])
            ->setPaginatorPageSize(50)
            ->setTimezone('Asia/Shanghai')
            ->setHelp('index', '管理系统评论，包括评论审核、回复管理和统计信息')
        ;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        // 按置顶状态、状态和创建时间排序
        $qb->addOrderBy('entity.pinned', 'DESC')
            ->addOrderBy('entity.status', 'ASC')
            ->addOrderBy('entity.createTime', 'DESC')
        ;

        return $qb;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermissions([
                Action::NEW => 'ROLE_ADMIN',
                Action::EDIT => 'ROLE_ADMIN',
                Action::DELETE => 'ROLE_ADMIN',
                Action::DETAIL => 'ROLE_USER',
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', 'ID')
            ->onlyOnIndex()
        ;

        yield TextField::new('targetType', '目标类型')
            ->setRequired(true)
            ->setColumns(3)
            ->setHelp('评论的目标类型，如：article、product等')
        ;

        yield TextField::new('targetId', '目标ID')
            ->setRequired(true)
            ->setColumns(3)
            ->setHelp('评论的目标对象唯一标识')
        ;

        yield TextareaField::new('content', '评论内容')
            ->setRequired(true)
            ->setColumns(12)
            ->setHelp('评论的具体内容')
            ->hideOnIndex()
        ;

        // 在列表页显示截断的评论内容
        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('content', '评论内容')
                ->formatValue(function ($value) {
                    if (!is_string($value) && !is_numeric($value) && !(is_object($value) && method_exists($value, '__toString'))) {
                        return '';
                    }

                    $content = (string) $value;
                    if (mb_strlen($content) > 50) {
                        return mb_substr($content, 0, 50) . '...';
                    }

                    return $content;
                })
                ->setColumns(6)
            ;
        }

        yield TextField::new('authorId', '作者ID')
            ->setColumns(3)
            ->setHelp('评论作者的用户ID，匿名评论时为空')
        ;

        yield TextField::new('authorName', '作者姓名')
            ->setColumns(3)
            ->setHelp('评论作者的显示名称')
        ;

        yield TextField::new('authorEmail', '作者邮箱')
            ->setColumns(6)
            ->setHelp('评论作者的邮箱地址')
            ->hideOnIndex()
        ;

        yield TextField::new('authorIp', '作者IP')
            ->setColumns(3)
            ->setHelp('评论作者的IP地址')
            ->hideOnIndex()
        ;

        yield TextField::new('userAgent', '用户代理')
            ->setColumns(12)
            ->setHelp('评论提交时的浏览器信息')
            ->hideOnIndex()
        ;

        yield AssociationField::new('parent', '父评论')
            ->setFormTypeOption('placeholder', '请选择父评论（留空表示顶级评论）')
            ->setColumns(6)
            ->setHelp('该评论回复的父评论，为空表示顶级评论')
            ->hideOnIndex()
        ;

        yield AssociationField::new('replies', '回复评论')
            ->onlyOnDetail()
            ->setHelp('该评论的所有回复')
        ;

        $statusField = EnumField::new('status', '状态')
            ->setColumns(3)
            ->setRequired(true)
            ->setHelp('评论的审核状态')
            ->setFormTypeOption('empty_data', CommentStatus::PENDING)
            ->renderAsBadges([
                CommentStatus::PENDING->value => 'warning',
                CommentStatus::APPROVED->value => 'success',
                CommentStatus::REJECTED->value => 'danger',
                CommentStatus::DELETED->value => 'secondary',
            ])
        ;
        $statusField->setEnumCases(CommentStatus::cases());
        yield $statusField;

        yield IntegerField::new('likesCount', '点赞数')
            ->setColumns(3)
            ->setHelp('评论获得的点赞数量')
        ;

        yield IntegerField::new('dislikesCount', '踩数')
            ->setColumns(3)
            ->setHelp('评论获得的踩数量')
            ->hideOnIndex()
        ;

        yield BooleanField::new('pinned', '置顶')
            ->renderAsSwitch(false)
            ->setColumns(3)
            ->setHelp('是否置顶显示该评论')
        ;

        yield BooleanField::new('valid', '有效状态')
            ->renderAsSwitch(false)
            ->setColumns(3)
            ->setHelp('评论是否有效')
            ->hideOnIndex()
        ;

        yield AssociationField::new('votes', '投票记录')
            ->onlyOnDetail()
            ->setHelp('该评论的所有投票记录')
        ;

        yield DateTimeField::new('createTime', '创建时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnIndex()
        ;

        yield DateTimeField::new('updateTime', '更新时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->hideOnIndex()
            ->hideOnForm()
        ;

        yield DateTimeField::new('deleteTime', '删除时间')
            ->setFormat('yyyy-MM-dd HH:mm:ss')
            ->onlyOnDetail()
            ->setHelp('评论被软删除的时间')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('targetType', '目标类型'))
            ->add(TextFilter::new('targetId', '目标ID'))
            ->add(TextFilter::new('content', '评论内容'))
            ->add(
                ChoiceFilter::new('status', '状态')
                    ->setChoices([
                        '待审核' => CommentStatus::PENDING->value,
                        '已通过' => CommentStatus::APPROVED->value,
                        '已拒绝' => CommentStatus::REJECTED->value,
                        '已删除' => CommentStatus::DELETED->value,
                    ])
            )
            ->add(TextFilter::new('authorName', '作者姓名'))
            ->add(TextFilter::new('authorEmail', '作者邮箱'))
            ->add(TextFilter::new('authorIp', '作者IP'))
            ->add(BooleanFilter::new('pinned', '置顶状态'))
            ->add(BooleanFilter::new('valid', '有效状态'))
            ->add(EntityFilter::new('parent', '父评论'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('updateTime', '更新时间'))
            ->add(DateTimeFilter::new('deleteTime', '删除时间'))
        ;
    }
}
