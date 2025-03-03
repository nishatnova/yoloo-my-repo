<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\Order;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;

class TemplatePurchaseController extends Controller
{
    use ResponseTrait;
    public function initiatePayment(Request $request, $template_id)
    {
        try {
            $template = Template::findOrFail($template_id);

            Stripe::setApiKey(env('STRIPE_SECRET'));

            // $paymentIntent = PaymentIntent::create([
            //     'amount' => $template->price * 100, // Convert price to cents
            //     'currency' => 'usd',
            // ]);

            $paymentIntent = PaymentIntent::create([
                'amount' => $template->price * 100, 
                'currency' => 'usd',
                'automatic_payment_methods' => [
                    'enabled' => true,  
                    'allow_redirects' => 'never', 
                ],
                'metadata' => [
                    'template_id' => $template->id, 
                    'user_id' => Auth::id(),
                ]
            ]);
            

            return $this->sendResponse([
                'payment_intent_id' => $paymentIntent->id,
                'metadata' => $paymentIntent->metadata,
                'template' => $template,
            ], 'Payment initiation successful.');
        } catch (\Exception $e) {
            return $this->sendError('Error initiating payment: ' . $e->getMessage(), [], 500);
        }
    }

    // Confirm Payment for Template
    public function confirmPayment(Request $request, $template_id)
    {
        try {
            $validated = $request->validate([
                'payment_method_id' => 'required|string', 
                'payment_intent_id' => 'required|string', 
            ]);


            Stripe::setApiKey(env('STRIPE_SECRET'));


            $paymentIntent = PaymentIntent::retrieve($validated['payment_intent_id']);


            $metadata = $paymentIntent->metadata;

            $paymentIntent->confirm([
                'payment_method' => $validated['payment_method_id'],
            ]);

            $order = Order::create([
                'user_id' => Auth::id(),
                'template_id' => $template_id,
                'amount' => $paymentIntent->amount_received / 100, 
                'status' => 'completed',
                'service_booked' => 'Template', 
                'stripe_payment_id' => $paymentIntent->id,
                'metadata' => json_encode($metadata),
            ]);

            // Return success response
            return $this->sendResponse([
                'order' => $order,
                'message' => 'Payment successful. Your order has been placed.',
                'metadata' => $metadata,
            ], 'Payment completed successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Error confirming payment: ' . $e->getMessage(), [], 500);
        }
    }
}
