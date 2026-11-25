<?php

declare(strict_types=1);

namespace App\Domain\Card;

/**
 * Наборы иллюстраций персонажей. Расширяется новыми id без смены протокола.
 */
final class ArtPackCatalog
{
    public const DEFAULT = 'ink-minimal';

    /** @var list<string> */
    private const IDS = [
        'ink-minimal',
        'brush-flat',
        'sao-anime',
    ];

    /**
     * @return list<array{id: string, label: string, description: string, format: string}>
     */
    private static function definitions(): array
    {
        return [
            [
                'id' => 'ink-minimal',
                'label' => 'Чернильный минимализм',
                'description' => 'Плоские силуэты, ограниченная палитра',
                'format' => 'svg',
            ],
            [
                'id' => 'brush-flat',
                'label' => 'Плоская кисть',
                'description' => 'Минималистичные векторные портреты в духе noir',
                'format' => 'png',
            ],
            [
                'id' => 'sao-anime',
                'label' => 'Sword Art Online',
                'description' => 'Кирито, Асуна и другие — у каждого своё имя и лицо',
                'format' => 'png',
            ],
        ];
    }

    /**
     * @return list<array{id: string, label: string, description: string, format: string, preview: list<array{id: string, name: string}>, rosterSize: int}>
     */
    public static function all(): array
    {
        $packs = self::definitions();
        foreach ($packs as &$pack) {
            $roster = SuspectCatalog::all($pack['id']);
            $pack['preview'] = array_map(
                static fn (array $c): array => ['id' => $c['id'], 'name' => $c['name']],
                array_slice($roster, 0, 5)
            );
            $pack['rosterSize'] = count($roster);
        }
        unset($pack);

        return $packs;
    }

    public static function isValid(string $id): bool
    {
        return in_array($id, self::IDS, true);
    }

    public static function normalize(?string $id): string
    {
        $id = $id ?? self::DEFAULT;

        return self::isValid($id) ? $id : self::DEFAULT;
    }

    public static function format(string $id): string
    {
        foreach (self::definitions() as $pack) {
            if ($pack['id'] === $id) {
                return $pack['format'];
            }
        }

        return 'png';
    }
}
