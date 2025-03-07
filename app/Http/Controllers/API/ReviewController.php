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
                'comment' => 'required|string',
                'status' => 'required', 
                'home_status' => 'nullable', 
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
                'order_id' => $order->id
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
                'status' => $validated['status'],
                'home_status' => $validated['home_status'],
            ]);
    
            return $this->sendResponse($review, 'Review submitted successfully.');
            
        } catch (ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (Exception $e) {
            return $this->sendError('Error submitting or updating review: ' . $e->getMessage(), [], 500);
        }
    }

    public function getAllReviews(Request $request)
    {
        try {
            $status = $request->query('status');
            $home_status = $request->query('home_status'); 
            $limit = $request->query('limit', 10);
            $page = $request->query('page', 1);

            $query = Review::with(['user', 'package', 'order'])->orderBy('created_at', 'desc');

            if ($status !== null) {
                $query->where('status', $status);
            }

            if ($home_status !== null) {
                $query->where('home_status', $home_status);
            }

            $reviews = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);

            $reviews->getCollection()->transform(function ($review) {
                return [
                    'id' => $review->id,
                    'user_name' => $review->user->name,
                    'package_name' => $review->package->service_title,
                    'rating' => $review->rating,
                    'status' => $review->status,
                    'home_status' => $review->home_status,
                    'created_at' => $review->created_at->format('d M, Y h:i A'),
                ];
            });

            return $this->sendResponse([
                'reviews' => $reviews->items(),
                'meta' => [
                    'current_page' => $reviews->currentPage(),
                    'total' => $reviews->total(),
                    'per_page' => $reviews->perPage(),
                    'last_page' => $reviews->lastPage(),
                ],
            ], 'All reviews retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving reviews: ' . $e->getMessage(), [], 500);
        }
    }

    public function getPackageReviews($package_id)
    {
        try {
            $reviews = Review::with(['user', 'package', 'order'])
                            ->where('package_id', $package_id)
                            ->where('status', 1)
                            ->orderBy('rating', 'desc') 
                            ->take(3) // Limit to the top 3 highest rated reviews
                            ->get(); 
    
            $reviews->transform(function ($review) {
                return [
                    'id' => $review->id,
                    'user_name' => $review->user->name,
                    'package_name' => $review->package->service_title,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'status' => $review->status,
                    'home_status' => $review->home_status,
                    'created_at' => $review->created_at->format('d M, Y h:i A'),
                ];
            });
    
            return $this->sendResponse([
                'reviews' => $reviews,
            ], 'Top 3 highest rated active reviews for the package retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving top 3 highest rated active reviews: ' . $e->getMessage(), [], 500);
        }
    }


    public function getReviewDetails($review_id)
    {
        try {
            $review = Review::with(['user', 'package', 'order'])
                            ->findOrFail($review_id);

            return $this->sendResponse([
                'id' => $review->id,
                'user_name' => $review->user->name,
                'package_name' => $review->package->service_title,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'status' => $review->status,
                'home_status' => $review->home_status,
                'created_at' => $review->created_at->format('d M, Y h:i A'),
                'updated_at' => $review->updated_at->format('d M, Y h:i A'),
            ], 'Review details retrieved successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('Review not found.', [], 404);
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving review details: ' . $e->getMessage(), [], 500);
        }
    }

    




    

    
}
