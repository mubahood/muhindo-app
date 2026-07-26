<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function create(Quiz $quiz): View
    {
        return view('admin.quizzes.question-form', ['quiz' => $quiz, 'question' => new Question]);
    }

    public function store(Request $request, Quiz $quiz): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($quiz, $data) {
            $questionData = $data['question'];
            $questionData['sort_order'] ??= $quiz->questions()->count();

            $question = $quiz->questions()->create($questionData);
            $this->syncOptions($question, $data['options']);
        });

        return redirect()->route('admin.quizzes.edit', $quiz)->with('success', 'Question added.');
    }

    public function edit(Question $question): View
    {
        return view('admin.quizzes.question-form', ['quiz' => $question->quiz, 'question' => $question->load('options')]);
    }

    public function update(Request $request, Question $question): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($question, $data) {
            $questionData = $data['question'];
            $questionData['sort_order'] ??= $question->sort_order;

            $question->update($questionData);
            $question->options()->delete();
            $this->syncOptions($question, $data['options']);
        });

        return redirect()->route('admin.quizzes.edit', $question->quiz)->with('success', 'Question updated.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $quiz = $question->quiz;
        $question->delete();

        return redirect()->route('admin.quizzes.edit', $quiz)->with('success', 'Question deleted.');
    }

    /** @return array{question: array<string,mixed>, options: array<int,array<string,mixed>>} */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_column(QuestionType::cases(), 'value'))],
            'prompt' => 'required|string',
            'explanation' => 'nullable|string',
            'points' => 'required|numeric|min:0.01',
            'sort_order' => 'nullable|integer',
            'options' => 'array',
            'options.*.label' => 'nullable|string',
            'options.*.is_correct' => 'nullable|boolean',
            'options.*.match_key' => 'nullable|string',
            'accepted_answers' => 'nullable|string',
            'case_sensitive' => 'nullable|boolean',
            'numeric_expected' => 'nullable|numeric',
            'numeric_tolerance' => 'nullable|numeric|min:0',
        ]);

        $type = QuestionType::from($validated['type']);
        $meta = match ($type) {
            QuestionType::FillBlank, QuestionType::ShortText => [
                'accepted_answers' => array_values(array_filter(array_map('trim', explode("\n", $validated['accepted_answers'] ?? '')))),
                'case_sensitive' => $request->boolean('case_sensitive'),
            ],
            QuestionType::Numeric => [
                'expected' => (float) ($validated['numeric_expected'] ?? 0),
                'tolerance' => (float) ($validated['numeric_tolerance'] ?? 0),
            ],
            default => null,
        };

        $options = [];
        if ($type->usesOptions()) {
            foreach ($request->input('options', []) as $index => $option) {
                if (($option['label'] ?? '') === '') {
                    continue;
                }
                $options[] = [
                    'label' => $option['label'],
                    'is_correct' => in_array($type, [QuestionType::McqSingle, QuestionType::McqMulti, QuestionType::TrueFalse], true)
                        ? (bool) ($option['is_correct'] ?? false)
                        : false,
                    'match_key' => $type === QuestionType::Matching ? ($option['match_key'] ?? null) : null,
                    'sort_order' => $index,
                ];
            }
        }

        return [
            'question' => [
                'type' => $validated['type'],
                'prompt' => $validated['prompt'],
                'explanation' => $validated['explanation'] ?? null,
                'points' => $validated['points'],
                'sort_order' => $validated['sort_order'] ?? null,
                'meta' => $meta,
            ],
            'options' => $options,
        ];
    }

    /** @param  array<int,array<string,mixed>>  $options */
    private function syncOptions(Question $question, array $options): void
    {
        foreach ($options as $option) {
            $question->options()->create($option);
        }
    }
}
