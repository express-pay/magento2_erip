<?php

namespace Expresspay\Erip\Model;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Magento\Sales\Model\Order;

/**
 * Class Erip
 * @package Expresspay\Erip\Model
 */
class Erip extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE = 'erip';
    /**
     * @var bool
     */
    protected $_isInitializeNeeded = true;
    /**
     * @var bool
     */
    protected $_isGateway = false;
    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'erip';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = false;
    /**
     * Sidebar payment info block
     *
     * @var string
     */
    protected $_infoBlockType = 'Magento\Payment\Block\Info\Instructions';

    protected $_gateUrl = "https://api.express-pay.by/v1/web_invoices";

    protected $_encryptor;

    protected $orderFactory;

    protected $urlBuilder;

    protected $_transactionBuilder;

    protected $_logger;

    protected $_canUseCheckout = true;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Module\ModuleListInterface $moduleList,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $builderInterface,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    )
    {
        $this->orderFactory = $orderFactory;
        $this->urlBuilder = $urlBuilder;
        $this->_transactionBuilder = $builderInterface;
        $this->_encryptor = $encryptor;
        parent::__construct($context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data);
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/expresspay_erip.log');
        $this->_logger = new \Zend\Log\Logger();
        $this->_logger->addWriter($writer);
        $this->_gateUrl = 'https://api.express-pay.by/v1/web_invoices';
    }


    /**
     * Получить объект Order по его orderId
     *
     * @param $orderId
     * @return Order
     */
    public function getOrder($orderId)
    {
        return $this->orderFactory->create()->loadByIncrementId($orderId);
    }


    /**
     * Получить сумму платежа по orderId заказа
     *
     * @param $orderId
     * @return float
     */
    public function getAmount($order)
    {
        return $order->getGrandTotal();
    }

    public static function compute_signature($request_params, $token, $secret_word, $method = 'add_invoice')
	{
		$secret_word = trim($secret_word);
		$normalized_params = array_change_key_case($request_params, CASE_LOWER);
		$api_method = array(
			'add_invoice' => array(
				"serviceid",
				"accountno",
				"amount",
				"currency",
				"expiration",
				"info",
				"surname",
				"firstname",
				"patronymic",
				"city",
				"street",
				"house",
				"building",
				"apartment",
				"isnameeditable",
				"isaddresseditable",
				"isamounteditable",
				"emailnotification",
				"smsphone",
				"returntype",
				"returnurl",
				"failurl"
			),
			'get_qr_code' => array(
				"invoiceid",
				"viewtype",
				"imagewidth",
				"imageheight"
			),
			'add_invoice_return' => array(
				"accountno",
				"invoiceno"
			)
		);

		$result = $token;

		foreach ($api_method[$method] as $item)
            $result .= (isset($normalized_params[$item])) ? $normalized_params[$item] : '';

		$hash = strtoupper(hash_hmac('sha1', $result, $secret_word));

		return $hash;
	}

    public function getSignature($data, $password, $encoded = true)
    {
        $data = array_filter($data, function ($var) {
            return $var !== '' && $var !== null;
        });
        ksort($data);
        $str = $password;
        foreach ($data as $k => $v) {
            $str .= '|' . $v;
        }
        if ($encoded) {
            return sha1($str);
        } else {
            return $str;
        }
    }

    /**
     * Получить идентификатор клиента по orderId заказа
     *
     * @param $orderId
     * @return int|null
     */
    public function getCustomerId($orderId)
    {
        return $this->getOrder($orderId)->getCustomerId();
    }


    /**
     * Получить код используемой валюты по $order
     *
     * @param $order
     * @return null|string
     */
    public function getCurrencyCode($order)
    {
        return $order->getBaseCurrencyCode();
    }

    /**
     * Get Merchant Data string
     *
     * @param $order
     * @return mixed
     */
    public function getMerchantDataString($order)
    {
        $addData = $order->getBillingAddress()->getData();
        if (!$addData){
            $addData = $order->getShippigAddress()->getData();
        }
        if ($addData){
            $addInfo = [
                'Fullname' => $addData['firstname'] . ' ' . $addData['middlename'] . ' ' . $addData['lastname']
            ];
            return $addInfo;
        }
        return null;
    }

    /**
     * Get Reservation Data string
     *
     * @param $order
     * @return mixed
     */
    public function getReservDataString($order)
    {
        $addData = $order->getBillingAddress()->getData();
        if (!$addData){
            $addData = $order->getShippigAddress()->getData();
        }

        $skuString = '';
        try {
            $orderItems = $order->getAllVisibleItems();
            $countItems = count($orderItems);
            $i = 0;
            foreach ($orderItems as $key => $orderItem) {
                $sku = $orderItem->getData()['sku'];
                if ($countItems > 1) {
                    $skuString .= ++$i === $countItems ? $sku . '' : $sku . ', ';
                } else {
                    $skuString .= $sku;
                }
            }
        } catch (Exception $e) {
            $skuString = "No sku";
            $this->_logger->debug("Cant get products sku");
        }

        $addInfo = [
            'customer_zip' => isset($addData['postcode']) ? $addData['postcode'] : '',
            'customer_name' => $addData['firstname'] . ' ' . $addData['middlename'] . ' ' . $addData['lastname'],
            'customer_address' => isset($addData['street']) ? $addData['street'] : '',
            'customer_state' => isset($addData['region_id']) ? $addData['region_id'] : '',
            'customer_country' => isset($addData['country_id']) ? $addData['country_id'] : '',
            'phonemobile' => isset($addData['telephone']) ? $addData['telephone'] : '',
            'account' => isset($addData['email']) ? $addData['email'] : '',
            'products_sku' => isset($skuString) ? $skuString : ''
        ];

        try {
            $addInfo['Shipping total'] = number_format($order->getShippingAmount(), 2, '.', '');
        } catch (Exception $e) {
            $this->_logger->debug("Can't get products shipping price");
        }

        return $addInfo;
    }


    /**
     * Check whether payment method can be used with selected shipping method
     * @param $shippingMethod
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function isCarrierAllowed($shippingMethod)
    {
        $allowedConfig = $this->getConfigData('allowed_carrier');

        if ($allowedConfig == '' || !$allowedConfig) {
            return true;
        }

        $allow = explode(',', $allowedConfig);
        foreach ($allow as $v) {
            if (preg_match("/{$v}/i", $shippingMethod)) {
                return true;
            }
        }

        return strpos($allowedConfig, $shippingMethod) !== false;
    }


    /**
     * Check whether payment method can be used
     * @param CartInterface|null $quote
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ($quote === null) {
            return false;
        }
        return parent::isAvailable($quote) && $this->isCarrierAllowed(
                $quote->getShippingAddress()->getShippingMethod()
            );
    }


    /**
     * @return string
     */
    public function getGateUrl()
    {
        if ($this->getConfigData("TEST_MODE"))
        {
            return "https://sandbox-api.express-pay.by/v1/web_invoices";
        }
        else
        {
            return $this->_gateUrl;
        }
        
    }


    /**
     * @return string
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getDataIntegrityCode()
    {
        return $this->_encryptor->decrypt($this->getConfigData('SECRET_WORD'));
    }


    /**
     * Get form array
     * @param $orderId
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getPostData($orderId)
    {
        $order = $this->getOrder($orderId);

        if (!$order){
            return ['error' => 'No data'];
        }

        $merchant_data = $this->getMerchantDataString($order);
        $reservation_data = $this->getReservDataString($order);
        $email = $order->getCustomerEmail();

		$postData = array(
			'ServiceId'         => $this->getConfigData("SERVICE_ID"),
			'AccountNo'         => $orderId,
			'Amount'            => number_format(floatval($this->getAmount($order)), 2, ',', ''),
			'Currency'          => 933,
			'ReturnType'        => 'json',
			'ReturnUrl'         => '',
			'FailUrl'           => '',
			'Expiration'        => '',
			'Info'              => "Оплата заказа №" . $orderId,
			'Surname'           => '',
			'FirstName'         => '',
			'Patronymic'        => '',
			'Street'            => '',
			'House'             => '',
			'Apartment'         => '',
			'IsNameEditable'    =>  $this->getConfigData("IS_NAME_EDIT"),
			'IsAddressEditable' =>  $this->getConfigData("IS_ADDRESS_EDIT"),
			'IsAmountEditable'  =>  $this->getConfigData("IS_AMOUNT_EDIT"),
			'EmailNotification' => $email,
			'SmsPhone'          => ''
        );
        
        $secretWord =$this->getConfigData("USE_SIGNATURE") ? $this->getConfigData("SECRET_WORD") : '';

        $sign = $this->compute_signature($postData, $this->getConfigData("TOKEN"), $secretWord);
        $postData['Signature'] = $sign;

        return $postData;
    }

    /**
     * @param string $field
     * @param null $storeId
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

        if (null === $storeId) {
            $storeId = $storeManager->getStore()->getStoreId();
        }
        $path = 'payment/' . $this->_code . '/' . $field;
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
}
