<?php

namespace Epaytech\Epay\Controller\Payment;


use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Model\Order\Payment\Transaction;

class Notice extends \Magento\Framework\App\Action\Action implements CsrfAwareActionInterface
{

    const PUSH = "[PUSH]";
    const CALLBACK = "[CALLBACK]";

    protected $_processingArray = array('processing', 'complete','canceled');


    /**
     * Customer session model
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;
    protected $resultPageFactory;
    protected $checkoutSession;
    protected $orderRepository;
    protected $_scopeConfig;
    protected $_orderFactory;
    protected $orderSender;
    protected $_paymentMethod;
    protected $transactionBuilder;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Action\Context $context,
        \Epaytech\Epay\Model\PaymentMethod $paymentMethod,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $orderSender,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface $transactionBuilder
    )
    {
        $this->_customerSession = $customerSession;
        $this->checkoutSession = $checkoutSession;
        $this->orderRepository = $orderRepository;
        parent::__construct($context);
        $this->_scopeConfig = $scopeConfig;
        $this->_orderFactory = $orderFactory;
        $this->_paymentMethod = $paymentMethod;
        $this->orderSender = $orderSender;
        $this->transactionBuilder = $transactionBuilder;
    }


    public function execute()
    {
        $input = $_POST;
        $this->_paymentMethod->postLog(self::CALLBACK, $input);
        $data = $this->praseInput($input);
        $model = $this->_paymentMethod;
        $check_data = $model->checkResult($data);
        if (!$check_data) {
            $this->_paymentMethod->postLog('check fail', $check_data);
            echo "fail";
            exit;
        }
        $order_id = substr($check_data['merchantOrderNo'], 0, -10);
        $order = $this->_orderFactory->create()->loadByIncrementId($order_id);
        $builder = $this->transactionBuilder->setPayment($order->getPayment())
            ->setOrder($order)
            ->setTransactionId($check_data['epayOrderNo']);
        $builder->build(Transaction::TYPE_VOID);
        $history = ' (payment_id:' . $check_data['epayOrderNo'] . ' | order_number:' . $order_id . ' | ' . $check_data['paymentCurrency'] . ':' . $check_data['paymentAmount'] . ')';
        if (in_array($order->getState(), $this->_processingArray)) {
            $this->_paymentMethod->postLog(self::CALLBACK, 'order is paid' . $order_id);
            echo 'success';
            exit;
        }
        if ($check_data['status'] == 7) {
            //支付成功
            $order->setState($model->getConfigData('success_order_status'));
            $order->setStatus($model->getConfigData('success_order_status'));
            $order->addStatusToHistory($model->getConfigData('success_order_status'), __(self::PUSH . 'Payment Success!' . $history));

            //发送邮件
            $this->orderSender->send($order);
            $order->save();
            $this->_paymentMethod->postLog(self::CALLBACK, 'order is pay success' . $order_id);
            echo "success";
            exit;

        }
        if ($check_data['status'] == 6) {
            //支付失败
            $order->setState($model->getConfigData('failure_order_status'));
            $order->setStatus($model->getConfigData('failure_order_status'));
            $order->addStatusToHistory($model->getConfigData('failure_order_status'), __(self::PUSH . 'Payment Failed!' . $history));
            $order->save();
            $this->_paymentMethod->postLog(self::CALLBACK, 'order is pay failed' . $order_id);
            echo "success";
            exit;
        }
        echo 'fail';
        exit;
    }

    private function praseInput($input)
    {
        $res = [];
        $res['sign'] = $input['sign'];
        unset($input['sign']);
        if (isset($input['isForward'])) {
            unset($input['isForward']);
        }

        if (isset($input['payUrl'])) {
            unset($input['payUrl']);
        }
        if (isset($input['message'])) {
            $message = json_decode($input['message'], true);
            if (is_array($message)) {
                $input['message'] = $message;
            }
        }
        $res['data'] = $input;
        return $res;
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}


