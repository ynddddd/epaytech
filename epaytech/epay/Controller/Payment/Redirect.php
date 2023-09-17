<?php

namespace Epaytech\Epay\Controller\Payment;

class Redirect extends \Magento\Framework\App\Action\Action
{
    /**
     * Customer session model
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;
    protected $_paymentMethod;
    protected $_checkoutSession;
    protected $_orderFactory;
    protected $_curl;
    protected $_api_url = 'https://api.epay.com/capi/openapi/';
    protected $magento_currency;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Epaytech\Epay\Model\PaymentMethod $paymentMethod,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Directory\Model\Currency $magento_currency

    )
    {
        $this->_customerSession = $customerSession;
        $this->_paymentMethod = $paymentMethod;
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_curl = $curl;
        $this->magento_currency = $magento_currency;

    }

    private function getOrderCurrency($orderCurrency)
    {
        if (in_array($orderCurrency, ['USD', 'HKD', 'EUR', 'GBP', 'JPY', 'CNY', 'AUD'])) {
            return $orderCurrency;
        }
        return $this->_paymentMethod->getConfigData('epay_currency');
    }

    public function execute()
    {
        $orderIncrementId = $this->_checkoutSession->getLastRealOrderId();
        $order = $this->_orderFactory->create()->loadByIncrementId($orderIncrementId);
        if ($order) {
            //支付币种
            $order_currency = $order->getOrderCurrencyCode();
            //金额
            $order_amount = sprintf('%.2f', $order->getGrandTotal());
            $payCurrency = $this->getOrderCurrency($order_currency);
            if ($payCurrency != $order_currency) {
                $rates = $this->magento_currency->getCurrencyRates($order_currency, $payCurrency);
                if(isset($rates[$payCurrency])){
                    $rate = floatval($rates[$payCurrency]);
                    $order_amount = bcmul($order_amount, $rate, 2);
                }else{
                    $this->messageManager->addErrorMessage(__('pay currency is not support'));
                    $url = $this->urlBuilder->getUrl('checkout/onepage/failure');
                    $this->getResponse()->setRedirect($url);
                }

            }
            $parameter = [
                'currency' => $payCurrency,
                'merchantOrderNo' => $orderIncrementId . time(),
                'amount' => $order_amount,
            ];

            $data = array_merge($this->_paymentMethod->getConfigParams(), $this->_paymentMethod->getUrlData(), $parameter);

            $params = $this->_paymentMethod->signatureAndBuild($data);

            //记录提交日志
            //$this->_paymentMethod->postLog("before_send", $params);
            $response = $this->post_transaction($params);
            //$this->_paymentMethod->postLog("before_check", $response);
            $checkData = $this->_paymentMethod->checkResult($response);

            //$this->_paymentMethod->postLog("after_check", $checkData);
            if ($checkData && $checkData['epayUrl']) {
                $redirectUrl = $checkData['epayUrl'];
                $this->getResponse()->setRedirect($redirectUrl);
                //$this->_redirect($redirectUrl);
                //exit;
            } else {
                $this->_paymentMethod->postLog("Payment info error", $checkData);
                //throw new \Magento\Framework\Validator\Exception(__('Payment info error.'));
                $this->messageManager->addErrorMessage(__('Payment info error.'));
            }

        } else {
            $this->_paymentMethod->postLog(["after_check", $orderIncrementId]);
            //throw new \Magento\Framework\Validator\Exception(__('order info info error.'));
            $this->messageManager->addErrorMessage(__('order info info error.'));
        }
    }

    /**
     * 请求epay接口
     *
     * @param $requestData
     * @param int $refundRequest
     * @return mixed
     * @throws \Magento\Framework\Validator\Exception
     */
    private function post_transaction($requestData, $path = "gateway/sendTransaction")
    {
        $pay_mode = $this->_paymentMethod->getConfigData('pay_mode');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->addHeader("Content-Type", "application/json");
        if ($pay_mode == 'sandbox') {
            $this->_api_url = 'http://29597375fx.epaydev.xyz/capi/openapi/';
        }
        $this->_curl->post($this->_api_url . $path, json_encode($requestData));
        $response = $this->_curl->getBody();
        $response = json_decode($response, true);
        return $response;
    }

    /**
     * @param $amount
     * @param $currency
     * @param string $path
     * @return mixed
     */
    private function post_currency($amount, $currency, $path = "payinApi/calculateAmount")
    {
        $pay_mode = $this->_paymentMethod->getConfigData('pay_mode');
        $this->_curl->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->_curl->addHeader("Content-Type", "application/json");
        if ($pay_mode == 'sandbox') {
            $this->_api_url = 'http://29597375fx.epaydev.xyz/capi/openapi/';
        }
        $data = [
            'epayAccount' => $this->_paymentMethod->getConfigData('account'),
            'version' => 'V2.0.0',
            'category' => 'CASH',
            'paymentCurrency' => $this->_paymentMethod->getConfigData('epay_currency'),
            'receiveCurrency' => $currency,
            'receiveAmount' => "",
            'paymentAmount' => $amount,
            'countryCode' => 'US',
        ];

        $requestData = $this->_paymentMethod->signatureAndBuild($data);
        $this->_paymentMethod->postLog('currency', $requestData);

        $this->_curl->post($this->_api_url . $path, json_encode($requestData));
        $response = $this->_curl->getBody();
        $response = json_decode($response, true);
        return $response;
    }

}


