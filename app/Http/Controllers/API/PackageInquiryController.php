<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PackageInquiry;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;

class PackageInquiryController extends Controller
{
    use ResponseTrait;
    public function getCompletedPackageInquiries(Request $request)
    {
        try {
            // Fetch package inquiries where the associated order status is 'completed'
            $inquiries = PackageInquiry::with(['package', 'user', 'order']) // Eager load related models
                ->whereHas('order', function ($query) {
                    $query->where('status', 'Completed'); 
                })
                ->get();

            if ($inquiries->isEmpty()) {
                return $this->sendError('No completed package inquiries found.', [], 404);
            }

            // Format the inquiries with necessary details
            $formattedInquiries = $inquiries->map(function ($inquiry) {
                return [
                    'event_id' => $inquiry->id,
                    'customer' => $inquiry->name,
                    'event_name' => $inquiry->package->service_title,
                    'date' => $inquiry->event_start_date . ' to ' . $inquiry->event_end_date,
                    'amount' => $inquiry->order->amount, 
                    'status' => $inquiry->status, 
                    'order_status' => $inquiry->order->status
                ];
            });

            return $this->sendResponse([
                'inquiries' => $formattedInquiries,
            ], 'Completed package inquiries retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving completed package inquiries: ' . $e->getMessage(), [], 500);
        }
    }

    public function showDetail($inquiry_id)
    {
        try {
            // Find the package inquiry by its ID
            $inquiry = PackageInquiry::with(['package', 'user', 'order'])
                ->findOrFail($inquiry_id);

            // Format the inquiry details
            $inquiryDetails = [
                'event_id' => $inquiry->id,
                'customer' => $inquiry->name,
                'email' => $inquiry->email,
                'phone' => $inquiry->phone,
                'event_name' => $inquiry->package->service_title,
                'event_type' => $inquiry->event_type,
                'date' => $inquiry->event_start_date . ' to ' . $inquiry->event_end_date,
                'guests' => $inquiry->guests,
                'amount' => $inquiry->order->amount,
                'status' => $inquiry->status,
                'order_status' => $inquiry->order->status,
                'order_date' => $inquiry->order->created_at->format('Y-m-d H:i:s'),
                'payment_id' => $inquiry->order->stripe_payment_id,
            ];

            return $this->sendResponse($inquiryDetails, 'Package inquiry details retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving package inquiry details: ' . $e->getMessage(), [], 500);
        }
    }

    // Method to update status in PackageInquiry
    public function updateStatus(Request $request, $inquiry_id)
    {
        try {
            // Validate the incoming status data
            $validated = $request->validate([
                'status' => 'required|string|in:Pending,Active,Completed,Cancel', // Ensure status is one of the predefined options
            ]);

            // Find the inquiry and update status
            $inquiry = PackageInquiry::findOrFail($inquiry_id);
            $inquiry->status = $validated['status'];
            $inquiry->save();

            return $this->sendResponse([
                'status' => $inquiry->status,
            ], 'Status updated successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error updating package inquiry status: ' . $e->getMessage(), [], 500);
        }
    }

   

}
