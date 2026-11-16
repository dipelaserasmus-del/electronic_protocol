<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

use File;
use Storage;
use App\Models\Config;
use Carbon\Carbon;
use App\Models\Attachment;
use App\Services\DatabaseBackup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ConfigController extends Controller implements HasMiddleware
{

    protected  $protocolfields = [
        'fakelos' => 'Φάκελος',
        'thema' => 'Θέμα',
        'in_num' => 'Αριθ. Εισερχ.',
        'in_topos_ekdosis' => 'Τόπος έκδοσης',
        'in_arxi_ekdosis' => 'Αρχή έκδοσης',
        'in_paraliptis' => 'Παραλήπτης',
        'in_perilipsi' => 'Περιλ. Εισερχ',
        'diekperaiosi' => 'Διεκπεραίωση',
        'sxetiko' => 'Σχετικοί Αριθ.',
        'out_to' => 'Απευθύνεται',
        'out_perilipsi' => 'Περιλ. Εξερχ',
        'keywords' => 'Λέξεις κλειδιά',
        'paratiriseis' => 'Παρατηρήσεις'
    ];
    protected  $attachmentfields = [
        'name' => 'Όνομα συνημμένου',
        'ada' => 'ΑΔΑ'
    ];

    /** Who may use this controller (was the constructor's \$this->middleware(), N02). */
    public static function middleware(): array
    {
        return [
            'auth',
            'admin:home',
        ];
    }

    public function index()
    {
        $fields = array_merge($this->protocolfields, $this->attachmentfields);
        $settings = Config::getConfigValues();
        return view('config', compact('fields', 'settings'));
    }

    public function store()
    {

        $data = request()->all();


        $this->validate(request(), [
            'ipiresiasName' => 'required|max:255',
            'yearInUse' => 'nullable|sometimes|integer|digits:4',
            'firstProtocolNum' => 'required|integer',
            'showRowsInPage' => 'required|integer',
            'protocolArrowStep' => 'required|integer',
            'maxRowsInFindPage' => 'nullable|integer',
            'maxRowsInXlsExport' => 'nullable|integer',
            'minutesRefreshInterval' => 'required|numeric',
            'gapCheckFromNumber' => 'nullable|integer|min:1',
            'gapCheckMonths' => 'nullable|integer|min:1|max:24',
            'trashRetentionDays' => 'nullable|integer|min:0|max:3650',
        ], [
            'trashRetentionDays.*' => 'Οι ημέρες του κάδου είναι από 0 (μέχρι να αδειάσει με το χέρι) έως 3650.',
            'gapCheckFromNumber.*' => 'Ο αριθμός έναρξης ελέγχου κενών πρέπει να είναι θετικός ακέραιος.',
            'gapCheckMonths.*' => 'Οι μήνες ελέγχου κενών πρέπει να είναι από 1 έως 24.',
        ]);


        foreach ($data as $key => $value) {
            Config::setConfigValueOf($key, trim($value));
            if ($key == 'updatesAutoCheck' and $value == 0) Config::setConfigValueOf('needsUpdate', 0);
        }

        $notification = array(
            'message' => 'Επιτυχημένη καταχώριση.',
            'alert-type' => 'success'
        );
        session()->flash('notification', $notification);

        return redirect("/settings");
    }


    public function backups()
    {
        $files = [];
        foreach (Storage::disk('local')->files(DatabaseBackup::DIR) as $path) {
            if (DatabaseBackup::isValidName(basename($path))) {
                $files[] = pathinfo($path);
            }
        }
        rsort($files);   // newest first (names end in the date)
        return view('backup', compact('files'));
    }

    public function backup()
    {
        try {
            $name = (new DatabaseBackup(Config::getConfigValueOf('mysqldumpPath')))->create();
            $notification = array(
                'message' => "Επιτυχημένη δημιουργία αρχείου backup: $name",
                'alert-type' => 'success'
            );
        } catch (\RuntimeException $e) {
            Log::error('Backup failed: ' . $e->getMessage());
            $notification = array(
                'message' => 'Πρόβλημα στη δημιουργία αρχείου backup. ' . $e->getMessage(),
                'alert-type' => 'error'
            );
        }
        session()->flash('notification', $notification);

        return back();
    }


    public function downloadBackup($name)
    {
        $path = DatabaseBackup::DIR . '/' . $name;
        abort_unless(DatabaseBackup::isValidName($name) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $name, [
            'Content-Type' => str_ends_with($name, '.gz') ? 'application/gzip' : 'application/sql',
        ]);
    }

    public function deleteBackup($name)
    {
        abort_unless(DatabaseBackup::isValidName($name), 404);
        Storage::disk('local')->delete(DatabaseBackup::DIR . '/' . $name);
        return back();
    }

    public function arxeio()
    {
        $arxeianum = Attachment::where('expires', '<', Carbon::today()->toDateString())->count();
        $arxeiaNumTrash = Attachment::onlyTrashed()->count();
        return view('arxeio', compact('arxeianum', 'arxeiaNumTrash'));
    }

    public function expired($deleted = null)
    {
        if ($deleted) {
            $arxeia = Attachment::onlyTrashed()->with('protocol')->get();
            $title = "ΕΓΓΡΑΦΑ ΓΙΑ ΟΡΙΣΤΙΚΗ ΔΙΑΓΡΑΦΗ";
        } else {
            $arxeia = Attachment::whereNotNull('expires')->where('expires', '<', Carbon::today()->toDateString())->with('protocol')->get();
            $title = "ΕΚΚΑΘΑΡΙΣΗ ΑΡΧΕΙΟΥ";
        }
        foreach ($arxeia as $arxeio) {
            if ($arxeio->protocol()->protocoldate ?? null) $arxeio->protocol()->protocoldate = Carbon::createFromFormat('Ymd', $arxeio->protocol()->protocoldate)->format('d/m/Y');
            if ($arxeio->protocol()->in_date  ?? null) $arxeio->protocol()->in_date = Carbon::createFromFormat('Ymd', $arxeio->protocol()->in_date)->format('d/m/Y');
            if ($arxeio->protocol()->out_date  ?? null) $arxeio->protocol()->out_date = Carbon::createFromFormat('Ymd', $arxeio->protocol()->out_date)->format('d/m/Y');
            if ($arxeio->expires  ?? null) $arxeio->expires = Carbon::createFromFormat('Ymd', $arxeio->expires)->format('d/m/Y');
        }

        $arxeia = $arxeia->sort(
            function ($a, $b) {
                // sort by column1 first, then 2, and so on
                return strcmp($a->protocol()->fakelos ?? null, $b->protocol()->fakelos ?? null)
                    ?: strcmp($a->protocol()->protocolnum ?? null, $b->protocol()->protocolnum ?? null)
                    ?: strcmp($a->id, $b->id);
            }
        );

        $config = new Config;
        $datetime = Carbon::now()->format('d/m/Y H:m:s');
        return view('expired', compact('arxeia', 'datetime', 'title'));
    }

    public function delExpired()
    {
        $arxeianum = Attachment::where('expires', '<', Carbon::today()->toDateString())->count();
        $arxeia = Attachment::whereNotNull('expires')->where('expires', '<', Carbon::today()->toDateString())->get();
        $arxeiaNumTrash = Attachment::whereNotNull('deleted_at')->count();
        $arxeiaTrash = Attachment::whereNotNull('deleted_at')->get();

        // R4b: through the trash service (path recorded for restore; nothing overwritten in trash/)
        $trash = app(\App\Services\Attachments\Trash::class);
        foreach ($arxeia as $arxeio) {
            $trash->trash($arxeio, Auth::user());
        }

        $notification = array(
            'message' => "Διαγράφηκαν $arxeianum αρχεία.",
            'alert-type' => 'success'
        );
        session()->flash('notification', $notification);

        return back();
    }

    public function delDeleted()
    {
        // R4b: empty the trash: each file and row (before: the whole trash/ folder was deleted)
        $trash = app(\App\Services\Attachments\Trash::class);
        Attachment::onlyTrashed()->orderBy('id')->chunkById(200, fn ($rows) => $rows->each(fn ($a) => $trash->purge($a)));
        return back();
    }
}
