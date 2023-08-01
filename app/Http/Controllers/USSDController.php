<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class USSDController extends Controller
{
    public function handleUSSDRequest(Request $request)
    {
        $mobile = $request->Mobile;
        $session_id = $request->SessionId;
        $service_code = $request->ServiceCode;
        $type = $request->Type;
        $message = $request->Message;
        $operator = $request->Operator;

        $response = array();

        $merchant = DB::table('merchants')->where('ussd_code', $service_code)->first();

        if ($type === "initiation") {
            if ($merchant) {
                $merchants_name = $merchant->merchants_name;
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
                    $merchants_name = $merchant->merchants_name;
                    $order_id = Str::random(12);

                    $json_data = array(
                        "app_id" => $app_id,
                        "app_key" => $app_key,
                        "name" => $merchants_name,
                        "FeeTypeCode" => "GENERALPAYMENT",
                        "mobile" => $mobile,
                        "currency" => "GHS",
                        "amount" => $amount,
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

                        if ($paymentTransaction = DB::table('payment_transactions')->where('order_id', $order_id)->first()) {
                            $transaction_id = $paymentTransaction->id;


                            DB::table('payment_transactions')
                            ->where('id', $transaction_id)
                            ->update([
                                'status_code' => $return->status_code,
                                'amount' => $amount,
                                'status_message' => $return->status_message,
                                'merchantcode' => $return->merchantcode,
                                'transaction_no' => $return->transaction_no,
                                'resource_id' => $mobile,
                                'transaction_type' => 'payment',
                                'order_id' => $order_id,
                                'merchant_name'=> $merchants_name,
                                'client_timestamp' => DB::raw('CURRENT_TIMESTAMP'),
                            ]);

                        } else {
                            $sql = "INSERT INTO payment_transactions 
                                    (status_code, amount, status_message, merchantcode, transaction_no, resource_id, transaction_type, order_id, merchants_name, client_timestamp) 
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
                                $merchants_name,
                            ]);
                        }

                        $paymentTransaction = DB::table('mother_merchants.payment_transactions')->where('order_id', $order_id)->first();

                        if ($return->status_code == 1) {
                            $response_message = "You will receive a payment prompt to complete your payment";
                        } else {
                            $response_message = "E3. Please Try Again Later.";
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
        }

        return response()->json($response);
    }
}
