<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\PaymentServicesPaypal\Controller\Adminhtml\Order;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Session\Quote as QuoteSession;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\PaymentServicesBase\Model\HttpException;
use Magento\PaymentServicesPaypal\Helper\OrderHelper;
use Magento\PaymentServicesPaypal\Model\OrderService;
use Magento\Quote\Model\Quote\Address as Address;
use Magento\ServiceProxy\Controller\Adminhtml\AbstractProxyController;

class Create extends AbstractProxyController implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_PaymentServicesPaypal::ordercreate';

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var QuoteSession
     */
    private $quoteSession;

    /**
     * @var OrderHelper
     */
    private $orderHelper;

    /**
     * @param Context $context
     * @param QuoteSession $quoteSession
     * @param OrderService $orderService
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        Context $context,
        QuoteSession $quoteSession,
        OrderService $orderService,
        OrderHelper $orderHelper
    ) {
        parent::__construct($context);
        $this->quoteSession = $quoteSession;
        $this->orderService = $orderService;
        $this->orderHelper = $orderHelper;
    }

    /**
     * @inheritDoc
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        try {
            $quote = $this->quoteSession->getQuote();
            $customerId = $quote->getCustomerId();
            $payer = $customerId !== null && $customerId != ""
                ? $this->orderService->buildPayer($quote, (string)$customerId)
                : $this->orderService->buildGuestPayer($quote);
            $paymentSource = $this->getRequest()->getPost('payment_source');
            $response = $this->orderService->create(
                [
                    'amount' => $this->orderHelper->formatAmount((float)$quote->getBaseGrandTotal()),
                    'currency_code' => $quote->getCurrency()->getBaseCurrencyCode(),
                    'shipping_address' => $this->orderService->mapAddress($quote->getShippingAddress()),
                    'billing_address' => $this->orderService->mapAddress($quote->getBillingAddress()),
                    'payer' => $payer,
                    'is_digital' => $quote->isVirtual(),
                    'website_id' => $quote->getStore()->getWebsiteId(),
                    'store_code' => $quote->getStore()->getCode(),
                    'payment_source' => $paymentSource,
                    'quote_id' => $quote->getId()
                ]
            );
            $result->setHttpResponseCode($response['status'])
                ->setData(['response' => $response]);

        } catch (HttpException $e) {
            $result->setHttpResponseCode(500);
            $result->setData($e->getMessage());
        }
        return $result;
    }
}
