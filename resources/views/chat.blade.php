<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat with {{ $receiver->name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite(['resources/js/app.js'])
    <style>
        .preview-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .preview-content {
            background: white;
            padding: 20px;
            border-radius: 10px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
        }

        .preview-image {
            max-width: 100%;
            max-height: 500px;
            object-fit: contain;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <h1>Chat with {{ $receiver->name }}</h1>
        <div id="chat-box" class="border p-3" style="height: 400px; overflow-y: scroll;">
            @foreach ($messages as $message)
                <div class="mb-2 {{ $message->sender_id == auth()->id() ? 'text-start' : 'text-end' }}">
                    <div class="badge {{ $message->sender_id == auth()->id() ? 'bg-primary' : 'bg-secondary' }}">
                        @if ($message->message)
                            {{ $message->message }}
                        @endif

                        @foreach ($message->attachments as $attachment)
                            @if ($attachment->file_type === 'image')
                                <img src="{{ asset('storage/chat/images/' . $attachment->file_path) }}"
                                    class="attachment-preview d-block mt-2" alt="image">
                            @elseif($attachment->file_type === 'audio')
                                <audio controls class="audio-player mt-2">
                                    <source src="{{ asset('storage/chat/audio/' . $attachment->file_path) }}"
                                        type="{{ $attachment->mime_type }}">
                                </audio>
                            @elseif($attachment->file_type === 'video')
                                <video controls class="attachment-preview d-block mt-2">
                                    <source src="{{ asset('storage/chat/videos/' . $attachment->file_path) }}"
                                        type="{{ $attachment->mime_type }}">
                                </video>
                            @elseif($attachment->file_type === 'document')
                                <a href="{{ asset('storage/chat/documents/' . $attachment->file_path) }}"
                                    class="text-white d-block mt-2" download>
                                    📄 {{ $attachment->file_name }}
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        <div id="typing-indicator" class="mt-2 text-muted" style="display: none;">{{ $receiver->name }} is typing...
        </div>
        <form id="message-form" class="mt-3">
            @csrf
            <div class="input-group">
                <button type="button" id="attach-btn" class="btn btn-outline-secondary">📎</button>
                <input type="text" id="message-input" class="form-control" placeholder="Type a message">
                <button type="submit" class="btn btn-primary">Send</button>
                <button type="button" id="voice-btn" class="btn btn-outline-success">🎤</button>
            </div>

            <!-- Attachment menu -->
            <div id="attach-menu" class="card p-2 shadow position-absolute"
                style="display:none; z-index: 1000; margin-top: 5px;">
                <button type="button" class="btn btn-light mb-1" data-type="image">🖼 Image</button>
                <button type="button" class="btn btn-light mb-1" data-type="video">🎥 Video</button>
                <button type="button" class="btn btn-light mb-1" data-type="audio">🎵 Audio File</button>
                <button type="button" class="btn btn-light" data-type="document">📄 Document</button>
            </div>

            <!-- Hidden file input -->
            <input type="file" id="file-input" hidden>

            <!-- Voice recording indicator -->
            <div id="voice-recording-container" style="display:none;" class="mt-2">
                <div class="alert alert-info d-flex align-items-center justify-content-between">
                    <span class="voice-recording">🔴 Recording... <span id="recording-time">0:00</span></span>
                    <div>
                        <button type="button" id="voice-send-btn" class="btn btn-success btn-sm">✓ Send</button>
                        <button type="button" id="voice-cancel-btn" class="btn btn-danger btn-sm">✗ Cancel</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Preview Modal -->
    <div id="preview-modal" class="preview-container" style="display: none;">
        <div class="preview-content">
            <h5 class="mb-3">Preview and Send</h5>
            <div id="preview-area" class="mb-3 text-center"></div>
            <div class="d-flex justify-content-end gap-2">
                <button type="button" id="cancel-preview-btn" class="btn btn-secondary">Cancel</button>
                <button type="button" id="send-preview-btn" class="btn btn-primary">Send</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            let receiverId = {{ $receiver->id }};
            let senderId = {{ auth()->id() }};
            let chatBox = document.getElementById('chat-box');
            let messageForm = document.getElementById('message-form');
            let messageInput = document.getElementById('message-input');
            let typingIndicator = document.getElementById('typing-indicator');

            // Preview modal elements
            let previewModal = document.getElementById('preview-modal');
            let previewArea = document.getElementById('preview-area');
            let sendPreviewBtn = document.getElementById('send-preview-btn');
            let cancelPreviewBtn = document.getElementById('cancel-preview-btn');
            let selectedFile = null;
            let selectedFileType = null;

            // Set user online
            fetch('/online', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ||
                        '{{ csrf_token() }}'
                }
            });

            // subscribe to chat channel
            window.Echo.private('chat.' + senderId)
                .listen('MessageSent', (e) => {
                    // show the message
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'mb-2 text-end';
                    messageDiv.innerHTML = `<span class="badge bg-secondary">${e.message.message}</span>`;
                    chatBox.appendChild(messageDiv);
                    chatBox.scrollTop = chatBox.scrollHeight;
                });

            // subscribe to typing channel
            window.Echo.private('typing.' + receiverId)
                .listen('UserTyping', (e) => {
                    if (e.typerId === receiverId) {
                        typingIndicator.style.display = 'block';
                        setTimeout(() => typingIndicator.style.display = 'none', 3000);
                    }
                });

            messageForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const message = messageInput.value;
                if (message) {
                    fetch(`/chat/${receiverId}/send`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            message
                        })
                    });
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'mb-2 text-start';
                    messageDiv.innerHTML = `<span class="badge bg-primary">${message}</span>`;
                    chatBox.appendChild(messageDiv);
                    chatBox.scrollTop = chatBox.scrollHeight;
                    messageInput.value = '';
                }
            });

            let typingTimeOut;
            messageInput.addEventListener('input', function() {
                clearTimeout(typingTimeOut);
                fetch(`/chat/typing`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                typingTimeOut = setTimeout(() => {
                    typingIndicator.style.display = 'none'
                }, 3000);
            });

            // Attachment button/menu handling
            let attachBtn = document.getElementById('attach-btn');
            let attachMenu = document.getElementById('attach-menu');
            let fileInput = document.getElementById('file-input');

            // Toggle menu
            attachBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                if (attachMenu.style.display === 'block') {
                    attachMenu.style.display = 'none';
                    return;
                }
                const rect = attachBtn.getBoundingClientRect();
                attachMenu.style.display = 'block';
                attachMenu.style.left = (rect.left + window.scrollX) + 'px';
                attachMenu.style.top = (rect.bottom + window.scrollY) + 'px';
            });

            // Hide menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!attachMenu.contains(e.target) && e.target !== attachBtn) {
                    attachMenu.style.display = 'none';
                }
            });

            // Select file type and open file picker
            attachMenu.addEventListener('click', function(e) {
                const btn = e.target.closest('button[data-type]');
                if (!btn) return;
                const type = btn.dataset.type;
                fileInput.accept = (type === 'image') ? 'image/*' : (type === 'video') ? 'video/*' : (
                    type === 'audio') ? 'audio/*' : '*/*';
                fileInput.dataset.type = type;
                attachMenu.style.display = 'none';
                fileInput.click();
            });

            // STEP 1: Handle file selection - Show preview instead of immediate upload
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length === 0) return;

                selectedFile = fileInput.files[0];
                selectedFileType = fileInput.dataset.type;

                // Clear previous preview
                previewArea.innerHTML = '';

                // Create preview based on file type
                if (selectedFileType === 'image') {
                    const img = document.createElement('img');
                    img.className = 'preview-image';
                    img.src = URL.createObjectURL(selectedFile);
                    previewArea.appendChild(img);
                } else if (selectedFileType === 'video') {
                    const video = document.createElement('video');
                    video.className = 'preview-image';
                    video.controls = true;
                    video.src = URL.createObjectURL(selectedFile);
                    previewArea.appendChild(video);
                } else if (selectedFileType === 'audio') {
                    const audio = document.createElement('audio');
                    audio.controls = true;
                    audio.src = URL.createObjectURL(selectedFile);
                    previewArea.appendChild(audio);
                } else {
                    previewArea.innerHTML =
                        `<p>📄 ${selectedFile.name}</p><p class="text-muted">${(selectedFile.size / 1024).toFixed(2)} KB</p>`;
                }

                // Show preview modal
                previewModal.style.display = 'flex';
            });

            // STEP 2: Cancel preview - Close modal and reset
            cancelPreviewBtn.addEventListener('click', function() {
                previewModal.style.display = 'none';
                selectedFile = null;
                selectedFileType = null;
                fileInput.value = '';
                previewArea.innerHTML = '';
            });

            // STEP 3: Send file after preview confirmation
            sendPreviewBtn.addEventListener('click', function() {
                if (!selectedFile) return;

                // Hide preview modal
                previewModal.style.display = 'none';

                // Show temporary uploading message in chat
                const tempDiv = document.createElement('div');
                tempDiv.className = 'mb-2 text-start';
                tempDiv.innerHTML =
                    `<span class="badge bg-primary">${selectedFile.name} <small class="text-muted">uploading...</small></span>`;
                chatBox.appendChild(tempDiv);
                chatBox.scrollTop = chatBox.scrollHeight;

                // Upload file to server
                const fd = new FormData();
                fd.append('attachment', selectedFile);
                fd.append('type', selectedFileType || '');
                fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);


                // request
                const formData = new FormData();
                formData.append('file', selectedFile);
                formData.append('message_id', receiverId);

                fetch(`/api/chat/upload/${selectedFileType}`, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log("data response", data.data.attachment.file_url);
                        if (selectedFileType === 'image' && data.data.attachment.file_url) {
                            tempDiv.innerHTML = `
            <span class="badge bg-primary">
                <img src="${data.data.attachment.file_url}" class="d-block mt-2" style="max-width: 200px;">
            </span>`;
                        }
                        if (selectedFileType === 'video' && data.data.attachment.file_url) {
                            tempDiv.innerHTML = `
            <span class="badge bg-primary">
                <video width="320" height="240" controls class="d-block mt-2" style="max-width: 200px;">
  <source src="${data.data.attachment.file_url}" type="video/mp4">
</video>
            </span>`;
                        }
                        if (selectedFileType === 'document' && data.data.attachment.file_url) {
                            tempDiv.innerHTML = `
        <a href="${data.data.attachment.file_url}" target="_blank" class="btn btn-primary">
            Download ${data.data.attachment.file_name}
        </a>
    `;
                        }
//                          else {
//                             // console.log('')
//                             tempDiv.innerHTML = `
//             <span class="badge bg-primary">
//                 <audio width="320" height="240" controls class="d-block mt-2" style="max-width: 200px;">
//   <source src="${data.data.attachment.file_url}" type="audio/mpeg">
// </audio>
//             </span>`;
//                         }
                    })
                    .catch(err => {
                        tempDiv.innerHTML =
                            `<span class="badge bg-danger">${ data.data.attachment.file_url} (failed)</span>`;
                        console.error('Upload error', err);
                    })
                    .finally(() => {
                        fileInput.value = '';
                        selectedFile = null;
                        selectedFileType = null;
                        previewArea.innerHTML = '';
                    });

            });

            // Set user offline on window close
            window.addEventListener('beforeunload', function() {
                fetch('/offline', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
            });

        });
    </script>
</body>

</html>
