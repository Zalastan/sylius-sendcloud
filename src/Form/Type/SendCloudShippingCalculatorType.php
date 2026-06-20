<?php

declare(strict_types=1);

namespace SpiderWeb\Sylius\SendCloudPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
            ->add('from_country_code', TextType::class, [
                'label' => 'spiderweb_sendcloud.form.from_country_code',
                'constraints' => [new NotBlank()],
                'attr' => ['placeholder' => 'ex: FR'],
            ])
            ->add('from_postal_code', TextType::class, [
                'label' => 'spiderweb_sendcloud.form.from_postal_code',
                'constraints' => [new NotBlank()],
                'attr' => ['placeholder' => 'ex: 75001'],
            ])
            ->add('enable_checkout_override', CheckboxType::class, [
                'label' => 'spiderweb_sendcloud.form.enable_checkout_override',
                'required' => false,
                'attr' => ['id' => 'sendcloud_enable_checkout_override'],
            ])
            ->add('shipping_option_code', TextType::class, [
                'label' => 'spiderweb_sendcloud.form.shipping_option_code',
                'required' => false,
                'constraints' => [
                    new Callback(static function (?string $value, ExecutionContextInterface $context): void {
                        $form = $context->getRoot();
                        $override = (bool) $form->get('enable_checkout_override')->getData();
                        if (!$override && ($value === null || trim($value) === '')) {
                            $context->buildViolation('spiderweb_sendcloud.form.shipping_option_code_required')
                                ->addViolation();
                        }
                    }),
                ],
                'attr' => [
                    'placeholder' => 'ex: colissimo:home_delivery',
                    'id' => 'sendcloud_shipping_option_code_wrapper',
                ],
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'spiderweb_sendcloud_shipping_calculator';
    }
}
