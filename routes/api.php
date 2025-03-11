<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Controllers\API\TemplateController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\PackageController;
use App\Http\Controllers\API\JobPostController;
use App\Http\Controllers\API\ContactController;
use App\Http\Controllers\API\JobApplicationController;
use App\Http\Controllers\API\TemplatePurchaseController;
use App\Http\Controllers\API\PackageBookingController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\StripeWebhookController;
use App\Http\Controllers\API\PackageInquiryController;
use App\Http\Controllers\API\CustomTemplateContentController;
use App\Http\Controllers\API\RSVPController;
use App\Http\Controllers\API\ReviewController;
use App\Http\Controllers\API\DashboardController;


    Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook']);
    Route::post('/register', [AuthController::class, 'register']);

    
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    Route::options('/refresh-token', function () {
        return response('', 200)->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Authorization, Content-Type');
    });


    
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::get('/faqs', [FaqController::class, 'index']);
    Route::get('/faqs/{id}', [FaqController::class, 'show']);

    Route::get('/events', [PackageInquiryController::class, 'getCompletedPackageInquiries']);
    Route::get('/latest-events', [PackageInquiryController::class, 'getLatestCompletedPackageInquiries']);
    Route::get('/events/{id}', [PackageInquiryController::class, 'showDetail']);
    

    Route::get('/search', [PackageController::class, 'search']);
    Route::get('/packages/search', [PackageController::class, 'searchPackagePage']);

    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/packages/{id}', [PackageController::class, 'show']);
    Route::delete('/venue-images/{package_id}/{image_id}', [PackageController::class, 'deleteVenueImage']);

    Route::get('/packages/{id}/show-review', [ReviewController::class, 'reviewShow']);
    Route::get('/package-reviews/{package_id}', [ReviewController::class, 'getPackageReviews']);
    Route::get('/reviews', [ReviewController::class, 'getAllReviews']);
    Route::get('/reviews/{review_id}', [ReviewController::class, 'getReviewDetails']);

    Route::get('/job-posts', [JobPostController::class, 'index']);
    Route::get('/job-posts/{id}', [JobPostController::class, 'show']);

    Route::post('/contact-us', [ContactController::class, 'store']);

    Route::get('/template/{order_id}/{template_id}/preview-custom-template', [CustomTemplateContentController::class, 'previewCustomTemplate']);

    Route::post('/template/{order_id}/{template_id}/rsvp', [RSVPController::class, 'submitRSVP']);

    Route::get('/template/{order_id}/rsvp-list', [RSVPController::class, 'getRSVPList']);
    Route::get('/rsvp/{rsvp_id}', [RSVPController::class, 'getRSVPDetails']);

    Route::get('/dashboard-stats', [DashboardController::class, 'getDashboardStats']);





Route::middleware([JwtAuthMiddleware::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/update-password', [AuthController::class, 'updatePassword']);
    Route::post('/job-post/apply/{id}', [JobApplicationController::class, 'apply']);

    Route::post('/template/{template_id}/payment', [TemplatePurchaseController::class, 'initiatePayment']);
    Route::post('/template/{template_id}/confirm-payment', [TemplatePurchaseController::class, 'confirmPayment']);

    // Package Payment Routes
    Route::post('/package/{package_id}/payment', [PackageBookingController::class, 'initiatePayment']);
    Route::post('/package/{package_id}/confirm-payment', [PackageBookingController::class, 'confirmPayment']);

    // Orders Routes (User can view their own orders)
    Route::get('/user/orders', [OrderController::class, 'getUserOrders']);
    Route::get('/orders', [OrderController::class, 'getAllOrders']);
    Route::get('/orders/{order_id}', [OrderController::class, 'getOrderDetails']);

    Route::post('/events/{id}/status', [PackageInquiryController::class, 'updateStatus']);
    
    Route::post('/template/{template_id}/update-custom-content', [CustomTemplateContentController::class, 'updateCustomContent']);

    
    Route::post('/add-review/{package_id}', [ReviewController::class, 'storeReview']);
    
    

    

    //  USER Routes (Only "user" role can access)
    Route::middleware(['role:user'])->group(function () {

        
    });

    // ADMIN Routes (Only "admin" role can access)
    Route::middleware(['role:admin'])->group(function () {
        Route::get('admin/profile/{id}', [AuthController::class, 'getProfile']);
        Route::post('admin/profile/{id}', [AuthController::class, 'updateProfile']);
        Route::post('admin/profile/photo/{id}', [AuthController::class, 'uploadProfilePhoto']);
        Route::delete('admin/profile/photo/{id}', [AuthController::class, 'removeProfilePhoto']);

        // Templates
        Route::post('/templates/{id}', [TemplateController::class, 'update']);

        //FAQ
        Route::post('/faq/create-faq', [FaqController::class, 'store']);
        Route::post('/faq/update-faq/{id}', [FaqController::class, 'update']);
        Route::delete('/faq/delete-faq/{id}', [FaqController::class, 'destroy']);

       
        Route::post('/packages', [PackageController::class, 'store']);
        Route::post('/packages/{id}', [PackageController::class, 'update']);
        Route::delete('/packages/{id}', [PackageController::class, 'destroy']);

        Route::post('/job-posts', [JobPostController::class, 'store']);
        Route::post('/job-posts/{id}', [JobPostController::class, 'update']);
        Route::delete('/job-posts/{id}', [JobPostController::class, 'destroy']);

        Route::get('/job-applications', [JobApplicationController::class, 'getAllApplications']);

        Route::get('/approved-applicant', [JobApplicationController::class, 'getJobApplicantsForSelection']);
        Route::get('/approved-photographer', [JobApplicationController::class, 'getApprovedPhotographer']);
        Route::get('/approved-catering', [JobApplicationController::class, 'getApprovedCatering']);
        Route::get('/approved-decorator', [JobApplicationController::class, 'getApprovedDecorator']);

        Route::post('/job-applications/status/{id}', [JobApplicationController::class, 'updateApplicationStatus']);
        Route::get('/job-applications/{id}', [JobApplicationController::class, 'show']);
        Route::post('/events/{id}/status', [PackageInquiryController::class, 'updateStatus']);

        Route::post('/reviews/{review_id}/update-status', [ReviewController::class, 'updateStatus']);

        Route::post('/package-inquiries/{inquiryId}/assign-staff', [PackageInquiryController::class, 'assignStaffToInquiry']);

        
        
    });

    //  GUEST Routes (Only "guest" role can access)
    Route::middleware(['role:guest'])->group(function () {
       
    });
});
