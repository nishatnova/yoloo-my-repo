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
            $inquiries = PackageInquiry::with(['package', 'user', 'order', 'packageInquireStaff'])
                ->whereHas('order', function ($query) {
                    $query->where('status', 'Completed');
                })
                ->get();

            if ($inquiries->isEmpty()) {
                return $this->sendError('No completed package inquiries found.', [], 404);
            }

            $formattedInquiries = $inquiries->map(function ($inquiry) {
                // Get the assigned staff info
                $assignedStaffCount = 0;
                if ($inquiry->packageInquireStaff) {
                    // Count the assigned staff (photographer, decorator, catering)
                    if ($inquiry->packageInquireStaff->photographerApplication) {
                        $assignedStaffCount++;
                    }
                    if ($inquiry->packageInquireStaff->decoratorApplication) {
                        $assignedStaffCount++;
                    }
                    if ($inquiry->packageInquireStaff->cateringApplication) {
                        $assignedStaffCount++;
                    }
                }

                return [
                    'event_id' => $inquiry->id,
                    'customer' => $inquiry->name,
                    'event_name' => $inquiry->package->service_title,
                    'date' => $inquiry->event_start_date . ' to ' . $inquiry->event_end_date,
                    'amount' => $inquiry->order->amount,
                    'status' => $inquiry->status,
                    'order_status' => $inquiry->order->status,
                    'assigned_staff_count' => $assignedStaffCount,  // Assigned staff count
                ];
            });

            return $this->sendResponse([
                'inquiries' => $formattedInquiries,
            ], 'Completed package inquiries retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving completed package inquiries: ' . $e->getMessage(), [], 500);
        }
    }

    public function getLatestCompletedPackageInquiries(Request $request)
{
    try {
        // Fetch the latest 5 package inquiries where the associated order status is 'completed'
        $inquiries = PackageInquiry::with(['package', 'user', 'order', 'packageInquireStaff'])
            ->whereHas('order', function ($query) {
                $query->where('status', 'Completed');
            })
            ->latest() // Order by latest created_at
            ->take(5) // Limit to the latest 5 inquiries
            ->get();

        if ($inquiries->isEmpty()) {
            return $this->sendError('No completed package inquiries found.', [], 404);
        }

        $formattedInquiries = $inquiries->map(function ($inquiry) {
            // Get the assigned staff info
            $assignedStaffCount = 0;
            if ($inquiry->packageInquireStaff) {
                // Count the assigned staff (photographer, decorator, catering)
                if ($inquiry->packageInquireStaff->photographerApplication) {
                    $assignedStaffCount++;
                }
                if ($inquiry->packageInquireStaff->decoratorApplication) {
                    $assignedStaffCount++;
                }
                if ($inquiry->packageInquireStaff->cateringApplication) {
                    $assignedStaffCount++;
                }
            }

            return [
                'event_id' => $inquiry->id,
                'customer' => $inquiry->name,
                'event_name' => $inquiry->package->service_title,
                'date' => $inquiry->event_start_date . ' to ' . $inquiry->event_end_date,
                'amount' => $inquiry->order->amount,
                'status' => $inquiry->status,
                'order_status' => $inquiry->order->status,
                'assigned_staff_count' => $assignedStaffCount,  // Assigned staff count
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
            $inquiry = PackageInquiry::with(['package', 'user', 'order', 'packageInquireStaff'])
                ->findOrFail($inquiry_id);

            // Prepare assigned staff info
            $assignedStaff = [];
            if ($inquiry->packageInquireStaff) {
                if ($inquiry->packageInquireStaff->photographerApplication) {
                    $assignedStaff[] = [
                        'role' => 'Photographer',
                        'name' => $inquiry->packageInquireStaff->photographerApplication->applicant_name,
                    ];
                }
                if ($inquiry->packageInquireStaff->decoratorApplication) {
                    $assignedStaff[] = [
                        'role' => 'Decorator',
                        'name' => $inquiry->packageInquireStaff->decoratorApplication->applicant_name,
                    ];
                }
                if ($inquiry->packageInquireStaff->cateringApplication) {
                    $assignedStaff[] = [
                        'role' => 'Catering',
                        'name' => $inquiry->packageInquireStaff->cateringApplication->applicant_name,
                    ];
                }
            }

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
                'assigned_staff' => $assignedStaff,  // Assigned staff with their role and name
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

  
    public function assignStaffToInquiry(Request $request, $inquiry_id)
    {
        try {
            $validated = $request->validate([
                'photographer_application_id' => 'nullable|exists:job_applications,id',
                'decorator_application_id' => 'nullable|exists:job_applications,id',
                'catering_application_id' => 'nullable|exists:job_applications,id',
            ]);

            $inquiry = PackageInquiry::findOrFail($inquiry_id);

            $photographerApplication = JobApplication::where('status', 'Approved')
                ->where('id', $validated['photographer_application_id'] ?? null)
                ->first();

            $decoratorApplication = JobApplication::where('status', 'Approved')
                ->where('id', $validated['decorator_application_id'] ?? null)
                ->first();

            $cateringApplication = JobApplication::where('status', 'Approved')
                ->where('id', $validated['catering_application_id'] ?? null)
                ->first();

            $staffAssignment = PackageInquireStaff::where('package_inquiry_id', $inquiry->id)->first();

            if ($staffAssignment) {
             
                $staffAssignment->update([
                    'photographer_application_id' => $photographerApplication ? $photographerApplication->id : null,
                    'decorator_application_id' => $decoratorApplication ? $decoratorApplication->id : null,
                    'catering_application_id' => $cateringApplication ? $cateringApplication->id : null,
                ]);

                return $this->sendResponse($staffAssignment, 'Staff assignment updated successfully.');
            } else {
                
                $staffAssignment = PackageInquireStaff::create([
                    'package_inquiry_id' => $inquiry->id,
                    'photographer_application_id' => $photographerApplication ? $photographerApplication->id : null,
                    'decorator_application_id' => $decoratorApplication ? $decoratorApplication->id : null,
                    'catering_application_id' => $cateringApplication ? $cateringApplication->id : null,
                ]);

                return $this->sendResponse($staffAssignment, 'Staff assigned to event successfully.');
            }
        } catch (\Exception $e) {
            return $this->sendError('Error assigning or updating staff: ' . $e->getMessage(), [], 500);
        }
    }


   

}
