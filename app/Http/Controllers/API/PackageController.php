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
            $limit = $request->query('limit', 10); // Default limit is 10
            $page = $request->query('page', 1); // Default page is 1
            // $title = $request->query('title'); 
            // $location = $request->query('location'); 
            // $priceMin = $request->query('price_min'); 
            // $priceMax = $request->query('price_max'); 
           

            $query = Package::with('images');

            // Apply filters
            if ($activeStatus !== null) {
                $query->where('active_status', $activeStatus);
            }
            // if ($title) {
            //     $query->where('title', 'like', '%' . $title . '%');
            // }
            // if ($location) {
            //     $query->where('location', 'like', '%' . $location . '%');
            // }
            // if ($priceMin && $priceMax) {
            //     $query->whereBetween('price', [$priceMin, $priceMax]);
            // }

            // Fetch paginated results
            $packages = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

            // Transform results to include image URLs
            $packages->getCollection()->transform(function ($package) {
                return [
                    'id' => $package->id,
                    'title' => $package->title,
                    'location' => $package->location,
                    'about' => $package->about,
                    'estate_details' => $package->estate_details,
                    'included_services' => $package->included_services,
                    'price' => $package->price,
                    'address' => $package->address,
                    'email' => $package->email,
                    'phone' => $package->phone,
                    'cover_image' => $package->cover_image ? asset('storage/' . $package->cover_image) : null, 
                    'venue_photos' => $package->images->map(fn ($image) => asset('storage/' . $image->image_path)),
                    'active_status' => $package->active_status,
                ];
            });

            $totalPackages = $query->count();

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
                'title' => 'required|string',
                'location' => 'required|string',
                'about' => 'nullable|string',
                'estate_details' => 'required|string', // JSON string
                'included_services' => 'required|string', // JSON string
                'price' => 'required|numeric|min:0',
                'address' => 'nullable|string',
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'venue_images' => 'nullable|array',
                'venue_images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
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
                'title' => $package->title,
                'location' => $package->location,
                'about' => $package->about,
                'estate_details' => $package->estate_details, // Full estate details array
                'included_services' => $package->included_services,
                'price' => $package->price,
                'address' => $package->address,
                'email' => $package->email,
                'phone' => $package->phone,
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
                'title' => $package->title,
                'location' => $package->location,
                'about' => $package->about,
                'estate_details' => $package->estate_details,
                'included_services' => $package->included_services,
                'price' => $package->price,
                'address' => $package->address,
                'email' => $package->email,
                'phone' => $package->phone,
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
                'title' => 'required|string',
                'location' => 'required|string',
                'about' => 'nullable|string',
                'estate_details' => 'nullable|string', // Optional JSON string
                'included_services' => 'nullable|string', // Optional JSON string
                'price' => 'required|numeric|min:0',
                'address' => 'nullable|string',
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
                'cover_image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'venue_images' => 'nullable|array',
                'venue_images.*' => 'image|mimes:jpeg,png,jpg|max:2048',
                'active_status' => 'required',
            ]);

            // Decode JSON fields to array if provided
            if (isset($validated['estate_details'])) {
                $validated['estate_details'] = json_decode($validated['estate_details'], true);
            }
            if (isset($validated['included_services'])) {
                $validated['included_services'] = json_decode($validated['included_services'], true);
            }

            // Find the package
            $package = Package::findOrFail($id);

            // Update cover image if provided
            if ($request->hasFile('cover_image')) {
                $coverImagePath = $request->file('cover_image')->store('packages', 'public');
                $package->cover_image = $coverImagePath;
            }

            // Update venue photos if provided
            if ($request->hasFile('venue_images')) {
                foreach ($request->file('venue_images') as $photo) {
                    $photoPath = $photo->store('package_photos', 'public');
                    $package->images()->create(['image_path' => $photoPath]);
                }
            }

            $package->update($validated);

            return $this->sendResponse([
                'id' => $package->id,
                'title' => $package->title,
                'location' => $package->location,
                'about' => $package->about,
                'estate_details' => $package->estate_details,
                'included_services' => $package->included_services,
                'price' => $package->price,
                'address' => $package->address,
                'email' => $package->email,
                'phone' => $package->phone,
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
    
}
