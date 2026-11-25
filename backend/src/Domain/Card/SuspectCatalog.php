<?php

declare(strict_types=1);

namespace App\Domain\Card;

/**
 * Ростер подозреваемых зависит от арт-пака:
 * у каждого набора свои id, имена и портреты.
 */
final class SuspectCatalog
{
    /**
     * @return list<array{id: string, name: string, sizeMark: int|null}>
     */
    public static function all(?string $artPack = null): array
    {
        $pack = ArtPackCatalog::normalize($artPack);

        return match ($pack) {
            'sao-anime' => self::sao(),
            default => self::noir(),
        };
    }

    /**
     * @return list<array{id: string, name: string, sizeMark: int|null}>
     */
    public static function forBoardSize(int $size, ?string $artPack = null): array
    {
        $needed = $size * $size;
        $cards = array_values(array_filter(
            self::all($artPack),
            static function (array $card) use ($size): bool {
                $mark = $card['sizeMark'];
                if ($mark === null) {
                    return true;
                }
                if ($mark === 6) {
                    return $size >= 6;
                }
                if ($mark === 7) {
                    return $size >= 7;
                }

                return false;
            }
        ));

        if (count($cards) < $needed) {
            throw new \RuntimeException(sprintf(
                'Недостаточно карт для поля %dx%d (нужно %d, есть %d)',
                $size,
                $size,
                $needed,
                count($cards)
            ));
        }

        return array_slice($cards, 0, $needed);
    }

    /** Классический Нуар. */
    /** @return list<array{id: string, name: string, sizeMark: int|null}> */
    private static function noir(): array
    {
        return [
            ['id' => 'alistair', 'name' => 'Алистер', 'sizeMark' => null],
            ['id' => 'barrin', 'name' => 'Бэррин', 'sizeMark' => null],
            ['id' => 'clive', 'name' => 'Клайв', 'sizeMark' => null],
            ['id' => 'deirdre', 'name' => 'Дейдра', 'sizeMark' => null],
            ['id' => 'elisse', 'name' => 'Элисс', 'sizeMark' => null],
            ['id' => 'ernest', 'name' => 'Эрнест', 'sizeMark' => null],
            ['id' => 'geneva', 'name' => 'Женева', 'sizeMark' => null],
            ['id' => 'harold', 'name' => 'Гарольд', 'sizeMark' => null],
            ['id' => 'isolde', 'name' => 'Изольда', 'sizeMark' => null],
            ['id' => 'jack', 'name' => 'Джек', 'sizeMark' => null],
            ['id' => 'kasim', 'name' => 'Касим', 'sizeMark' => null],
            ['id' => 'lillian', 'name' => 'Лилиан', 'sizeMark' => null],
            ['id' => 'marion', 'name' => 'Мэрион', 'sizeMark' => null],
            ['id' => 'nigel', 'name' => 'Найджел', 'sizeMark' => null],
            ['id' => 'ophelia', 'name' => 'Офелия', 'sizeMark' => null],
            ['id' => 'pedro', 'name' => 'Педро', 'sizeMark' => null],
            ['id' => 'quinton', 'name' => 'Куинтон', 'sizeMark' => null],
            ['id' => 'ramon', 'name' => 'Рамон', 'sizeMark' => null],
            ['id' => 'simon', 'name' => 'Саймон', 'sizeMark' => null],
            ['id' => 'suzanne', 'name' => 'Сюзанна', 'sizeMark' => null],
            ['id' => 'ulysses', 'name' => 'Улисс', 'sizeMark' => null],
            ['id' => 'vincent', 'name' => 'Винсан', 'sizeMark' => null],
            ['id' => 'wanda', 'name' => 'Ванда', 'sizeMark' => null],
            ['id' => 'yvonne', 'name' => 'Ивонн', 'sizeMark' => null],
            ['id' => 'zachary', 'name' => 'Закари', 'sizeMark' => null],
            ['id' => 'bruno', 'name' => 'Бруно', 'sizeMark' => 6],
            ['id' => 'celeste', 'name' => 'Селеста', 'sizeMark' => 6],
            ['id' => 'diego', 'name' => 'Диего', 'sizeMark' => 6],
            ['id' => 'elena', 'name' => 'Елена', 'sizeMark' => 6],
            ['id' => 'felix', 'name' => 'Феликс', 'sizeMark' => 6],
            ['id' => 'gretchen', 'name' => 'Гретхен', 'sizeMark' => 6],
            ['id' => 'hugo', 'name' => 'Хьюго', 'sizeMark' => 6],
            ['id' => 'iris', 'name' => 'Айрис', 'sizeMark' => 6],
            ['id' => 'julian', 'name' => 'Джулиан', 'sizeMark' => 6],
            ['id' => 'kira', 'name' => 'Кира', 'sizeMark' => 6],
            ['id' => 'lorenzo', 'name' => 'Лоренцо', 'sizeMark' => 6],
            ['id' => 'marco', 'name' => 'Марко', 'sizeMark' => 7],
            ['id' => 'nadia', 'name' => 'Надя', 'sizeMark' => 7],
            ['id' => 'oscar', 'name' => 'Оскар', 'sizeMark' => 7],
            ['id' => 'petra', 'name' => 'Петра', 'sizeMark' => 7],
            ['id' => 'quincy', 'name' => 'Куинси', 'sizeMark' => 7],
            ['id' => 'rosa', 'name' => 'Роза', 'sizeMark' => 7],
            ['id' => 'stefan', 'name' => 'Стефан', 'sizeMark' => 7],
            ['id' => 'tara', 'name' => 'Тара', 'sizeMark' => 7],
            ['id' => 'umar', 'name' => 'Умар', 'sizeMark' => 7],
            ['id' => 'vera', 'name' => 'Вера', 'sizeMark' => 7],
            ['id' => 'wade', 'name' => 'Уэйд', 'sizeMark' => 7],
            ['id' => 'xenia', 'name' => 'Ксения', 'sizeMark' => 7],
            ['id' => 'yuri', 'name' => 'Юрий', 'sizeMark' => 7],
            ['id' => 'xavier', 'name' => 'Ксавье', 'sizeMark' => 7],
        ];
    }

