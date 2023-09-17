<?php
/**
 * Copyright © 2016 Epay Design. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Epaytech\Epay\Block;

class Info extends \Magento\Payment\Block\Info
{
    /**
     * @var string
     */
    protected $_payableTo;

    /**
     * @var string
     */
    protected $_mailingAddress;

    /**
     * @var string
     */
    protected $_template = 'Epaytech_Epay::info.phtml';


    public function getMethodCode()
    {
        return $this->getInfo()->getLast();
    }

    

    /**
     * @return string
     */
    public function toPdf()
    {
        //$this->setTemplate('Easypayment_Epay::info/pdf/checkmo.phtml');
        $this->setTemplate('Epaytech_Epay::pdf/info.phtml');
        return $this->toHtml();
    }
}
