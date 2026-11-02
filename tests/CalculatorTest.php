<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 *
 * 計算（詳細設計書 3_計算仕様）の単体テスト。
 * 架空のテストデータで、旧システムの決まりで出した期待値と全行を照らし合わせる。
 */
declare(strict_types=1);

use Motolog\Service\Elapsed;
use Motolog\Service\FuelCalculator;
use Motolog\Service\Pager;
use Motolog\Service\RideCalculator;

function fixture(): array
{
    static $data = null;
    return $data ??= require __DIR__ . '/fixtures/sample_data.php';
}

/** テストデータを、移行後と同じ形（品目10件・走行記録・交換の記録）にする。 */
function migratedRide(array $extraDeltas = []): array
{
    $f = fixture();
    $items = [];
    foreach ($f['items'] as $n => $name) {
        $items[] = ['id' => $n + 1, 'name' => $name, 'kind' => 'replace',
            'measure' => $name === 'バッテリー' ? 'period' : 'distance', 'initial_value' => null];
    }
    $items[] = ['id' => 9, 'name' => 'Fプリロード', 'kind' => 'setting', 'measure' => null, 'initial_value' => 6];
    $items[] = ['id' => 10, 'name' => 'Rプリロード', 'kind' => 'setting', 'measure' => null, 'initial_value' => 6];
    $logs = [];
    foreach ($f['rides'] as $n => [$date, $odo, $flags]) {
        $replaced = [];
        foreach ($flags as $i => $flag) {
            if ($flag === 1) {
                $replaced[] = $i + 1;
            }
        }
        $logs[] = ['id' => $n + 1, 'date' => $date, 'odo' => $odo, 'comment' => '',
            'replaced' => $replaced, 'deltas' => $extraDeltas[$date] ?? []];
    }
    $vehicle = ['acquired_on' => $f['acquired_on'], 'first_registered_on' => null];
    return [$vehicle, $items, $logs];
}

function cellText(array $c): string
{
    return match ($c['type']) {
        'replaced' => '○',
        'distance' => number_format($c['value']) . ' km',
        'period' => Elapsed::format($c['value']),
        'setting' => ($c['delta'] > 0 ? '+' . $c['delta'] : ($c['delta'] < 0 ? (string) $c['delta'] : '±0')) . "({$c['value']})",
    };
}

function test_elapsed_format(): void
{
    assertSame('2年07ヶ月05日', Elapsed::format(['y' => 2, 'm' => 7, 'd' => 5]));
    assertSame('3ヶ月12日', Elapsed::format(['y' => 0, 'm' => 3, 'd' => 12]));
    assertSame('5日', Elapsed::format(['y' => 0, 'm' => 0, 'd' => 5]));
    assertSame('0日', Elapsed::format(['y' => 0, 'm' => 0, 'd' => 0]));
    assertSame('1年05日', Elapsed::format(['y' => 1, 'm' => 0, 'd' => 5]), '月が0のとき');
    assertSame('3ヶ月05日', Elapsed::format(['y' => 0, 'm' => 3, 'd' => 5]));
}

function test_elapsed_between(): void
{
    assertSame(['y' => 2, 'm' => 7, 'd' => 5], Elapsed::between('2020-01-15', '2022-08-20'));
    assertSame(['y' => 0, 'm' => 0, 'd' => 0], Elapsed::between('2020-01-15', '2020-01-15'));
    assertSame(['y' => 0, 'm' => 0, 'd' => 0], Elapsed::between('2020-01-15', '2020-01-01'), '逆順は0');
}

