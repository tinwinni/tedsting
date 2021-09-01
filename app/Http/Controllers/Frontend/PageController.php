<?php

namespace App\Http\Controllers\Frontend;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Helpers\UUIDGenerate;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\UpdatePassword;
use App\Notifications\GeneralNotification;
use App\Http\Requests\TransferFormValidate;
use Illuminate\Support\Facades\Notification;

class PageController extends Controller
{
    public function home()
    {
        $user = Auth::user();
        return view('frontend.home', compact('user'));
    }

    public function profile()
    {
        $user = Auth::user();
        return view('frontend.profile', compact('user'));
    }
    public function updatePassword()
    {
        return view('frontend.update-Password');
    }
    public function store(UpdatePassword $request)
    {

        $old_password = $request->old_password;
        $new_password = $request->new_password;

        $user = Auth::guard('web')->user();
        if (Hash::check($old_password, $user->password)) {
            $user->password = Hash::make($new_password);
            $user->update();

            $title = 'Change Password!';
            $message = 'Your account password is successfully changed.';
            $sourceable_id = $user->id;
            $sourceable_type = User::class;
            $web_link = url('profile');
            $deep_link =[
                'target' => 'profile',
                'parameter' => null
            ];

            Notification::send([$user], new GeneralNotification($title, $message, $sourceable_id, $sourceable_type, $web_link,$deep_link));

            return redirect()->route('profile')->with('update', 'Successfully Updated.');
        }
        return back()->withErrors(['old_password' => 'The old password is not correct.'])->withInput();
    }
    public function wallet()
    {
        $user = Auth::user();
        return view('frontend.wallet', compact('user'));
    }

    public function transfer()
    {
        $user = Auth::user();
        return view('frontend.transfer', compact('user'));
    }
    public function transferConfirm(TransferFormValidate $request)
    {
        $user = Auth::user();
        $from_account = $user;
        $to_phone = $request->to_phone;
        $amount = $request->amount;
        $description = $request->description;
        $hash_value = $request->hash_value;

        $str = $to_phone . $amount . $description;
        $hash_value2 = hash_hmac('sha256', $str, 'magicpay123!@#');

        if ($hash_value !== $hash_value2) {
            return back()->withErrors(['fail' => 'The given data is invalid'])->withInput();
        }

        if ($amount < 1000) {
            return back()->withErrors(['amount' => 'The amount must be at least 1000 MMK.'])->withInput();
        }

        if ($from_account->phone == $to_phone) {
            return back()->withErrors(['to_phone' => 'To account is invalid.'])->withInput();
        }

        $to_account = User::where('phone', $to_phone)->first();
        if (!$to_account) {
            return back()->withErrors(['to_phone' => 'To account is invalid.'])->withInput();
        }

        if (!$from_account->wallet || !$to_account->wallet) {
            return back()->withErrors(['fail' => 'The given data is invalid.'])->withInput();
        }
        if ($from_account->wallet->amount < $amount) {
            return back()->withErrors(['amount' => 'The amount is not enough.'])->withInput();
        }
        return view('frontend.transfer_confirm', compact('from_account', 'to_account', 'amount', 'description', 'hash_value'));
    }


