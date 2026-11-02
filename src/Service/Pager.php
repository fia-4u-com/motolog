<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Service;

/**
 * 一覧の区切り方（F-10／F-20。v1.1）。
 *   給油記録：新しい側から20件ずつ区切る。最新のページはいつも20件で、半端は一番古いページに回す。
 *   走行記録：「最新」（直近20件）と、年ごと（その年の記録すべて）で見せる。
 * どちらも、行は古い順に並べたまま返す。
 */
final class Pager
{
    public const PER_PAGE = 20;

    /**
     * 新しい側から20件ずつ区切ったページ。ページの指定がなければ最新（最後のページ）。
     *
     * @param list<mixed> $rows 古い順に並んだすべての行
     * @return array{page: array{current: int, last: int, total: int, from: int, to: int}, rows: list<mixed>}
     */
    public static function slice(array $rows, mixed $page): array
    {
        $total = count($rows);
        $last = max(1, (int) ceil($total / self::PER_PAGE));
        $current = is_numeric($page) ? (int) $page : $last;
        $current = min(max($current, 1), $last);
        $end = $total - self::PER_PAGE * ($last - $current);          // このページの最後の行の次の位置
        $start = max(0, $end - self::PER_PAGE);
        return [
            'page' => ['current' => $current, 'last' => $last, 'total' => $total, 'from' => $total === 0 ? 0 : $start + 1, 'to' => $end],
            'rows' => array_slice($rows, $start, $end - $start),
        ];
    }

    /**
     * 指定した ID の行が載っているページ（slice と同じ区切り方）。見つからなければ null。
     *
     * @param list<array{id: int}> $rows 古い順に並んだすべての行
     */
    public static function pageOf(array $rows, int $id): ?int
    {
        $total = count($rows);
        $last = max(1, (int) ceil($total / self::PER_PAGE));
        foreach ($rows as $index => $row) {
            if ($row['id'] === $id) {
                return $last - intdiv($total - 1 - $index, self::PER_PAGE);
            }
        }
        return null;
    }

    /**
     * 直近の20件（走行記録の「最新」）。
     *
     * @param list<mixed> $rows 古い順に並んだすべての行
     * @return list<mixed>
     */
    public static function latest(array $rows): array
    {
        return array_slice($rows, max(0, count($rows) - self::PER_PAGE));
    }

    /**
     * 記録のある年（古い順）。
     *
     * @param list<array{date: string}> $rows
     * @return list<int>
     */
    public static function years(array $rows): array
    {
        $years = array_values(array_unique(array_map(static fn (array $r): int => (int) substr($r['date'], 0, 4), $rows)));
        sort($years);
        return $years;
    }

    /**
     * その年（1月1日〜12月31日）の記録。
     *
     * @param list<array{date: string}> $rows
     * @return list<array<string, mixed>>
     */
    public static function byYear(array $rows, int $year): array
    {
        return array_values(array_filter($rows, static fn (array $r): bool => (int) substr($r['date'], 0, 4) === $year));
    }
}
