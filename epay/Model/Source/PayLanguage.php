<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Epaytech\Epay\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class PayLanguage implements ArrayInterface {

    /**
     * @return array
     */
	public function toOptionArray() {
        return [
            ['value' => 'EN', 'label' =>__('EN')],
            ['value' => 'CN', 'label' =>__('CN')]
        ];
    }
}

