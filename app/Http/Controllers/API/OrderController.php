<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Template;
use App\Models\Package;
use App\Models\Order;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    use ResponseTrait;

    /**
     * Get all orders for the authenticated user.
     */
    public function getOrders(Request $request)
    {
        try {
            // Fetch all orders of the authenticated user
            $orders = Order::where('user_id', Auth::id())->get();

            if ($orders->isEmpty()) {
                return $this->sendError('No orders found for this user.', [], 404);
            }

            return $this->sendResponse($orders, 'Orders retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving orders: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get details of a specific order by ID.
     */
    public function getOrderDetails(Request $request, $order_id)
    {
        try {
            // Find the order by ID and ensure it's the user's order
            $order = Order::where('id', $order_id)->where('user_id', Auth::id())->first();

            if (!$order) {
                return $this->sendError('Order not found or access denied.', [], 404);
            }

            // Fetch the related template or package details
            $orderDetails = [
                'order_id' => $order->id,
                'service_booked' => $order->service_booked,
                'amount' => $order->amount,
                'status' => $order->status,
                'payment_id' => $order->stripe_payment_id,
                'booking_date' => $order->created_at->format('Y-m-d H:i:s'),
            ];

            // If the order is related to a template purchase, get the template details
            if ($order->template_id) {
                $template = Template::find($order->template_id);
                $orderDetails['template'] = [
                    'name' => $template->name,
                    'title' => $template->title,
                    'price' => $template->price,
                ];
            }

            // If the order is related to a package booking, get the package details
            if ($order->package_id) {
                $package = Package::find($order->package_id);
                $orderDetails['package'] = [
                    'title' => $package->title,
                    'location' => $package->location,
                    'price' => $package->price,
                ];
            }

            return $this->sendResponse($orderDetails, 'Order details retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving order details: ' . $e->getMessage(), [], 500);
        }
    }
}
