<?php

namespace Expresspay\Erip\Controller\Url;

use Magento\Framework\App\Action\Action;
use Magento\Sales\Model\Order;
use Symfony\Component\Config\Definition\Exception\Exception;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Notification extends Action implements CsrfAwareActionInterface
{

    /**
     * @var \Expresspay\Erip\Model\Erip
     */
    protected $Config;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Expresspay\Erip\Model\Erip $paymentConfig
    ) {
        $this->Config = $paymentConfig;
        parent::__construct($context);
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

    public function execute()
    {
        $json = $this->getRequest()->getPostValue();
        // Преобразуем из JSON в Object
        $data = json_decode($json['Data'], true);
        if (isset($json['Signature'])) {
            $signature = $json['Signature'];
            $this->processResponse($data, $signature);
        } else {
            $this->processResponse($data, '');
        }
    }


    public function processResponse($responseData, $signature)
    {
        $order = $this->Config->getOrder($responseData['AccountNo']);
        $state = $order->getStatus();

        if ($this->Config->getConfigData("USE_SIGNATURE_FOR_NOTIF")) {
            if ($signature == $this->computeSignature($responseData, $this->Config->getConfigData("SECRET_WORD_FOR_NOTIF"))) {
                if (!empty($state) && $order && ($this->_processOrder($order, $responseData) === true)) {
                    $status = 'OK | payment received';
                    echo ($status);
                    header("HTTP/1.0 200 OK");
                    die;
                } else {
                    $status = 'FAILED | ID заказа неизвестен';
                    echo ($status);
                    header("HTTP/1.0 500 Bad Request");
                    die;
                }
            } else {
                $status = 'FAILED | wrong notify signature';
                echo ($status);
                header("HTTP/1.0 400 Bad Request");
                die;
            }
        } else {
            if (!empty($state) && $order && ($this->_processOrder($order, $responseData) === true)) {
                $status = 'OK | payment received';
                echo ($status);
                header("HTTP/1.0 200 OK");
                die;
            } else {
                $status = 'FAILED | ID заказа неизвестен';
                echo ($status);
                header("HTTP/1.0 400 Bad Request");
                die;
            }
        }
    }

    // обновление статуса заказа
    protected function _processOrder($order, $response)
    {
        if ($response['CmdType'] == '1') {
            $order
                ->setState(Order::STATE_PROCESSING)
                ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING))
                ->save();
            return true;
        }
        // Изменился статус счета
        if ($response['CmdType'] == '3') {
            // Счет оплачен
            if ($response['Status'] == '3') {

                $order
                    ->setState(Order::STATE_PROCESSING)
                    ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING))
                    ->save();
                return true;
            }
            // Счет оплачен
            if ($response['Status'] == '6') {

                $order
                    ->setState(Order::STATE_PROCESSING)
                    ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING))
                    ->save();
                return true;
            }
            // Счет отменён
            if ($response['Status'] == '5') {
                $order
                    ->setState(Order::STATE_CANCELED)
                    ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED))
                    ->setCanSendNewEmailFlag(false)
                    ->save();
                return true;
            }
        }
    }

    function computeSignature($json, $secretWord)
    {
        $hash = NULL;

        $secretWord = trim($secretWord);

        if (empty($secretWord))
            $hash = strtoupper(hash_hmac('sha1', $json, ""));
        else
            $hash = strtoupper(hash_hmac('sha1', $json, $secretWord));
        return $hash;
    }
}
