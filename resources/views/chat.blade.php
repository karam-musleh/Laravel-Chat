<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Chat with {{ $receiver->name }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    @vite(['resources/js/app.js'])
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            let receiverId = {{ $receiver->id }};
            let senderId = {{ auth()->id() }};
            let chatBox = document.getElementById('chat-box');
            let messageForm = document.getElementById('message-form');
            let messageInput = document.getElementById('message-input');
            let typingIndicator = document.getElementById('typing-indicator');

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

            // Toggle menu and position it under the button
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

            // Handle file selection (show temporary message and attempt upload)
            fileInput.addEventListener('change', function() {
                if (fileInput.files.length === 0) return;
                const file = fileInput.files[0];

                // show temporary message while uploading
                const tempDiv = document.createElement('div');
                tempDiv.className = 'mb-2 text-start';
                tempDiv.innerHTML =
                    `<span class="badge bg-primary">${file.name} <small class="text-muted">uploading...</small></span>`;
                chatBox.appendChild(tempDiv);
                chatBox.scrollTop = chatBox.scrollHeight;

                // attempt upload to backend route (create route if not present)
                const fd = new FormData();
                fd.append('attachment', file);
                fd.append('type', fileInput.dataset.type || '');
                fd.append('_token', document.querySelector('meta[name="csrf-token"]').content);

                fetch(`/chat/${receiverId}/upload`, {
                        method: 'POST',
                        body: fd
                    })
                    .then(response => response.json())
                    .then(data => {
                        // replace uploading indicator
                        tempDiv.innerHTML = `<span class="badge bg-primary">${file.name}</span>`;
                    })
                    .catch(err => {
                        tempDiv.innerHTML =
                        `<span class="badge bg-danger">${file.name} (failed)</span>`;
                        console.error('Upload error', err);
                    })
                    .finally(() => {
                        fileInput.value = '';
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
