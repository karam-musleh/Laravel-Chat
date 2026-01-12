<?php

namespace App\Http\Controllers\Api;

use App\Models\Message;
use Illuminate\Http\Request;
use App\Models\MessageAttachment;
use App\Services\ChatFileService;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ChatFileController extends Controller
{
    protected ChatFileService $fileService;

    public function __construct(ChatFileService $fileService)
    {
        $this->fileService = $fileService;
    }


    public function uploadImage(Request $request)
    {
        return $this->uploadFile($request, 'image');
    }


    public function uploadVideo(Request $request)
    {
        return $this->uploadFile($request, 'video');
    }


    public function uploadAudio(Request $request)
    {
        return $this->uploadFile($request, 'audio');
    }

    public function uploadDocument(Request $request)
    {
        return $this->uploadFile($request, 'document');
    }


    private function uploadFile(Request $request, string $fileType)
    {
        $validator = Validator::make($request->all(), [
            'file' => [
                'required',
                'file',
                'max:' . $this->fileService->getMaxFileSize($fileType),
            ],
            'message_id' => 'required|exists:messages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $message = Message::findOrFail($request->message_id);

            تحقق من الصلاحيات
            if ($message->sender_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            // رفع الملف باستخدام الـ Service
            $attachment = $this->fileService->uploadFile($file, $message, $fileType);

            return response()->json([
                'success' => true,
                'message' => ucfirst($fileType) . ' uploaded successfully',
                'data' => [
                    'attachment' => $attachment,
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // public function downloadFile($attachmentId)
    // {
    //     try {
    //         $attachment = MessageAttachment::findOrFail($attachmentId);


    //         $message = $attachment->message;
    //         if ($message->sender_id !== Auth::id() && $message->receiver_id !== Auth::id()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Unauthorized',
    //             ], 403);
    //         }


    //         if (!$this->fileService->fileExists($attachment)) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'File not found',
    //             ], 404);
    //         }


    //         return response()->download(
    //             $this->fileService->getFilePath($attachment),
    //             $attachment->file_name
    //         );
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to download file',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }


    public function deleteFile($attachmentId)
    {
        try {
            $attachment = MessageAttachment::findOrFail($attachmentId);

            تحقق من الصلاحيات
            if ($attachment->message->sender_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            $this->fileService->deleteFile($attachment);

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getMessageFiles($messageId)
    {
        try {
            $message = Message::findOrFail($messageId);


            if ($message->sender_id !== Auth::id() && $message->receiver_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }


            $attachments = $this->fileService->getMessageAttachments($message);

            return response()->json([
                'success' => true,
                'data' => [
                    'attachments' => $attachments,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get files',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
