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
                $order->metadata = json_decode($order->metadata); 
                return [
                    'order_id' => $order->id,
                    'service_booked' => $order->service_booked,
                    'date' => $order->created_at->format('Y-M-d'),
                    'status' => $order->status,
                    'amount' => $order->amount,
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
            $status = $request->query('status'); 
            $search = $request->query('search'); 
            $limit = $request->query('limit', 10); 
            $page = $request->query('page', 1);

            $query = Order::with('user'); 
            if ($status) {
                $query->where('status', $status);
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'LIKE', "%$search%") 
                    ->orWhereHas('user', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%$search%"); 
                    })
                    ->orWhere('amount', 'LIKE', "%$search%") 
                    ->orWhere('service_booked', 'LIKE', "%$search%")
                    ->orWhere('status', 'LIKE', "%$search%")
                    ->orWhereDate('created_at', 'LIKE', "%$search%"); 
                });
            }

            // Fetch paginated results
            $orders = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

            // Format orders with necessary details
            $formattedOrders = $orders->map(function ($order) {
                $order->metadata = json_decode($order->metadata); // Decode the metadata
                return [
                    'order_id' => $order->id,
                    'customer_name' => $order->user->name,
                    'customer_email' => $order->user->email,
                    'amount' => $order->amount,
                    'status' => $order->status,
                    'date' => $order->created_at->format('Y-M-d'),
                    'service_booked' => $order->service_booked,
                    'payment_id' => $order->stripe_payment_id,
                ];
            });

            // Return the paginated response with orders
            return $this->sendResponse([
                'orders' => $formattedOrders,
                'meta' => [
                    'current_page' => $orders->currentPage(),
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'last_page' => $orders->lastPage(),
                ],
            ], 'All orders retrieved successfully.');
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
                'date' => $order->created_at->format('Y-M-d'),
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
