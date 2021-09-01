<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Helpers\UUIDGenerate;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\ProfileResource;
use App\Notifications\GeneralNotification;
use App\Http\Requests\TransferFormValidate;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\NotificationResource;
use Illuminate\Support\Facades\Notification;
use App\Http\Resources\TransactionDetailResource;
use App\Http\Resources\NotificationDetailResource;

class PageController extends Controller
{
    public function profile()
    {
        $user = auth()->user();
        $data = new ProfileResource($user);

        return success($data, 'success');
    }

    public function transaction(Request $request)
    {
        $user = auth()->user();
        $transactions = Transaction::with('user', 'source')->orderBy('created_at', 'DESC')->where('user_id', $user->id);
        if ($request->date) {
            $transactions = $transactions->whereDate('created_at', $request->date);
        }
        if ($request->type) {
            $transactions = $transactions->where('type', $request->type);
        }
        $transactions = $transactions->paginate(5);

        $data = TransactionResource::collection($transactions)->additional(['result' => 1, 'message' => 'success']);
        return $data;
    }
    public function transactionDetail($trx_id)
    {
        $user = auth()->user();
        $transaction = Transaction::with('user', 'source')->where('user_id', $user->id)->where('trx_id', $trx_id)->firstorfail();
        $data = new TransactionDetailResource($transaction);
        return success($data, 'success');
    }

    public function notification()
    {
        $user = auth()->user();
        $notifications = $user->notifications()->paginate(5);
        return NotificationResource::collection($notifications)->additional(['result' => 1, 'message' => 'success']);
    }

    public function notificationDetail($id)
    {
        $user = auth()->user();
        $notification = $user->notifications()->where('id', $id)->firstOrfail();
        $notification->markAsread();

        $data = new NotificationDetailResource($notification);
        return success($data, 'success');
    }

    public function toAccountVerify(Request $request)
    {
        if ($request->phone) {
            $user = auth()->user();
            if ($user->phone != $request->phone) {
                $user = User::where('phone', $request->phone)->first();
                if ($user) {
                    return success(['name' => $user->name, 'phone' => $user->phone], 'success');
                }
            }
        }
        return fail(null, 'Invalid Data');
    }

    public function transferConfirm(TransferFormValidate $request)
    {
        $user = auth()->user();
        $from_account = $user;
        $to_phone = $request->to_phone;
        $amount = $request->amount;
        $description = $request->description;
        $hash_value = $request->hash_value;

        $str = $to_phone . $amount . $description;
        $hash_value2 = hash_hmac('sha256', $str, 'magicpay123!@#');

        if ($hash_value !== $hash_value2) {
            return fail(null, 'The given data is invalid');
        }

        if ($amount < 1000) {
            return fail(null, 'The amount must be at least 1000 MMK.');
        }

        if ($from_account->phone == $to_phone) {
            return fail(null, 'To account is invalid.');
        }

        $to_account = User::where('phone', $to_phone)->first();
        if (!$to_account) {
            return fail(null, 'To account is invalid.');
        }

        if (!$from_account->wallet || !$to_account->wallet) {
            return fail(null, 'The given data is invalid.');
        }
        if ($from_account->wallet->amount < $amount) {
            return fail(null, 'The amount is not enough.');
        }
        return success([
            'from_name' => $from_account->name,
            'from_phone' => $from_account->phone,

            'to_name' => $to_account->name,
            'to_phone' => $to_account->phone,

            'amount' => $amount,
            'description' => $description,
            'hash_value' => $hash_value
        ], 'success');
    }

    public function transferComplete(TransferFormValidate $request)
    {
        if (!$request->password) {
            return fail(null, 'Please fill your password.');
        }
        $user = auth()->user();
        if (!Hash::check($request->password, $user->password)) {
            return fail(null, 'The password is incorrect.');
        }

        $from_account = $user;
        $to_phone = $request->to_phone;
        $amount = $request->amount;
        $description = $request->description;
        $hash_value = $request->hash_value;

        $str = $to_phone . $amount . $description;
        $hash_value2 = hash_hmac('sha256', $str, 'magicpay123!@#');

        if ($hash_value !== $hash_value2) {
            return fail(null, 'The given data is invalid.');
        }

        if ($amount < 1000) {
            return fail(null, 'The amount must be at least 1000 MMK.');
        }

        if ($from_account->phone == $to_phone) {
            return fail(null, 'To account is invalid.');
        }

        $to_account = User::where('phone', $to_phone)->first();
        if (!$to_account) {
            return fail(null, 'To account is invalid.');
        }

        if (!$from_account->wallet || !$to_account->wallet) {
            return fail(null, 'The given data is invalid.');
        }
        if ($from_account->wallet->amount < $amount) {
            return fail(null, 'The amount is not enough.');
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
            $message = 'Your wallet transfered ' . number_format($amount) . ' MMK to ' . $to_account->name . ' (' . $to_account->phone . ')';
            $sourceable_id = $from_account_transaction->id;
            $sourceable_type = Transaction::class;
            $web_link = url('/transaction/' . $from_account_transaction->trx_id);
            $deep_link = [
                'target' => 'transaction_detail',
                'parameter' => [
                    'trx_id' => $from_account_transaction->trx_id
                ]
            ];

            Notification::send([$from_account], new GeneralNotification($title, $message, $sourceable_id, $sourceable_type, $web_link, $deep_link));

            //To Noti
            $title = 'E-money Received!';
            $message = 'Your wallet received ' . number_format($amount) . ' MMK from ' . $from_account->name . ' (' . $from_account->phone . ')';
            $sourceable_id = $to_account_transaction->id;
            $sourceable_type = Transaction::class;
            $web_link = url('/transaction/' . $to_account_transaction->trx_id);
            $deep_link = [
                'target' => 'transaction_detail',
                'parameter' => [
                    'trx_id' => $to_account_transaction->trx_id,
                ]
            ];
            Notification::send([$to_account], new GeneralNotification($title, $message, $sourceable_id, $sourceable_type, $web_link, $deep_link));


            DB::commit();
            return success(['trx_id' => $from_account_transaction->trx_id], 'Successfully Transfered');
        } catch (\Exception $e) {
            DB::rollback();
            return fail(null, 'Something wrong. ' . $e->getMessage());
        }
    }

