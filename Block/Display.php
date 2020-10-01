<?php

namespace Expresspay\Erip\Block;



class Display extends \Magento\Framework\View\Element\Template
{

	/**
	 * @var \Magento\Framework\Registry
	 */
	protected $_coreRegistry;

	/**
	 * @var \Expresspay\Erip\Model\Erip
	 */
	protected $Config;


	public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		\Magento\Framework\Registry $coreRegistry,
		\Expresspay\Erip\Model\Erip $paymentConfig,
		array $data = []
	) {
		$this->_coreRegistry = $coreRegistry;
		$this->Config = $paymentConfig;
		parent::__construct($context, $data);
	}

	public function getFoo()
	{
		// will return 'bar'
		return $this->_coreRegistry->registry('foo');
	}

	public function getPath()
	{
		return $this->_coreRegistry->registry('path');
	}

	public function qrCode()
	{
		if ($this->Config->getConfigData("SHOW_QR_CODE")) {
			$qrCode = '<td style="text-align: center;padding: 0px 0px 0 0;vertical-align: middle">
						<img src="data:image/jpeg;base64, ##OR_CODE##" width="200" height="200"/></p>
						<p><b>Отсканируйте QR-код для оплаты</b></p>
						</td>';
			$qrCodeBase64 = $this->getQrCode($this->_coreRegistry->registry('ExpressPayInvoiceNo'), $this->Config->getConfigData("SECRET_WORD"), $this->Config->getConfigData("TOKEN"));
			$qrCode = str_replace('##OR_CODE##',  $qrCodeBase64,  $qrCode);
			return $qrCode;
		} else {
			return "";
		}
	}

	//Получение Qr-кода
	public function getQrCode($ExpressPayInvoiceNo, $secretWord, $token)
	{
		$request_params_for_qr = array(
			"Token" => $token,
			"InvoiceId" => $ExpressPayInvoiceNo,
			'ViewType' => 'base64'
		);
		$request_params_for_qr["Signature"] = $this->Config->compute_signature($request_params_for_qr, $token, $secretWord, 'get_qr_code');

		$request_params_for_qr  = http_build_query($request_params_for_qr);
		$response_qr = $this->sendRequestGET('https://api.express-pay.by/v1/qrcode/getqrcode/?' . $request_params_for_qr);
		$response_qr = json_decode($response_qr, true);
		if (!isset($response_qr['QrCodeBody'])) {
			return '';
		} else {
			$qr_code = $response_qr['QrCodeBody'];
			return $qr_code;
		}
	}

	public function getAmount()
	{
		return $this->_coreRegistry->registry('Amount');
	}

	// Отправка GET запроса
	public function sendRequestGET($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}

	public function getBaseUrl()
	{
		$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$storeManager = $objectManager->get('\Magento\Store\Model\StoreManagerInterface');

		return $storeManager->getStore()->getBaseUrl();  // to get Base Url
	}
}
