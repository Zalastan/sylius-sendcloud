<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use SpiderWeb\Sylius\SendCloudPlugin\Entity\SendCloudConfiguration;

final class SendCloudConfigurationRepository
{
    /** @var EntityRepository<SendCloudConfiguration> */
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(SendCloudConfiguration::class);
    }

    public function findConfiguration(): ?SendCloudConfiguration
    {
        return $this->repository->findOneBy([]);
    }

    public function save(SendCloudConfiguration $configuration): void
    {
        $this->entityManager->persist($configuration);
        $this->entityManager->flush();
    }
}
