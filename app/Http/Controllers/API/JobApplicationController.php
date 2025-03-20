<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobPost;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Auth;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserNotificationMail;
use App\Mail\AdminNotificationMail;
use App\Mail\ApplicationApprovedMail;

class JobApplicationController extends Controller
{
    use ResponseTrait;
    public function apply(Request $request, $job_id)
    {
        try {
        $validated = $request->validate([
            'applicant_name' => 'required|string',
            'applicant_email' => 'required|email',
            'applicant_phone' => 'required|string',
            'portfolio_link' => 'required|url',
            'portfolio_description' => 'required|string',
        ]);

        $jobPost = JobPost::findOrFail($job_id);

        $application = JobApplication::create([
            'user_id' => Auth::id(), 
            'job_post_id' => $job_id,
            'role' => $jobPost->role, 
            'applicant_name' => $validated['applicant_name'],
            'applicant_email' => $validated['applicant_email'],
            'applicant_phone' => $validated['applicant_phone'],
            'portfolio_link' => $validated['portfolio_link'],
            'portfolio_description' => $validated['portfolio_description'],
            'status' => 'Pending',
        ]);

        // Send Email to Applicant
        Mail::to($application->applicant_email)->send(new UserNotificationMail($application, $jobPost));

        // Send Email to Admin
        Mail::to('weddingplanner951@gmail.com')->send(new AdminNotificationMail($application, $jobPost));

        return $this->sendResponse([
            'application' => $application,
        ], 'Job Applied Successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Error during apply: ' . $e->getMessage(), [], 500);
        }
    }

    // Method to get all job applications for an admin
    public function getAllApplications(Request $request)
    {
        try {
        $status = $request->query('status'); 
        $role = $request->query('role');
        $limit = $request->query('limit', 10); 
        $page = $request->query('page', 1);

        $query = JobApplication::query();

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($role) {
            $query->where('role', $role);
        }

        $applications = $query->with(['user', 'jobPost'])->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

        $applications->getCollection()->transform(function ($application) {
            return [
                'application_id' => $application->id,
                'id' => $application->id,
                'Staff Name' => $application->applicant_name,
                'Job Title' => optional($application->jobPost)->job_title,
                'Status' => $application->status,
                'Role' => $application->role,
            ];
        });

        return $this->sendResponse([
            'applications' => $applications->items(),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'total' => $applications->total(),
                'per_page' => $applications->perPage(),
                'last_page' => $applications->lastPage(),
            ],
            
        ], 'All applications retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving job applications: ' . $e->getMessage(), [], 500);
        }
    }

    public function getJobApplicantsForSelection(Request $request)
    {
        try {
            $role = $request->query('role');
            $query = JobApplication::query();

            if ($role) {
                $query->where('role', $role);
            }

            $query->where('status', 'Approved');  

            $applicants = $query->get(['id', 'applicant_name', 'role']); 

            return $this->sendResponse([
                'applicants' => $applicants,
            ], 'Approved job applicants retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving approved job applicants: ' . $e->getMessage(), [], 500);
        }
    }

    public function getApprovedPhotographer(Request $request)
    {
        try {
            $photographer = JobApplication::where('role', 'Photography')->where('status', 'Approved')->get(['id', 'applicant_name', 'role']);


            return $this->sendResponse([
                'photographer' => $photographer,
            ], 'Approved job photographer retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving approved job photographer: ' . $e->getMessage(), [], 500);
        }
    }
    public function getApprovedCatering(Request $request)
    {
        try {
            $catering = JobApplication::where('role', 'Catering')->where('status', 'Approved')->get(['id', 'applicant_name', 'role']);


            return $this->sendResponse([
                'catering' => $catering,
            ], 'Approved job photographer retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving approved job photographer: ' . $e->getMessage(), [], 500);
        }
    }
    public function getApprovedDecorator(Request $request)
    {
        try {
            $decorator = JobApplication::where('role', 'Decorator')->where('status', 'Approved')->get(['id', 'applicant_name', 'role']);


            return $this->sendResponse([
                'decorator' => $decorator,
            ], 'Approved job photographer retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving approved job photographer: ' . $e->getMessage(), [], 500);
        }
    }




    public function updateApplicationStatus(Request $request, $application_id)
    {
        try {
            $request->validate([
                'status' => 'required|in:Pending,Approved,Rejected', // Ensure only valid values
            ]);

            $application = JobApplication::findOrFail($application_id);
            $application->status = $request->status;
            $application->save();

            if ($request->status === 'Approved') {
                Mail::to($application->applicant_email)->send(new ApplicationApprovedMail($application, $application->jobPost));
            }

            return $this->sendResponse([
                'application' => $application,
            ], 'Application status updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Error updating application status: ' . $e->getMessage(), [], 500);
        }
    }

    public function show($application_id)
    {
        try {
            $application = JobApplication::with('jobPost', 'user')->findOrFail($application_id);

            return $this->sendResponse([
                'applicant_name' => $application->applicant_name,
                'applicant_email' => $application->applicant_email,
                'applicant_phone' => $application->applicant_phone,
                'role' => $application->role,
                'portfolio_link' => $application->portfolio_link,
                'portfolio_description' => $application->portfolio_description,
                'status' => $application->status,
                'applied_on' => $application->created_at->format('d M, Y h:i A'),
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Application not found', [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving application: ' . $e->getMessage(), [], 500);
        }
    }

}
