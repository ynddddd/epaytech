<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Epaytech\Epay\Model;


use Magento\Payment\Model\Method\AbstractMethod;

class PaymentMethod extends AbstractMethod
{
    const CODE = 'epay';
    const POST = "[POST to Epay]";

    protected $_code = self::CODE;

    protected $_isInitializeNeeded = false;

    protected $_formBlockType = 'Epaytech\Epay\Block\Form';
    protected $_infoBlockType = 'Epaytech\Epay\Block\Info';

    protected $_isGateway = false;
    protected $_canAuthorize = false;
    protected $_canCapture = false;
    protected $_canCapturePartial = false;
    protected $_canRefund = false;
    protected $_canRefundInvoicePartial = false;
    protected $_canVoid = false;
    protected $_canUseInternal = false;
    protected $_canUseCheckout = true;
    protected $_canUseForMultishipping = false;
    protected $_canSaveCc = false;

    protected $urlBuilder;
    protected $_moduleList;
    protected $_version = "V2.0.0";
    protected $logger;

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
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Framework\Url $urlBuilder,
        array $data = []
    )
    {

        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            null,
            null,
            $data
        );

        $this->urlBuilder = $urlBuilder;
        $this->_moduleList = $moduleList;
        $this->logger = $logger;
    }

    /**
     *  Redirect URL
     *
     * @return   string Redirect URL
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->urlBuilder->getUrl('epay/payment/redirect', ['_secure' => false]);
    }


    public function canUseForCurrency($currencyCode)
    {
        return true;
    }

    public function initialize($paymentAction, $stateObject)
    {

    }

    public function getCanChangeStatus($status){
        return in_array($status,[]);
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {

        if (parent::isAvailable($quote) && $quote) {
            return true;
        }
        return false;
    }


    /**
     * @param $result
     * @return array
     */
    public function checkResult($result)
    {
        if (empty($result) || !$result['data'] || !$result['sign']) {
            $this->postLog("check:empty result or data or sing: ", $result);
            return [];
        }
        $sign = $result['sign'];
        $data = $result['data'];
        $sign_result = $this->signatureAndBuild($data);
        if ($sign_result['sign'] === $sign) {
            //$this->postLog("check:sign is ringt: ", $sign_result);
            return $data;
        }
        if (isset($data['status']) && $data['status'] == 6) {
            //$this->postLog("check:callback sign is wrong : ", $sign_result);
            return $data;
        }

        $this->postLog("check:sign is wrong : ", $sign_result);

        return [];
    }

    /**
     * 回调地址
     *
     * @return array
     */
    public function getUrlData()
    {
        return [
            'notifyUrl' => $this->urlBuilder->getUrl('epay/payment/notice', ['_secure' => true, '_nosid' => true]),
            'successUrl' => $this->urlBuilder->getUrl('checkout/onepage/success', ['_secure' => true, '_nosid' => true]),
            'failUrl' => $this->urlBuilder->getUrl('checkout/onepage/failure', ['_secure' => true, '_nosid' => true]),
        ];
    }

    /**
     * config 设置参数
     * @return array
     */
    public function getConfigParams()
    {
        return [
            'epayAccount' => $this->getConfigData('account'),
            'version' => $this->_version,
            'merchantName' => $this->getConfigData('store_name'),
            'currency' => $this->getConfigData('epay_currency'),
            'language' => $this->getConfigData('epay_language'),
            'successUrlMethod' => "GET",
            'failUrlMethod' => "GET",
        ];
    }

    /**
     * [SignatureAndBuild 获取签名]
     * @DateTime 2023-08-05
     * @param    [type]     $params [description]
     * @param    [type]     $appKey [description]
     */
    public function signatureAndBuild(array $params): array
    {

        // 排序&组装
        $sign_str = $this->sortParams($params);
        // 拼接key
        $sign_str = $sign_str . "&key=" . $this->getConfigData('api_key');
        // 加密&转大写
        $sign = strtoupper(hash("sha256", $sign_str));
        $res = [
            "param" => $params,
            "sign" => $sign
        ];
        return $res;
    }

    /**
     * 参数排序
     *
     * @param array $params
     * @return string
     */
    private function sortParams(array $params): string
    {
        // 排序
        ksort($params);
        $sign_arr = [];
        // 参数值为空不参与签名，值为数组需要递归
        foreach ($params as $key => $value) {
            if ((!is_numeric($value) && empty($value)) || is_null($value)) {
                continue;
            }
            if (is_array($value)) {
                $value = $this->sortParams($value);
                $value = "{" . $value . "}";
            }
            $sign_arr[] = "$key=$value";
        }
        return join('&', $sign_arr);
    }

    /**
     * post log
     */
    public function postLog($logType, $data)
    {
        $this->logger->debug([$logType,$data],null,true);
    }

}