    public function transferComplete(TransferFormValidate $request)
    {
        $user = Auth::user();
        $from_account = $user;
        $to_phone = $request->to_phone;
        $amount = $request->amount;
        $description = $request->description;
        $hash_value = $request->hash_value;

        $str = $to_phone . $amount . $description;
        $hash_value2 = hash_hmac('sha256', $str, 'magicpay123!@#');

        if ($hash_value !== $hash_value2) {
            return back()->withErrors(['amount' => 'The given data is invalid'])->withInput();
        }

        if ($amount < 1000) {
            return back()->withErrors(['amount' => 'The amount must be at least 1000 MMK.'])->withInput();
        }

        if ($from_account->phone == $to_phone) {
            return back()->withErrors(['to_phone' => 'To account is invalid.'])->withInput();
        }

        $to_account = User::where('phone', $to_phone)->first();
        if (!$to_account) {
            return back()->withErrors(['to_phone' => 'To account is invalid.'])->withInput();
        }

        if (!$from_account->wallet || !$to_account->wallet) {
            return back()->withErrors(['fail' => 'The given data is invalid.'])->withInput();
        }
        if ($from_account->wallet->amount < $amount) {
            return back()->withErrors(['amount' => 'The amount is not enough.'])->withInput();
        }
        DB::beginTransaction();
        try {
            $from_account_wallet = $from_account->wallet;
            $from_account_wallet->decrement('amount', $amount);
            $from_account_wallet->update();

            $to_account_wallet = $to_account->wallet;
            $to_account_wallet->increment('amount', $amount);
            $to_account_wallet->update();


            $ref_no = UUIDGenerate::refNumber();
            $from_account_transaction = new Transaction();
            $from_account_transaction->ref_no = $ref_no;
            $from_account_transaction->trx_id = UUIDGenerate::trxId();
            $from_account_transaction->user_id = $from_account->id;
            $from_account_transaction->type = 2;
            $from_account_transaction->amount = $amount;
            $from_account_transaction->source_id = $to_account->id;
            $from_account_transaction->description = $description;
            $from_account_transaction->save();

            $to_account_transaction = new Transaction();
            $to_account_transaction->ref_no = $ref_no;
            $to_account_transaction->trx_id = UUIDGenerate::trxId();
            $to_account_transaction->user_id = $to_account->id;
            $to_account_transaction->type = 1;
            $to_account_transaction->amount = $amount;
            $to_account_transaction->source_id = $from_account->id;
            $to_account_transaction->description = $description;
            $to_account_transaction->save();
            //From Noti
            $title = 'E-money Transfered!';
            $message = 'Your wallet transfered ' . number_format($amount) . ' MMK to '. $to_account->name . ' (' . $to_account->phone.')';
            $sourceable_id = $from_account_transaction->id;
            $sourceable_type = Transaction::class;
            $web_link = url('/transaction/' . $from_account_transaction->trx_id);
            $deep_link =[
                'target' => 'transaction_detail',
                'parameter' => [
                    'trx_id'=> $from_account_transaction->trx_id
                ]
            ];
            
            Notification::send([$from_account], new GeneralNotification($title, $message, $sourceable_id, $sourceable_type, $web_link,$deep_link));

            //To Noti
            $title = 'E-money Received!';
            $message = 'Your wallet received ' . number_format($amount) . ' MMK from '. $from_account->name . ' (' . $from_account->phone.')';
            $sourceable_id = $to_account_transaction->id;
            $sourceable_type = Transaction::class;
            $web_link = url('/transaction/' . $to_account_transaction->trx_id);
            $deep_link =[
                'target' => 'transaction_detail',
                'parameter' => [
                    'trx_id'=> $to_account_transaction->trx_id
                ]
            ];
            Notification::send([$to_account], new GeneralNotification($title, $message, $sourceable_id, $sourceable_type, $web_link,$deep_link));


            DB::commit();
            return redirect('/transaction/' . $from_account_transaction->trx_id)->with('transfer_success', 'Successfully transfered.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['fail' => 'Something wrong.' . $e->getMessage()])->withInput();
        }
    }

    public function transaction(Request $request)
    {
        $user = Auth::user();
        $transactions = Transaction::with('user', 'source')->orderBy('created_at', 'DESC')->where('user_id', $user->id);

        if ($request->type) {
            $transactions = $transactions->where('type', $request->type);
        }
        if ($request->date) {
            $transactions = $transactions->whereDate('created_at', $request->date);
        }
        $transactions = $transactions->paginate(4);
        return view('frontend.transaction', compact('transactions'));
    }

    public function transactionDetail($trx_id)
    {
        $user = Auth::user();

        $transaction = Transaction::with('user', 'source')->where('user_id', $user->id)->where('trx_id', $trx_id)->first();
        return view('frontend.transaction_detail', compact('transaction'));
    }

    public function toAccountVerify(Request $request)
    {
        $user = Auth::user();
        if ($user->phone != $request->phone) {
            $user = User::where('phone', $request->phone)->first();
            if ($user) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'success',
                    'data' => $user

                ]);
            }
        }

        return response()->json([
            'status' => 'fail',
            'message' => 'Invalid data'

        ]);
    }

    public function passwordCheck(Request $request)
    {
        if (!$request->password) {
            return response()->json([
                'status' => 'fail',
                'message' => 'Please fill your password.'
            ]);
        }
        $user = Auth::user();
        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 'success',
                'message' => 'The password is correct.'

            ]);
        }

        return response()->json([
            'status' => 'fail',
            'message' => 'The password is incorrect.'
        ]);
    }

    public function transferHash(Request $request)
    {
        $str = $request->to_phone . $request->amount . $request->description;
        $hash_value = hash_hmac('sha256', $str, 'magicpay123!@#');

        return response()->json([
            'status' => 'success',
            'data' => $hash_value
        ]);
    }

    public function receiveQR()
    {
        $user = Auth::user();
        return view('frontend.receive_qr', compact('user'));
    }
    public function scanAndPay()
    {
        return view('frontend.scan_and_pay');
    }
    public function scanAndPayForm(Request $request)
    {
        $from_account = Auth::user();
        $to_account = User::where('phone', $request->to_phone)->first();
        if (!$to_account) {
            return back()->withErrors(['fail' => 'QR code is invalid.'])->withInput();
        }
        return view('frontend.scan_and_pay_form', compact('to_account', 'from_account'));
    }

    public function scanAndPayConfirm(TransferFormValidate $request)
    {
        $user = Auth::user();
        $from_account = $user;
        $to_phone = $request->to_phone;
        $amount = $request->amount;
        $description = $request->description;
        $hash_value = $request->hash_value;

        $str = $to_phone . $amount . $description;
        $hash_value2 = hash_hmac('sha256', $str, 'magicpay123!@#');

        if ($hash_value !== $hash_value2) {
            return back()->withErrors(['amount' => 'The given data is invalid'])->withInput();
        }

        if ($amount < 1000) {
            return back()->withErrors(['amount' => 'The amount must be at least 1000 MMK.'])->withInput();
        }

        if ($from_account->phone == $to_phone) {
            return back()->withErrors(['to_phone' => 'To account is invalid.'])->withInput();
        }

        $to_account = User::where('phone', $to_phone)->first();
        if (!$to_account) {
            return back()->withErrors(['to_phone' => 'To account is invalid.'])->withInput();
        }

        if (!$from_account->wallet || !$to_account->wallet) {
            return back()->withErrors(['fail' => 'The given data is invalid.'])->withInput();
        }
        if ($from_account->wallet->amount < $amount) {
            return back()->withErrors(['amount' => 'The amount is not enough.'])->withInput();
        }
        return view('frontend.scan_and_pay_confirm', compact('from_account', 'to_account', 'amount', 'description', 'hash_value'));
    }
    public function scanAndPayComplete(TransferFormValidate $request)
    {
        $user = Auth::user();
        $from_account = $user;
        $to_phone = $request->to_phone;
        $amount = $request->amount;
        $description = $request->description;
        $hash_value = $request->hash_value;

        $str = $to_phone . $amount . $description;
        $hash_value2 = hash_hmac('sha256', $str, 'magicpay123!@#');

        if ($hash_value !== $hash_value2) {
            return back()->withErrors(['amount' => 'The given data is invalid'])->withInput();
        }

        if ($amount < 1000) {
            return back()->withErrors(['amount' => 'The amount must be at least 1000 MMK.'])->withInput();
        }

        if ($from_account->phone == $to_phone) {
            return back()->withErrors(['to_phone' => 'To account is invalid.'])->withInput();
        }

        $to_account = User::where('phone', $to_phone)->first();
        if (!$to_account) {
            return back()->withErrors(['to_phone' => 'To account is invalid.'])->withInput();
        }

        if (!$from_account->wallet || !$to_account->wallet) {
            return back()->withErrors(['fail' => 'The given data is invalid.'])->withInput();
        }
        if ($from_account->wallet->amount < $amount) {
            return back()->withErrors(['amount' => 'The amount is not enough.'])->withInput();
        }
        DB::beginTransaction();
        try {
            $from_account_wallet = $from_account->wallet;
            $from_account_wallet->decrement('amount', $amount);
            $from_account_wallet->update();

            $to_account_wallet = $to_account->wallet;
            $to_account_wallet->increment('amount', $amount);
            $to_account_wallet->update();


            $ref_no = UUIDGenerate::refNumber();
            $from_account_transaction = new Transaction();
            $from_account_transaction->ref_no = $ref_no;
            $from_account_transaction->trx_id = UUIDGenerate::trxId();
            $from_account_transaction->user_id = $from_account->id;
            $from_account_transaction->type = 2;
            $from_account_transaction->amount = $amount;
            $from_account_transaction->source_id = $to_account->id;
            $from_account_transaction->description = $description;
            $from_account_transaction->save();

            $to_account_transaction = new Transaction();
            $to_account_transaction->ref_no = $ref_no;
            $to_account_transaction->trx_id = UUIDGenerate::trxId();
            $to_account_transaction->user_id = $to_account->id;
            $to_account_transaction->type = 1;
            $to_account_transaction->amount = $amount;
            $to_account_transaction->source_id = $from_account->id;
            $to_account_transaction->description = $description;
            $to_account_transaction->save();


            //From Noti
            $title = 'E-money Transfered!';
            $message = 'Your wallet transfered ' . number_format($amount) . ' MMK to '. $to_account->name . ' (' . $to_account->phone.')';
            $sourceable_id = $from_account_transaction->id;
            $sourceable_type = Transaction::class;
            $web_link = url('/transaction/' . $from_account_transaction->trx_id);
            $deep_link =[
                'target' => 'transaction_detail',
                'parameter' => [
                    'trx_id'=> $from_account_transaction->trx_id
                ]
            ];
            Notification::send([$from_account], new GeneralNotification($title, $message, $sourceable_id, $sourceable_type, $web_link,$deep_link));

            //To Noti
            $title = 'E-money Received!';
            $message = 'Your wallet received ' . number_format($amount) . ' MMK from '. $from_account->name . ' (' . $from_account->phone.')';
            $sourceable_id = $to_account_transaction->id;
            $sourceable_type = Transaction::class;
            $web_link = url('/transaction/' . $to_account_transaction->trx_id);
            $deep_link =[
                'target' => 'transaction_detail',
                'parameter' => [
                    'trx_id'=> $to_account_transaction->trx_id
                ]
            ];
            Notification::send([$to_account], new GeneralNotification($title, $message, $sourceable_id, $sourceable_type, $web_link,$deep_link));

            DB::commit();
            return redirect('/transaction/' . $from_account_transaction->trx_id)->with('transfer_success', 'Successfully transfered.');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->withErrors(['fail' => 'Something wrong.' . $e->getMessage()])->withInput();
        }
    }
}
