<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;
use App\Models\PackageImage;
use App\Traits\ResponseTrait;

class PackageController extends Controller
{
    use ResponseTrait;
    public function index()
    {
        try {
            $packages = Package::with('images')->get()->map(function ($package) {
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
                    'cover_image' => $package->cover_image ? asset('storage/' . $package->cover_image) : null, // Return full URL for cover image
                    'venue_photos' => $package->images->map(function ($image) {
                        return asset('storage/' . $image->image_path); // Return full URL for venue photos
                    })
                ];
            });

            return $this->sendResponse($packages, 'Packages retrieved successfully.');
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
                'venue_images.*' => 'image|mimes:jpeg,png,jpg|max:2048'
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
                'cover_image' => $package->cover_image ? asset('storage/' . $package->cover_image) : null, // Return full URL
                'venue_images' => $package->images->map(fn ($image) => asset('storage/' . $image->image_path)) // Return URLs for venue images
            ], 'Package created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Error creating package: ' . $e->getMessage(), [], 500);
        }
    }
    
}
