<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\NewsletterGraphQl\Model\Resolver;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\EnumLookup;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Newsletter\Model\SubscriptionManagerInterface;
use Magento\NewsletterGraphQl\Model\SubscribeEmailToNewsletter\Validation;
use Psr\Log\LoggerInterface;

/**
 * Resolver class for the `subscribeEmailToNewsletter` mutation. Adds an email into a newsletter subscription.
 */
class SubscribeEmailToNewsletter implements ResolverInterface
{
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EnumLookup
     */
    private $enumLookup;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SubscriptionManagerInterface
     */
    private $subscriptionManager;

    /**
     * @var Validation
     */
    private $validator;

    /**
     * @var \Magento\Newsletter\Model\Subscriber
     */
    protected $_subscriber;

    /**
     * SubscribeEmailToNewsletter constructor.
     *
     * @param CustomerRepositoryInterface $customerRepository
     * @param EnumLookup $enumLookup
     * @param LoggerInterface $logger
     * @param SubscriptionManagerInterface $subscriptionManager
     * @param Validation $validator
     */
    public function __construct(
        CustomerRepositoryInterface $customerRepository,
        EnumLookup $enumLookup,
        LoggerInterface $logger,
        SubscriptionManagerInterface $subscriptionManager,
        Validation $validator,
        \Magento\Newsletter\Model\Subscriber $subscriber
    ) {
        $this->customerRepository = $customerRepository;
        $this->enumLookup = $enumLookup;
        $this->logger = $logger;
        $this->subscriptionManager = $subscriptionManager;
        $this->validator = $validator;
        $this->_subscriber = $subscriber;
    }

    /**
     * @inheritDoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/subscribe  .log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);

//        $email = trim($args['email']);
//        $logger->info(var_dump(trim($args['email'])));

        if (empty($args['email'])) {
            throw new GraphQlInputException(
                __('You must specify an email address to subscribe to a newsletter.')
            );
        }

        $currentUserId = (int)$context->getUserId();
        $storeId = (int)$context->getExtensionAttributes()->getStore()->getId();
        $websiteId = (int)$context->getExtensionAttributes()->getStore()->getWebsiteId();

//        $startMilliseconds = floor(microtime(true) * 1000);
//        $endMilliseconds = floor(microtime(true) * 1000);
//        var_dump($endMilliseconds-$startMilliseconds);

        // Email validation start
        if (!filter_var($args['email'], FILTER_VALIDATE_EMAIL)) {
            echo "Custom Invalid email format";
        }
        else if ($currentUserId > 0) {
            $this->validator->validateEmailAvailable($args['email'], $currentUserId, $websiteId);
        } else {
            $this->validator->validateGuestSubscription();
        }

        $this->validator->validateAlreadySubscribed($args['email'], $websiteId);

        // Email Validation End

//        $this->validator->execute($args['email'], $currentUserId, $websiteId);

        try {
            $startMilliseconds = floor(microtime(true) * 1000);
//            $subscriber = $this->isCustomerSubscription($args['email'], $currentUserId)
//                ? $this->subscriptionManager->subscribeCustomer($currentUserId, $storeId)
//                : $this->subscriptionManager->subscribe($args['email'], $storeId);

            $subscriber = $this->_subscriber;
            $status = $subscriber->subscribe($args['email']);
            $subscriber->save();

            $endMilliseconds = floor(microtime(true) * 1000);
            var_dump($endMilliseconds-$startMilliseconds);

//            $status = $this->enumLookup->getEnumValueFromField(
//                'SubscriptionStatusesEnum',
//                (string)$subscriber->getSubscriberStatus()
//            );
//            var_dump($status);
        } catch (LocalizedException $e) {
            $this->logger->error($e->getMessage());

            throw new GraphQlInputException(
                __('Cannot create a newsletter subscription.')
            );
        }

        return [
            'status' => $status
        ];
    }

//    /**
//     * Returns true if a provided email equals to a current customer one
//     *
//     * @param string $email
//     * @param int $currentUserId
//     * @return bool
//     * @throws LocalizedException
//     * @throws NoSuchEntityException
//     */
//    private function isCustomerSubscription(string $email, int $currentUserId): bool
//    {
//        if ($currentUserId > 0) {
//            $customer = $this->customerRepository->getById($currentUserId);
//
//            if ($customer->getEmail() == $email) {
//                return true;
//            }
//        }
//
//        return false;
//    }
}
