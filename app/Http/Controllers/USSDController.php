<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class USSDController extends Controller
{
    public function handleUSSDRequest(Request $request)
    {
        DB::connection()->enableQueryLog();

        $mobile = $request->Mobile;
        $session_id = $request->SessoionId;
        $service_code = $request->ServiceCode;
        $type = $request->Type;
        $message = $request->Message;
        $operator = $request->Operator;

        $response = array();

        $merchant = DB::table('merchants')->where('ussd_code', $service_code)->first();
        $app_id = $merchant->app_id;
        $app_key = $merchant->app_key;

        if ($type === "initiation") {
            if ($merchant) {
                $merchants_name = $merchant->merchants_name;
                $app_id = $merchant->app_id;
                $app_key = $merchant->app_key;

                $response = array(
                    "Type" => "Response",
                    "Message" => "Welcome to " . $merchants_name . ".\nPlease enter the amount to pay:"
                );
            } else {
                $response = array(
                    "Type" => "Release",
                    "Message" => "Sorry, This Merchant is not registered."
                );
            }
        } elseif ($type === "response") {
            $amount = trim($message);

            if (!is_numeric($amount)) {
                $response_message = "Invalid amount entered. Please try again.";
            } elseif ($amount <= 0) {
                $response_message = "Amount must be greater than zero. Please try again.";
            } else {
                if ($merchant) {
                    $app_id = $merchant->app_id;
                    $app_key = $merchant->app_key;
                    $order_id = Str::random(12);

                    $json_data = array(
                        "app_id" => $app_id,
                        "app_key" => $app_key,
                         "name" => "Instant-Ussd",
                        "FeeTypeCode" => "GENERALPAYMENT",
                        "mobile" => $mobile,
                        "currency" => "GHS",
                        "amount" => $amount,
                        "mobile_network" => strtoupper($operator),
                        "order_id" => $order_id,
                        "order_desc" => "Payment",
                        "ussd_code" => $service_code
                    );
                    
                    \Log::info('Data sent in the cURL request:', $json_data);

                    $post_data = json_encode($json_data, JSON_UNESCAPED_SLASHES);

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => "https://api.interpayafrica.com/v3/interapi.svc/CreateMMPayment",
                        //CURLOPT_URL => "https://testsrv.interpayafrica.com/v7/interapi.svc/CreateMMPayment",

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

                        \Log::info('cURL response:', [$response_message]);
                        \Log::info('cURL error:', [$err]);

                         curl_close($curl);

                    if ($err) {
                            \Log::error("cURL error: {$err}");
                        $response_message = "E1. Please Try Again Later.";

                        $response = [
                            "Type" => "Release",
                            "Message" => $response_message
                        ];
                        return response()->json($response);
                    } else {
                        $return = json_decode($response_message);
                        $params = json_decode($response_message, true);

                        try {
                            \Log::info("Order ID: {$order_id}");
                             $paymentTransaction = DB::table('payment_transactions')->where('order_id', $order_id)->first();
                             $transaction_id = $payment_transaction['id'];



                            if ($paymentTransaction) {
                                $sql = "UPDATE payment_transactions 
                                        SET status_code=?, 
                                        amount=?, 
                                        status_message=?, 
                                        merchantcode=?, 
                                        transaction_no=?, 
                                        resource_id=?, 
                                        transaction_type='payment', 
                                        order_id=?, 
                                        client_timestamp=CURRENT_TIMESTAMP 
                                        WHERE id=?";

                                DB::update($sql, [
                                    $return->status_code,
                                    $amount,
                                    $return->status_message,
                                    $return->merchantcode,
                                    $return->transaction_no,
                                    $mobile,
                                    'payment',
                                    $order_id,
                                    $paymentTransaction->id
                                ]);
                            } else {
                                $sql = "INSERT INTO payment_transactions 
                                        (status_code, amount, status_message, merchantcode, transaction_no, resource_id, transaction_type, order_id, ussd_code, client_timestamp) 
                                        VALUES 
                                        (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

                                DB::insert($sql, [
                                    $return->status_code,
                                    $amount,
                                    $return->status_message,
                                    $return->merchantcode,
                                    $return->transaction_no,
                                    $mobile,
                                    'payment',
                                    $order_id,
                                    $service_code, // Add the USSD code to the insert statement
                                ]);

                                $paymentTransaction = DB::table('payment_transactions')->where('order_id', $order_id)->first();
                                // LOG THE QUERY HERE
                                $log = DB::getQueryLog();
                                \Log::info('DB query log:', $log);
                            }
                        } catch(\Exception $e) {
                             $response_message = $e->getMessage();
                             $response = [
                                  "Type" => "Release",
                                  "Message" => $response_message
                              ];
                                    return response()->json($response);
                        }

                        if ($return->status_code == 1) {
                            $response_message = "You will receive a payment prompt to complete your payment";

                            $response = [
                                "Type" => "Release",
                                "Message" => $response_message
                            ];
                            return response()->json($response);
                        } else {
                            $response_message = "E3. Please Try Again Later.";
                            $response = [
                                "Type" => "Release",
                                "Message" => $response_message
                            ];
                            return response()->json($response);
                        }
                    }
                }
            }
        }
        return response()->json($response);
    }
}
