<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JobPost;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Auth;
use App\Traits\ResponseTrait;

class JobApplicationController extends Controller
{
    use ResponseTrait;
    public function apply(Request $request, $job_id)
    {
        try {
        $validated = $request->validate([
            'user_name' => 'required|string',
            'user_email' => 'required|email',
            'user_phone' => 'required|string',
            'portfolio_link' => 'nullable|url',
            'portfolio_description' => 'nullable|string|max:250',
        ]);

        $jobPost = JobPost::findOrFail($job_id);

        $application = JobApplication::create([
            'user_id' => Auth::id(), 
            'job_post_id' => $job_id,
            'role' => $jobPost->role, 
            'user_name' => $validated['user_name'],
            'user_email' => $validated['user_email'],
            'user_phone' => $validated['user_phone'],
            'portfolio_link' => $validated['portfolio_link'],
            'portfolio_description' => $validated['portfolio_description'],
            'status' => 0, 
        ]);

        return $this->sendResponse([
            'application' => $application,
        ], 'Job Post created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Error creating package: ' . $e->getMessage(), [], 500);
        }
    }

    // Method to get all job applications for an admin
    public function getAllApplications(Request $request)
    {
        try {
        $status = $request->query('status'); 
        $limit = $request->query('limit', 10); 
        $page = $request->query('page', 1);

        $query = JobApplication::query();

        if ($status !== null) {
            $query->where('status', $status);
        }

        $applications = $query->with(['user', 'jobPost'])->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

        $applications->getCollection()->transform(function ($application) {
            return [
                'Application ID' => $application->id,
                'Staff Name' => $application->user_name,
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
            return $this->sendError('Error retrieving job posts: ' . $e->getMessage(), [], 500);
        }
    }
}
