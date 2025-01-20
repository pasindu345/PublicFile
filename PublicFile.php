<?php

// Telegram Bot Token
define('BOT_TOKEN', '7292391889:AAFaxFx7SUrAMB0vM8FhjFR_9XlV1tjkkCw');
define('API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

// File storage and statistics
$shared_files = [];
$bot_users = [];
$monthly_users = [];
$start_time = time(); // Bot uptime tracker

// Function to send API requests
function sendRequest($method, $data = [])
{
    $url = API_URL . $method;
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ],
    ];
    $context = stream_context_create($options);
    return json_decode(file_get_contents($url, false, $context), true);
}

// Handle incoming updates
$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $message = $update['message'];
    $chat_id = $message['chat']['id'];
    $user_id = $message['from']['id'];
    $text = $message['text'] ?? '';
    $caption = $message['caption'] ?? 'No caption provided.';

    // Track bot users
    if (!in_array($user_id, $bot_users)) {
        $bot_users[] = $user_id;
    }

    // Track monthly active users
    $current_month = date('Y-m');
    if (!isset($monthly_users[$current_month])) {
        $monthly_users[$current_month] = [];
    }
    if (!in_array($user_id, $monthly_users[$current_month])) {
        $monthly_users[$current_month][] = $user_id;
    }

    // Welcome Message
    if (strpos($text, '/start') === 0) {
        $parts = explode(' ', $text);
        if (count($parts) > 1) {
            $file_id = $parts[1];
            if (isset($shared_files[$file_id])) {
                $file_data = $shared_files[$file_id];
                sendRequest('sendDocument', [
                    'chat_id' => $chat_id,
                    'document' => $file_data['file_id'],
                    'caption' => $file_data['caption'],
                ]);
            } else {
                sendRequest('sendMessage', [
                    'chat_id' => $chat_id,
                    'text' => "❌ File not found.",
                ]);
            }
        } else {
            sendRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "👋 **Welcome to Public File Sharing Bot!**\n\n"
                    . "📁 **How to Use:**\n"
                    . "- Upload files to get a shareable link.\n"
                    . "- Users can download files using the shared link.\n\n"
                    . "**Commands:**\n"
                    . "/help - View bot instructions\n"
                    . "/statistics - Check bot usage stats",
                'parse_mode' => 'Markdown',
            ]);
        }
    }

    // Help Command
    elseif ($text == '/help') {
        sendRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📋 **Help Menu:**\n\n"
                . "1️⃣ **Upload a File:** Send a file to the bot.\n"
                . "2️⃣ **Get Share Link:** Receive a unique link for sharing.\n"
                . "3️⃣ **Download Files:** Click the shared link to access files.\n\n"
                . "Enjoy sharing files with ease!",
            'parse_mode' => 'Markdown',
        ]);
    }

    // Statistics Command
    elseif ($text == '/statistics') {
        $file_count = count($shared_files);
        $total_users = count($bot_users);
        $current_month_users = count($monthly_users[date('Y-m')] ?? []);
        $uptime_seconds = time() - $start_time;

        // Convert uptime to days, hours, minutes, seconds
        $days = floor($uptime_seconds / (3600 * 24));
        $hours = floor(($uptime_seconds % (3600 * 24)) / 3600);
        $minutes = floor(($uptime_seconds % 3600) / 60);
        $seconds = $uptime_seconds % 60;

        // Current Time in Sri Lanka
        date_default_timezone_set('Asia/Colombo');
        $current_time = date('Y-m-d H:i:s');

        sendRequest('sendMessage', [
            'chat_id' => $chat_id,
            'text' => "📊 **Bot Statistics:**\n\n"
                . "🗂 **Total Files Shared:** {$file_count}\n"
                . "👥 **Total Users:** {$total_users}\n"
                . "📅 **Monthly Active Users:** {$current_month_users}\n"
                . "⏳ **Uptime:** {$days}d {$hours}h {$minutes}m {$seconds}s\n"
                . "🕒 **Current Time (Sri Lanka):** {$current_time}",
            'parse_mode' => 'Markdown',
        ]);
    }

    // File Upload and Sharing
    elseif (isset($message['document']) || isset($message['audio']) || isset($message['video']) || isset($message['photo'])) {
        $file_id = null;
        $file_name = 'file';

        if (isset($message['document'])) {
            $file_id = $message['document']['file_id'];
            $file_name = $message['document']['file_name'];
        } elseif (isset($message['audio'])) {
            $file_id = $message['audio']['file_id'];
            $file_name = $message['audio']['file_name'] ?? 'audio.mp3';
        } elseif (isset($message['video'])) {
            $file_id = $message['video']['file_id'];
            $file_name = $message['video']['file_name'] ?? 'video.mp4';
        } elseif (isset($message['photo'])) {
            $file_id = end($message['photo'])['file_id'];
            $file_name = 'photo.jpg';
        }

        if ($file_id) {
            $unique_id = $chat_id . '_' . $message['message_id'];
            $shared_files[$unique_id] = ['file_id' => $file_id, 'caption' => $caption];

            $inline_keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🔗 Share Link',
                            'url' => "https://t.me/PublicFile_xBot?start=" . $unique_id,
                        ],
                    ],
                ],
            ];

            sendRequest('sendMessage', [
                'chat_id' => $chat_id,
                'text' => "✅ **File '{$file_name}' uploaded successfully!**\n\n"
                    . "🔗 **Share this link:**\n"
                    . "https://t.me/PublicFile_xBot?start=" . $unique_id . "\n\n"
                    . "📄 **Caption:** {$caption}",
                'reply_markup' => json_encode($inline_keyboard),
                'parse_mode' => 'Markdown',
            ]);
        }
    }
}
