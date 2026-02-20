<?php

namespace App\Http\Controllers;

use App\Services\PdfExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class BookingController extends Controller
{
    private PdfExtractionService $pdfExtractionService;

    public function __construct(PdfExtractionService $pdfExtractionService)
    {
        $this->pdfExtractionService = $pdfExtractionService;
    }
 
    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'nullable|file|mimes:pdf',
            'client_name' => 'nullable|string',
        ]);

        if (! $request->hasFile('file')) {
            return response()->json([
                'status' => 'no_file_provided',
            ]);
        }

        $stored = $request->file('file')->store('uploads');
        $absolutePath = storage_path("app/{$stored}");

        try {
            $text = $this->pdfExtractionService->extractText($absolutePath);
        } catch (Throwable $exception) {
            return response()->json([
                'error' => 'PDF parsing failed',
                'message' => $exception->getMessage(),
            ], 500);
        }

        $preview = mb_substr($text, 0, 500);

        return response()->json([
            'status' => 'pdf_processed',
            'file_path' => $stored,
            'text_length' => mb_strlen($text),
            'preview' => $preview,
        ]);
    }

    public function testPdf(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf',
        ]);

        $file = $request->file('file');
        $path = $file->getRealPath();

        try {
            $text = $this->pdfExtractionService->extractText($path);
        } catch (Throwable $exception) {
            return response()->json([
                'error' => 'PDF parsing failed',
                'message' => $exception->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'text' => $text,
        ]);
    }
}