    public function scanAndPayForm(Request $request)
    {
        $from_account = auth()->user();
        $to_account = User::where('phone', $request->to_phone)->first();
        if (!$to_account) {
            return fail(null,'QR code is invalid.');
        }
        return success([
            'from_name'=>$from_account->name,
            'from_phone'=>$from_account->phone,
            'to_name'=>$to_account->name,
            'to_phone'=>$to_account->phone,
        ],
        'success');
    }

    public function scanAndPayConfirm(TransferFormValidate $request)
    {
        $user = auth()->user();
        $from_account = $user;
        $to_phone = $request->to_phone;
        $amount = $request->amount;
        $description = $request->description;
        $hash_value = $request->hash_value;

        $str = $to_phone . $amount . $description;
        $hash_value2 = hash_hmac('sha256', $str, 'magicpay123!@#');

        if ($hash_value !== $hash_value2) {
            return fail(null,'The given data is invalid');
        }

        if ($amount < 1000) {
            return fail(null,'The amount must be at least 1000 MMK.');
        }

        if ($from_account->phone == $to_phone) {
            return fail(null,'To account is invalid.');
        }

        $to_account = User::where('phone', $to_phone)->first();
        if (!$to_account) {
            return fail(null,'To account is invalid.');
        }

        if (!$from_account->wallet || !$to_account->wallet) {
            return fail(null,'The given data is invalid.');
        }
        if ($from_account->wallet->amount < $amount) {
            return fail(null,'The amount is not enough.');
        }
        return success([
            'from_name' => $from_account->name,
            'from_phone' => $from_account->phone,

            'to_name' => $to_account->name,
            'to_phone' => $to_account->phone,

            'amount' => $amount,
            'description' => $description,
            'hash_value' => $hash_value
        ], 'success');
       
    }

    public function scanAndPayComplete(TransferFormValidate $request)
    {
        if (!$request->password) {
            return fail(null, 'Please fill your password.');
        }
        $user = auth()->user();
        if (!Hash::check($request->password, $user->password)) {
            return fail(null, 'The password is incorrect.');
        }

        $from_account = $user;
        $to_phone = $request->to_phone;
        $amount = $request->amount;
        $description = $request->description;
        $hash_value = $request->hash_value;

        $str = $to_phone . $amount . $description;
        $hash_value2 = hash_hmac('sha256', $str, 'magicpay123!@#');

        if ($hash_value !== $hash_value2) {
            return fail(null, 'The given data is invalid.');
        }

        if ($amount < 1000) {
            return fail(null, 'The amount must be at least 1000 MMK.');
        }

        if ($from_account->phone == $to_phone) {
            return fail(null, 'To account is invalid.');
        }

        $to_account = User::where('phone', $to_phone)->first();
        if (!$to_account) {
            return fail(null, 'To account is invalid.');
        }

        if (!$from_account->wallet || !$to_account->wallet) {
            return fail(null, 'The given data is invalid.');
        }
        if ($from_account->wallet->amount < $amount) {
            return fail(null, 'The amount is not enough.');
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
            $message = 'Your wallet transfered ' . number_format($amount) . ' MMK to ' . $to_account->name . ' (' . $to_account->phone . ')';
            $sourceable_id = $from_account_transaction->id;
            $sourceable_type = Transaction::class;
            $web_link = url('/transaction/' . $from_account_transaction->trx_id);
            $deep_link = [
                'target' => 'transaction_detail',
                'parameter' => [
                    'trx_id' => $from_account_transaction->trx_id
                ]
            ];

            Notification::send([$from_account], new GeneralNotification($title, $message, $sourceable_id, $sourceable_type, $web_link, $deep_link));

            //To Noti
            $title = 'E-money Received!';
            $message = 'Your wallet received ' . number_format($amount) . ' MMK from ' . $from_account->name . ' (' . $from_account->phone . ')';
            $sourceable_id = $to_account_transaction->id;
            $sourceable_type = Transaction::class;
            $web_link = url('/transaction/' . $to_account_transaction->trx_id);
            $deep_link = [
                'target' => 'transaction_detail',
                'parameter' => [
                    'trx_id' => $to_account_transaction->trx_id,
                ]
            ];
            Notification::send([$to_account], new GeneralNotification($title, $message, $sourceable_id, $sourceable_type, $web_link, $deep_link));


            DB::commit();
            return success(['trx_id' => $from_account_transaction->trx_id], 'Successfully Transfered');
        } catch (\Exception $e) {
            DB::rollback();
            return fail(null, 'Something wrong. ' . $e->getMessage());
        }
    }
}
