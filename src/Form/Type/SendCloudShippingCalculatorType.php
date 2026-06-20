<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

final class SendCloudShippingCalculatorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('public_key', TextType::class, [
                'label' => 'spiderweb_sendcloud.form.public_key',
                'constraints' => [new NotBlank()],
            ])
            ->add('private_key', PasswordType::class, [
                'label' => 'spiderweb_sendcloud.form.private_key',
                'always_empty' => false,
                'constraints' => [new NotBlank()],
            ])
            ->add('amount', IntegerType::class, [
                'label' => 'spiderweb_sendcloud.form.amount',
                'constraints' => [new NotBlank(), new PositiveOrZero()],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'spiderweb_sendcloud_shipping_calculator';
    }
}
