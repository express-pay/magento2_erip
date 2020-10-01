<?php

namespace Expresspay\Erip\Controller\Url;

use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Submit extends Action implements CsrfAwareActionInterface
{
    /** @var \Magento\Framework\View\Result\PageFactory */
    public $resultPageFactory;
    /**
     * @var \Expresspay\Erip\Block\Widget\Redirect
     */
    public $erip;

    /** @var \Magento\Framework\View\Result\RawFactory */
    public $rawResultFactory;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Framework\Registry
     */
    protected $_coreRegistry;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Expresspay\Erip\Block\Widget\Redirect $erip_form,
        \Magento\Framework\Registry $coreRegistry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->_checkoutSession = $checkoutSession;
        $this->erip = $erip_form;
        $this->_coreRegistry = $coreRegistry;
        $this->_isScopePrivate = true;
        parent::__construct($context);
    }

    public function execute()
    {
        $data = $this->getRequest()->getParams();
        $post_data = $this->erip->getPostData($data);

        $request = $this->doRequest($post_data, $this->erip->getGateUrl());
        $response = json_decode($request, true);

        if (isset($response['Errors'])) {
            $this->restoreCart();
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        } else {
            $this->_coreRegistry->register('foo', $response['ExpressPayAccountNumber']);
            $this->_coreRegistry->register('path', $this->erip->getPath());
            $this->_coreRegistry->register('ExpressPayInvoiceNo', $response['ExpressPayInvoiceNo']);
            $this->_coreRegistry->register('Amount', $this->erip->getAmount());

            $resultPage = $this->resultPageFactory->create();
            $resultPage->getConfig()->getTitle()->set(__('Счет выставлен в системе ЕРИП'));
            return $resultPage;
        }
    }

    /**
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(
        RequestInterface $request
    ): InvalidRequestException {
        return null;
    }

    /**
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): bool
    {
        return true;
    }

    /**
     * @param $data
     * @return mixed
     * @throws \Magento\Framework\Validator\Exception
     */
    private function doRequest($data, $url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    /**
     * Restore cart data
     */
    public function restoreCart()
    {
        $lastQuoteId = $this->_checkoutSession->getLastQuoteId();
        if ($quote = $this->_objectManager->get('Magento\Quote\Model\Quote')->loadByIdWithoutStore($lastQuoteId)) {
            $quote->setIsActive(true)
                ->setReservedOrderId(null)
                ->save();
            $this->_checkoutSession->setQuoteId($lastQuoteId);
        }
        $message = __('При выполнении запроса произошла непредвиденная ошибка. Пожалуйста, повторите запрос позже или обратитесь в службу технической поддержки магазина');
        $this->messageManager->addError($message);
    }
}
