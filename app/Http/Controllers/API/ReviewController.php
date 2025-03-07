<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\ResponseTrait;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ResponseTrait;

    public function storeReview(Request $request, $package_id)
    {
        try {
            $validated = $request->validate([
                'rating' => 'required|numeric|between:0,5',
                'comment' => 'required|string|max:1000', 
            ]);
    
            // Check if the package exists
            $package = Package::findOrFail($package_id);
    
            // Check if the user has purchased the package
            $order = Order::where('user_id', Auth::id())
                          ->where('package_id', $package_id)
                          ->first();
    
            if (!$order) {
                return $this->sendError('You must purchase this package before submitting a review.', [], 400);
            }
    
            // Check if the user has already reviewed this package
            $existingReview = Review::where('user_id', Auth::id())
                                    ->where('package_id', $package_id)
                                    ->first();
    
            if ($existingReview) {
                // If the review exists, update it
                $existingReview->update([
                    'rating' => $validated['rating'],
                    'comment' => $validated['comment'],
                ]);
    
                return $this->sendResponse($existingReview, 'Review updated successfully.');
            }
    
            // If no review exists, create a new one
            $review = Review::create([
                'user_id' => Auth::id(),
                'package_id' => $package_id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
            ]);
    
            return $this->sendResponse($review, 'Review submitted successfully.');
            
        } catch (ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Error submitting or updating review: ' . $e->getMessage(), [], 500);
        }
    }
    

    
}
