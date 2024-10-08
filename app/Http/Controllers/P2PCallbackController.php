<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
<<<<<<< HEAD
use Illuminate\Support\Facades\Log;



class Peer2PeerController extends Controller
{
    public function handleP2PRequest(Request $request, $merchant_id) 
    {
         $mobile = $request->Mobile;
        $session_id = $request->SessionId;
        $service_code = $request->ServiceCode;
        $type = $request->Type;
        $message = $request->Message;
        $operator = $request->Operator;
=======
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
>>>>>>> 1daf984 (ref update)

        $response = array();

        $number = $request->input('Mobile');

        $apiResponseData = $this->sendPostRequest($mobile);

        $firstName = $apiResponseData['response']['firstname'] ?? ''; // Default to an empty string if not present

        Log::info("Extracted firstName: $firstName");

        $user = DB::table('mother_merchants.users')->where('phone_number', $number)->first();

        if ($user) {
            // Update the existing record for the user
            try {
                DB::table('mother_merchants.users')
                    ->where('phone_number', $number)
                    ->update(['previous_step' => 'welcome', 'date_updated' => now()]);
            } catch (\Illuminate\Database\QueryException $e) {
                $errorMessage = "Failed to execute SQL query: " . $e->getMessage();
                error_log($errorMessage);
        
                return response()->json([
                    "Type" => "Release",
                    "Message" => $errorMessage
                ]);
            }
        } else {
            // Insert a new record for the user
            try {
                DB::table('mother_merchants.users')->insert([
                    'user_name' => $number,
                    'phone_number' => $number,
                    'previous_step' => 'welcome',
                    'date_updated' => now(),
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                $errorMessage = "Failed to execute SQL query: " . $e->getMessage();
                error_log($errorMessage);
        
                return response()->json([
                    "Type" => "Release",
                    "Message" => $errorMessage
                ]);
            }
        
            $user= DB::table('users')
                ->where('phone_number', $number)
                ->first();
        }


        //ussd session starting
        $merchant = DB::table('merchants')
            ->where('ussd_code', $service_code)
            ->where('merchant_id', $merchant_id)
            ->first();

            

            if ($type === "initiation") {

                if ($merchant) {
                    $merchants_name = $merchant->merchants_name;
                    $merchant_id = $merchant->merchant_id;
                    $response = array(
                        "Type" => "Response",
                        "Message" => "Welcome to " . $merchants_name . ".\nPlease enter the amount to pay:"
                    );
            
                    $number = $request->input('Mobile');
            
                    // Update the user's previous step to 'welcome_enter_amount'
                    DB::table('mother_merchants.users')
                        ->where('phone_number', $number)
                        ->update(['previous_step' => 'welcome_enter_amount', 'date_updated' => now()]);
                } else {
                    $response = array(
                        "Type" => "Release",
                        "Message" => "Sorry, This Merchant is not registered."
                    );
                }
            } else if ($user->previous_step == "welcome_enter_amount") {
                // Check if the message is a valid amount
                $amount = trim($message);
                if (!is_numeric($amount)) {
                    // Handle the case where the user entered an invalid amount
                    $response = array(
                        "Type" => "Response",
                        "Message" => "Invalid amount. Please enter a valid amount to pay:"
                    );

                    DB::table('mother_merchants.users')
                    ->where('phone_number', $number)
                    ->update(['previous_step' => 'welcome_enter_amount', 'date_updated' => now()]);
        


                } else {
                    $number = $request->input('Mobile');
                    // Check if a payment amount already exists for the user
                    $existingAmount = DB::table('mother_merchants.users')
                        ->where('phone_number', $number)
                        ->value('payment_amount');
                    
                        $number = $request->input('Mobile');
                        
                        if ($existingAmount !== null) {
                            // If the user has an existing amount, update it
                            DB::table('mother_merchants.users')
                                ->where('phone_number', $number)
                                ->update(['payment_amount' => $amount, 'date_updated' => now()]);
                        } else {
                            // If the user does not have an existing amount, insert it into the same column
                            DB::table('mother_merchants.users')
                                ->where('phone_number', $number)
                                ->update(['payment_amount' => $amount, 'date_updated' => now()]);
                        }
                        
                    
            
                    // Update the user's previous step to 'welcome_enter_amount'
                    DB::table('mother_merchants.users')
                        ->where('phone_number', $number)
                        ->update(['previous_step' => 'welcome_enter_amount', 'date_updated' => now()]);
            
                    // Ask the user to enter the reference
                    $response = array(
                        "Type" => "Response",
                        "Message" => "You have entered the amount: " . $amount . "\nPlease enter the reference:"
                    );
            
                    // Update the user's previous step to 'enter_reference'
                    DB::table('mother_merchants.users')
                        ->where('phone_number', $number)
                        ->update(['previous_step' => 'enter_reference', 'date_updated' => now()]);
                }

            } 
            
            
            if ($user->previous_step == "enter_reference") {
                $reference = trim($message);
            
                $number = $request->input('Mobile');
                $existingReference = DB::table('mother_merchants.users')
                    ->where('phone_number', $number)
                    ->first();
            
                if ($existingReference) {
                    // If the user has an existing reference, update it
                    DB::table('mother_merchants.users')
                        ->where('phone_number', $number)
                        ->update(['previous_step' => 'enter_reference', 'date_updated' => now(), 'payment_reference' => $reference]);

                        
                } /*else {
                    // If the user does not have an existing reference, create a new entry
                    DB::table('mother_merchants.users')->insert([
                        'phone_number' => $number,
                        'payment_reference' => $reference
                    ]);
                }    */    
            
                // Place your payment prompt code here
                $number = $request->input('Mobile');
                $paymentData = DB::table('mother_merchants.users')
                    ->where('phone_number', $number)
                    ->select('payment_amount', 'payment_reference')
                    ->first();
                
                if ($paymentData) {
                    $paymentAmount = $paymentData->payment_amount;
                    $paymentReference = $paymentData->payment_reference;
                }
                
                if ($merchant) {
                    $app_id = $merchant->app_id;
                    $app_key = $merchant->app_key;
                    $merchant_id = $merchant->merchant_id;
                    $order_id = Str::random(12);
                
                  $json_data = array(

                        "app_id" => $app_id,
                        "app_key" => $app_key,
                        "email" => $paymentReference,
                        "name" => $firstName,
                        "FeeTypeCode" => "GENERALPAYMENT",
                        "mobile" => $mobile,
                        "currency" => "GHS",
                        "amount" => $paymentAmount,
                        "mobile_network" => strtoupper($operator),
                        "order_id" => $order_id,
                        "order_desc" => "Payment",

                    );
            
                    $post_data = json_encode($json_data, JSON_UNESCAPED_SLASHES);
            
                    $curl = curl_init();
            
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => "https://api.interpayafrica.com/v3/interapi.svc/CreateMMPayment",
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
                        $response_message = "E1. Please Try Again Later.";
                    } else {
                        $return = json_decode($response_message);
                        $params = json_decode($response_message, true);
            
                        if ($paymentTransaction = DB::table('payment_transactions')->where('order_id', $order_id)->first()) {
                            $transaction_id = $paymentTransaction->id;
            
                            DB::table('payment_transactions')
                                ->where('id', $transaction_id)
                                ->update(array(
                                    'status_code' => $return->status_code,
                                    'amount' => $paymentAmount,
                                    'status_message' => $return->status_message,
                                    'merchantcode' => $return->merchantcode,
                                    'transaction_no' => $return->transaction_no,
                                    'resource_id' => $mobile,
                                    'transaction_type' => 'payment',
                                    'order_id' => $order_id,
                                    'merchant_name' => $merchant_id,
                                    'client_timestamp' => DB::raw('CURRENT_TIMESTAMP'),
                                ));
                        } else {
                            $sql = "INSERT INTO payment_transactions 
                                    (status_code, amount, status_message, merchantcode, transaction_no, resource_id, transaction_type, order_id, merchants_name, client_timestamp) 
                                    VALUES 
                                    (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            
                            DB::insert($sql, array(
                                $return->status_code,
                                $paymentAmount,
                                $return->status_message,
                                $return->merchantcode,
                                $return->transaction_no,
                                $mobile,
                                'payment',
                                $order_id,
                                $merchant_id,
                            ));
                        }
            
                        if ($return->status_code == 1) {
                            $response_message = "You will receive a payment prompt to complete your payment";

                            $number = $request->input('Mobile');
            
                        // Update the user's previous step to 'welcome_enter_amount'
                        DB::table('mother_merchants.users')
                            ->where('phone_number', $number)
                            ->update(['previous_step' => 'welcome', 'date_updated' => now()]);


                        } else {
                            $response_message = "E3. Please Try Again Later.";

                            $number = $request->input('Mobile');
                            DB::table('mother_merchants.users')
                            ->where('phone_number', $number)
                            ->update(['previous_step' => 'welcome', 'date_updated' => now()]);

                        }
                    }
                    $response = [
                        "Type" => "Release",
                        "Message" => $response_message
                    ];
                } else {
                    $response_message = "Sorry, This Merchant is not registered.";
                }
            }
            return response()->json($response);

        }

        private function sendPostRequest($mobile)
        {
            // Build the URL for the POST request with the number appended
            $url = "https://emergentghanadev.com/api/name-validation/live/$mobile";
        
            try {
                // Make an HTTP POST request to the URL
                $response = Http::post($url);
        
                // Log the response
                Log::info("API Response: " . $response->body());
        
                // Decode the JSON response and store it in an array
                $apiResponse = $response->json();
        
                // You can access the values like this
                $statusCode = $apiResponse['status_code'];
                $statusMessage = $apiResponse['status_message'];
                $firstName = $apiResponse['firstname'];
                $surname = $apiResponse['surname'];
                $valid = $apiResponse['valid'];
        
                // Save the response in an array along with the phone number
                $responseData = ['phone_number' => $mobile, 'response' => $apiResponse];
        
                return $responseData;
            } catch (\Exception $e) {
                // Handle exceptions (e.g., connection issues)
                Log::error("HTTP Request Error: " . $e->getMessage());
                // You may want to return or log an error response here
                return ['phone_number' => $mobile, 'error' => $e->getMessage()];
            }
        }

    }
<<<<<<< HEAD
            
            
    
=======

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
            $payeeName = $merchant_name;
            $transCurrency = "GHS";
            $merchTransRefNo = $order_id;
            $recipientMobile = $merchant_phone_number; // Number of the merchant
            $payeeMobile = $mobile; // Number of the user
            $recipientName = "";
            $transactionDate = now()->format('/Date(Y-m-d\TH:i:s)/');
            $expiryDate = now()->addDay()->format('/Date(Y-m-d\TH:i:s)/');
            
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
                "merch_trans_ref_no" => $$order_id,
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


                if ($paymentTransaction = DB::table('payment_trans_cashout')->where('merch_trans_ref_no', $order_id)->first()) {
                    $transaction_id = $paymentTransaction->id;


                    DB::table('payment_trans_cashout')
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
                    $sql = "INSERT INTO payment_trans_cashout 
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
>>>>>>> 1daf984 (ref update)
