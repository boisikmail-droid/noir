<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        return $this->json([
            'ok' => true,
            'app' => 'Noir',
            'modes' => [
                ['id' => 'spy_games', 'label' => 'Шпионские игры', 'players' => '3–6, 8 или 9'],
            ],
        ]);
    }

    #[Route('/dist/{path}', name: 'dist_assets', requirements: ['path' => '.+'], methods: ['GET'])]
    public function distAssets(string $path): Response
    {
        $base = realpath(dirname(__DIR__, 2).'/public/dist');
        $file = realpath(dirname(__DIR__, 2).'/public/dist/'.$path);
        if ($base === false || $file === false || !str_starts_with($file, $base) || !is_file($file)) {
            throw new NotFoundHttpException();
        }

        return new BinaryFileResponse($file);
    }

    #[Route('/{vue}', name: 'spa', requirements: ['vue' => '^(?!api|dist).*$'], defaults: ['vue' => ''], methods: ['GET'], priority: -10)]
    public function spa(): Response
    {
        $index = dirname(__DIR__, 2).'/public/dist/index.html';
        if (!is_file($index)) {
            return new Response(
                '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>Noir</title></head>'
                .'<body style="font-family:sans-serif;background:#0c0b0a;color:#e6dfd3;padding:2rem">'
                .'<h1>NOIR</h1><p>Соберите фронтенд:</p>'
                .'<pre style="background:#1c1916;padding:1rem">cd frontend'."\n".'npm install'."\n".'npm run build</pre>'
                .'<p>Затем WebSocket: <code>php bin/console app:websocket</code></p>'
                .'</body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        return new Response((string) file_get_contents($index), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
