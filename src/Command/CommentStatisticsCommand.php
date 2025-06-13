<?php

namespace Tourze\CommentBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tourze\CommentBundle\Service\CommentService;

#[AsCommand(
    name: 'comment:statistics',
    description: '显示评论系统统计信息'
)]
class CommentStatisticsCommand extends Command
{
    public function __construct(
        private readonly CommentService $commentService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('target-type', 't', InputOption::VALUE_REQUIRED, '目标类型')
            ->addOption('target-id', 'i', InputOption::VALUE_REQUIRED, '目标ID')
            ->addOption('recent', 'r', InputOption::VALUE_OPTIONAL, '显示最近N条评论', 10);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $targetType = $input->getOption('target-type');
        $targetId = $input->getOption('target-id');

        // 显示基本统计信息
        $this->showBasicStats($io, $targetType, $targetId);

        // 显示最近评论
        if ($recentCount = $input->getOption('recent')) {
            $this->showRecentComments($io, (int) $recentCount);
        }

        return Command::SUCCESS;
    }

    private function showBasicStats(SymfonyStyle $io, ?string $targetType, ?string $targetId): void
    {
        $stats = $this->commentService->getStatistics($targetType, $targetId);

        $title = '评论系统统计信息';
        if ($targetType && $targetId) {
            $title .= " ({$targetType}:{$targetId})";
        } elseif ($targetType) {
            $title .= " ({$targetType})";
        }

        $io->title($title);

        $data = [
            ['总评论数', $stats['total_comments']],
            ['已批准', $stats['approved_comments']],
            ['待审核', $stats['pending_comments']],
            ['已拒绝', $stats['rejected_comments']],
            ['总点赞数', $stats['total_likes']],
            ['总踩数', $stats['total_dislikes']],
        ];

        $io->table(['项目', '数量'], $data);

        // 计算百分比
        $totalComments = (int) $stats['total_comments'];
        if ($totalComments > 0) {
            $approvalRate = round(($stats['approved_comments'] / $totalComments) * 100, 2);
            $pendingRate = round(($stats['pending_comments'] / $totalComments) * 100, 2);
            $rejectionRate = round(($stats['rejected_comments'] / $totalComments) * 100, 2);

            $io->section('审核统计');
            $ratioData = [
                ['批准率', $approvalRate . '%'],
                ['待审核率', $pendingRate . '%'],
                ['拒绝率', $rejectionRate . '%'],
            ];
            $io->table(['项目', '比例'], $ratioData);
        }
    }

    private function showRecentComments(SymfonyStyle $io, int $limit): void
    {
        $comments = $this->commentService->getRecentComments($limit);

        if (empty($comments)) {
            $io->info('暂无最近评论');
            return;
        }

        $io->section("最近 {$limit} 条评论");

        $rows = [];
        foreach ($comments as $comment) {
            $rows[] = [
                $comment->getId(),
                $comment->getTargetType(),
                $comment->getTargetId(),
                $comment->getAuthorName() ?? '匿名',
                $comment->getStatus(),
                mb_substr($comment->getContent(), 0, 50) . '...',
                $comment->getCreatedAt()->format('Y-m-d H:i:s')
            ];
        }

        $io->table(
            ['ID', '目标类型', '目标ID', '作者', '状态', '内容', '时间'],
            $rows
        );
    }
}