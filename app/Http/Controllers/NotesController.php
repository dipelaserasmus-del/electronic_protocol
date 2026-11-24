<?php

namespace App\Http\Controllers;

use App\Models\UserNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * N05: the logged-in user's notepad (resources/modern/notes.js). Only one's own note is ever read or written.
 * A save names the version it started from; if the note was saved meanwhile (another tab or computer), it is refused
 * with the newer copy (409) unless the user chose to keep theirs (force).
 */
class NotesController extends Controller
{
    /** longest note, in characters */
    public const MAX = 100000;

    public function show(): JsonResponse
    {
        return response()->json($this->state(UserNote::firstWhere('user_id', Auth::id())));
    }

    public function update(): JsonResponse
    {
        $data = request()->validate([
            'body' => ['present', 'nullable', 'string', 'max:' . self::MAX],
            'version' => ['required', 'integer', 'min:0'],
            'force' => ['sometimes', 'boolean'],
        ], ['body.max' => 'Οι σημειώσεις είναι πολύ μεγάλες (έως ' . number_format(self::MAX, 0, ',', '.') . ' χαρακτήρες).']);

        return DB::transaction(function () use ($data) {
            $note = UserNote::where('user_id', Auth::id())->lockForUpdate()->first() ?? new UserNote(['user_id' => Auth::id(), 'version' => 0]);
            if ((int) $note->version !== (int) $data['version'] && empty($data['force'])) {
                return response()->json(['conflict' => true] + $this->state($note), 409);
            }
            $note->body = (string) ($data['body'] ?? '');
            $note->version = (int) $note->version + 1;
            $note->save();
            return response()->json($this->state($note));
        });
    }

    private function state(?UserNote $note): array
    {
        return [
            'body' => (string) ($note?->body ?? ''),
            'version' => (int) ($note?->version ?? 0),
            'saved' => $note?->updated_at?->timezone(config('app.timezone'))->format('H:i'),
        ];
    }
}
