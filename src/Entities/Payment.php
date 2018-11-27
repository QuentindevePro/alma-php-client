<?php
/*
 * Copyright (c) 2018 Alma
 * http://www.getalma.eu/
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 *
 */

namespace Alma\Entities;

class Payment extends Base {
	const STATE_IN_PROGRESS = 'in_progress';

	public $url;
	public $state;
	public $purchase_amount;
	public $payment_plan;
	public $return_url;
	public $custom_data;

	public function __construct( $attributes ) {
		// Manually process `payment_plan` to create Instalment instances
 		if (array_key_exists('payment_plan', $attributes)) {
			$this->payment_plan = array();

			foreach ($attributes['payment_plan'] as $instalment) {
				$this->payment_plan[] = new Instalment($instalment);
			}

			unset($attributes['payment_plan']);
		}

		parent::__construct( $attributes );
	}
}