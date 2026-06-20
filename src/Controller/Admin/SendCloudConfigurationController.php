<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Controller\Admin;

use SpiderWeb\Sylius\SendCloudPlugin\Encryption\CredentialEncryptor;
use SpiderWeb\Sylius\SendCloudPlugin\Entity\SendCloudConfiguration;
use SpiderWeb\Sylius\SendCloudPlugin\Form\SendCloudConfigurationData;
use SpiderWeb\Sylius\SendCloudPlugin\Form\Type\SendCloudConfigurationType;
use SpiderWeb\Sylius\SendCloudPlugin\Repository\SendCloudConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SendCloudConfigurationController extends AbstractController
{
    public function __construct(
        private readonly SendCloudConfigurationRepository $repository,
        private readonly CredentialEncryptor $encryptor,
    ) {}

    public function __invoke(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMINISTRATION_ACCESS');

        $configuration = $this->repository->findConfiguration() ?? new SendCloudConfiguration();
        $isNew = $configuration->getId() === null;
        $data = $this->buildFormData($configuration);

        $form = $this->createForm(SendCloudConfigurationType::class, $data, ['is_new' => $isNew]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($data->publicKey !== '') {
                $configuration->setPublicKey($this->encryptor->encrypt($data->publicKey));
            }
            if ($data->privateKey !== '') {
                $configuration->setPrivateKey($this->encryptor->encrypt($data->privateKey));
            }
            $configuration->setEnabled($data->enabled);
            $this->repository->save($configuration);

            $this->addFlash('success', 'spiderweb_sendcloud.ui.configuration_saved');

            return $this->redirectToRoute('spiderweb_sendcloud_admin_configuration');
        }

        return $this->render('@SendCloudPlugin/admin/sendcloud/configuration.html.twig', [
            'form' => $form->createView(),
            'configuration' => $configuration,
            'is_new' => $isNew,
        ]);
    }

    private function buildFormData(SendCloudConfiguration $configuration): SendCloudConfigurationData
    {
        $data = new SendCloudConfigurationData();
        $data->enabled = $configuration->isEnabled();

        return $data;
    }
}
