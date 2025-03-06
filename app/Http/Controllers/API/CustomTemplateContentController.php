<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\Order;
use App\Models\CustomTemplateContent;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CustomTemplateContentController extends Controller
{
    use ResponseTrait;

    public function updateCustomContent(Request $request, $template_id)
    {
        try {
            $validated = $request->validate([
                'welcome_message' => 'required|string',
                'description' => 'nullable|string',
                'rsvp_date' => 'required|date',
                'personal_name' => 'required|string',
                'partner_name' => 'required|string',
                'venue_name' => 'required|string',
                'venue_address' => 'required|string',
                'wedding_date' => 'required|date',
                'wedding_time' => 'required',
                'city' => 'required|string',
            ]);

            $order = Order::where('user_id', Auth::user()->id)
                        ->where('template_id', $template_id)
                        ->firstOrFail();

            $customContent = CustomTemplateContent::where('order_id', $order->id)
                                                ->where('template_id', $template_id)
                                                ->first();

            if (!$customContent) {
                $customContent = CustomTemplateContent::create([
                    'order_id' => $order->id,
                    'template_id' => $template_id,
                    'welcome_message' => $validated['welcome_message'],
                    'description' => $validated['description'],
                    'rsvp_date' => $validated['rsvp_date'],
                    'personal_name' => $validated['personal_name'],
                    'partner_name' => $validated['partner_name'],
                    'venue_name' => $validated['venue_name'],
                    'venue_address' => $validated['venue_address'],
                    'wedding_date' => $validated['wedding_date'],
                    'wedding_time' => $validated['wedding_time'],
                    'city' => $validated['city'],
                ]);
            } else {
                $customContent->update([
                    'welcome_message' => $validated['welcome_message'],
                    'description' => $validated['description'],
                    'rsvp_date' => $validated['rsvp_date'],
                    'personal_name' => $validated['personal_name'],
                    'partner_name' => $validated['partner_name'],
                    'venue_name' => $validated['venue_name'],
                    'venue_address' => $validated['venue_address'],
                    'wedding_date' => $validated['wedding_date'],
                    'wedding_time' => $validated['wedding_time'],
                    'city' => $validated['city'],
                ]);
            }

            return $this->sendResponse($customContent, 'Custom template content updated successfully.');

        } catch (ValidationException $e) {
            $errors = $e->errors();

            // Get the first error message for each field
            $firstErrorMessages = collect($errors)->map(fn($messages) => $messages[0])->implode(', ');
            return $this->sendError($firstErrorMessages, []);

        } catch (\Exception $e) {
            return $this->sendError('Error saving or updating custom template content: ' . $e->getMessage(), [], 500);
        }
    }

    public function previewCustomTemplate(Request $request, $template_id)
    {
        try {
            // Fetch the user's order for the given template
            $order = Order::where('user_id', Auth::id())
                        ->where('template_id', $template_id)
                        ->firstOrFail();

            // Retrieve the custom template content
            $customContent = CustomTemplateContent::where('order_id', $order->id)
                                                ->where('template_id', $template_id)
                                                ->first();

            if (!$customContent) {
                return $this->sendError('Custom content not found. Please update the content first.', [], 404);
            }

            // Ensure that the date fields are formatted correctly
            $previewData = [
                'welcome_message' => $customContent->welcome_message,
                'description' => $customContent->description,
                'rsvp_date' => Carbon::parse($customContent->rsvp_date)->format('Y-m-d H:i:s'),
                'personal_name' => $customContent->personal_name,
                'partner_name' => $customContent->partner_name,
                'venue_name' => $customContent->venue_name,
                'venue_address' => $customContent->venue_address,
                'wedding_date' => Carbon::parse($customContent->wedding_date)->format('Y-m-d'),
                'wedding_time' => $customContent->wedding_time,
                'city' => $customContent->city,
            ];

            // Return the preview response
            return $this->sendResponse($previewData, 'Template preview retrieved successfully.');

        } catch (\Exception $e) {
            return $this->sendError('Error fetching template preview: ' . $e->getMessage(), [], 500);
        }
    }


}
