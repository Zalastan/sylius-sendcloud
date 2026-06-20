<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class SendCloudConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $keyConstraints = $options['is_new'] ? [new NotBlank()] : [];

        $builder
            ->add('publicKey', TextType::class, [
                'label' => 'spiderweb_sendcloud.form.public_key',
                'required' => $options['is_new'],
                'constraints' => $keyConstraints,
            ])
            ->add('privateKey', PasswordType::class, [
                'label' => 'spiderweb_sendcloud.form.private_key',
                'always_empty' => true,
                'required' => $options['is_new'],
                'constraints' => $keyConstraints,
            ])
            ->add('enabled', CheckboxType::class, [
                'label' => 'spiderweb_sendcloud.form.enabled',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['is_new' => true]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }

    public function getBlockPrefix(): string
    {
        return 'spiderweb_sendcloud_configuration';
    }
}