function test_ride_rows_match_legacy(): void
{
    [$vehicle, $items, $logs] = migratedRide();
    $rows = RideCalculator::rows($vehicle, $items, $logs);
    $expected = fixture()['expected_rides'];
    assertSame(count($expected), count($rows), '行数');
    foreach ($rows as $n => $row) {
        [$date, $elapsed, $diff, $cells] = $expected[$n];
        assertSame($date, $row['date'], "{$n}行目の日付");
        if ($n === 0) {
            // E1／E2：最初の行は、経過が 0日、差分が －（null）
            assertSame('0日', Elapsed::format($row['elapsed']), '最初の行の経過');
            assertSame(null, $row['diff'], '最初の行の差分');
        } else {
            if ($elapsed !== null) {
                assertSame($elapsed, Elapsed::format($row['elapsed']), "{$date} 経過");
            }
            assertSame($diff, number_format($row['diff']) . ' km', "{$date} 差分");
        }
        $c = (array) $row['cells'];
        foreach ($cells as $i => $text) {
            if ($text !== null) {
                assertSame($text, cellText($c[$i + 1]), "{$date} {$items[$i]['name']}");
            }
        }
    }
}

function test_preload_setting(): void
{
    // テスト C：最後の記録で F +2、R +1（M05）
    $f = fixture();
    [$vehicle, $items, $logs] = migratedRide([$f['last_date'] => [9 => 2, 10 => 1]]);
    $rows = RideCalculator::rows($vehicle, $items, $logs);
    $last = (array) end($rows)['cells'];
    assertSame('+2(8)', cellText($last[9]));
    assertSame('+1(7)', cellText($last[10]));
    $before = (array) $rows[count($rows) - 2]['cells'];
    assertSame('±0(6)', cellText($before[9]), '変更前の行');
    $status = RideCalculator::status($vehicle, $items, $logs, $f['last_odo'], $f['status_date']);
    assertSame(8, $status[8]['value'], 'Fプリロードの現在値');
    assertSame(7, $status[9]['value'], 'Rプリロードの現在値');
}

function test_status_and_unreplaced(): void
{
    $f = fixture();
    $e = $f['expected_status'];
    [$vehicle, $items, $logs] = migratedRide();
    $status = RideCalculator::status($vehicle, $items, $logs, $f['last_odo'], $f['status_date']);
    assertSame($e['oil_last_date'], $status[0]['last_date'], 'オイルの最終交換日');
    assertSame($e['oil_distance'], $status[0]['distance'], 'オイルの交換後の距離');
    assertSame($e['filter_distance'], $status[1]['distance'], 'フィルターの交換後の距離');
    assertSame($e['battery_period'], $status[4]['period'], 'バッテリーの経過');

    // 未交換（中古車・初度登録 2020-04）：距離は ODO、期間は初度登録から（C-03／C-04／D10）
    $used = ['acquired_on' => '2027-01-10', 'first_registered_on' => '2020-04-01'];
    $item = [['id' => 1, 'name' => 'バッテリー', 'kind' => 'replace', 'measure' => 'period', 'initial_value' => null],
        ['id' => 2, 'name' => 'Rタイヤ', 'kind' => 'replace', 'measure' => 'distance', 'initial_value' => null]];
    $log = [['id' => 1, 'date' => '2027-01-10', 'odo' => 15000, 'comment' => '', 'replaced' => [], 'deltas' => []]];
    $row = (array) RideCalculator::rows($used, $item, $log)[0]['cells'];
    assertSame(['type' => 'period', 'value' => ['y' => 6, 'm' => 9, 'd' => 9], 'unreplaced' => true], $row[1]);
    assertSame(['type' => 'distance', 'value' => 15000, 'unreplaced' => true], $row[2]);
}

function test_fuel_matches_legacy(): void
{
    $f = fixture();
    $fuels = [];
    foreach ($f['fuels'] as $n => [$odo, $liters]) {
        $fuels[] = ['id' => $n + 1, 'odo' => $odo, 'centiliters' => (int) round((float) $liters * 100), 'is_full' => true];
    }
    $calc = FuelCalculator::calculate($fuels, 0);
    foreach ($calc['rows'] as $n => $row) {
        assertSame($f['expected_fuel_economy'][$n], number_format((float) $row['economy'], 2, '.', ''), "ODO {$row['odo']} の燃費");
    }
    assertSame($f['expected_total_liters'], number_format($calc['total_liters'], 2, '.', ''), '総給油量');
    assertSame($f['expected_lifetime'], number_format((float) $calc['lifetime_economy'], 2, '.', ''), '生涯燃費');
    assertSame($f['expected_max'], number_format((float) $calc['max_economy'], 2, '.', ''), '最高燃費');
    assertSame($f['expected_min'], number_format((float) $calc['min_economy'], 2, '.', ''), '最低燃費');
}

