<?php

namespace App\Enums;

// Tracks where a task came from — 'ai_chat' is unused today, reserved for
// the future AI-assisted task creation mentioned in the feature spec.
enum TaskSource: string
{
    case Manual = 'manual';
    case Seeder = 'seeder';
    case AiChat = 'ai_chat';
}
