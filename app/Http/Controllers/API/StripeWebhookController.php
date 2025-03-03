<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Webhook;
use Stripe\PaymentIntent;
use Stripe\Exception\SignatureVerificationException;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    /**
     * Handle incoming webhook events from Stripe
     */
    public function handleWebhook(Request $request)
    {
        $endpointSecret = env('STRIPE_WEBHOOK_SECRET'); 

        // Get the raw payload and the Stripe-Signature header
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
         
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
            Log::info("Stripe webhook received", ['event' => $event]);

        } catch (SignatureVerificationException $e) {
            Log::error('Webhook signature verification failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Webhook signature verification failed: ' . $e->getMessage()], 400);
        }

        
        switch ($event->type) {
            case 'payment_intent.succeeded':
               
                $paymentIntent = $event->data->object;
                $this->handlePaymentIntentSucceeded($paymentIntent);
                break;

            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                $this->handlePaymentIntentFailed($paymentIntent);
                break;

            case 'payment_intent.created':
                $paymentIntent = $event->data->object;
                $this->handlePaymentIntentCreated($paymentIntent);
                break;

            default:
                Log::info("Unhandled event type", ['event_type' => $event->type]);
                return response()->json(['message' => 'Unhandled event type'], 200);
        }

        return response()->json(['message' => 'Webhook received'], 200);
    }

    /**
     * Handle successful payment
     */
    protected function handlePaymentIntentSucceeded($paymentIntent)
    {
        Log::info('Payment intent succeeded', ['payment_intent_id' => $paymentIntent->id]);

        // Get the order associated with this payment
        $order = Order::where('stripe_payment_id', $paymentIntent->id)->first();

        if ($order) {
            $order->status = 'completed';
            $order->save();
            Log::info('Order marked as completed', ['order_id' => $order->id]);
        } else {
            Log::error("Order not found for payment intent: " . $paymentIntent->id);
        }
    }

    /**
     * Handle failed payment
     */
    protected function handlePaymentIntentFailed($paymentIntent)
    {
        Log::info('Payment intent failed', ['payment_intent_id' => $paymentIntent->id]);

        $order = Order::where('stripe_payment_id', $paymentIntent->id)->first();

        if ($order) {
            $order->status = 'failed';
            $order->save();
            Log::info('Order marked as failed', ['order_id' => $order->id]);
        } else {
            Log::error("Order not found for payment intent: " . $paymentIntent->id);
        }
    }

    /**
     * Handle payment intent creation
     */
    protected function handlePaymentIntentCreated($paymentIntent)
    {
        Log::info('Payment intent created', ['payment_intent_id' => $paymentIntent->id]);

        
        $order = Order::create([
            'stripe_payment_id' => $paymentIntent->id,
            'amount' => $paymentIntent->amount / 100, 
            'status' => 'pending',
        ]);

        Log::info('Payment intent created and order saved', ['order_id' => $order->id]);
    }
}
