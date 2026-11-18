<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

use DB;
use Illuminate\Support\Facades\Gate;
use URL;
use Auth;
use Active;
use Storage;
use App\Models\Role;
use App\Models\User;
use Validator;
use App\Models\Config;
use App\Models\Protocol;
use App\Models\Keepvalue;
use Carbon\Carbon;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Webklex\IMAP\Facades\Client;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use ZBateson\MailMimeParser\Message;
use ZBateson\MailMimeParser\MailMimeParser;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use Yasapurnama\DocumentWatermark\WatermarkFactory;

//use Illuminate\Support\Facades\Log;
//Log::info('test');

/*

            $table->increments('id');
            $table->integer('user_id')->unsigned()->required();
            $table->integer('protocolnum')->required()->unsigned()->index();
            $table->integer('protocoldate')->required()->unsigned();
            $table->integer('etos')->unsigned()->required();
            $table->string('fakelos')->nullable();
            $table->string('thema')->nullable();
            $table->string('in_num')->nullable();
            $table->integer('in_date')->unsigned()->nullable();
            $table->string('in_topos_ekdosis')->nullable();
            $table->string('in_arxi_ekdosis')->nullable();
            $table->string('in_paraliptis')->nullable();
            $table->string('diekperaiosi')->nullable();
            $table->integer('diekp_eos')->unsigned()->nullable();
            $table->string('in_perilipsi')->nullable();
            $table->integer('out_date')->unsigned()->nullable();
            $table->integer('diekp_date')->unsigned()->nullable();
            $table->string('sxetiko')->nullable();
            $table->string('out_to')->nullable();
            $table->string('out_perilipsi')->nullable();
            $table->string('keywords')->nullable();
            $table->text('paratiriseis')->nullable();
            $table->timestamps();
            $table->unique(array('protocolnum', 'etos'));

*/

class ProtocolController extends Controller implements HasMiddleware
{
    /** Who may use this controller (was the constructor's \$this->middleware(), N02). */
    public static function middleware(): array
    {
        return [
            'auth',
            new Middleware('writer:home/list', except: ['gotonum', 'download', 'about']),
        ];
    }

    /**
     * B07: disabled. It asked the ORIGINAL author's GitHub repository (g-theodoroy) for new commits on login:
     * a network call that waits for a timeout on servers without internet, and — since this fork has its own
     * history — advice to apply someone else's changes. Updates of this fork come through its own repository.
     * The route stays: the login redirects here, and it forwards each role to its landing page as before.
     */
    public function chkForUpdates()
    {
        return redirect(config('landing-page.page.' . auth()->user()->role_id));
    }

    public function getKeep4Fakelos($fakelos)
    {
        $keepval = null;
        if ($fakelos) {
            $keepval = Keepvalue::whereFakelos($fakelos)->first()->keep;
            if (!$keepval) {
                $keepval = Keepvalue::whereFakelos($fakelos)->first()->keep_alt;
            }
        }
        return $keepval;
    }

    /** R1a: saving goes through ProtocolWriter (rules, numbering, files, e-mails); this only adapts the legacy form. */
    public function store()
    {
        try {
            $result = app(\App\Services\Protocols\ProtocolWriter::class)->create(Auth::user(), request()->all(), $this->postedAttachments());
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;   // back to the form with the messages and everything typed
        } catch (\Throwable $e) {
            report($e);
            session()->flash('notification', [
                'message' => 'Υπήρξε κάποιο πρόβλημα στην καταχώριση του Πρωτοκόλλου<br>Παρακαλώ επαναλάβετε την καταχώριση.',
                'alert-type' => 'error',
            ]);
            return back()->withInput();
        }
        return $this->afterSave($result);
    }

    public function update(Protocol $protocol)
    {
        $this->authorize('update', $protocol);   // S02
        try {
            $result = app(\App\Services\Protocols\ProtocolWriter::class)->update(Auth::user(), $protocol, request()->all(), $this->postedAttachments());
        } catch (\Illuminate\Validation\ValidationException|\Illuminate\Auth\Access\AuthorizationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            session()->flash('notification', [
                'message' => 'Υπήρξε κάποιο πρόβλημα στην ενημέρωση του Πρωτοκόλλου<br>Παρακαλώ επαναλάβετε την ενημέρωση.',
                'alert-type' => 'error',
            ]);
            return redirect("home/{$protocol->id}");
        }
        return $this->afterSave($result);
    }

