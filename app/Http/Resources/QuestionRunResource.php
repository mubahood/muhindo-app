<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A question as shown DURING an in-progress attempt: never exposes
 * `QuestionOption::is_correct`/`match_key`, which would leak the answer before grading.
 * The web quiz runner gets this for free by simply never echoing those fields in Blade;
 * a JSON API has no such implicit safety, so this resource is the explicit equivalent.
 *
 * @mixin \App\Models\Question
 */
class QuestionRunResource extends JsonResource
{
    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'prompt' => $this->prompt,
            'points' => (float) $this->points,
            'options' => $this->whenLoaded('options', fn () => $this->options->map(fn ($option) => [
                'id' => $option->id,
                'label' => $option->label,
            ])->values()),
        ];
    }
}
