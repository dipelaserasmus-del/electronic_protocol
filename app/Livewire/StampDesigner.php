<?php

namespace App\Livewire;

use App\Services\Stamp\StampRenderer;
use App\Services\Stamp\StampTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * R4e: the stamp designer (/stamp). Only Διαχειριστής edits the stamp (R4i, owner, 2026-11-25; R4e had Αναθέτων and
 * Συγγραφέας too): emblem, lines, colour, width, position; the preview follows every change (drawn as it will be, at
 * a lower resolution) and shows where it goes on an A4 page. Saved as the setting "stampTemplate" (StampTemplate).
 */
class StampDesigner extends Component
{
    use WithFileUploads;

    public const ROLES = ['Διαχειριστής'];   // R4i (owner, 2026-11-25): administrators only

    /** StampTemplate::toArray() being edited */
    public array $tpl = [];

    /** a new emblem, kept on save */
    public $emblemUpload = null;

    /** the saved emblem is to be removed on save */
    public bool $dropEmblem = false;

    public static function allowed(?\App\Models\User $user): bool
    {
        return $user !== null && in_array($user->role?->role, self::ROLES, true);
    }

    public function boot(): void
    {
        abort_unless(self::allowed(Auth::user()), 403);
    }

    public function mount(): void
    {
        $this->tpl = StampTemplate::current()->toArray();
    }

    public function addLine(): void
    {
        if (count($this->tpl['lines'] ?? []) < StampTemplate::LIMITS['lines'][1]) {
            $this->tpl['lines'][] = StampTemplate::line('', 8, false, 'center');
        }
    }

    public function removeLine(int $i): void
    {
        unset($this->tpl['lines'][$i]);
        $this->tpl['lines'] = array_values($this->tpl['lines']);
    }

    public function moveLine(int $i, int $by): void
    {
        $lines = $this->tpl['lines'];
        $j = $i + $by;
        if (isset($lines[$i], $lines[$j])) {
            [$lines[$i], $lines[$j]] = [$lines[$j], $lines[$i]];
            $this->tpl['lines'] = $lines;
        }
    }

    public function updatedEmblemUpload(): void
    {
        $this->validate(['emblemUpload' => 'image|mimes:png,jpg,jpeg,webp|max:5120'], [
            'emblemUpload.*' => 'Εικόνα PNG (κατά προτίμηση με διάφανο φόντο), JPG ή WEBP, έως 5 MB.',
        ]);
        $info = @getimagesize($this->emblemUpload->getRealPath());   // the content, not only the name
        if (! $info || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true) || $info[0] * $info[1] > StampRenderer::MAX_EMBLEM_PIXELS) {
            $this->emblemUpload = null;
            $this->addError('emblemUpload', 'Η εικόνα δεν διαβάζεται ή είναι πολύ μεγάλη (έως 6000 × 6000).');
            return;
        }
        $this->dropEmblem = false;
        $this->tpl['emblem'] = true;
    }

    public function removeEmblem(): void
    {
        $this->emblemUpload = null;
        $this->dropEmblem = true;
        $this->tpl['emblem'] = false;
    }

    public function resetDefaults(): void
    {
        $this->tpl = StampTemplate::defaults()->toArray();
        $this->emblemUpload = null;
    }

    public function save(): void
    {
        $t = StampTemplate::fromArray($this->tpl);
        if ($this->emblemUpload) {
            $info = @getimagesize($this->emblemUpload->getRealPath());
            $img = $info && $info[0] * $info[1] <= StampRenderer::MAX_EMBLEM_PIXELS
                ? @imagecreatefromstring((string) file_get_contents($this->emblemUpload->getRealPath())) : false;
            if (! $img) {
                $this->addError('emblemUpload', 'Η εικόνα δεν διαβάζεται ή είναι πολύ μεγάλη (έως 6000 × 6000).');
                return;
            }
            imagesavealpha($img, true);
            ob_start();
            imagepng($img);   // always stored as PNG, whatever was uploaded
            Storage::put(StampTemplate::EMBLEM, (string) ob_get_clean());
            $this->emblemUpload = null;
        } elseif ($this->dropEmblem) {
            Storage::delete(StampTemplate::EMBLEM);
        }
        $this->dropEmblem = false;
        $t->emblem = $t->emblem && StampTemplate::emblemPath() !== null;
        $t->save();
        $this->tpl = $t->toArray();
        $this->dispatch('toast', message: 'Η σφραγίδα αποθηκεύτηκε. Ισχύει για τα αρχεία που προστίθενται από τώρα.', type: 'success');
    }

    public function render()
    {
        $t = StampTemplate::fromArray($this->tpl);
        if ($this->emblemUpload && ! $this->getErrorBag()->has('emblemUpload')) {
            $t->emblemFile = $this->emblemUpload->getRealPath();
        } elseif ($this->dropEmblem) {
            $t->emblem = false;
        }
        $renderer = new StampRenderer();
        $img = $renderer->render($t, StampTemplate::sampleValues(), StampRenderer::PREVIEW_DPI);
        [$w, $h] = StampRenderer::sizeMm($img, StampRenderer::PREVIEW_DPI);
        ob_start();
        imagepng($img);
        $png = base64_encode((string) ob_get_clean());

        return view('livewire.stamp-designer', [
            'preview' => 'data:image/png;base64,' . $png,
            'sizeMm' => [$w, $h],
            'template' => $t,
            'hasEmblem' => ! $this->dropEmblem && ($this->emblemUpload || StampTemplate::emblemPath()),
            'corners' => StampTemplate::CORNERS,
            'aligns' => StampTemplate::ALIGN,
            'limits' => StampTemplate::LIMITS,
        ]);
    }
}
