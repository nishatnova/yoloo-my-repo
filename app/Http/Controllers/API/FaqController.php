<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\FAQ;

class FaqController extends Controller
{
    use ResponseTrait;

//FAQ list
    public function index(Request $request)
    {
        try {
            $activeStatus = $request->query('active_status'); // Optional active_status filter

            // Base query
            $query = FAQ::query();
            if ($activeStatus !== null) {
                $query->where('active_status', $activeStatus);
            }

            $faqs = $query->orderBy('created_at', 'desc')->get();


            return $this->sendResponse($faqs, 'FAQs retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving FAQs.', []);
        }
    }


    // Create FAQ
    public function store(Request $request)
    {
        try {
            $request->validate([
                'question' => 'required|string|max:255',
                'answer' => 'required',
                'active_status' => 'required',
            ]);

            // Create blog
            $faq = FAQ::create([
                'question' => $request->question,
                'answer' => $request->answer,
                'active_status' => $request->active_status ?? 1,
            ]);

            return $this->sendResponse([
              'faq' => $faq
            ], 'FAQ created successfully.');

        } catch (ValidationException $e) {
            $errors = $e->errors();

            $firstErrorMessages = collect($errors)->map(fn($messages) => $messages[0])->implode(', ');
            return $this->sendError($firstErrorMessages, []);


        } catch (\Exception $e) {
            return $this->sendError('Error during FAQ Creation'. $e->getMessage(), []);
        }
    }

    public function show($id)
    {
        try {
            $faq = FAQ::findOrFail($id);

            return $this->sendResponse([
                'id' => $faq->id,
                'question' => $faq->question,
                'answer' => $faq->answer,
                'active_status' => $faq->active_status,
            ], 'FAQ retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving FAQ: ' . $e->getMessage(), [], 500);
        }
    }



    //FAQ Update

    public function update(Request $request, $id)
    {
        try {
            $faq = FAQ::findOrFail($id);

            $request->validate([
                'question' => 'nullable|string|max:255',
                'answer' => 'nullable',

            ]);

            // Update other fields
            $faq->update($request->only(['question', 'answer', 'active_status']));


            return $this->sendResponse($faq, 'FAQ updated successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('FAQ not found.', []);
        }catch (ValidationException $e) {
            $errors = $e->errors();

            // Get the first error message for each field
            $firstErrorMessages = collect($errors)->map(fn($messages) => $messages[0])->implode(', ');
            return $this->sendError($firstErrorMessages, []);

        }catch (\Exception $e) {
            return $this->sendError('Error during FAQ update.'. $e->getMessage(), []);
        }
    }

    //FAQ Delete

    public function destroy($id)
    {
        try {
            $faq = FAQ::findOrFail($id);


            $faq->delete();

            return $this->sendResponse([], 'FAQ deleted successfully.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->sendError('FAQ not found.', []);
        } catch (\Exception $e) {
            return $this->sendError('Error deleting the FAQ.'. $e->getMessage(), []);
        }
    }
}
