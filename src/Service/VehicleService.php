<?php
/*
 * Motolog（バイクのメンテナンス記録）
 * Copyright (C) 2026 合同会社FIA. All rights reserved.
 * 作成：Takeshi Hakamata
 */
declare(strict_types=1);

namespace Motolog\Service;

use Motolog\Http\ApiException;
use Motolog\Repository\ItemRepository;
use Motolog\Repository\VehicleRepository;
use Motolog\Support\Db;
use Motolog\Support\Msg;
use Motolog\Support\Validate;

/** 車両の登録・編集・画像（F-40／F-41／D10／D12）。 */
final class VehicleService
{
    public const IMAGE_WIDTH = 1200;
    public const IMAGE_HEIGHT = 900;
    public const IMAGE_MAX_BYTES = 10 * 1024 * 1024;

    public function __construct(
        private readonly VehicleRepository $vehicles = new VehicleRepository(),
        private readonly ItemRepository $items = new ItemRepository(),
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(bool $includeRetired): array
    {
        return array_map([self::class, 'present'], $this->vehicles->all($includeRetired));
    }

    /** 車両がなければ「見つからない」で止める。 @return array<string, mixed> */
    public function mustFind(int $id): array
    {
        $v = $this->vehicles->find($id);
        if ($v === null) {
            throw ApiException::notFound();
        }
        return $v;
    }

    /** @return array<string, mixed> */
    public function get(int $id): array
    {
        return self::present($this->mustFind($id));
    }

    /** @param array<string, mixed> $input */
    public function create(array $input): int
    {
        $v = $this->validate($input, null);
        $copyFrom = $input['copy_items_from'] ?? null;
        $copyFromId = ($copyFrom === null || $copyFrom === '') ? null : Validate::int($copyFrom, 1, PHP_INT_MAX);
        if ($copyFromId !== null) {
            $this->mustFind($copyFromId);
        }
        return Db::transaction(function () use ($v, $copyFromId): int {
            $id = $this->vehicles->insert($v);
            if ($copyFromId !== null) {
                foreach ($this->items->byVehicle($copyFromId) as $item) {
                    $this->items->insert($id, $item);
                }
            }
            return $id;
        });
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input): void
    {
        $this->mustFind($id);
        $v = $this->validate($input, $id);
        $this->vehicles->update($id, $v);
    }

    /**
     * 画像を 4:3 の 1200×900 の JPEG にして保存する（D08／D12）。
     * ブラウザで切り取った画像が届くが、念のためサーバでも中身を確かめて作り直す。
     *
     * @param array<string, mixed>|null $file $_FILES の1件
     */
    public function saveImage(int $id, ?array $file): void
    {
        $vehicle = $this->mustFind($id);
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_uploaded_file((string) $file['tmp_name']) || (int) $file['size'] > self::IMAGE_MAX_BYTES) {
            throw ApiException::validation(['image' => Msg::M20]);
        }
        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ApiException::validation(['image' => Msg::M20]);
        }
        $src = @imagecreatefromstring((string) file_get_contents((string) $file['tmp_name']));
        if ($src === false) {
            throw ApiException::validation(['image' => Msg::M20]);
        }

        // 4:3 になるよう、中央を切り取る
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w * 3 > $h * 4) {
            $cropH = $h;
            $cropW = intdiv($h * 4, 3);
        } else {
            $cropW = $w;
            $cropH = intdiv($w * 3, 4);
        }
        $dst = imagecreatetruecolor(self::IMAGE_WIDTH, self::IMAGE_HEIGHT);
        imagecopyresampled(
            $dst, $src, 0, 0, intdiv($w - $cropW, 2), intdiv($h - $cropH, 2),
            self::IMAGE_WIDTH, self::IMAGE_HEIGHT, $cropW, $cropH
        );

        $name = sprintf('vehicle_%d_%s.jpg', $id, bin2hex(random_bytes(6)));
        if (!imagejpeg($dst, self::uploadDir() . '/' . $name, 88)) {
            throw new \RuntimeException('車両画像を保存できませんでした');
        }
        $this->vehicles->setImage($id, $name);

        $old = $vehicle['image_file'];
        if (is_string($old) && $old !== '' && is_file(self::uploadDir() . '/' . $old)) {
            unlink(self::uploadDir() . '/' . $old);
        }
    }

    /** 車両画像のファイルの場所。なければ null。 */
    public function imagePath(int $id): ?string
    {
        $file = $this->mustFind($id)['image_file'];
        if (!is_string($file) || $file === '' || basename($file) !== $file) {
            return null;
        }
        $path = self::uploadDir() . '/' . $file;
        return is_file($path) ? $path : null;
    }

    private static function uploadDir(): string
    {
        return APP_ROOT . '/storage/uploads';
    }

    /**
     * 入力チェック（詳細設計書 4_入力チェック S03）。
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function validate(array $input, ?int $id): array
    {
        $errors = [];

        $name = Validate::text($input['name'] ?? '', 50);
        if ($name === null) {
            $errors['name'] = Msg::m16('車両名', 50);
        } elseif ($name === '') {
            $errors['name'] = Msg::m10('車両名');
        }
        $vin = Validate::text($input['vin'] ?? '', 30);
        if ($vin === null) {
            $errors['vin'] = Msg::m16('車台番号', 30);
        }
        $plate = Validate::text($input['plate'] ?? '', 30);
        if ($plate === null) {
            $errors['plate'] = Msg::m16('登録番号', 30);
        }

        $acquiredRaw = $input['acquired_on'] ?? '';
        $acquired = Validate::date($acquiredRaw);
        if ($acquired === null) {
            $errors['acquired_on'] = ($acquiredRaw === '' || $acquiredRaw === null) ? Msg::m10('取得日') : Msg::m11('取得日');
        } elseif ($id !== null) {
            $first = $this->vehicles->firstRideLog($id);
            if ($first !== null && $acquired > (string) $first['ride_date']) {
                $errors['acquired_on'] = Msg::m19((string) $first['ride_date']);
            }
        }

        $firstRaw = $input['first_registered_on'] ?? '';
        $firstRegistered = null;
        if ($firstRaw !== '' && $firstRaw !== null) {
            $firstRegistered = Validate::month($firstRaw);
            if ($firstRegistered === null) {
                $errors['first_registered_on'] = Msg::m11('初度登録年月');
            } elseif ($acquired !== null && $firstRegistered > $acquired) {
                $errors['first_registered_on'] = Msg::M23;
            }
        }

        $status = $input['status'] ?? 'active';
        if (!in_array($status, ['active', 'retired'], true)) {
            $errors['status'] = Msg::m10('保有状態');
        }

        if ($errors !== []) {
            throw ApiException::validation($errors);
        }
        return [
            'name' => $name,
            'vin' => $vin === '' ? null : $vin,
            'plate' => $plate === '' ? null : $plate,
            'acquired_on' => $acquired,
            'first_registered_on' => $firstRegistered,
            'status' => $status,
        ];
    }

    /** 画面に返す形。 @param array<string, mixed> $v @return array<string, mixed> */
    private static function present(array $v): array
    {
        $first = $v['first_registered_on'];
        return [
            'id' => (int) $v['id'],
            'name' => (string) $v['name'],
            'vin' => $v['vin'],
            'plate' => $v['plate'],
            'acquired_on' => (string) $v['acquired_on'],
            'first_registered_on' => is_string($first) ? substr($first, 0, 7) : null,
            'status' => (string) $v['status'],
            'has_image' => is_string($v['image_file']) && $v['image_file'] !== '',
        ];
    }
}