    /** The legacy form's file rows: att1…attN and ada1…adaN (3 per "file_inputs_count"). */
    private function postedAttachments(): array
    {
        $rows = [];
        $count = 3 * max(0, (int) request('file_inputs_count'));
        for ($i = 1; $i <= $count; $i++) {
            $rows[] = ['file' => request()->file("att$i"), 'ada' => request("ada$i")];
        }
        return $rows;
    }

    private function afterSave(\App\Services\Protocols\SaveResult $result)
    {
        $lines = '';
        foreach ($result->messages as $line) {
            $lines .= '<li>' . e($line) . '</li>';
        }
        foreach ($result->warnings as $line) {
            $lines .= '<li><strong>' . e($line) . '</strong></li>';
        }
        session()->flash('notification', [
            'message' => 'Επιτυχημένη ενημέρωση.' . "<ul>$lines</ul>",
            'alert-type' => $result->warnings ? 'warning' : 'success',
        ]);
        return redirect("home/{$result->protocol->id}");
    }

    public function gotonum($etos, $protocolnum, $step = null)
    {
        $limitProtocolAccessList = $this->limitProtocolAccessList();
        if (request('find')) {
            // φιλτραρω τα πρωτόκολλα για το χρήστη
            if ($limitProtocolAccessList) {
                $count = Protocol::whereEtos($etos)->where('protocolnum', $protocolnum)->where(function ($query) {
                    $query->ownOrAssigned(Auth::user()->id);   // D02
                })->count();
                if ($count) {
                    $protocol_id = Protocol::whereEtos($etos)->where('protocolnum', $protocolnum)->where(function ($query) {
                        $query->ownOrAssigned(Auth::user()->id);   // D02
                    })->first()->id;
                    return redirect("home/$protocol_id");
                }
            } else {
                if (Protocol::whereEtos($etos)->where('protocolnum', $protocolnum)->count()) {
                    $protocol_id = Protocol::whereEtos($etos)->where('protocolnum', $protocolnum)->first()->id;
                    return redirect("home/$protocol_id");
                }
            }
        } else {
            if ($step == 'b') {
                if ($limitProtocolAccessList) {
                    $protocol_id = Protocol::where(function ($query) {
                        $query->ownOrAssigned(Auth::user()->id);   // D02
                    })->where(function ($query) use ($protocolnum, $etos) {
                        $query->where([['protocolnum', '<', $protocolnum], ['etos', $etos]])->orwhere('etos', "<", $etos);
                    })->orderby('etos', 'DESC')->orderby('protocolnum', 'DESC')->take(1)->get('id');
                    if (count($protocol_id)) return redirect("home/" . $protocol_id[0]->id);
                    return redirect("home");
                } else {
                    $protocol_id = Protocol::where([['protocolnum', '<', $protocolnum], ['etos', $etos]])->orwhere('etos', "<", $etos)->orderby('etos', 'DESC')->orderby('protocolnum', 'DESC')->take(1)->get('id');
                    if (count($protocol_id)) return redirect("home/" . $protocol_id[0]->id);
                    return redirect("home");
                }
            } elseif ($step == 'f') {
                if ($limitProtocolAccessList) {
                    $protocol_id = Protocol::where(function ($query) {
                        $query->ownOrAssigned(Auth::user()->id);   // D02
                    })->where(function ($query) use ($protocolnum, $etos) {
                        $query->where([['protocolnum', '>', $protocolnum], ['etos', $etos]])->orwhere('etos', ">", $etos);
                    })->orderby('etos', 'ASC')->orderby('protocolnum', 'ASC')->take(1)->get('id');
                    if (count($protocol_id)) return redirect("home/" . $protocol_id[0]->id);
                    return redirect("home");
                } else {
                    $protocol_id = Protocol::where([['protocolnum', '>', $protocolnum], ['etos', $etos]])->orwhere('etos', ">", $etos)->orderby('etos', 'ASC')->orderby('protocolnum', 'ASC')->take(1)->get('id');
                    if (count($protocol_id)) return redirect("home/" . $protocol_id[0]->id);
                    return redirect("home");
                }
            } elseif ($step == 'bb') {
                if ($limitProtocolAccessList) {
                    $protocol_id = Protocol::where(function ($query) {
                        $query->ownOrAssigned(Auth::user()->id);   // D02
                    })->where(function ($query) use ($protocolnum, $etos) {
                        $query->where([['protocolnum', '<', $protocolnum], ['etos', $etos]])->orwhere('etos', "<", $etos);
                    })->orderby('etos', 'DESC')->orderby('protocolnum', 'DESC')->skip(Config::getConfigValueOf('protocolArrowStep') - 1)->take(1)->get('id');
                    if (count($protocol_id)) return redirect("home/" . $protocol_id[0]->id);
                    return redirect("home");
                } else {
                    $protocol_id = Protocol::where([['protocolnum', '<', $protocolnum], ['etos', $etos]])->orwhere('etos', "<", $etos)->orderby('etos', 'DESC')->orderby('protocolnum', 'DESC')->skip(Config::getConfigValueOf('protocolArrowStep') - 1)->take(1)->get('id');
                    if (count($protocol_id)) return redirect("home/" . $protocol_id[0]->id);
                    return redirect("home");
                }
            } elseif ($step == 'ff') {
                if ($limitProtocolAccessList) {
                    $protocol_id = Protocol::where(function ($query) {
                        $query->ownOrAssigned(Auth::user()->id);   // D02
                    })->where(function ($query) use ($protocolnum, $etos) {
                        $query->where([['protocolnum', '>', $protocolnum], ['etos', $etos]])->orwhere('etos', ">", $etos);
                    })->orderby('etos', 'ASC')->orderby('protocolnum', 'ASC')->skip(Config::getConfigValueOf('protocolArrowStep') - 1)->take(1)->get('id');
                    if (count($protocol_id)) return redirect("home/" . $protocol_id[0]->id);
                    return redirect("home");
                } else {
                    $protocol_id = Protocol::where([['protocolnum', '>', $protocolnum], ['etos', $etos]])->orwhere('etos', ">", $etos)->orderby('etos', 'ASC')->orderby('protocolnum', 'ASC')->skip(Config::getConfigValueOf('protocolArrowStep') - 1)->take(1)->get('id');
                    if (count($protocol_id)) return redirect("home/" . $protocol_id[0]->id);
                    return redirect("home");
                }
            }
        }
        $notification = array(
            'message' => 'Δεν βρέθηκε Πρωτόκολλο να ικανοποιεί τα στοιχεία που δώσατε.',
            'alert-type' => 'warning'
        );
        session()->flash('notification', $notification);
        return back();
    }

