<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\P2PEmpireNotifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class P2PEmpireCrawlerCommand extends Command
{
    protected static $defaultName = 'lamasfoker:p2pempire-crawl';

    private P2PEmpireNotifier $p2pempireNotifier;

    public function __construct(
        P2PEmpireNotifier $p2pempireNotifier,
        string $name = null
    ) {
        $this->p2pempireNotifier = $p2pempireNotifier;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Crawl news from p2pempire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->p2pempireNotifier->notify();
        return Command::SUCCESS;
    }
}
