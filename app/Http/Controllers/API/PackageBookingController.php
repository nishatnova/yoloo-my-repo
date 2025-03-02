<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\Order;
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
            $package = Package::findOrFail($package_id);

            Stripe::setApiKey(env('STRIPE_SECRET'));

            $paymentIntent = PaymentIntent::create([
                'amount' => $package->price * 100, // Convert price to cents
                'currency' => 'usd',
            ]);

            return $this->sendResponse([
                'client_secret' => $paymentIntent->client_secret,
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
                'client_secret' => 'required|string',
            ]);

            Stripe::setApiKey(env('STRIPE_SECRET'));

            $paymentIntent = PaymentIntent::retrieve($validated['client_secret']);
            $paymentIntent->confirm(['payment_method' => $validated['payment_method_id']]);

            $order = Order::create([
                'user_id' => Auth::id(),
                'package_id' => $package_id,
                'amount' => $paymentIntent->amount_received / 100,
                'status' => 'completed',
                'service_booked' => 'Package',
                'stripe_payment_id' => $paymentIntent->id,
            ]);

            return $this->sendResponse([
                'order' => $order,
                'message' => 'Payment successful. Your booking is confirmed.',
            ], 'Payment completed successfully.');
        } catch (Exception $e) {
            return $this->sendError('Error confirming payment: ' . $e->getMessage(), [], 500);
        }
    }
}