function test_fuel_partial_fill(): void
{
    // 継ぎ足しの行は燃費なし。次の満タンで、前回の満タンからの距離 ÷ 継ぎ足し分を含む給油量（C-10／C-11）
    $fuels = [
        ['id' => 1, 'odo' => 200, 'centiliters' => 1000, 'is_full' => true],
        ['id' => 2, 'odo' => 300, 'centiliters' => 300, 'is_full' => false],
        ['id' => 3, 'odo' => 450, 'centiliters' => 1000, 'is_full' => true],
        ['id' => 4, 'odo' => 500, 'centiliters' => 200, 'is_full' => false],
    ];
    $calc = FuelCalculator::calculate($fuels, 0);
    assertSame(20.0, $calc['rows'][0]['economy']);
    assertSame(null, $calc['rows'][1]['economy']);
    assertSame(19.23, $calc['rows'][2]['economy'], '250 km ÷ 13.00 L');
    assertSame(null, $calc['rows'][3]['economy']);
    assertSame(19.57, $calc['lifetime_economy'], '450 km ÷ 23.00 L（最後の継ぎ足しは含めない）');
    assertSame(25.0, $calc['total_liters']);
}

function test_fuel_empty(): void
{
    $calc = FuelCalculator::calculate([], 0);
    assertSame(null, $calc['lifetime_economy']);
    assertSame(null, $calc['max_economy']);
    assertSame(null, FuelCalculator::economy(100, 0), '0で割らない');
}

function test_economy_rounding(): void
{
    assertSame(16.12, FuelCalculator::economy(137, 850), '137 ÷ 8.50 = 16.117…');
    assertSame(18.18, FuelCalculator::economy(200, 1100), '200 ÷ 11.00 = 18.181…');
    assertSame(0.13, FuelCalculator::economy(1, 800), '0.125 は四捨五入で 0.13');
}

function test_pager(): void
{
    // 給油記録：新しい側から20件ずつ（v1.1）。55件なら 15・20・20 件
    $rows = range(1, 55);
    $p = Pager::slice($rows, null);
    assertSame(['current' => 3, 'last' => 3, 'total' => 55, 'from' => 36, 'to' => 55], $p['page'], '最初は最新のページ');
    assertSame(range(36, 55), $p['rows'], '最新のページはいつも20件');
    assertSame(range(16, 35), Pager::slice($rows, '2')['rows']);
    assertSame(range(1, 15), Pager::slice($rows, '1')['rows'], '半端は一番古いページ');
    assertSame(3, Pager::slice($rows, '99')['page']['current'], '範囲外は最後のページ');
    assertSame(['current' => 1, 'last' => 1, 'total' => 0, 'from' => 0, 'to' => 0], Pager::slice([], null)['page'], '0件');
    assertSame(range(1, 20), Pager::slice(range(1, 20), null)['rows'], 'ちょうど20件は1ページ');
    assertSame([1], Pager::slice(range(1, 21), '1')['rows'], '21件なら一番古いページは一番古い1件');

    $logs = array_map(static fn (int $id): array => ['id' => $id], range(101, 155));
    assertSame(1, Pager::pageOf($logs, 101), '最初の行');
    assertSame(1, Pager::pageOf($logs, 115), '15行目（一番古いページの最後）');
    assertSame(2, Pager::pageOf($logs, 116), '16行目');
    assertSame(3, Pager::pageOf($logs, 136), '36行目（最新のページの最初）');
    assertSame(3, Pager::pageOf($logs, 155), '最後の行');
    assertSame(null, Pager::pageOf($logs, 999), 'ない ID');
}

