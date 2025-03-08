<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\PackageImage;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PackageController extends Controller
{
    use ResponseTrait;
    public function index(Request $request)
    {
        try {
            $activeStatus = $request->query('active_status'); 
            $limit = $request->query('limit', 10); 
            $page = $request->query('page', 1);  

            $query = Package::with('images');

            // Apply filters
            if ($activeStatus !== null) {
                $query->where('active_status', $activeStatus);
            }
            
            $packages = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

            // Transform results to include image URLs
            $packages->getCollection()->transform(function ($package) {
                return [
                    'id' => $package->id,
                    'location' => $package->location,
                    'service_title' => $package->service_title,
                    'about' => $package->about,
                    'estate_details' => $package->estate_details,
                    'included_services' => $package->included_services,
                    'price' => $package->price,
                    'address' => $package->address,
                    'email' => $package->email,
                    'phone' => $package->phone,
                    'capacity' => $package->capacity,
                    'cover_image' => $package->cover_image ? asset('storage/' . $package->cover_image) : null, 
                    'venue_photos' => $package->images->map(function ($image) {
                    return [
                        'image_id' => $image->id, // Add the image ID
                        'image_url' => asset('storage/' . $image->image_path), // Add the image URL
                    ];
                }),
                    'active_status' => $package->active_status,
                ];
            });

            // $totalPackages = $query->count();

            return $this->sendResponse([
                'packages' => $packages->items(),
                'meta' => [
                    'current_page' => $packages->currentPage(),
                    'total' => $packages->total(),
                    'per_page' => $packages->perPage(),
                    'last_page' => $packages->lastPage(),
                ],
                
            ], 'Packages retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving packages: ' . $e->getMessage(), [], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            // Validate the incoming data
            $validated = $request->validate([
                'service_title' => 'required|string',
                'location' => 'required|string',
                'about' => 'nullable|string',
                'estate_details' => 'required|string', // JSON string
                'included_services' => 'required|string', // JSON string
                'price' => 'required|numeric|min:0',
                'address' => 'nullable|string',
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
                'capacity' => 'nullable|integer',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
                'venue_images' => 'nullable|array',
                'venue_images.*' => 'image|mimes:jpeg,png,jpg|max:10240',
                'active_status' => 'required',
            ]);

            // Decode JSON fields to array
            $validated['estate_details'] = json_decode($validated['estate_details'], true);
            $validated['included_services'] = json_decode($validated['included_services'], true);

            // Store cover image
            $coverImagePath = $request->hasFile('cover_image') ? 
                $request->file('cover_image')->store('packages', 'public') : null;

            // Create the package
            $package = Package::create(array_merge($validated, ['cover_image' => $coverImagePath]));

            // Store venue photos if uploaded
            if ($request->hasFile('venue_images')) {
                foreach ($request->file('venue_images') as $photo) {
                    $photoPath = $photo->store('package_photos', 'public');
                    $package->images()->create(['image_path' => $photoPath]);
                }
            }

            // Return the created package with all details
            return $this->sendResponse([
                'id' => $package->id,
                'service_title' => $package->service_title,
                'location' => $package->location,
                'about' => $package->about,
                'estate_details' => $package->estate_details, // Full estate details array
                'included_services' => $package->included_services,
                'price' => $package->price,
                'address' => $package->address,
                'email' => $package->email,
                'phone' => $package->phone,
                'capacity' => $package->capacity,
                'cover_image' => $package->cover_image ? asset('storage/' . $package->cover_image) : null,
                'venue_images' => $package->images->map(fn ($image) => asset('storage/' . $image->image_path)) ,
                'active_status' => $package->active_status,
            ], 'Package created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Error creating package: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Show a specific package
     */
    public function show($id)
    {
        try {
            $package = Package::with('images')->findOrFail($id);

            return $this->sendResponse([
                'id' => $package->id,
                'service_title' => $package->service_title,
                'location' => $package->location,
                'about' => $package->about,
                'estate_details' => $package->estate_details,
                'included_services' => $package->included_services,
                'price' => $package->price,
                'address' => $package->address,
                'email' => $package->email,
                'phone' => $package->phone,
                'capacity' => $package->capacity,
                'cover_image' => $package->cover_image ? asset('storage/' . $package->cover_image) : null, 
                'venue_images' => $package->images->map(fn ($image) => asset('storage/' . $image->image_path)),
                'active_status' => $package->active_status,
            ], 'Package retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving package: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Update a specific package (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return $this->sendError('Access denied. Only admins can update packages.', [], 403);
            }

            $validated = $request->validate([
                'service_title' => 'required|string',
                'location' => 'required|string',
                'about' => 'nullable|string',
                'estate_details' => 'nullable|string', // Optional JSON string
                'included_services' => 'nullable|string', // Optional JSON string
                'price' => 'required|numeric|min:0',
                'address' => 'nullable|string',
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
                'capacity' => 'nullable|integer',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:10240',
                'venue_images' => 'nullable|array',
                'venue_images.*' => 'image|mimes:jpeg,png,jpg|max:10240',
                'active_status' => 'required',
            ]);

            // Decode JSON fields to array if provided
            if (isset($validated['estate_details'])) {
                $validated['estate_details'] = json_decode($validated['estate_details'], true);
            }
            if (isset($validated['included_services'])) {
                $validated['included_services'] = json_decode($validated['included_services'], true);
            }

            $package = Package::findOrFail($id);

            if ($request->hasFile('cover_image')) {
                
                $coverImagePath = $request->file('cover_image')->store('packages', 'public');
                $validated['cover_image'] = $coverImagePath;
            }

            $package->update($validated);

            // Store venue photos if provided
            if ($request->hasFile('venue_images')) {
                foreach ($request->file('venue_images') as $photo) {
                    $photoPath = $photo->store('package_photos', 'public');
                    $package->images()->create(['image_path' => $photoPath]);
                }
            }

            return $this->sendResponse([
                'id' => $package->id,
                'service_title' => $package->service_title,
                'location' => $package->location,
                'about' => $package->about,
                'estate_details' => $package->estate_details,
                'included_services' => $package->included_services,
                'price' => $package->price,
                'address' => $package->address,
                'email' => $package->email,
                'phone' => $package->phone,
                'capacity' => $package->capacity,
                'cover_image' => $package->cover_image ? asset('storage/' . $package->cover_image) : null, 
                'venue_images' => $package->images->map(fn ($image) => asset('storage/' . $image->image_path)),
                'active_status' => $package->active_status,
            ], 'Package updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Error updating package: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Delete a specific package (Admin only)
     */
    public function destroy($id)
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return $this->sendError('Access denied. Only admins can delete packages.', [], 403);
            }

            // Find the package
            $package = Package::findOrFail($id);

            // Delete associated images
            $package->images()->each(function ($image) {
                if (Storage::exists('public/' . $image->image_path)) {
                    Storage::delete('public/' . $image->image_path);
                }
            });

            // Delete the package itself
            $package->delete();

            return $this->sendResponse([], 'Package deleted successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->sendError('Package not found.', [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Error deleting package: ' . $e->getMessage(), [], 500);
        }
    }

    public function search(Request $request)
    {
        try {
            $query = $request->query('package'); // Get the search query

            if (!$query) {
                return $this->sendError('No search query provided.', [], 400);
            }

            // Search packages by title or location
            $packages = Package::where('service_title', 'LIKE', "%$query%")
                ->orWhere('location', 'LIKE', "%$query%")
                ->get();

            // If no packages found, return an appropriate response
            if ($packages->isEmpty()) {
                return $this->sendResponse([], 'No packages found matching your search.');
            }

            // Format the response data
            $formattedPackages = $packages->map(function ($package) {
                return [
                    'id' => $package->id,
                    'service_title' => $package->service_title,
                    'location' => $package->location,
                ];
            });

            return $this->sendResponse($formattedPackages, 'Search results retrieved successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->sendError('Database error occurred. Please try again later.', [], 500);
        } catch (\Exception $e) {
            return $this->sendError('An unexpected error occurred: ' . $e->getMessage(), [], 500);
        }
    }


    public function searchPackagePage(Request $request)
    {
        try {
            $venue = $request->query('venue'); // Get venue search input (title)
            $location = $request->query('location'); // Get location search input

            if (!$venue && !$location) {
                return $this->sendError('Please provide a search query for venue, location, or both.', [], 400);
            }

            $query = Package::query();

            // If both venue & location are provided
            if ($venue && $location) {
                $query->where('service_title', 'LIKE', "%$venue%")
                    ->where('location', 'LIKE', "%$location%");
            } else {
                // If only venue is provided
                if ($venue) {
                    $query->where('service_title', 'LIKE', "%$venue%");
                }

                // If only location is provided
                else {
                    $query->where('location', 'LIKE', "%$location%");
                }
            }

            // Get results with a limit of 10 (optional)
            $packages = $query->get();

            if ($packages->isEmpty()) {
                return $this->sendResponse([], 'No matching packages found.');
            }

            $formattedPackages = $packages->map(function ($package) {
                return [
                    'id' => $package->id,
                    'service_title' => $package->service_title,
                    'location' => $package->location,
                    'included_services' => $package->included_services,
                    'cover_image' => $package->cover_image ? asset('storage/' . $package->cover_image) : null,
                ];
            });

            return $this->sendResponse($formattedPackages, 'Search results retrieved successfully.');
        } catch (\Illuminate\Database\QueryException $e) {
            return $this->sendError('Database error occurred. Please try again later.', [], 500);
        } catch (\Exception $e) {
            return $this->sendError('An unexpected error occurred: ' . $e->getMessage(), [], 500);
        }
    }



    public function deleteVenueImage(Request $request, $package_id, $image_id)
    {
        try {
            // Find the package
            $package = Package::findOrFail($package_id);

            // Find the image to delete
            $image = PackageImage::findOrFail($image_id);

            // Check if the image belongs to the package
            if ($image->package_id !== $package->id) {
                return $this->sendError('Image not associated with this package.', [], 400);
            }

            // Delete image file from storage
            Storage::delete('public/' . $image->image_path);

            // Delete the image record from the database
            $image->delete();

            return $this->sendResponse([], 'Venue image deleted successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting venue image: ' . $e->getMessage(), [], 500);
        }
    }
    
}
