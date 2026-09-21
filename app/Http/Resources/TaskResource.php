<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a task looks like on the way out. Explicit, like the portfolio's two
 * resources and for the same reason: a column added later should join the
 * response because someone decided it, not because it exists.
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            // Wall-clock, unlike running_log below. See CLAUDE.md on dates.
            'start_datetime' => $this->start_datetime,
            'end_datetime' => $this->end_datetime,
            'planned_duration_minutes' => $this->planned_duration_minutes,
            'status' => $this->status,
            'result_notes' => $this->result_notes,
            // Writable, so it is readable: a task made by hand and one pulled
            // from a calendar will not be editable on the same terms.
            'source' => $this->source,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category'),
            // The open log alone — is a timer running, and since when. Closed
            // logs are report input, and the report sums them in SQL.
            'running_log' => $this->whenLoaded('runningTimeLog', fn () => [
                'id' => $this->runningTimeLog->id,
                'started_at' => $this->runningTimeLog->started_at,
            ]),
        ];
    }
}
