<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\Order;
use App\Models\PackageInquiry;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Customer;
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

            // Check for overlapping bookings for the same package in the given date range
            $existingBooking = Order::join('package_inquiries', 'orders.package_inquiry_id', '=', 'package_inquiries.id') 
                ->where('orders.package_id', $package->id)
                ->where('orders.status', 'Completed')
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('package_inquiries.event_start_date', [$validated['event_start_date'], $validated['event_end_date']])
                        ->orWhereBetween('package_inquiries.event_end_date', [$validated['event_start_date'], $validated['event_end_date']])
                        ->orWhere(function ($query) use ($validated) {
                            $query->where('package_inquiries.event_start_date', '<=', $validated['event_start_date'])
                                    ->where('package_inquiries.event_end_date', '>=', $validated['event_end_date']);
                        });
                })
                ->exists();

            if ($existingBooking) {
                return $this->sendError('This package is already booked for the selected dates.', [], 400);
            }


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

            $customer = Customer::create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => $user->id,
                ],
            ]);

            $paymentIntent = PaymentIntent::create([
                'amount' => $package->price * 100,
                'currency' => 'usd',
                'customer' => $customer->id,
                'automatic_payment_methods' => [
                    'enabled' => true,
                    'allow_redirects' => 'never',
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

            $order = Order::create([
                'user_id' => $user->id,
                'package_id' => $package_id,
                'package_inquiry_id' => $inquiry->id,
                'amount' => $paymentIntent->amount / 100,
                'status' => 'Pending',
                'service_booked' => 'Package',
                'stripe_payment_id' => $paymentIntent->id,
                'stripe_customer_id' => $customer->id,
                'metadata' => json_encode($paymentIntent->metadata),
            ]);

            return $this->sendResponse([
                'order_id' => $order->id,
                'payment_intent_id' => $paymentIntent->id,
                'customer_id' => $customer->id,
                'metadata' => $paymentIntent->metadata,
                'package' => $package,
                'inquiry' => $inquiry,
            ], 'Payment initiation successful.');
        } catch (Exception $e) {
            return $this->sendError('Error initiating payment: ' . $e->getMessage(), [], 500);
        }
    }


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

            $order = Order::where('stripe_payment_id', $paymentIntent->id)->first();

            if ($order) {
                $order->status = 'Completed';
                $order->save();

                return $this->sendResponse([
                    'user_id' => Auth::id(),
                    'package_id' => $package_id,
                    'transaction_id' => $paymentIntent->id,
                    'amount' => $paymentIntent->amount_received / 100, 
                    'date' => date('d-M-Y', $paymentIntent->created), 
                    'time' => date('H:i:s', $paymentIntent->created), 
                    'payment_method' => $paymentIntent->payment_method_types[0], 
                    'product' => $metadata['service_title'], 
                    'customer' => $metadata['user_name'], 
                    'status' => $order->status,
                ], 'Payment completed successfully.');
            }
            return $this->sendError('Order not found for payment intent.', [], 404);

        } catch (Exception $e) {
            return $this->sendError('Error confirming payment: ' . $e->getMessage(), [], 500);
        }
    }

    public function getBookedDates(Request $request, $package_id)
    {
        try {
            $package = Package::findOrFail($package_id);  
    
            $bookedDates = Order::join('package_inquiries', 'orders.package_inquiry_id', '=', 'package_inquiries.id')
                ->where('orders.package_id', $package_id)
                ->where('orders.status', 'Completed')
                ->get(['package_inquiries.event_start_date', 'package_inquiries.event_end_date']);
    
            $bookedDatesArray = $bookedDates->flatMap(function ($booking) {
                $startDate = \Carbon\Carbon::parse($booking->event_start_date);
                $endDate = \Carbon\Carbon::parse($booking->event_end_date);
                $dates = [];
    
                while ($startDate <= $endDate) {
                    $dates[] =  $startDate->format('Y-m-d');
                    $startDate->addDay(); 
                }
    
                return $dates;
            });
    
            // Check if no dates are found
            if ($bookedDatesArray->isEmpty()) {
                return $this->sendResponse([], 'No bookings found for the selected package.');
            }
    
            return $this->sendResponse($bookedDatesArray, 'Booked dates retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving booked dates: ' . $e->getMessage(), [], 500);
        }
    }


}
