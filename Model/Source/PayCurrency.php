<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Epaytech\Epay\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class PayCurrency implements ArrayInterface {

    /**
     * @return array
     */
	public function toOptionArray() {
        return [
            ['value' => 'USD', 'label' =>'USD'],
            ['value' => 'HKD', 'label' =>'HKD'],
            ['value' => 'EUR', 'label' =>'EUR'],
            ['value' => 'GBP', 'label' =>'GBP'],
            ['value' => 'JPY', 'label' =>'JPY'],
            ['value' => 'CNY', 'label' =>'CNY'],
            ['value' => 'AUD', 'label' =>'AUD'],
        ];
    }
}

