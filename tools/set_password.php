<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * ログインのパスワードを設定する（M03／M07）。入力したパスワードは画面に表示しない。
 * パスワードはチャット・手順書・スクリプトには書かない。
 *
 * 使い方：php tools/set_password.php [ログインID]
 *   ログインIDを省略すると、ユーザが1人だけのときはそのユーザを対象にする。
 */
declare(strict_types=1);

require __DIR__ . '/cli.php';

use Motolog\Repository\UserRepository;

const MIN_LENGTH = 12;

connectAdmin();
$users = new UserRepository();

$loginId = $argv[1] ?? null;
if ($loginId === null) {
    $all = $users->all();
    if (count($all) !== 1) {
        fail('ユーザが1人ではありません。ログインIDを指定してください。');
    }
    $loginId = (string) $all[0]['login_id'];
}
$user = $users->findByLoginId($loginId);
if ($user === null) {
    fail("ログインID「{$loginId}」のユーザがいません。");
}

function askHidden(string $prompt): string
{
    fwrite(STDOUT, $prompt);
    $tty = stream_isatty(STDIN);
    if ($tty) {
        shell_exec('stty -echo');
    }
    $line = fgets(STDIN);
    if ($tty) {
        shell_exec('stty echo');
    }
    fwrite(STDOUT, PHP_EOL);
    return $line === false ? '' : rtrim($line, "\r\n");
}

out("ログインID「{$loginId}」のパスワードを設定します（" . MIN_LENGTH . '文字以上。入力した文字は表示されません）。');
$password = askHidden('新しいパスワード：');
if (mb_strlen($password) < MIN_LENGTH) {
    fail('パスワードは' . MIN_LENGTH . '文字以上にしてください。何も変更していません。');
}
if (askHidden('もう一度：') !== $password) {
    fail('2回の入力が一致しません。何も変更していません。');
}

$users->updatePassword((int) $user['id'], password_hash($password, PASSWORD_DEFAULT));
out('パスワードを設定しました。https://motolog.fia-4u.com/ からログインしてください。');
