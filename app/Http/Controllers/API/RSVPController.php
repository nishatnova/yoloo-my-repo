<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\ResponseTrait;
use App\Models\RSVP;
use App\Models\Order;
use App\Models\CustomTemplateContent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class RSVPController extends Controller
{
    use ResponseTrait;

    public function submitRSVP(Request $request, $order_id)
    {
        try {
            // Validate the incoming request data
            $validated = $request->validate([
                'guest_name' => 'required|string',
                'guest_email' => 'required|email',
                'guest_phone' => 'required|string',
                'bring_guests' => 'nullable|string',
                'attendance' => 'required',
            ]);

            $validated['bring_guests'] = json_decode($validated['bring_guests'], true);

            $order = Order::findOrFail($order_id); 
            $template_id = $order->template_id;

            $existingRSVP = RSVP::where('order_id', $order_id)
                                ->where('guest_email', $validated['guest_email'])
                                ->first();

            if ($existingRSVP) {
                $existingRSVP->update([
                    'guest_name' => $validated['guest_name'],
                    'guest_phone' => $validated['guest_phone'],
                    'bring_guests' => $validated['bring_guests'],
                    'attendance' => $validated['attendance'],
                ]);

                return $this->sendResponse($existingRSVP, 'RSVP updated successfully.');
            } else {
                $rsvp = RSVP::create([
                    'order_id' => $order_id,
                    'template_id' => $template_id,
                    'guest_name' => $validated['guest_name'],
                    'guest_email' => $validated['guest_email'],
                    'guest_phone' => $validated['guest_phone'],
                    'bring_guests' => $validated['bring_guests'],
                    'attendance' => $validated['attendance'],
                ]);

                return $this->sendResponse($rsvp, 'RSVP submitted successfully.');
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError('Validation error: ' . $e->getMessage(), $e->errors(), 422);
        } catch (\Exception $e) {
            return $this->sendError('Error submitting RSVP: ' . $e->getMessage(), [], 500);
        }
    }


    public function getRSVPList(Request $request, $template_id)
    {
        try {

            if (Auth::check()) {
                
                $order = Order::where('user_id', Auth::id())
                              ->where('template_id', $template_id)
                              ->first();
    
                if (!$order) {
                    return $this->sendError('You have not purchased this template or the order does not exist.', [], 403);
                }

                $customContent = CustomTemplateContent::where('order_id', $order->id)
                ->where('template_id', $template_id)
                ->pluck('rsvp_date')
                ->first();

                if (!$customContent) {
                    return $this->sendError('No custom template content found for this order and template.', [], 404);
                }

            } else {
                
                return $this->sendError('User not authenticated.', [], 401);
            }
            
            $attendance = $request->query('attendance'); 
            $limit = $request->query('limit', 10); 
            $page = $request->query('page', 1);
    
            $query = RSVP::with(['order', 'template'])->where('order_id', $order->id);

            if ($attendance !== null) {
                $query->where('attendance', $attendance);
            }
    
            $rsvps = $query->orderBy('created_at', 'desc')->paginate($limit, ['*'], 'page', $page);
    
            $rsvps->getCollection()->transform(function ($rsvp) use ($customContent) {
                return [
                    'id' => $rsvp->id,
                    'guest_name' => $rsvp->guest_name,
                    'guest_email' => $rsvp->guest_email,
                    'guest_phone' => $rsvp->guest_phone,
                    'bring_guests' => $rsvp->bring_guests,
                    'attendance' => $rsvp->attendance, 
                ];
            });
    
            return $this->sendResponse([
                'rsvps' => $rsvps->items(),
                'rsvp_date' => $customContent, 
                'meta' => [
                    'current_page' => $rsvps->currentPage(),
                    'total' => $rsvps->total(),
                    'per_page' => $rsvps->perPage(),
                    'last_page' => $rsvps->lastPage(),
                ],
                
            ], 'All RSVPS retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving RSVPS: ' . $e->getMessage(), [], 500);
        }
    }

    public function getRSVPDetails($rsvp_id)
    {
        try {
            $rsvp = RSVP::findOrFail($rsvp_id);

            return $this->sendResponse($rsvp, 'RSVP details retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving RSVP details: ' . $e->getMessage(), [], 500);
        }
    }
    
}
