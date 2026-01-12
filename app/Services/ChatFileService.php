<?php

namespace App\Services;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ChatFileService
{

    private const DISK_MAP = [
        'image' => 'chat_images',
        'video' => 'chat_videos',
        'audio' => 'chat_audio',
        'document' => 'chat_documents',
    ];


    private const ALLOWED_EXTENSIONS = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
        'video' => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv'],
        'audio' => ['mp3', 'wav', 'ogg', 'm4a', 'aac'],
        'document' => ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'ppt', 'pptx'],
    ];


    private const MAX_FILE_SIZES = [
        'image' => 5120,      // 5MB
        'video' => 51200,     // 50MB
        'audio' => 10240,     // 10MB
        'document' => 10240,  // 10MB
    ];


    public function uploadFile(UploadedFile $file, Message $message, string $fileType): MessageAttachment
    {
        $this->validateFileType($file, $fileType);

        $attachment = $this->storeFile($file, $message, $fileType);

        return $attachment;
    }


    public function validateFileType(UploadedFile $file, string $fileType): void
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (!in_array($extension, self::ALLOWED_EXTENSIONS[$fileType])) {
            throw new \InvalidArgumentException(
                "Invalid file type. Allowed types: " . implode(', ', self::ALLOWED_EXTENSIONS[$fileType])
            );
        }
    }


    private function storeFile(UploadedFile $file, Message $message, string $fileType): MessageAttachment
    {
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $size = $file->getSize();

        $disk = self::DISK_MAP[$fileType];

        $fileName = time() . '_' . Str::random(10) . '.' . $extension;

        $path = $file->storeAs('', $fileName, $disk);

        $attachment = MessageAttachment::create([
            'message_id' => $message->id,
            'file_path' => $path,
            'file_name' => $originalName,
            'file_type' => $fileType,
            'file_size' => $size,
        ]);

        $attachment->file_url = Storage::disk($disk)->url($path);

        return $attachment;
    }


    public function deleteFile(MessageAttachment $attachment): bool
    {
        $disk = self::DISK_MAP[$attachment->file_type];

        if (Storage::disk($disk)->exists($attachment->file_path)) {
            Storage::disk($disk)->delete($attachment->file_path);
        }

        // حذف من قاعدة البيانات
        return $attachment->delete();
    }


    public function getFilePath(MessageAttachment $attachment): string
    {
        $disk = self::DISK_MAP[$attachment->file_type];
        return Storage::disk($disk)->path($attachment->file_path);
    }

    public function getFileUrl(MessageAttachment $attachment): string
    {
        $disk = self::DISK_MAP[$attachment->file_type];
        return Storage::disk($disk)->url($attachment->file_path);
    }

    /**
     * التحقق من وجود الملف
     */
    public function fileExists(MessageAttachment $attachment): bool
    {
        $disk = self::DISK_MAP[$attachment->file_type];
        return Storage::disk($disk)->exists($attachment->file_path);
    }

    /**
     * الحصول على كل ملفات رسالة معينة مع الـ URLs
     */
    public function getMessageAttachments(Message $message)
    {
        $attachments = $message->attachments;

        // إضافة الـ URLs
        foreach ($attachments as $attachment) {
            $attachment->file_url = $this->getFileUrl($attachment);
        }

        return $attachments;
    }


    public function getMaxFileSize(string $fileType): int
    {
        return self::MAX_FILE_SIZES[$fileType] ?? 10240;
    }


    public function getAllowedExtensions(string $fileType): array
    {
        return self::ALLOWED_EXTENSIONS[$fileType] ?? [];
    }


    public function validateFileSize(UploadedFile $file, string $fileType): void
    {
        $maxSize = $this->getMaxFileSize($fileType) * 1024; // تحويل من KB إلى bytes

        if ($file->getSize() > $maxSize) {
            throw new \InvalidArgumentException(
                "File size exceeds maximum allowed size of {$this->getMaxFileSize($fileType)} KB"
            );
        }
    }
}
