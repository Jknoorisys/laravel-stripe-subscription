<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\Bookings;
use App\Models\Charges;
use App\Models\Plan;
use App\Models\Subscriptions;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Stripe\Stripe;

class StripeController extends Controller
{
    //
    private $stripe;
    private $stripe_key;

    public function __construct() {
        // Multiligual
        $lang = (isset($_POST['language']) && !empty($_POST['language'])) ? $_POST['language'] : 'en';
        App::setlocale($lang);

         // Create a new instance of the Stripe client using the Stripe API key obtained from the 'STRIPE_SECRET' environment variable
         $this->stripe = new \Stripe\StripeClient(
            env('STRIPE_SECRET')
        );

        // Set the Stripe API key globally using the 'setApiKey' method from the 'Stripe' class
        $this->stripe_key = Stripe::setApiKey(env('STRIPE_SECRET'));
    }

    public function Subscribe(Request $request)
    {
        // Input validation
        $validator = Validator::make($request->all(), [
            'language' => [
                'required' ,
                Rule::in(['en','hi','ur','bn','ar','in','ms','tr','fa','fr','de','es']),
            ],
            'user_id'   => 'required||numeric',
            'plan_id' =>'required'
        ]);

        if($validator->fails()){
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.Validation Failed!'),
                'errors'    => $validator->errors()
            ],400);
        }

        try {

            $user_id = $request->user_id;
            $plan_id = $request->plan_id;

            // Fetch a plan object based on the given 'plan_id' from the 'Plan' model
            $plan = Plan::where('plan_id', '=', $plan_id)->first();
            
            // if plan does not exists, throw an erorr
            if (!$plan || empty($plan)) {
                return response()->json([
                    'status'    => 'failed',
                    'message'   => trans('msg.stripe.session.invalid'),
                ],400);
            }
          
            // Fetch a user based on the given 'user_id' from the 'Users' model
            $user = User::where('id', '=', $user_id)->first();

            // if user does not exists, throw an erorr
            if (!$user || empty($user)) {
                return response()->json([
                    'status'    => 'failed',
                    'message'   => trans('msg.stripe.session.not-found'),
                ],400);
            }
            
            // Set the success and cancel URLs for the checkout session
            $success_url = url('api/stripe/success');
            $cancel_url = url('api/stripe/fail');

            // Create a new Stripe checkout session object 
            $session = \Stripe\Checkout\Session::Create([
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'payment_method_types' => ['card'],
                'line_items' => [
                    ['price' => $plan_id, 'quantity' => 1]
                ],
                'mode' => 'subscription',
                'currency' => env('STRIPE_CURRENCY'),
            ]);

             // if session does not exists, throw an erorr
            if (!$session || empty($session)) {
                return response()->json([
                    'status'    => 'failed',
                    'message'   => trans('msg.stripe.session.failure'),
                ],400);
            }

            $booking_data = [
                'session_id' => $session->id,
                'user_id' => $user_id,
                'subscription_id' => $session->subscription ? $session->subscription : '',
                'customer_id' => $session->customer ? $session->customer : '',
                'amount_paid' => $session->amount_total/100,
                'currency' => $session->currency,
                'payment_status' => $session->payment_status,
                'session_status' => $session->status,
                'created_at' => Carbon::now()
            ];

            // Insert the booking data into the booking table
            $booking_id = Bookings::insertGetId($booking_data);

            if($booking_id){
                return response()->json([
                    'status'    => 'success',
                    'message'   => trans('msg.stripe.session.success'),
                    'data'      => $session
                ],200);
            }else{
                return response()->json([
                    'status'    => 'failed',
                    'message'   => trans('msg.stripe.session.failure'),
                ],400);
            }
        } catch (\Stripe\Exception\CardException $e) {
            // Since it's a decline, \Stripe\Exception\CardException will be caught
            $err  = 'Status:' . $e->getHttpStatus() . '<br>';
            $err  .= 'Type:' . $e->getError()->type . '<br>';
            $err  .= 'Code:' . $e->getError()->code . '<br>';
            // param is '' in this case
            $err  .= 'Param:' . $e->getError()->param . '<br>';
            $err  .= 'Message:' . $e->getError()->message . '<br>';
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $err
            ],500);
            // $this->session->set_flashdata('error',  $err);
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
            // $this->session->set_flashdata('error',  $err);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Network communication with Stripe failed
             return response()->json([
                 'status'    => 'failed',
                 'message'   => trans('msg.error'),
                 'error'     => $e->getMessage()
             ],500);
         } catch (\Throwable $e) {
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        }
    }

    public function paymentSuccess(Request $request){
        // input validation
        $validator = Validator::make($request->all(), [
            'language' => [
                'required' ,
                Rule::in(['en','hi','ur','bn','ar','in','ms','tr','fa','fr','de','es']),
            ],
            'session_id'   => 'required',
        ]);

        if($validator->fails()){
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.Validation Failed!'),
                'errors'    => $validator->errors()
            ],400);
        }

        try{
            $session_id = $request->session_id;

            // Fetch payment details based on the given 'session_id' from the 'Booking' table
            $payment_details = Bookings::where('session_id', '=', $session_id)->first();

            if (!empty($payment_details)) {
                $user_id = $payment_details->user_id ? $payment_details->user_id : '';
                $booking_id = $payment_details->id;

                // retrive session from stripe using session_id
                $session = \Stripe\Checkout\Session::Retrieve(
                    $payment_details->session_id,
                    []
                );

                if($session->payment_status == "paid" && $session->status == "complete"){
                    // retrive subscription details from stripe using subscription_id   
                    $subscription = \Stripe\Subscription::Retrieve($session->subscription);

                    // assign subscription item_1 object to variable $item
                    $item = $subscription->items->data[0];

                    $update_booking  =  [
                        'subscription_id' => $session->subscription,
                        'customer_id' => $session->customer,
                        'amount_paid' => $session->amount_total/100,
                        'payment_status' => $session->payment_status,
                        'session_status' => $session->status,
                        'updated_at'     => Carbon::now(),
                    ];

                    // update data in booking table
                    $update = Bookings::where('session_id', '=', $payment_details->session_id)->update($update_booking);

                    $subscription_data = [
                        'booking_id' => $booking_id,
                        'user_id' => $user_id,
                        'customer_id' => $session->customer,
                        'subscription_id' => $session->subscription,
                        'plan_id' => $item->plan->id,
                        'amount_paid' => $session->amount_total/100,
                        'currency' => $session->currency,
                        'plan_interval' => $item->plan->interval,
                        'plan_period_start' => $subscription ? date("Y-m-d H:i:s", $subscription->current_period_start) : '',
                        'plan_period_end' => $subscription ? date("Y-m-d H:i:s", $subscription->current_period_end) : '',
                        'payment_status' => $session->payment_status,
                        'subscription_status' => $subscription->status,
                        'created_at' => Carbon::now()
                    ];

                    // insert subscription details in subscriptions table
                    $insert = Subscriptions::insert($subscription_data);
                    return response()->json([
                        'status'    => 'success',
                        'message'   => trans('msg.stripe.success'),
                    ],200);
                }elseif ($session->payment_status == "unpaid" && $session->status == "open") {
                    return response()->json([
                        'status'    => 'failed',
                        'message'   => trans('msg.stripe.failure'),
                        'stripe'  => [
                            'session_id'  => $session['id'],
                            'url'         => $session['url'],
                        ],
                    ],400);
                } else {
                    return response()->json([
                        'status'    => 'failed',
                        'message'   => trans('msg.stripe.failure'),
                    ],400);
                }
            }else{
                return response()->json([
                    'status'    => 'failed',
                    'message'   => trans('msg.stripe.invalid'),
                ],400);
            }
        } catch (\Stripe\Exception\CardException $e) {
            // Since it's a decline, \Stripe\Exception\CardException will be caught
            $err  = 'Status:' . $e->getHttpStatus() . '<br>';
            $err  .= 'Type:' . $e->getError()->type . '<br>';
            $err  .= 'Code:' . $e->getError()->code . '<br>';
            // param is '' in this case
            $err  .= 'Param:' . $e->getError()->param . '<br>';
            $err  .= 'Message:' . $e->getError()->message . '<br>';
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $err
            ],500);
            // $this->session->set_flashdata('error',  $err);
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
            // $this->session->set_flashdata('error',  $err);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
           // Network communication with Stripe failed
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        } catch (\Throwable $e) {
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        }
    }

    public function paymentFail(Request $request){
        // input validation
        $validator = Validator::make($request->all(), [
            'language' => [
                'required' ,
                Rule::in(['en','hi','ur','bn','ar','in','ms','tr','fa','fr','de','es']),
            ],
            'session_id'   => 'required',
        ]);

        if($validator->fails()){
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.Validation Failed!'),
                'errors'    => $validator->errors()
            ],400);
        }

        try{

            $session_id = $request->session_id;

            // Fetch payment details based on the given 'session_id' from the 'Booking' table
            $payment_details = Bookings::where('session_id', '=', $session_id)->first();
            if (!empty($payment_details)) {
                $user_id = $payment_details->user_id ? $payment_details->user_id : '';
                $booking_id = $payment_details->id;

                // retrive session from stripe using session_id
                $session = \Stripe\Checkout\Session::Retrieve(
                    $payment_details->session_id,
                    []
                );

                if($session->status == "open"){
                    // if session status is open, expire that session using session_id
                    $expire = $this->stripe->checkout->sessions->expire(
                        $payment_details->session_id,
                        []
                    );

                    $update_booking  =  [
                        'payment_status' => $expire->payment_status,
                        'session_status' => $expire->status,
                        'updated_at'     => Carbon::now(),
                    ];

                    // update status in booking table
                    $update = Bookings::where('session_id', '=', $payment_details->session_id)->update($update_booking);

                    $subscription_data = [
                        'booking_id' => $booking_id,
                        'user_id' => $user_id,
                        'currency' => $session->currency,
                        'payment_status' => $session->payment_status,
                        'subscription_status' => 'inactive',
                        'created_at' => Carbon::now()
                    ];

                    // insert subscription details in subscriptions table
                    $insert = Subscriptions::insert($subscription_data);

                    if($update){
                        return response()->json([
                            'status'    => 'failed',
                            'message'   => trans('msg.stripe.failure'),
                        ],400);
                    }
                } else if ($session->status == "complete") {
                    return response()->json([
                        'status'    => 'failed',
                        'message'   => trans('msg.stripe.paid'),
                    ],400);
                } else if ($session->status == "expired") {
                    return response()->json([
                        'status'    => 'failed',
                        'message'   => trans('msg.stripe.expaired'),
                    ],400);
                } else {
                    return response()->json([
                        'status'    => 'failed',
                        'message'   => trans('msg.stripe.failure'),
                    ],400);
                }
            } else {
                return response()->json([
                    'status'    => 'failed',
                    'message'   => trans('msg.stripe.invalid'),
                ],400);
            }
        } catch (\Stripe\Exception\CardException $e) {
            // Since it's a decline, \Stripe\Exception\CardException will be caught
            $err  = 'Status:' . $e->getHttpStatus() . '<br>';
            $err  .= 'Type:' . $e->getError()->type . '<br>';
            $err  .= 'Code:' . $e->getError()->code . '<br>';
            // param is '' in this case
            $err  .= 'Param:' . $e->getError()->param . '<br>';
            $err  .= 'Message:' . $e->getError()->message . '<br>';
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $err
            ],500);
            // $this->session->set_flashdata('error',  $err);
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
            // $this->session->set_flashdata('error',  $err);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently)
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        } catch (\Stripe\Exception\ApiConnectionException $e) {
           // Network communication with Stripe failed
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        } catch (\Throwable $e) {
            return response()->json([
                'status'    => 'failed',
                'message'   => trans('msg.error'),
                'error'     => $e->getMessage()
            ],500);
        }
    }

    public function webhookHandler(Request $request)
    {
        // Retrieve the Stripe webhook secret key from environment variables
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');

        // Get the request payload from the incoming webhook request
        $payload = $request->getContent();

        // Get the Stripe signature header from the incoming webhook request
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];

        // Initialize the event variable to null
        $event = null;

        // Try to construct the event object from the received payload and signature
        // If successful, the event object will contain the Stripe event data
        // If not, catch the relevant exceptions and return a 400 status code
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            http_response_code(400);
            exit();
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            http_response_code(400);
            exit();
        }

        // Handle the event
        switch ($event->type) {
            case 'charge.failed':
                $charge = $event->data->object;
                $charge_id = $charge->id;
                $object = $charge->object;
                $customer_id = $charge->customer;
                $balance_transaction = $charge->balance_transaction;
                $amount_captured     = $charge->amount_captured;
                $name     = $charge->billing_details->name;
                $currency = $charge->currency;
                $charge_time     = $charge->created;
                $sub_convert_date = date('Y-m-d H:i:s', $charge_time);
                $description     = $charge->description;
                $invoice = $charge->invoice;
                $paid_status = $charge->paid;
                $ayment_intent = $charge->payment_intent;
                $payment_method = $charge->payment_method;
                $card_brand = $charge->payment_method_details->card->brand;
                $country = $charge->payment_method_details->card->country;
                $exp_month = $charge->payment_method_details->card->exp_month;
                $exp_year = $charge->payment_method_details->card->exp_year;
                $funding = $charge->payment_method_details->card->funding;
                $last4 = $charge->payment_method_details->card->last4;
                $network = $charge->payment_method_details->card->network;
                $card_type = $charge->payment_method_details->type;
                $paid_status = $charge->paid;
                $status = $charge->status;
                $seller_message = $charge->outcome['seller_message'];
                $charge_data = [
                    'charge_id' => $charge_id,
                    'object' => $object,
                    'charge_customer_id' => $customer_id,
                    'balance_transaction' => $balance_transaction,
                    'plan_amount' => $amount_captured/100,
                    'payer_email' => $name,
                    'plan_amount_currency' => $currency,
                    'charge_create' => $sub_convert_date,
                    'charge_currency' => $currency,
                    'charge_description' => $description,
                    'charge_invoice' => $invoice,
                    'seller_message' => $seller_message,
                    'payment_intent' => $ayment_intent,
                    'payment_method' => $payment_method,
                    'paid_status' => $paid_status,
                    'charge_country' => $country,
                    'exp_month' => $exp_month,
                    'exp_year' => $exp_year,
                    'funding' => $funding,
                    'last4' => $last4,
                    'network' => $network,
                    'type'=> $card_type,
                    'status'=> $status,
                    'updated_at' => date('Y-m-d h:i:s')
                ];

                $query = Charges::insert($charge_data);


                if($status == 'failed')
                {
                    $inactive = ['subscription_status' => 'inactive'];
                    Subscriptions::where('customer_id', $customer_id)->update($inactive);
                }
                break;
            case 'charge.succeeded':
                $charge = $event->data->object;
                $charge_id = $charge->id;
                $object = $charge->object;
                $customer_id = $charge->customer;
                $balance_transaction = $charge->balance_transaction;
                $amount_captured     = $charge->amount_captured;
                $name     = $charge->billing_details->name;
                $currency = $charge->currency;
                $charge_time     = $charge->created;
                $sub_convert_date = date('Y-m-d H:i:s', $charge_time);
                $description     = $charge->description;
                $invoice = $charge->invoice;
                $paid_status = $charge->paid;
                $ayment_intent = $charge->payment_intent;
                $payment_method = $charge->payment_method;
                $card_brand = $charge->payment_method_details->card->brand;
                $country = $charge->payment_method_details->card->country;
                $exp_month = $charge->payment_method_details->card->exp_month;
                $exp_year = $charge->payment_method_details->card->exp_year;
                $funding = $charge->payment_method_details->card->funding;
                $last4 = $charge->payment_method_details->card->last4;
                $network = $charge->payment_method_details->card->network;
                $card_type = $charge->payment_method_details->type;
                $paid_status = $charge->paid;
                $status = $charge->status;
                $seller_message = $charge->outcome['seller_message'];

                if ($status == 'succeeded') {
                    $charge_data = [
                        'charge_id' => $charge_id,
                        'object' => $object,
                        'charge_customer_id' => $customer_id,
                        'balance_transaction' => $balance_transaction,
                        'plan_amount' => $amount_captured/100,
                        'payer_email' => $name,
                        'plan_amount_currency' => $currency,
                        'charge_create' => $sub_convert_date,
                        'charge_currency' => $currency,
                        'charge_description' => $description,
                        'charge_invoice' => $invoice,
                        'seller_message' => $seller_message,
                        'payment_intent' => $ayment_intent,
                        'payment_method' => $payment_method,
                        'paid_status' => $paid_status,
                        'charge_country' => $country,
                        'exp_month' => $exp_month,
                        'exp_year' => $exp_year,
                        'funding' => $funding,
                        'last4' => $last4,
                        'network' => $network,
                        'type'=> $card_type,
                        'status'=> $status,
                        'created_at' => date('Y-m-d h:i:s')
                    ];
                    $query = Charges::insert($charge_data);
                }
                break;
            case 'customer.subscription.updated':
                $subscription = $event->data->object;
                $item = $subscription->items->data[0];

                $user_sub_data=Subscriptions::where('subscription_id', '=', $subscription->id)->first();

                $user_id   = $user_sub_data->user_id;
                $status = $user_sub_data->payment_status;

                $booking_data = [
                    'user_id' => $user_id,
                    'subscription_id' => $subscription->id,
                    'customer_id' => $subscription->customer_id,
                    'currency' => $subscription->currency,
                    'amount_paid' => $item->plan->amount/100,
                    'payment_status' => $status,
                    'created_at' => Carbon::now()
                ];
    
                $booking_id = Bookings::insertGetId($booking_data);
                
                $subscription_data = [
                    'booking_id' => $booking_id,
                    'plan_period_start' => date('Y-m-d', $subscription->current_period_start),
                    'plan_period_end' => date('Y-m-d', $subscription->current_period_end),
                    'amount_paid' => $item->plan->amount/100,
                    'subscription_status' => $subscription->status,
                    'updated_at' => Carbon::now()
                ];

                Subscriptions::where('subscription_id', '=', $subscription->id)->update($subscription_data);
                break;
            case 'invoice.paid':
                // generate invoice pdf and send to customer
                $invoice = $event->data->object;
                
                // helper function tp generate and send invoice
                generateInvoicePdf($invoice);
                break;
            case 'checkout.session.completed':
                $session = $event->data->object;
            case 'checkout.session.expired':
                $session = $event->data->object;
            case 'customer.created':
                $customer = $event->data->object;
            case 'customer.updated':
                $customer = $event->data->object;
            case 'customer.subscription.created':
                $subscription = $event->data->object;
            case 'customer.subscription.deleted':
                $subscription = $event->data->object;
            case 'customer.subscription.paused':
                $subscription = $event->data->object;
            case 'customer.subscription.resumed':
                $subscription = $event->data->object;
            case 'invoice.created':
                $invoice = $event->data->object;
            case 'invoice.deleted':
                $invoice = $event->data->object;
            case 'invoice.finalization_failed':
                $invoice = $event->data->object;
            case 'invoice.finalized':
                $invoice = $event->data->object;
            case 'invoice.sent':
                $invoice = $event->data->object;
            case 'invoice.upcoming':
                $invoice = $event->data->object;
            case 'invoice.updated':
                $invoice = $event->data->object;
            case 'payment_intent.canceled':
                $paymentIntent = $event->data->object;
            case 'payment_intent.created':
                $paymentIntent = $event->data->object;
            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
            // ... handle other event types
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        http_response_code(200);
    }
}
