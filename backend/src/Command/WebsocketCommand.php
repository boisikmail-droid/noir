<?php

declare(strict_types=1);

namespace App\Command;

use App\Realtime\GameSocketServer;
use App\Service\GameHub;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:websocket', description: 'WebSocket-сервер лобби и Шпионских игр')]
final class WebsocketCommand extends Command
{
    public function __construct(
        private readonly GameHub $hub,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'Порт', '8081');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Хост', '0.0.0.0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $port = (int) $input->getOption('port');
        $host = (string) $input->getOption('host');

        $output->writeln(sprintf('<info>WebSocket слушает ws://%s:%d</info>', $host, $port));

        $server = IoServer::factory(
            new HttpServer(new WsServer(new GameSocketServer($this->hub))),
            $port,
            $host
        );
        $server->run();

        return Command::SUCCESS;
    }
}
