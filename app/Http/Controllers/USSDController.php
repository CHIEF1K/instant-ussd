<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class USSDController extends Controller
{
    public function handleUSSDRequest(Request $request)
    {
        // Get the USSD request data
        $mobile = $request->Mobile;
        $session_id = $request->SessoionId;
        $service_code = $request->ServiceCode;
        $type = $request->Type;
        $message = $request->Message;
        $operator = $request->Operator;

        // Initialize the response array
        $response = array();

        // Check if the USSD code is registered, display the merchant name in the menu
        $merchant = DB::table('merchants')->where('ussd_code', $service_code)->first();
        $app_id = $merchant ? $merchant->app_id : null;
        $app_key = $merchant ? $merchant->app_key : null;

        // Check the USSD request type
        if ($type === "initiation") {
            // USSD initiation request
            if ($merchant) {
                // The merchant was found for the given USSD code
                $merchants_name = $merchant->merchants_name;
                $app_id = $merchant->app_id;
                $app_key = $merchant->app_key;

                // Build the merchant-specific menu
                $response = array(
                    "Type" => "Response",
                    "Message" => "Welcome to " . $merchants_name . ".\nPlease enter the amount to pay:"
                );
            } else {
                // USSD code is not registered
                $response = array(
                    "Type" => "Release",
                    "Message" => "Sorry, This Merchant is not registered."
                );
            }
        } elseif ($type === "response") {
            // USSD response request

            // Process the entered amount
            $amount = trim($message);

            if (!is_numeric($amount)) {
                $response_message = "Invalid amount entered. Please try again.";
            } elseif ($amount <= 0) {
                $response_message = "Amount must be greater than zero. Please try again.";
            } else {
                // Check if the merchant is not null before proceeding
                if ($merchant) {
                    $order_id = Str::random(12);

                    // JSON data for payment
                    $json_data = array(
                        "app_id" => $app_id,
                        "app_key" => $app_key,
                        "FeeTypeCode" => "GENERALPAYMENT",
                        "mobile" => $mobile,
                        "currency" => "GHS",
                        "amount" => $amount,
                        "mobile_network" => strtoupper($operator),
                        "order_id" => $order_id,
                        "order_desc" => "Payment",
                        "ussd_code" => $service_code // Add the USSD code to the data
                    );

                    $post_data = json_encode($json_data, JSON_UNESCAPED_SLASHES);

                    $curl = curl_init();

                    curl_setopt_array($curl, array(
                        CURLOPT_URL => "https://api.interpayafrica.com/v3/interapi.svc/CreateMMPayment", // live Url
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

                        $response = [
                            "Type" => "Release",
                            "Message" => $response_message
                        ];
                        return response()->json($response);
                    } else {
                        // Process the response
                        $return = json_decode($response_message);

                        // Get the transaction record from the database
                        $paymentTransaction = DB::table('payment_transactions')->where('order_id', $order_id)->first();

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
                } else {
                    // USSD code is not registered
                    $response_message = "Sorry, This Merchant is not registered.";
                }
            }
        }

        // Send the USSD response
        return response()->json($response);
    }
}
