<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use App\Models\Contact;
use App\Mail\ContactUsMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    use ResponseTrait;

    public function store(Request $request)
    {
        try {
            // Validate input
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email',
                'subject' => 'required|string|max:255',
            ]);

            // Store the contact information in the database
            $contact = Contact::create($request->all());

            Mail::to('weddingplanner951@gmail.com')->send(new ContactUsMail($contact));

            // Return a success response
            return $this->sendResponse([
                'contact' => $contact
            ], 'Your contact request has been submitted successfully!', 201);

        } catch (ValidationException $e) {
            $errors = $e->errors();
            
            // Get the first error message for each field
            $firstErrorMessages = collect($errors)->map(fn($messages) => $messages[0])->implode(', ');
            return $this->sendError($firstErrorMessages, [], 422);

        } catch (\Exception $e) {
            return $this->sendError('Error during contact submission', [], 500);
        }
    }
}
