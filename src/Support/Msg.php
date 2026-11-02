<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Support;

/** 画面に出すメッセージ（詳細設計書「4_メッセージ」の M番号に対応）。 */
final class Msg
{
    public const M01 = 'IDとパスワードを入力してください。';
    public const M02 = 'IDまたはパスワードが違います。';
    public const M03 = 'ログインの失敗が続いたため、しばらくログインできません。15分ほど待ってからお試しください。';
    public const M04 = 'ログインの有効期限が切れました。もう一度ログインしてください。';
    public const M30 = '保存しました。';
    public const M32 = '削除しました。';
    public const M90 = '通信できませんでした。電波の良いところで、もう一度お試しください。';
    public const M99 = 'エラーが発生しました。時間をおいて、もう一度お試しください。';
    public const VALIDATION = '入力内容を確認してください。';
    public const CSRF = '画面の有効期限が切れました。画面を読み直してから、もう一度お試しください。';
    public const NOT_FOUND = '指定されたデータが見つかりません。';

    public static function m10(string $label): string
    {
        return "{$label}を入力してください。";
    }

    public static function m11(string $label): string
    {
        return "{$label}の日付が正しくありません。";
    }

    public static function m12(string $date): string
    {
        return "{$date}の記録はすでにあります。同じ日の記録は1件だけです。その日の記録を修正してください。";
    }

    public static function m13(string $label, int|string $min, int|string $max): string
    {
        return "{$label}は{$min}〜{$max}の数字で入力してください。";
    }

    public static function m14(?array $prev, ?array $next): string
    {
        $parts = [];
        if ($prev !== null) {
            $parts[] = sprintf('%sは %s km', $prev['ride_date'], number_format((int) $prev['odo']));
        }
        if ($next !== null) {
            $parts[] = sprintf('%sは %s km', $next['ride_date'], number_format((int) $next['odo']));
        }
        return 'ODOが前後の記録と合いません。' . implode('、', $parts) . ' です。';
    }

    public static function m15(string $label): string
    {
        return "{$label}は -99〜+99 の整数で入力してください。";
    }

    public static function m16(string $label, int $length): string
    {
        return "{$label}は{$length}文字以内で入力してください。";
    }

    public static function m17(int $odo): string
    {
        return sprintf('ODO %s km の給油記録はすでにあります。', number_format($odo));
    }

    public const M18 = '給油量は、小数点なし（5.50 L なら 550）か小数点付き（5.5）の数字で、0.01〜99.99 L の範囲で入力してください。';

    public static function m19(string $date): string
    {
        return "取得日は、最初の記録の日付（{$date}）より後にできません。";
    }

    public const M20 = '画像は JPEG・PNG・WebP の形式で、10MBまでのファイルを選んでください。';

    public static function m21(string $name): string
    {
        return "「{$name}」はすでにあります。";
    }

    public const M22 = '記録のある品目は、種別を変更できません。';
    public const M23 = '初度登録年月は、取得日より後にできません。';
    public const ITEM_IN_USE = '記録のある品目は削除できません。使用停止にしてください。';
    public const RIDE_BEFORE_ACQUIRED = '日付は、車両の取得日より前にできません。';
}
