<?php

use App\Models\Subscriptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Stripe\Stripe;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

     function generateInvoicePdf($invoice) {
        ini_set('memory_limit', '8G');

        // Set the Stripe API key globally
        $stripe = Stripe::setApiKey(env('STRIPE_SECRET'));

        // Retrive customer subscription object
        $subscription = \Stripe\Subscription::Retrieve($invoice->subscription);

        // assign subscription item_1 to $item variable
        $item = $subscription['items']['data'][0];

        $data = [
            'name' => $invoice->customer_name ? $invoice->customer_name : '',
            'email' => $invoice->customer_email ? $invoice->customer_email : '',
            'phone' => $invoice->customer_phone ? $invoice->customer_phone : '',
            'invoice_number' => $invoice->number ? $invoice->number : '',
            'amount_paid' => $invoice->amount_paid ? $invoice->amount_paid/100 : '',
            'currency' => $invoice->currency ? $invoice->currency : '',
            'period_start' => $subscription->current_period_start ? $subscription->current_period_start : '',
            'period_end' => $subscription->current_period_end ? $subscription->current_period_end : '',
            'subtotal' => $invoice->subtotal ? $invoice->subtotal/100 : '',
            'total' => $invoice->total ? $invoice->total/100 : '',
            'item_name' => $item->price->nickname,
            'item_unit_price' => $item->price->unit_amount,
            'item_quantity' => $item->quantity,
        ];

        // send data to invoice.blade.php and generate pdf using Barryvdh\DomPDF
        $pdf = Pdf::loadView('invoice', $data);

        // save pdf in storage path i.e. storage/app/invoices
        $pdf_name = 'invoice_'.time().'.pdf';
        $path = Storage::put('invoices/'.$pdf_name,$pdf->output());

        // update pdf_url in database subscriptions table
        $invoice_url = ('storage/app/invoices/'.$pdf_name);
        Subscriptions::where('subscription_id', '=', $invoice->subscription)->update(['invoice_url' => $invoice_url, 'updated_at' => Carbon::now()]);

        // send invoice to customer
        $email = $invoice->customer_email;
        $data1 = ['salutation' => __('msg.Dear'),'name'=> $invoice->customer_name, 'msg'=> __('msg.This email serves to confirm the successful setup of your subscription with Us.'), 'msg1'=> __('msg.We are delighted to welcome you as a valued subscriber and are confident that you will enjoy the benefits of Premium Services.'),'msg2' => __('msg.Thank you for your trust!')];

        Mail::send('invoice_email', $data1, function ($message) use ($pdf_name, $email, $pdf) {
            $message->to($email)->subject('Invoice');
            $message->attachData($pdf->output(), $pdf_name, ['as' => $pdf_name, 'mime' => 'application/pdf']);
        });
        
        return $path;
    }
?>