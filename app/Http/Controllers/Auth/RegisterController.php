<?php

namespace App\Http\Controllers\Auth;

use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use App\Support\Registration;
use Illuminate\Http\Request;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers {
        register as traitRegister;
    }

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home/list';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    /** Registration form: only while the installation has no administrator yet. */
    public function showRegistrationForm()
    {
        if (!Registration::isOpen()) {
            return redirect('login');
        }
        $allowregister = true;
        return view('auth.register', compact('allowregister'));
    }

    /** Refuse registration once an administrator exists (security fix S00). */
    public function register(Request $request)
    {
        abort_unless(Registration::isOpen(), 403, 'Η εγγραφή νέων χρηστών είναι κλειστή. Νέους χρήστες δημιουργεί ο Διαχειριστής.');

        return $this->traitRegister($request);
    }

    /** The only self-registered user is the first administrator; registration then closes. */
    protected function create(array $data)
    {
        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role_id' => Registration::adminRoleId(),
            'active' => 1,
        ]);
        Registration::close();

        return $user;
    }
}
