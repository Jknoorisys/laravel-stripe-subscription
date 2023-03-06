<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Plan;
use App\Models\Subscriptions;
use Carbon\Carbon;
use Stripe\Stripe;

class PlanController extends Controller
{
    public function __construct() {
        // Define a middleware function that is executed before processing a request
        $this->middleware(function ($request, $next) {

            // Create a new instance of the Stripe client using the Stripe API key obtained from the 'STRIPE_SECRET' environment variable
            $this->stripe = new \Stripe\StripeClient(
                env('STRIPE_SECRET')
            );

            // Set the Stripe API key globally using the 'setApiKey' method from the 'Stripe' class
            $this->stripe_key = Stripe::setApiKey(env('STRIPE_SECRET'));

            // Retrieve the current authenticated user using the 'auth()' function and assign it to the '$user' variable
            $this->user = auth()->user();

            // Continue processing the request by invoking the '$next' callback and passing the '$request' object as a parameter
            return $next($request);
        });
    }

    public function checkout(Request $request) //Subscription
    {
        // Fetch a plan object based on the given 'plan_id' from the 'Plan' model
        $plan = Plan::where('plan_id', '=', $request->plan_id)->first();

        // Set the success and cancel URLs for the checkout session
        $success_url = url('subscription-success');
        $cancel_url = url('subscription-fail');

        // If the plan object exists
        if (!empty($plan)) {
            // Create a new Stripe checkout session object 
            $session = \Stripe\Checkout\Session::Create([
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'payment_method_types' => ['card'],
                'line_items' => [
                        ['price' => $plan->plan_id, 'quantity' => 1],
                    ],
                'mode' => 'subscription',
                'currency' => env('STRIPE_CURRENCY'),
            ]);

            // If the checkout session is successfully created
            if ($session) {
                $booking_data = [
                    'session_id' => $session->id,
                    'user_id' => $this->user->id,
                    'subscription_id' => $session->subscription ? $session->subscription : '',
                    'customer_id' => $session->customer ? $session->customer : '',
                    'amount_paid' => $session->amount_total/100,
                    'currency' => $session->currency,
                    'payment_status' => $session->payment_status,
                    'session_status' => $session->status,
                    'created_at' => Carbon::now()
                ];

                // Insert the booking data into the database using the 'Booking::insert' method
                $insert = Booking::insert($booking_data);

                // If the insert is successful, redirect the user to the checkout URL
                if ($insert) {
                    return redirect()->to($session->url);
                } else {
                    return redirect()->back()->with('fail','Something went wrong, Please try again...');
                }
            } else {
                return redirect()->back()->with('fail','Something went wrong, Please try again...');
            }
        } else {
            return redirect()->back()->with('fail','Invalid Plan, Please try again...');
        }
    }

    public function payment(Request $request) //One time payment
    {
        $amount = $request->amount;

        // Set the success and cancel URLs for the checkout session
        $success_url = url('subscription-success');
        $cancel_url = url('subscription-fail');

        // Check if amount is provided
        if ($amount) {

            // Create line items array
            $line_items = [
                [
                  'price_data'=> [
                    'currency'=> env('STRIPE_CURRENCY'),
                    'unit_amount'=> $amount * 100,
                    'product_data'=> [
                        'name'=> "One Time Payment",
                        ],
                    ],
                  'quantity'=> 1,
                ],
            ];

            // Create Stripe Checkout session
            $session = \Stripe\Checkout\Session::Create([
                'success_url' => $success_url,
                'cancel_url' => $cancel_url,
                'payment_method_types' => ['card'],
                'line_items' => $line_items,
                'mode' => 'payment',
                'customer_creation' => 'always',
            ]);

            // Store booking data and redirect to session URL if successful
            if ($session) {
                $booking_data = [
                    'session_id' => $session->id,
                    'user_id' => $this->user->id,
                    'subscription_id' => $session->payment_intent ? $session->payment_intent : '',
                    'customer_id' => $session->customer ? $session->customer : '',
                    'amount_paid' => $session->amount_total/100,
                    'currency' => $session->currency,
                    'payment_status' => $session->payment_status,
                    'session_status' => $session->status,
                    'created_at' => Carbon::now()
                ];
                $insert = Booking::insert($booking_data);

                if ($insert) {
                    return redirect()->to($session->url);
                } else {
                    return redirect()->back()->with('fail','Something went wrong, Please try again...');
                }
            } else {
                return redirect()->back()->with('fail','Something went wrong, Please try again...');
            }
        } else {
            return redirect()->back()->with('fail','Something went wrong, Please try again...');
        }
        
    }

