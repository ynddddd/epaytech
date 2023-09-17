<?php
/**
 * Copyright © 2016 Epay Design. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Epaytech\Epay\Block;

class Form extends \Magento\Payment\Block\Form
{
    /**
     * Checkmo template
     *
     * @var string
     */
    protected $_supportedInfoLocales = array('en');
    protected $_defaultInfoLocale = 'en';

    protected $_template = 'Epaytech_Epay::form.phtml';
}
