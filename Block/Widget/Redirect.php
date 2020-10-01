<?php

namespace Expresspay\Erip\Block\Widget;

/**
 * Abstract class
 */

use \Magento\Framework\View\Element\Template;


class Redirect extends Template
{
    /**
     * @var \Expresspay\Erip\Model\Erip
     */
    protected $Config;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Sales\Model\Order\Config
     */
    protected $_orderConfig;

    /**
     * @var \Magento\Framework\App\Http\Context
     */
    protected $httpContext;

    /**
     * @var string
     */
    protected $_template = 'html/expresspay_form.phtml';


    /**
     * @param Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Sales\Model\Order\Config $orderConfig
     * @param \Magento\Framework\App\Http\Context $httpContext
     * @param \Fondy\Fondy\Model\Fondy $paymentConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Sales\Model\Order\Config $orderConfig,
        \Magento\Framework\App\Http\Context $httpContext,
        \Expresspay\Erip\Model\Erip $paymentConfig,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        array $data = []
    )
    {
        parent::__construct($context, $data);
        $this->_checkoutSession = $checkoutSession;
        $this->_customerSession = $customerSession;
        $this->_orderConfig = $orderConfig;
        $this->_isScopePrivate = true;
        $this->httpContext = $httpContext;
        $this->Config = $paymentConfig;
        $this->_orderRepository = $orderRepository;
    }


    /**
     * Get instructions text from config
     *
     * @return null|string
     */
    public function getGateUrl()
    {
        return $this->Config->getGateUrl();
    }

    public function getPath()
    {
        $orderId = $this->_checkoutSession->getLastOrderId();

        if ($orderId) {
            return $this->Config->getConfigData("PATH_TO_ERIP");
        }

        return ['error' => 'No data'];
    }


    /**
     * Получить сумму к оплате
     *
     * @return float|null
     */
    public function getAmount()
    {
        $orderId = $this->_checkoutSession->getLastOrderId();

        if ($orderId) {
            $incrementId = $this->_checkoutSession->getLastRealOrderId();
            $order =  $this->Config->getOrder($incrementId);
            return number_format(floatval($this->Config->getAmount($order)), 2, ',', '');
        }

        return ['error' => 'No data'];
    }


    /**
     * @return array|null
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getPostData($data = [])
    {

        $orderId = $this->_checkoutSession->getLastOrderId();

        if ($orderId or isset($data['order'])) {
            $incrementId = $this->_checkoutSession->getLastRealOrderId();
            if (!$incrementId) {
                $order = $this->_orderRepository->get($data['order']);
                if ($order) {
                    if ($order->getStatus() == 'pending' and $order->getState() == 'new') {
                        $incrementId = $order->getIncrementId();
                    }
                }
            }
            if (!$incrementId){
                return ['error' => 'No data'];
            }
            return $this->Config->getPostData($incrementId);
        }

        return ['error' => 'No data'];
    }

}
