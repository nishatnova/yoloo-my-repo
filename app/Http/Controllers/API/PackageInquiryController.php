<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PackageInquiry;
use App\Models\JobApplication;
use App\Models\PackageInquireStaff;
use App\Traits\ResponseTrait;
use Illuminate\Http\Request;

class PackageInquiryController extends Controller
{
    use ResponseTrait;
    public function getCompletedPackageInquiries(Request $request)
    {
        try {
            // Fetch package inquiries where the associated order status is 'completed'
            $inquiries = PackageInquiry::with(['package', 'user', 'order']) 
                ->whereHas('order', function ($query) {
                    $query->where('status', 'Completed'); 
                })
                ->get();

            if ($inquiries->isEmpty()) {
                return $this->sendError('No completed package inquiries found.', [], 404);
            }

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
            $inquiry = PackageInquiry::with(['package', 'user', 'order'])
                ->findOrFail($inquiry_id);

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

    public function updateStatus(Request $request, $inquiry_id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|string|in:Pending,Active,Completed,Cancel', 
            ]);

            
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

    // Assign job application staff to a package inquiry
    public function assignStaffToInquiry(Request $request, $inquiry_id)
    {
        try {
            // Validate the incoming data
            $validated = $request->validate([
                'photographer_application_id' => 'nullable|exists:job_applications,id',
                'decorator_application_id' => 'nullable|exists:job_applications,id',
                'catering_application_id' => 'nullable|exists:job_applications,id',
            ]);

            // Fetch the package inquiry (event)
            $inquiry = PackageInquiry::findOrFail($inquiry_id);

            // Check if the job applications are approved for the respective roles (photographer, decorator, catering)
            $photographerApplication = JobApplication::where('status', 'Approved')
                ->where('id', $validated['photographer_application_id'] ?? null)
                ->first();

            $decoratorApplication = JobApplication::where('status', 'Approved')
                ->where('id', $validated['decorator_application_id'] ?? null)
                ->first();

            $cateringApplication = JobApplication::where('status', 'Approved')
                ->where('id', $validated['catering_application_id'] ?? null)
                ->first();

            // Assign the staff based on the job application IDs
            $staffAssignment = PackageInquireStaff::create([
                'package_inquiry_id' => $inquiry->id,
                'photographer_application_id' => $photographerApplication ? $photographerApplication->id : null,
                'decorator_application_id' => $decoratorApplication ? $decoratorApplication->id : null,
                'catering_application_id' => $cateringApplication ? $cateringApplication->id : null,
            ]);

            // Return response
            return $this->sendResponse($staffAssignment, 'Staff assigned to event successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Error assigning staff: ' . $e->getMessage(), [], 500);
        }
    }

   

}
