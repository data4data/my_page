<?php

namespace App\Enums;

// 'ai_chat' is unused today, reserved for AI-assisted task creation.
enum TaskSource: string
{
    case Manual = 'manual';
    case Seeder = 'seeder';
    case AiChat = 'ai_chat';
}
