<?php

namespace Database\Factories;

use App\Protocol;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Synthetic protocol entries. All names, numbers and subjects are invented.
 * Dates are stored as integers Ymd (legacy schema, see task D04).
 * @extends Factory<Protocol>
 */
class ProtocolFactory extends Factory
{
    protected $model = Protocol::class;

    private const SUBJECTS = [
        'Πρόσκληση σε επιμορφωτική ημερίδα', 'Εγκύκλιος έναρξης μαθημάτων', 'Διαβιβαστικό εγγράφων μαθητή',
        'Αίτηση βεβαίωσης φοίτησης', 'Ενημέρωση για εκπαιδευτική εκδρομή', 'Πρόγραμμα εφημεριών',
        'Κατάσταση μαθητών ανά τμήμα', 'Αίτηση άδειας εκπαιδευτικού', 'Προμήθεια σχολικών βιβλίων',
        'Συμμετοχή σε πολιτιστικό πρόγραμμα', 'Ορισμός επιτροπής παραλαβής', 'Βεβαίωση προϋπηρεσίας',
    ];

    private const SENDERS = [
        'Διεύθυνση Π.Ε. Δοκιμής', 'Υπουργείο Δοκιμών', 'Περιφέρεια Δοκιμής', 'Δήμος Δοκιμής',
        '2ο Δημοτικό Σχολείο Δοκιμής', 'ΚΕΠΕΑ Δοκιμής', 'Σύλλογος Γονέων Δοκιμής',
    ];

    public function definition(): array
    {
        $date = fake()->dateTimeBetween(date('Y') . '-01-01', 'now');

        return [
            'user_id' => User::factory(),
            'protocolnum' => fake()->unique()->numberBetween(1, 999999),
            'protocoldate' => (int) $date->format('Ymd'),
            'etos' => (int) $date->format('Y'),
            'fakelos' => null,
            'thema' => fake()->randomElement(self::SUBJECTS),
            'in_num' => null,
            'in_date' => null,
            'in_topos_ekdosis' => null,
            'in_arxi_ekdosis' => null,
            'in_paraliptis' => null,
            'diekperaiosi' => '',
            'diekp_eos' => null,
            'in_perilipsi' => null,
            'out_date' => null,
            'diekp_date' => null,
            'sxetiko' => null,
            'out_to' => null,
            'out_perilipsi' => null,
            'keywords' => null,
            'paratiriseis' => null,
        ];
    }

    /** Fixed number and year (and a date in that year if not given) */
    public function number(int $num, ?int $etos = null): static
    {
        $etos ??= (int) date('Y');
        return $this->state(fn (array $a) => [
            'protocolnum' => $num,
            'etos' => $etos,
            'protocoldate' => (int) ($etos . substr((string) $a['protocoldate'], 4)),
        ]);
    }

    public function onDate(string $ymd): static
    {
        return $this->state(fn () => ['protocoldate' => (int) $ymd, 'etos' => (int) substr($ymd, 0, 4)]);
    }

    public function incoming(): static
    {
        return $this->state(function (array $a) {
            $d = \DateTime::createFromFormat('Ymd', (string) $a['protocoldate'])->modify('-' . fake()->numberBetween(0, 5) . ' days');
            return [
                'in_num' => fake()->numerify('Φ.##/####/Δ#'),
                'in_date' => (int) $d->format('Ymd'),
                'in_topos_ekdosis' => 'Δοκιμή',
                'in_arxi_ekdosis' => fake()->randomElement(self::SENDERS),
                'in_paraliptis' => 'Διευθυντής Σχολείου',
                'in_perilipsi' => fake()->randomElement(self::SUBJECTS),
            ];
        });
    }

    public function outgoing(): static
    {
        return $this->state(fn (array $a) => [
            'out_date' => $a['protocoldate'],
            'out_to' => fake()->randomElement(self::SENDERS),
            'out_perilipsi' => fake()->randomElement(self::SUBJECTS),
        ]);
    }

    /** Assign for διεκπεραίωση (d) and/or ενημέρωση (e), legacy text format "d14,e10" (see task D02) */
    public function assignedTo(?User $diekp = null, ?User $inform = null): static
    {
        $parts = array_filter([$diekp ? 'd' . $diekp->id : null, $inform ? 'e' . $inform->id : null]);
        return $this->state(fn () => ['diekperaiosi' => implode(',', $parts)]);
    }

    public function placeholder(): static
    {
        return $this->state(fn () => ['thema' => 'ΕΛΕΥΘΕΡΟ']);
    }
}