    /**
     * R4a: an attachment, for users who may see its protocol (S02; an attachment without protocol: administrators).
     * Safe headers and streaming: App\Support\SafeFile.
     */
    public function download(Attachment $attachment)
    {
        $protocol = $attachment->protocol;
        $allowed = $protocol ? Gate::allows('view', $protocol) : Auth::user()->role->role === 'Διαχειριστής';
        abort_unless($allowed, 404);
        $path = $attachment->storedFilePath();
        if ($path !== null) {
            return \App\Support\SafeFile::download(Storage::path($path), $attachment->name ?: basename($path), $attachment->mimeType);
        }
        session()->flash('notification', [
            'message' => 'Το αρχείο «' . e($attachment->name) . '» δεν υπάρχει στον δίσκο. Πιθανόν να διαγράφηκε από άλλο πρόγραμμα.',
            'alert-type' => 'info',
        ]);
        return back();
    }

    public function about()
    {
        return view('about');
    }

    public function updated()
    {
        $file = storage_path('conf/.updateCheck');
        unlink($file);

        Config::setConfigValueOf('needsUpdate', 0);
        return redirect("home/list");
    }

    // βρίσκει τις καταχωρίσεις που ταιριάζουν με αυτά που πληκτρολογεί ο χρήστης και επιστρέφει λίστα
    /** Fields that the protocol / email forms autocomplete from previous entries */
    private const AUTOCOMPLETE_FIELDS = ['thema', 'in_topos_ekdosis', 'in_arxi_ekdosis', 'in_paraliptis', 'sxetiko', 'out_to', 'keywords', 'fakelos', 'in_num', 'out_perilipsi', 'in_perilipsi'];

    /** Autocomplete as called by the forms: /getValues/{field}/{id}/{divId}/{multi}?term=... */
    public function getValuesQuery($field, $id, $divId, $multi)
    {
        return $this->getValues((string) request('term', ''), $field, $id, $divId, $multi);
    }

