<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Epaytech\Epay\Model\Source;

use Magento\Framework\Option\ArrayInterface;

class PayMode implements ArrayInterface {

    /**
     * @return array
     */
	public function toOptionArray() {
        return [
            ['value' => 'sandbox', 'label' =>__('Sandbox')],
            ['value' => 'prod', 'label' =>__('Prod')]
        ];
    }
}

