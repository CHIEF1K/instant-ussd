<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaymentCallbackController extends Controller
{
    public function handlePaymentCallback(Request $request)
    {
        $request->validate([
            'order_id' => 'required',
            'status_code' => 'required',
             'name' => 'required'
        ]);

        $order_id = $request->order_id;
        $status_code = $request->status_code;
        $merchant_id = $request->name; // Extract the merchant_name from the request

        if ($status_code == 1) {
            // Get the Transaction Record
            $payment_transaction = DB::table('payment_transactions')->where('order_id', $order_id)->first();

            if ($payment_transaction) {
                $transaction_id = $payment_transaction->id; // Retrieve the transaction_id
                $transaction_type = $payment_transaction->transaction_type;
                $amount = $payment_transaction->amount;
                $mobile = $payment_transaction->resource_id;
                $merchant_id = $payment_transaction -> merchants_name;
                $status_message = $payment_transaction -> status_message;
                $status_code = $payment_transaction -> status_code;
                $transaction_no = $payment_transaction -> transaction_no;



                if ($transaction_type == "payment") {
                    // Handle successful payment
                    $this->handleSuccessfulPayment($transaction_id, $order_id, $mobile, $amount, $merchant_id,$status_code,$status_message,$transaction_no);

                    return response('Done');
                }
            }
        }

        return response('Failed', 400);
    }

    private function handleSuccessfulPayment($transaction_id,$order_id, $mobile, $amount, $merchant_id,$status_code,$status_message,$transaction_no)
    {
        // Insert payment record
        DB::table('payments')->insert([
            'order_id' => $order_id,
            'phone_number' => $mobile,
            'amount' => $amount,
            'date_updated' => now(),
            'status_message' => $status_message,
            'status_code' => $status_code,
            'transaction_no' => $transaction_no,
            'merchant_id'=> $merchant_id,
            'id' => $transaction_id
        ]);

        // Get the merchant's phone number
        $merchant = DB::table('merchants')->where('merchant_id', $merchant_id)->first();
        if ($merchant) {
            $merchant_phone_number = $merchant->phone_number;

            // Send SMS after successful transaction
            $successMessage = "Payment of " . $amount . " GHS made by " . $mobile . " was successful. \n" . "Powered by Emergent  ";

            // Send the SMS to the merchant's phone number
            $this->sendSMS($merchant_phone_number, $successMessage);
        }
    }

    private function sendSMS($destination, $message)
    {
        // API endpoint
        $url = "https://deywuro.com/api/sms";

        // The data to send to the API
        $postData = array(
            'username' => 'emergentpayment',
            'password' => 'Mission@1',
            'source' => 'Emergent',
            'destination' => $destination,
            'message' => $message
        );

        // Send the request
        $response = Http::post($url, $postData);

        // Check for errors
        if ($response->failed()) {
            die('Error occurred!');
        }
    }
}