    public function getValues($term, $field, $id, $divId, $multi)
    {
        // S02: only the fields the forms autocomplete, and only from protocols the user may see
        abort_unless(in_array($field, self::AUTOCOMPLETE_FIELDS, true), 404);
        $source = Protocol::latest();
        if (\App\Policies\ProtocolPolicy::isLimited(Auth::user())) {
            $uid = Auth::user()->id;
            $source->ownOrAssigned((int) $uid);   // D02
        }
        $protocols = $source->take(Config::getConfigValueOf('rowsToSearchGiaSimplirosi') ?? 1000)->pluck($field)->countBy()->sortDesc()->keys();
        $protocols = $protocols->filter(function ($item) use ($term) {
            return mb_stripos($item, $term) !== false;
        })->take(Config::getConfigValueOf('rowsToShowGiaSimplirosi') ?? 10)->sort();
        if (!$protocols) {
            return response('');
        }
        $output = '';
        if ($multi) {
            $valuesArray = [];
            foreach ($protocols as $protocol) {

                $valuesArray = array_merge($valuesArray, preg_split('/\s*,\s*/', $protocol));
            }
            $collection = collect($valuesArray)->unique()->sortBy('Key')->values()->all();
            foreach ($collection as $value) {
                if (mb_stristr($this->removeAccents($value), $this->removeAccents($term))) {
                    $output .= $this->suggestion($id, $value, $divId, $multi);
                }
            }
        } else {
            foreach ($protocols as $protocol) {
                $output .= $this->suggestion($id, $protocol, $divId, $multi);
            }
        }
        return response($output);
    }

    /**
     * R1a: one autocomplete suggestion. The value used to be pasted into the onclick script between quotes: a value
     * with ' (e.g. «Δ/νση Π.Ε. Ν' Αθηνών») broke the click, and a crafted one could run script. Now the arguments
     * are JSON, escaped for the attribute.
     */
    private function suggestion($id, $value, $divId, $multi): string
    {
        $args = e(json_encode([(string) $id, (string) $value, (string) $divId, (string) $multi], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        return '<li style="cursor: pointer"><a onclick="appendValue.apply(null, ' . $args . ')">' . e($value) . "</a></li>\n";
    }

    /**
     * Replace accented characters with non accented
     *
     * @param $str
     * @return mixed
     * @link http://myshadowself.com/coding/php-function-to-convert-accented-characters-to-their-non-accented-equivalant/
     */
    public function removeAccents($str)
    {
        $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ', 'Ά', 'ά', 'Έ', 'έ', 'Ό', 'ό', 'Ώ', 'ώ', 'Ί', 'ί', 'ϊ', 'ΐ', 'Ύ', 'ύ', 'ϋ', 'ΰ', 'Ή', 'ή');
        $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o', 'Α', 'α', 'Ε', 'ε', 'Ο', 'ο', 'Ω', 'ω', 'Ι', 'ι', 'ι', 'ι', 'Υ', 'υ', 'υ', 'υ', 'Η', 'η');
        return str_replace($a, $b, $str);
    }

    public function filter_filename($filename, $beautify = true)
    {
        // F08: κανόνες ονομάτων σε ένα σημείο (App\Support\FileNames)· το μήκος του αποθηκευμένου ονόματος το ορίζει FileNames::fit
        $filename = \App\Support\FileNames::clean($filename);
        // optional beautification
        if ($beautify) {
            $filename = $this->beautify_filename($filename);
        }
        return $filename;
    }

    public function beautify_filename($filename)
    {
        // reduce consecutive characters
        $filename = preg_replace(array(
            // "file   name.zip" becomes "file-name.zip"
            '/ +/',
            // "file___name.zip" becomes "file-name.zip"
            '/_+/',
            // "file---name.zip" becomes "file-name.zip"
            '/-+/'
        ), '-', $filename);
        $filename = preg_replace(array(
            // "file--.--.-.--name.zip" becomes "file.name.zip"
            '/-*\.-*/',
            // "file...name..zip" becomes "file.name.zip"
            '/\.{2,}/'
        ), '.', $filename);
        // lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
        $filename = mb_strtolower($filename, mb_detect_encoding($filename));
        // ".file-name.-" becomes "file-name"
        $filename = trim($filename, '.-');
        return $filename;
    }

    public static function limitProtocolAccessList()
    {
        $limitProtocolAccessList = 0;
        if (Config::getConfigValueOf('limitProtocolAccessList') && in_array(Auth::user()->role->role, ["Συγγραφέας",  "Αναγνώστης"])) {
            $limitProtocolAccessList = 1;
        }
        return $limitProtocolAccessList;
    }

}
