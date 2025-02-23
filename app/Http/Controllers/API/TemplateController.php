<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;
use App\Models\Template;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TemplateController extends Controller
{
    use ResponseTrait;

    public function index()
    {
        try {
            $templates = Template::select('id', 'name', 'title', 'price')->get();

            if ($templates->isEmpty()) {
                return $this->sendError('No templates found.', [], 404);
            }

            return $this->sendResponse($templates, 'Templates retrieved successfully.');
        } catch (Exception $e) {
            return $this->sendError('Failed to fetch templates. Please try again.' .$e->getMessage(), [], 500);
        }
    }

    /**
     * Update template title & price (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            if (Auth::user()->role !== 'admin') {
                return $this->sendError('Access denied. Only admins can update templates.', [], 403);
            }

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'price' => 'required|numeric|min:0',
            ]);

            // Find template, throw error if not found
            $template = Template::findOrFail($id);

            $template->update($validated);

            return $this->sendResponse([
                'id' => $template->id,
                'name' => $template->name,
                'title' => $template->title,
                'price' => $template->price
            ], 'Template updated successfully.');
        } catch (ModelNotFoundException $e) {
            return $this->sendError('Template not found.', [], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->sendError($e->validator->errors()->first(), [], 422);
        } catch (Exception $e) {
            return $this->sendError('An unexpected error occurred. Please try again.' .$e->getMessage(), [], 500);
        }
    }
}
