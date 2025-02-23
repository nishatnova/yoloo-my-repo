<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;
use App\Models\Template;

class TemplateController extends Controller
{
    use ResponseTrait;

    public function index()
    {
        $templates = Template::select('id', 'name', 'title', 'price')->get();

        return $this->sendResponse($templates, 'Templates retrieved successfully.');
    }

    /**
     * Update template title & price (Admin only)
     */
    public function update(Request $request, $id)
    {
        if (Auth::user()->role !== 'admin') {
            return $this->sendError('Access denied. Only admins can update templates.', [], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        $template = Template::find($id);
        if (!$template) {
            return $this->sendError('Template not found.', [], 404);
        }

        $template->update([
            'title' => $request->title,
            'price' => $request->price,
        ]);

        return $this->sendResponse($template, 'Template updated successfully.');
    }
}