function test_ride_latest_and_years(): void
{
    // 走行記録：「最新」は直近20件、年ごとはその年の記録すべて（v1.1）
    $f = fixture();
    [$vehicle, $items, $logs] = migratedRide();
    $rows = RideCalculator::rows($vehicle, $items, $logs);
    $latest = Pager::latest($rows);
    assertSame(20, count($latest), '最新は20件');
    assertSame(end($rows)['id'], end($latest)['id'], '最新の最後は一番新しい記録');
    assertSame($f['years'], Pager::years($rows), '記録のある年');
    $total = 0;
    foreach (Pager::years($rows) as $y) {
        $yearRows = Pager::byYear($rows, $y);
        foreach ($yearRows as $r) {
            assertSame((string) $y, substr($r['date'], 0, 4), "{$y}年に別の年の記録が混ざっていない");
        }
        $total += count($yearRows);
    }
    assertSame(count($rows), $total, '年ごとを合わせると全件');
    assertSame([], Pager::byYear($rows, 2020), '記録のない年は空');
    assertSame(5, count(Pager::latest(array_slice($rows, 0, 5))), '20件未満なら全件');

    // 札（設定値）に初期値も出す（v1.1）
    $status = RideCalculator::status($vehicle, $items, $logs, $f['last_odo'], $f['status_date']);
    assertSame(6, $status[8]['initial'], 'Fプリロードの初期値');
}

function test_ride_events(): void
{
    // 整備内容（C-03〜C-05）：交換した品目は、前回の交換（なければ新車時）からの距離・期間。設定値は変えた回だけ
    $f = fixture();
    [$vehicle, $items, $logs] = migratedRide([$f['last_date'] => [9 => 2, 10 => 1]]);
    $rows = RideCalculator::rows($vehicle, $items, $logs);
    $lastOdo = [];
    $lastDate = [];
    foreach ($rows as $n => $row) {
        $byItem = array_column($row['events'], null, 'item_id');
        foreach ($items as $item) {
            $id = $item['id'];
            if ($item['kind'] === 'setting') {
                assertSame(isset($logs[$n]['deltas'][$id]), isset($byItem[$id]), "{$row['date']} {$item['name']} の有無");
                continue;
            }
            $replaced = in_array($id, $logs[$n]['replaced'], true);
            assertSame($replaced, isset($byItem[$id]), "{$row['date']} {$item['name']} の有無");
            if (!$replaced) {
                continue;
            }
            $e = $byItem[$id];
            assertSame(!isset($lastOdo[$id]), $e['from_new'], "{$row['date']} {$item['name']} 新車から");
            if ($item['measure'] === 'period') {
                assertSame(Elapsed::between($lastDate[$id] ?? $f['acquired_on'], $row['date']), $e['value'], "{$row['date']} {$item['name']} の期間");
            } else {
                assertSame($row['odo'] - ($lastOdo[$id] ?? 0), $e['value'], "{$row['date']} {$item['name']} の距離");
            }
            $lastOdo[$id] = $row['odo'];
            $lastDate[$id] = $row['date'];
        }
    }

    $last = end($rows)['events'];
    assertSame(['item_id' => 9, 'name' => 'Fプリロード', 'type' => 'setting', 'delta' => 2, 'value' => 8], $last[0]);
    assertSame(['item_id' => 10, 'name' => 'Rプリロード', 'type' => 'setting', 'delta' => 1, 'value' => 7], $last[1]);

    // 札の移動先：最後に交換した記録／最後に値を変えた記録。なければ null
    $status = RideCalculator::status($vehicle, $items, $logs, $f['last_odo'], $f['status_date']);
    $oilLast = null;
    foreach ($logs as $log) {
        if (in_array(1, $log['replaced'], true)) {
            $oilLast = $log['id'];
        }
    }
    assertSame($oilLast, $status[0]['last_log_id'], 'オイルの移動先');
    assertSame(end($logs)['id'], $status[8]['last_log_id'], 'Fプリロードの移動先');
    [, , $plain] = migratedRide();
    assertSame(null, RideCalculator::status($vehicle, $items, $plain, $f['last_odo'], $f['status_date'])[8]['last_log_id'], '変えていない設定値');
}
