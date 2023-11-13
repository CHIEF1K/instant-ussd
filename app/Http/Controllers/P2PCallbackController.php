<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;


class P2PCallbackController extends Controller
{
    public function handleP2PCallback(Request $request)

    {


        $request->validate([
            'order_id' => 'required',
            'status_code' => 'required',
            'name' => 'required'
        ]);
        
        Log::info('P2P Callback Received:', $request->all());

        $order_id = $request->order_id;
        $status_code = $request->status_code;
        $merchant_id = $request->name; // Extract the merchant_name from the request

        if ($status_code == 1) {
            // Get the Transaction Record
            $payment_transaction = DB::table('payment_transactions')->where('order_id', $order_id)->first();

            if ($payment_transaction) {
                $transaction_id = $payment_transaction->id; 
                $transaction_type = $payment_transaction->transaction_type;
                $amount = $payment_transaction->amount;
                $mobile = $payment_transaction->resource_id;
                $merchant_id = $payment_transaction->merchants_name;
                $status_message = $payment_transaction->status_message;
                $status_code = $payment_transaction->status_code;
                $transaction_no = $payment_transaction->transaction_no;

                if ($transaction_type == "payment") {
                    // Handle successful payment
                    $this->handleSuccessfulPayment($transaction_id, $order_id, $mobile, $amount, $merchant_id, $status_code, $status_message, $transaction_no);
                    return response('Done');
                }
            }
        }

        return response('Failed', 400);
    }

    private function handleSuccessfulPayment($transaction_id, $order_id, $mobile, $amount, $merchant_id, $status_code, $status_message, $transaction_no)
    {
        // Insert payment record into payments table
        DB::table('payments')->insert([
            'order_id' => $order_id,
            'phone_number' => $mobile,
            'amount' => $amount,
            'date_updated' => now(),
            'status_message' => $status_message,
            'status_code' => $status_code,
            'transaction_no' => $transaction_no,
            'merchant_id' => $merchant_id,
            'id' => $transaction_id
        ]);

        // Get the merchant's details (phone number and name)
        $merchant = DB::table('merchants')->where('merchant_id', $merchant_id)->first();
        if ($merchant) {
            $merchant_phone_number = $merchant->phone_number;
            $merchant_name = $merchant->merchants_name; 
            $app_id = $merchant -> app_id;
            $app_key = $merchant -> app_key;




            // Send SMS to the merchant
            $successMessageToMerchant = "Payment of " . $amount . " GHS made by " . $mobile . " was successful. \n" . "Powered by Emergent  ";
            $this->sendSMS($merchant_phone_number, $successMessageToMerchant);

            // Send SMS to the user (payer) confirming the payment
           $successMessageToUser = "Hello! You have successfully paid GHS " . $amount . " to " . $merchant_name . ".\n\nPowered by Emergent Payments. Contact us on 0302263014.";
           $this->sendSMS($mobile, $successMessageToUser);


           
            // data for cash out 
            $transactionType = "local";
            $paymentMode = "MMT";
            $payeeName = "test Payee";
            $transCurrency = "GHS";
            $merchTransRefNo = "310320";
            $recipientMobile = $merchant_phone_number; // Number of the merchant
            $payeeMobile = $mobile; // Number of the user
            $recipientName = "";
            $transactionDate = now()->toDateString(); 
            $expiryDate = now()->addDay()->toDateString(); 

            $logMessage = "Transaction Type: $transactionType, Payment Mode: $paymentMode, Payee Name: $payeeName, Trans Currency: $transCurrency, Merch Trans Ref No: $merchTransRefNo, Recipient Mobile: $recipientMobile, Payee Mobile: $payeeMobile, Recipient Name: $recipientName, Transaction Date: $transactionDate, Expiry Date: $expiryDate";

            Log::info($logMessage);

            // Create the transaction data array
            $order_id = Str::random(12);

                
               $json_data = array(

                "app_id" => $app_id,
                "app_key" => $app_key,
                "transaction_type" => $transactionType,
                "payment_mode" => $paymentMode,
                "payee_name" => $payeeName,
                "trans_currency" => $transCurrency,
                "trans_amount" => $amount,
                "merch_trans_ref_no" => $merchTransRefNo,
                "payee_mobile" => $payeeMobile,
                "recipient_mobile" => $recipientMobile,
                "recipient_name" => $recipientName,
                "transaction_date" => "/Date($transactionDate)/",
                "expiry_date" => "/Date($expiryDate)/",
            );

            $post_data = json_encode($json_data, JSON_UNESCAPED_SLASHES);

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://testsrv.interpayafrica.com/v7/CashoutRESTV2.svc/CreateCashoutTrans",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json",
                ),
            ));

            $response_message = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {

            } else {
                $return = json_decode($response_message);
                $params = json_decode($response_message, true);


                if ($paymentTransaction = DB::table('payment_transactions')->where('order_id', $order_id)->first()) {
                    $transaction_id = $paymentTransaction->id;


                    DB::table('payment_transactions')
                    ->where('id', $transaction_id)
                    ->update([
                        'status_code' => $return->status_code,
                        'amount' => $amount,
                        'status_message' => $return->status_message,
                        'merchantcode' => $return->merchantcode,
                        'trans_ref_no' => $return->transaction_no,
                        'payee_mobile' => $merchant_phone_number,
                        'recienpt_mobile'=> $recipientMobile,
                        'transaction_type' => 'payment',
                        'merch_trans_ref_no' => $order_id,
                        'merchant_name'=> $merchant_id,
                        'transaction_date' => "/Date($transactionDate)/",
                        'expiry_date' => "/Date($expiryDate)/",
                    ]);

                } else {
                    $sql = "INSERT INTO payment_transactions 
                    (status_code, amount, status_message, trans_ref_no, payee_mobile, recipient_mobile, transaction_type, merch_trans_ref_no, merchant_name, transaction_date, expiry_date, ) 
                    VALUES 
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, )";

                    // Use the SQL INSERT statement with the provided values
                    DB::insert($sql, [
                        $return->status_code,
                        $amount,
                        $return->status_message,
                        $return->transaction_no,
                        $merchant_phone_number,
                        $recipientMobile,
                        'payment',
                        $order_id,
                        $merchant_id,
                        "/Date($transactionDate)/",
                        "/Date($expiryDate)/",
                    ]);


                $paymentTransaction = DB::table('mother_merchants.payment_transactions')->where('order_id', $order_id)->first();

            }

         }

        }
    }

    private function sendSMS($destination, $message)
    {
        // API endpoint
        $url = "https://deywuro.com/api/sms";

        // Data for the API
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
