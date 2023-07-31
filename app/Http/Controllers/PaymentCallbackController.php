<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    public function handlePaymentCallback(Request $request)
    {
        Log::info('Received request:', $request->all());

        $request->validate([
            'order_id' => 'required',
            'status_code' => 'required',
            'ussd_code' => 'required',
        ]);

        $order_id = $request->order_id;
        $status_code = $request->status_code;
        $ussd_code = $request->ussd_code;

        if ($status_code == 1) {
            $payment_transaction = DB::table('payment_transactions')->where('order_id', $order_id)->first();

            if ($payment_transaction) {
                $transaction_id = $payment_transaction->id;
                $transaction_type = $payment_transaction->transaction_type;
                $amount = $payment_transaction->amount;
                $mobile = $payment_transaction->resource_id;

                if ($transaction_type == "payment") {
                    DB::table('payments')->insert([
                        'order_id' => $order_id,
                        'phone_number' => $mobile,
                        'amount' => $amount,
                        'date_updated' => now()
                    ]);

                    $merchant = DB::table('merchants')->where('ussd_code', $ussd_code)->first();
                    $merchant_phone_number = $merchant->phone_number;

                    $successMessage = "Payment of " . $amount . " GHS made by " . $mobile . " was successful. \n" .  "Powered by Emergent  ";
                    $this->sendSMS($merchant_phone_number, $successMessage);

                    // Return the success message
                    return response('Message received', 200);
                }
            }
        }

        return response('Failed', 400);
    }

    private function sendSMS($destination, $message) {
        $url = "https://deywuro.com/api/sms";
        $postData = array(
            'username' => 'emergentpayment',
            'password' => 'Mission@1',
            'source' => 'Emergent',
            'destination' => $destination,
            'message' => $message
        );

        $response = Http::post($url, $postData);

        if ($response->failed()) {
            die('Error occurred!');
        }
    }
}
