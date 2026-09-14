<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;

require __DIR__.'/bootstrap.php';

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$doctrine = $kernel->getContainer()->get('doctrine');

if (!$doctrine instanceof ManagerRegistry) {
    throw new LogicException('Doctrine is not registered in the container.');
}

$manager = $doctrine->getManager();

if (!$manager instanceof EntityManagerInterface) {
    throw new LogicException('Expected a Doctrine ORM entity manager.');
}

return $manager;
