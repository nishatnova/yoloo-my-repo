<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\Order;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Customer;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;

class TemplatePurchaseController extends Controller
{
    use ResponseTrait;
    public function initiatePayment(Request $request, $template_id)
    {
        try {
            
            $user = Auth::user();
            $existingOrder = Order::where('user_id', $user->id)
            ->where('template_id', $template_id)
            ->where('status', 'Completed')
            ->first();

            if ($existingOrder) {
            return $this->sendError('You have already purchased this template.', [], 400);
            }

            $template = Template::findOrFail($template_id);


            Stripe::setApiKey(env('STRIPE_SECRET'));

            
            $customer = Customer::create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id, 
                ],
            ]);

            $paymentIntent = PaymentIntent::create([
                'amount' => $template->price * 100, 
                'currency' => 'usd',
                'customer' => $customer->id,
                'automatic_payment_methods' => [
                    'enabled' => true,  
                    'allow_redirects' => 'never', 
                ],
                'metadata' => [
                    'template_id' => $template->id, 
                    'template_title' => $template->title, 
                    'user_id' => $user->id,   
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                ]
            ]);

            $order = Order::create([
                'user_id' => $user->id,
                'template_id' => $template->id,
                'amount' => $paymentIntent->amount / 100, 
                'status' => 'Pending',  // Set initial status to 'pending'
                'service_booked' => 'Template',
                'stripe_payment_id' => $paymentIntent->id,
                'stripe_customer_id' => $customer->id,
                'metadata' => json_encode($paymentIntent->metadata),
            ]);
            

            return $this->sendResponse([
                'order_id' => $order->id,
                'payment_intent_id' => $paymentIntent->id,
                'customer_id' => $customer->id,
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

            $order = Order::where('stripe_payment_id', $paymentIntent->id)->first();

            if ($order) {
                // Update order status to 'completed'
                $order->status = 'Completed';
                $order->save();

                return $this->sendResponse([
                    'user_id' => Auth::id(),
                    'template_id' => $template_id,
                    'transaction_id' => $paymentIntent->id,
                    'amount' => $paymentIntent->amount_received / 100, 
                    'date' => date('d-M-Y', $paymentIntent->created), 
                    'time' => date('H:i:s', $paymentIntent->created), 
                    'payment_method' => $paymentIntent->payment_method_types[0], 
                    'product' => $metadata['template_title'], 
                    'customer' => $metadata['user_name'], 
                    'status' => $order->status,
                ], 'Payment completed successfully.');
            }
            return $this->sendError('Order not found for payment intent.', [], 404);

        } catch (\Exception $e) {
            return $this->sendError('Error confirming payment: ' . $e->getMessage(), [], 500);
        }
    }
}
