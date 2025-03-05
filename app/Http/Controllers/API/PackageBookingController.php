<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\Order;
use App\Models\PackageInquiry;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Illuminate\Support\Facades\Auth;
use App\Traits\ResponseTrait;
use Exception;

class PackageBookingController extends Controller
{
    use ResponseTrait;

    // Initiate Payment for Package Booking
    public function initiatePayment(Request $request, $package_id)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'email' => 'required|email',
                'phone' => 'required|string',
                'event_start_date' => 'required|date',
                'event_end_date' => 'required|date',
                'guests' => 'required|integer',
            ]);

            $package = Package::findOrFail($package_id);
            $user = Auth::user();

            $inquiry = PackageInquiry::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'event_start_date' => $validated['event_start_date'],
                'event_end_date' => $validated['event_end_date'],
                'guests' => $validated['guests'],
                'event_type' => 'Wedding',
                'status' => 'Pending',
            ]);

            Stripe::setApiKey(env('STRIPE_SECRET'));

            $paymentIntent = PaymentIntent::create([
                'amount' => $package->price * 100, 
                'currency' => 'usd',
                'automatic_payment_methods' => [
                    'enabled' => true,  
                    'allow_redirects' => 'never', // Avoid redirects
                ],
                'metadata' => [
                'inquiry_id' => $inquiry->id,  
                'package_id' => $package->id,
                'service_title' => $package->service_title,
                'user_id' => $user->id,
                'user_name' => $inquiry->name,
                'user_email' => $inquiry->email,
            ]
            ]);

            return $this->sendResponse([
                'payment_intent_id' => $paymentIntent->id,
                'metadata' => $paymentIntent->metadata,
                'package' => $package,
            ], 'Payment initiation successful.');
        } catch (Exception $e) {
            return $this->sendError('Error initiating payment: ' . $e->getMessage(), [], 500);
        }
    }

    // Confirm Payment for Package Booking
    public function confirmPayment(Request $request, $package_id)
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
                'package_id' => $package_id,
                'amount' => $paymentIntent->amount_received / 100, 
                'service_booked' => 'Package', 
                'status' => 'Completed', 
                'stripe_payment_id' => $paymentIntent->id,
                'package_inquiry_id' => $metadata['inquiry_id'],
                'metadata' => json_encode($metadata),
            ]);

            return $this->sendResponse([
                'user_id' => Auth::id(),
                'package_id' => $package_id,
                'transaction_id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount_received / 100, 
                'date' => date('m/d/Y', $paymentIntent->created), 
                'time' => date('H:i:s', $paymentIntent->created), 
                'payment_method' => $paymentIntent->payment_method_types[0], 
                'product' => $metadata['service_title'], 
                'customer' => $metadata['user_name'], 
            ], 'Payment completed successfully.');
        } catch (Exception $e) {
            return $this->sendError('Error confirming payment: ' . $e->getMessage(), [], 500);
        }
    }
}