    /** Sword Art Online — разные герои вселенной. */
    /** @return list<array{id: string, name: string, sizeMark: int|null}> */
    private static function sao(): array
    {
        return [
            ['id' => 'kirito', 'name' => 'Кирито', 'sizeMark' => null],
            ['id' => 'asuna', 'name' => 'Асуна', 'sizeMark' => null],
            ['id' => 'klein', 'name' => 'Кляйн', 'sizeMark' => null],
            ['id' => 'silica', 'name' => 'Силика', 'sizeMark' => null],
            ['id' => 'lisbeth', 'name' => 'Лизбет', 'sizeMark' => null],
            ['id' => 'agil', 'name' => 'Агил', 'sizeMark' => null],
            ['id' => 'yui', 'name' => 'Юи', 'sizeMark' => null],
            ['id' => 'sinon', 'name' => 'Синон', 'sizeMark' => null],
            ['id' => 'leafa', 'name' => 'Лифа', 'sizeMark' => null],
            ['id' => 'yuuki', 'name' => 'Юки', 'sizeMark' => null],
            ['id' => 'eugeo', 'name' => 'Юджио', 'sizeMark' => null],
            ['id' => 'alice', 'name' => 'Алиса', 'sizeMark' => null],
            ['id' => 'heathcliff', 'name' => 'Хитклифф', 'sizeMark' => null],
            ['id' => 'argo', 'name' => 'Арго', 'sizeMark' => null],
            ['id' => 'sachi', 'name' => 'Сати', 'sizeMark' => null],
            ['id' => 'diavel', 'name' => 'Диавел', 'sizeMark' => null],
            ['id' => 'kibaou', 'name' => 'Кибао', 'sizeMark' => null],
            ['id' => 'kuradeel', 'name' => 'Курадиил', 'sizeMark' => null],
            ['id' => 'thinker', 'name' => 'Синкер', 'sizeMark' => null],
            ['id' => 'yulier', 'name' => 'Юлиер', 'sizeMark' => null],
            ['id' => 'rosalia', 'name' => 'Розалия', 'sizeMark' => null],
            ['id' => 'pitohui', 'name' => 'Питох', 'sizeMark' => null],
            ['id' => 'mugen', 'name' => 'Муген', 'sizeMark' => null],
            ['id' => 'premier', 'name' => 'Премьера', 'sizeMark' => null],
            ['id' => 'streya', 'name' => 'Стрея', 'sizeMark' => null],
            // 6+
            ['id' => 'cardinal', 'name' => 'Кардинал', 'sizeMark' => 6],
            ['id' => 'quinella', 'name' => 'Квинелла', 'sizeMark' => 6],
            ['id' => 'bercouli', 'name' => 'Беркули', 'sizeMark' => 6],
            ['id' => 'fanatio', 'name' => 'Фанатио', 'sizeMark' => 6],
            ['id' => 'eldrie', 'name' => 'Элдри', 'sizeMark' => 6],
            ['id' => 'sheyta', 'name' => 'Шейта', 'sizeMark' => 6],
            ['id' => 'deusolbert', 'name' => 'Деусольберт', 'sizeMark' => 6],
            ['id' => 'renly', 'name' => 'Ренли', 'sizeMark' => 6],
            ['id' => 'ronie', 'name' => 'Рони', 'sizeMark' => 6],
            ['id' => 'tiese', 'name' => 'Тиэсика', 'sizeMark' => 6],
            ['id' => 'sortiliena', 'name' => 'Сортилиена', 'sizeMark' => 6],
            // 7+
            ['id' => 'fizel', 'name' => 'Физель', 'sizeMark' => 7],
            ['id' => 'linel', 'name' => 'Линель', 'sizeMark' => 7],
            ['id' => 'yuna', 'name' => 'Юна', 'sizeMark' => 7],
            ['id' => 'recon', 'name' => 'Рэкон', 'sizeMark' => 7],
            ['id' => 'sigurd', 'name' => 'Сигурд', 'sizeMark' => 7],
            ['id' => 'eugene', 'name' => 'Юджин', 'sizeMark' => 7],
            ['id' => 'sakuya', 'name' => 'Сакуя', 'sizeMark' => 7],
            ['id' => 'alicia', 'name' => 'Алисия', 'sizeMark' => 7],
            ['id' => 'kizmel', 'name' => 'Кизмель', 'sizeMark' => 7],
            ['id' => 'grimlock', 'name' => 'Гримлок', 'sizeMark' => 7],
            ['id' => 'schmitt', 'name' => 'Шмитт', 'sizeMark' => 7],
            ['id' => 'caynz', 'name' => 'Кейнз', 'sizeMark' => 7],
            ['id' => 'yolko', 'name' => 'Йолко', 'sizeMark' => 7],
            ['id' => 'keita', 'name' => 'Кейта', 'sizeMark' => 7],
        ];
    }
}
