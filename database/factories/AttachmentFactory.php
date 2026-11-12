<?php

namespace Database\Factories;

use App\Attachment;
use App\Protocol;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/** @extends Factory<Attachment> */
class AttachmentFactory extends Factory
{
    protected $model = Attachment::class;

    public function definition(): array
    {
        return [
            'protocol_id' => Protocol::factory(),
            'ada' => null,
            'name' => 'έγγραφο_' . fake()->numberBetween(1, 999) . '.pdf',
            'mimeType' => 'application/pdf',
            'savedPath' => null,
            'keep' => null,
            'expires' => null,
        ];
    }

    /** Also writes a small file to the default storage disk */
    public function withFile(string $contents = "%PDF-1.4 test\n"): static
    {
        return $this->afterMaking(function (Attachment $a) use ($contents) {
            $a->savedPath = 'arxeio/test/' . uniqid() . '_' . $a->name;
            Storage::put($a->savedPath, $contents);
        });
    }
}
