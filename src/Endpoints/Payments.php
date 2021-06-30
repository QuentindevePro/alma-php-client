<?php
/**
 * Copyright (c) 2018 Alma / Nabla SAS
 *
 * THE MIT LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
 * documentation files (the "Software"), to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and
 * to permit persons to whom the Software is furnished to do so, subject to the following conditions:
 * The above copyright notice and this permission notice shall be included in all copies or substantial portions of the
 * Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
 * WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 *
 * @author    Alma / Nabla SAS <contact@getalma.eu>
 * @copyright Copyright (c) 2018 Alma / Nabla SAS
 * @license   https://opensource.org/licenses/MIT The MIT License
 *
 */

namespace Alma\API\Endpoints;

use Alma\API\Endpoints\Results\Eligibility;
use Alma\API\Entities\Order;
use Alma\API\Entities\Payment;
use Alma\API\RequestError;
use Alma\API\Response;

class Payments extends Base
{
    const PAYMENTS_PATH = '/v1/payments';
    const ELIGIBILITY_PATH = '/v2/payments/eligibility';

    /**
     * @param array $data           Payment data to check the eligibility for – same data format as payment creation,
     *                              except that only payment.purchase_amount is mandatory and payment.installments_count
     *                              can be an array of integers, to test for multiple eligible plans at once.
     * @param bool $raiseOnError    Whether to raise a RequestError on 4xx and 5xx errors, as it should.
     *                              Defaults to false to preserve original behaviour. Will default to true in future
     *                              versions (next major update).
     *
     * @return Eligibility|Eligibility[]
     * @throws RequestError
     */
    public function eligibility(array $data, $raiseOnError = false)
    {
        // Old eligibiity endpoint (we keep both for now)
        $oldEligibility = array_key_exists('payment', $data);
        if ($oldEligibility) {
            $res = $this->request(self::PAYMENTS_PATH . '/eligibility')->setRequestBody($data)->post();
        } else {
            $res = $this->request(self::ELIGIBILITY_PATH)->setRequestBody($data)->post();
        }

        if ($raiseOnError && $res->isError()) {
            throw new RequestError($res->errorMessage, null, $res);
        }

        $serverError = $res->responseCode >= 500;

        if (!$serverError && is_assoc_array($res->json)) {
            $result = new Eligibility($res->json, $res->responseCode);

            if (!$result->isEligible()) {
                $this->logger->info(
                    "Eligibility check failed for following reasons: " .
                    var_export($result->reasons, true)
                );
            }
        } elseif (!$serverError && is_array($res->json)) {
            $result = [];

            foreach ($res->json as $eligibilityData) {
                $eligibility = new Eligibility($eligibilityData, $res->responseCode);
                if ($oldEligibility) {
                    $result[$eligibility->getInstallmentsCount()] = $eligibility;
                } else {
                    $key = 'general_'
                    . $eligibility->getInstallmentsCount()
                    . '_'
                    . $eligibility->getDeferredDays()
                    . '_'
                    . $eligibility->getDeferredMonths();
                    $result[$key] = $eligibility;
                }

                if (!$eligibility->isEligible()) {
                    $this->logger->info(
                        "Eligibility check failed for following reasons: " .
                        var_export($eligibility->reasons, true)
                    );
                }
            }
        } else {
            $this->logger->info(
                "Unexpected value from eligibility: " . var_export($res->json, true)
            );

            $result = new Eligibility(array("eligible" => false), $res->responseCode);
        }

        return $result;
    }

    /**
     * @param array $data
     *
     * @return Payment
     * @throws RequestError
     */
    public function create($data)
    {
        $res = $this->request(self::PAYMENTS_PATH)->setRequestBody($data)->post();

        if ($res->isError()) {
            throw new RequestError($res->errorMessage, null, $res);
        }

        return new Payment($res->json);
    }

    /**
     * @param array $data
     *
     * @return Payment
     * @throws RequestError
     *
     * @deprecated Use Payments::create() instead
     */
    public function createPayment($data)
    {
        return $this->create($data);
    }


    /**
     * @param string $id The external ID for the payment to fetch
     *
     * @return Payment
     * @throws RequestError
     */
    public function fetch($id)
    {
        $res = $this->request(self::PAYMENTS_PATH . "/$id")->get();

        if ($res->isError()) {
            throw new RequestError($res->errorMessage, null, $res);
        }

        return new Payment($res->json);
    }

    /**
     * @param string $id      The ID of the payment to flag as potential fraud
     * @param string $reason  An optional message indicating why this payment is being flagged
     *
     * @return bool
     * @throws RequestError
     */
    public function flagAsPotentialFraud($id, $reason=null)
    {
        $req = $this->request(self::PAYMENTS_PATH . "/$id/potential-fraud");

        if (!empty($reason)) {
            $req->setRequestBody(array("reason" => $reason));
        }

        $res = $req->post();
        if ($res->isError()) {
            throw new RequestError($res->errorMessage, $req, $res);
        }

        return true;
    }

    /**
     * @param string $id ID of the payment to be refunded
     * @param bool $totalRefund Should the payment be completely refunded? In this case, $amount is not required as the
     *                          API will automatically compute the amount to refund, including possible customer fees
     * @param int $amount Amount that should be refunded, for a partial refund. Must be expressed as a cents
     *                          integer
     * @return Payment
     * @throws RequestError
     */
    public function refund($id, $totalRefund = true, $amount = null)
    {
        $req = $this->request(self::PAYMENTS_PATH . "/$id/refund");

        if (!$totalRefund) {
            $req->setRequestBody(array("amount" => $amount));
        }

        $res = $req->post();
        if ($res->isError()) {
            throw new RequestError($res->errorMessage, $req, $res);
        }

        return new Payment($res->json);
    }

    /**
     * Adds an Order to the given Payment, possibly overwriting existing orders
     *
     * @param string $id ID of the payment to which the order must be added
     * @param array $orderData Data of the Order
     * @param bool $overwrite Should the order replace any other order set on the payment, or be appended to the payment's orders (default: false)
     *
     * @return Order
     *
     * @throws RequestError
     */
    public function addOrder($id, $orderData, $overwrite = false)
    {
        $req = $this->request(self::PAYMENTS_PATH . "/$id/orders")->setRequestBody(array("order" => $orderData));

        $res = null;
        if ($overwrite) {
            $res = $req->post();
        } else {
            $res = $req->put();
        }

        return new Order(end($res->json));
    }

    /**
     * Sends a SMS to the customer, containing a link to the payment's page
     * /!\ Your account must be authorized by Alma to use that endpoint; it will otherwise fail with a 403 error
     *
     * @param string $id ID of the payment to send a SMS for
     *
     * @return bool
     *
     * @throws RequestError
     */
    public function sendSms($id)
    {
        $res = $this->request(self::PAYMENTS_PATH . "/$id/send-sms")->post();
        if ($res->isError()) {
            throw new RequestError($res->errorMessage, null, $res);
        }

        return true;
    }

}
