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
    public function getUserOrders(Request $request)
    {
        try {
            $orders = Order::with('user') 
                            ->where('user_id', Auth::id())
                            ->get();
    
            if ($orders->isEmpty()) {
                return $this->sendError('No orders found for this user.', [], 404);
            }
    
            $formattedOrders = $orders->map(function ($order) {
                return [
                    'order_id' => $order->id,
                    'service_booked' => $order->service_booked,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'payment_id' => $order->stripe_payment_id,
                    'booking_date' => $order->created_at->format('Y-m-d H:i:s'),
                    'user' => [
                        'name' => $order->user->name, 
                        'email' => $order->user->email, 
                    ],
                ];
            });
    
            return $this->sendResponse($formattedOrders, 'Orders retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving orders: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Get all orders 
     */
    public function getAllOrders(Request $request)
    {
        try {
            $orders = Order::with('user')->get(); 

            if ($orders->isEmpty()) {
                return $this->sendError('No orders found.', [], 404);
            }

            // Format orders with user information
            $formattedOrders = $orders->map(function ($order) {
                return [
                    'order_id' => $order->id,
                    'service_booked' => $order->service_booked,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'payment_id' => $order->stripe_payment_id,
                    'booking_date' => $order->created_at->format('Y-m-d H:i:s'),
                    'user' => [
                        'name' => $order->user->name, 
                        'email' => $order->user->email, 
                    ],
                ];
            });

            return $this->sendResponse($formattedOrders, 'All orders retrieved successfully.');
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
            $order = Order::with('user')->where('id', $order_id)->first();

            if (!$order) {
                return $this->sendError('Order not found or access denied.', [], 404);
            }
            $orderDetails = [
                'order_id' => $order->id,
                'service_booked' => $order->service_booked,
                'amount' => $order->amount,
                'status' => $order->status,
                'payment_id' => $order->stripe_payment_id,
                'booking_date' => $order->created_at->format('Y-m-d H:i:s'),
                'user' => [
                        'name' => $order->user->name, 
                        'email' => $order->user->email, 
                    ],
            ];

            if ($order->template_id) {
                $template = Template::find($order->template_id);
                $orderDetails['template'] = [
                    'name' => $template->name,
                    'title' => $template->title,
                    'price' => $template->price,
                ];
            }

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
