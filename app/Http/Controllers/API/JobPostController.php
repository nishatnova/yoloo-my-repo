<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\JobPost;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Carbon\Carbon;

class JobPostController extends Controller
{
    use ResponseTrait;
    public function index(Request $request)
    {
        try {

            $expiredJobs = JobPost::where('status', 'Active')
            ->where('application_deadline', '<', Carbon::now())
            ->update(['status' => 'Inactive']);
        
            $activeStatus = $request->query('status'); 
            $limit = $request->query('limit', 10); 
            $page = $request->query('page', 1); 
           
           

            $query = JobPost::query();

            // Apply filters
            if ($activeStatus !== null) {
                $query->where('status', $activeStatus);
            }
            // if ($title) {
            //     $query->where('title', 'like', '%' . $title . '%');
            // }
           
            // Fetch paginated results
            $jobs = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

            // Transform results to include image URLs
            $jobs->getCollection()->transform(function ($job) {
                return [
                    'id' => $job->id,
                    'Job ID' => $job->id,
                    'Location' => $job->location,
                    'Job Title' => $job->job_title,                   
                    'Budget' => $job->budget,
                    'Role' => $job->role,
                    'About Job' => $job->about_job,
                    'Responsibilities' => $job->responsibilities,
                    'Requirements' => $job->requirements,
                    'Deadline' => $job->application_deadline,
                    'Image' => $job->cover_image ? asset('storage/' . $job->cover_image) : null, 
                    'Status' => $job->status,
                ];
            });

            // $totalPackages = $query->count();

            return $this->sendResponse([
                'jobs' => $jobs->items(),
                'meta' => [
                    'current_page' => $jobs->currentPage(),
                    'total' => $jobs->total(),
                    'per_page' => $jobs->perPage(),
                    'last_page' => $jobs->lastPage(),
                ],
                
            ], 'Job Posts retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving job posts: ' . $e->getMessage(), [], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            // Validate the incoming data
            $validated = $request->validate([
                'job_title' => 'required|string',
                'role' => 'required|string',
                'about_job' => 'nullable|string',
                'responsibilities' => 'required|string', // JSON string
                'requirements' => 'required|string', // JSON string
                'budget' => 'required|numeric|min:0',
                'location' => 'nullable|string',
                'application_deadline' => 'nullable|date',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
                'status' => 'required|in:Active,Inactive',
            ]);

            // Decode JSON fields to array
            $validated['responsibilities'] = json_decode($validated['responsibilities'], true);
            $validated['requirements'] = json_decode($validated['requirements'], true);

            // Store cover image
            $coverImagePath = $request->hasFile('cover_image') ? 
                $request->file('cover_image')->store('packages', 'public') : null;

            // Create the package
            $job = JobPost::create(array_merge($validated, ['cover_image' => $coverImagePath]));


            // Return the created package with all details
            return $this->sendResponse([
                'id' => $job->id,
                'job_title' => $job->job_title,
                'role' => $job->role,
                'about_job' => $job->about_job,
                'responsibilities' => $job->responsibilities, // Full estate details array
                'requirements' => $job->requirements,
                'budget' => $job->budget,
                'location' => $job->location,
                'application_deadline' => $job->application_deadline,
                'cover_image' => $job->cover_image ? asset('storage/' . $job->cover_image) : null,
                'status' => $job->status,
            ], 'Job Post created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Error creating package: ' . $e->getMessage(), [], 500);
        }
    }

    public function show($id)
    {
        try {
            $job = JobPost::findOrFail($id);

            return $this->sendResponse([
                'id' => $job->id,
                'job_title' => $job->job_title,
                'role' => $job->role,
                'about_job' => $job->about_job,
                'responsibilities' => $job->responsibilities, // Full estate details array
                'requirements' => $job->requirements,
                'budget' => $job->budget,
                'location' => $job->location,
                'application_deadline' => $job->application_deadline,
                'cover_image' => $job->cover_image ? asset('storage/' . $job->cover_image) : null, 
                'status' => $job->status,
            ], 'Job retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving package: ' . $e->getMessage(), [], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return $this->sendError('Access denied. Only admins can update packages.', [], 403);
            }

            $validated = $request->validate([
                'job_title' => 'required|string',
                'role' => 'required|string',
                'about_job' => 'nullable|string',
                'responsibilities' => 'nullable|string', 
                'requirements' => 'nullable|string', 
                'budget' => 'required|numeric|min:0',
                'location' => 'nullable|string',
                'application_deadline' => 'nullable|date',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:max:10240',
                'status' => 'required|in:Active,Inactive',
            ]);

            // Decode JSON fields to array if provided
            if (isset($validated['responsibilities'])) {
                $validated['responsibilities'] = json_decode($validated['responsibilities'], true);
            }
            if (isset($validated['requirements'])) {
                $validated['requirements'] = json_decode($validated['requirements'], true);
            }

            // Find the package
            $job = JobPost::findOrFail($id);

            // **Fix Cover Image Issue**
            if ($request->hasFile('cover_image')) {
                // Store new image and update path correctly
                $coverImagePath = $request->file('cover_image')->store('packages', 'public');
                $validated['cover_image'] = $coverImagePath; // Add to validated array
            }

            // Update package
            $job->update($validated);

            return $this->sendResponse([
                'id' => $job->id,
                'job_title' => $job->job_title,
                'role' => $job->role,
                'about_job' => $job->about_job,
                'responsibilities' => $job->responsibilities, // Full estate details array
                'requirements' => $job->requirements,
                'budget' => $job->budget,
                'location' => $job->location,
                'application_deadline' => $job->application_deadline,
                'cover_image' => $job->cover_image ? asset('storage/' . $job->cover_image) : null,
                'status' => $job->status,
            ], 'Job Post updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Error updating package: ' . $e->getMessage(), [], 500);
        }
    }

    public function destroy($id)
    {
        try {
            // Check if the job post exists
            $job = JobPost::findOrFail($id);

            // Delete the cover image from storage if it exists
            if ($job->cover_image && Storage::exists('public/' . $job->cover_image)) {
                Storage::delete('public/' . $job->cover_image);
            }

            // Delete the job post
            $job->delete();

            return $this->sendResponse([], 'Job Post deleted successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->sendError('Job Post not found.', [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Error deleting job post: ' . $e->getMessage(), [], 500);
        }
    }


    
}