    public function subscriptionSuccess()
    {
        return redirect()->to('plans')->with('success','Payment Successfull!');
    }

    public function subscriptionFail()
    {
        return redirect()->to('plans')->with('fail','Unable to Pay, Please try again...');
    }

    public function webhook(Request $request)
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
            case 'checkout.session.completed':
                $session = $event->data->object;
                $booking_data = [
                    'subscription_id' => $session->subscription ? $session->subscription : $session->payment_intent,
                    'customer_id' => $session->customer ? $session->customer : '',
                    'payment_status' => $session->payment_status,
                    'session_status' => $session->status,
                    'updated_at' => Carbon::now()
                ];
                Booking::where('session_id',$session->id)->update($booking_data);
                break;
            case 'checkout.session.expired':
                $session = $event->data->object;
                $booking_data = [
                    'session_status' => $session->status,
                    'updated_at' => Carbon::now()
                ];
                Booking::where('session_id',$session->id)->update($booking_data);
                break;
            case 'customer.created':
                $customer = $event->data->object;
            case 'customer.updated':
                $customer = $event->data->object;
            case 'customer.subscription.created':
                $subscription = $event->data->object;
                $booking = Booking::where('subscription_id',$subscription->id)->first();
                $item = $subscription->items->data[0];
                $subscription_data = [
                    'booking_id'    => $booking->id,
                    'user_id' => $booking->user_id,
                    'customer_id' => $subscription->customer ? $subscription->customer : '',
                    'subscription_id' => $subscription->id ? $subscription->id : '',
                    'plan_id' => $item->plan->id,
                    'currency' => $subscription->currency,
                    'plan_interval' => $item->plan->interval,
                    'plan_period_start' => date('Y-m-d', $subscription->current_period_start),
                    'plan_period_end' => date('Y-m-d', $subscription->current_period_end),
                    'amount_paid' => $item->plan->amount/100,
                    'payment_status' => $booking->payment_status,
                    'subscription_status' => $subscription->status,
                    'created_at' => Carbon::now()
                ];
                Subscriptions::insert($subscription_data);
                break;
            case 'customer.subscription.deleted':
                $subscription = $event->data->object;
            case 'customer.subscription.paused':
                $subscription = $event->data->object;
            case 'customer.subscription.resumed':
                $subscription = $event->data->object;
            case 'customer.subscription.updated':
                $subscription = $event->data->object;
                $item = $subscription->items->data[0];
                $subscription_data = [
                    'plan_period_start' => date('Y-m-d', $subscription->current_period_start),
                    'plan_period_end' => date('Y-m-d', $subscription->current_period_end),
                    'amount_paid' => $item->plan->amount/100,
                    'subscription_status' => $subscription->status,
                    'updated_at' => Carbon::now()
                ];
                Subscriptions::where('subscription_id', '=', $subscription->id)->update($subscription_data);
                break;
            case 'invoice.created':
                $invoice = $event->data->object;
            case 'invoice.deleted':
                $invoice = $event->data->object;
            case 'invoice.finalization_failed':
                $invoice = $event->data->object;
            case 'invoice.finalized':
                $invoice = $event->data->object;
            case 'invoice.paid':
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
                $booking = Booking::where('subscription_id',$paymentIntent->id)->first();
                $paymentIntent_data = [
                    'booking_id'    => $booking->id,
                    'user_id' => $booking->user_id,
                    'customer_id' => $paymentIntent->customer ? $paymentIntent->customer : '',
                    'subscription_id' => $paymentIntent->id ? $paymentIntent->id : '',
                    'currency' => $paymentIntent->currency,
                    'amount_paid' => $paymentIntent->amount_received/100,
                    'payment_status' => $booking->payment_status,
                    'subscription_status' => $paymentIntent->status,
                    'created_at' => Carbon::now()
                ];
                Subscriptions::insert($paymentIntent_data);
                break;
            // ... handle other event types
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        http_response_code(200);
    }
}
