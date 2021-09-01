<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\UUIDGenerate;
use Carbon\Carbon;

use App\Models\AdminUser;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Http\Requests\StoreUser;
use Yajra\Datatables\Datatables;
use App\Http\Requests\UpdateUser;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\StoreAdminUser;
use App\Http\Requests\UpdateAdminUser;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

use GrahamCampbell\ResultType\Success;

class UserController extends Controller
{
    public function index(){
        
        return view('backend.user.index');
    }
    public function ssd(){
        $data=User::query();
        return Datatables::of($data)
        ->editColumn('user_agent', function($each){
            if($each->user_agent){
                    $agent = new Agent();
                    $agent->setUserAgent($each->user_agent);
                    $device = $agent->device();
                    $platform = $agent->platform();
                    $browser = $agent->browser();

                    return '<table class="table table-bordered">
                    <tbody>
                    <tr><td>Device</td><td>'.$device.'</td></tr>
                    <tr><td>Platform</td><td>'.$platform.'</td></tr>
                    <tr><td>Browser</td><td>'.$browser.'</td></tr>

                    </tbody>
                    </table>';
            }
            return '-';
        })
        ->editColumn('created_at',function($each){
            return Carbon::parse($each->created_at)->format('Y-m-d H:i:s');
        })
        ->editColumn('updated_at',function($each){
            return Carbon::parse($each->updated_at)->format('Y-m-d H:i:s');
        })
        ->addColumn('action',function($each){
         
           $delete_icon = '<a href="" class="text-danger delete" data-id=" ' .$each->id .'"><i class="fas fa-trash-alt"></i></a>';
           $edit_icon = '<a href="'. route('admin.user.edit', $each->id) . '" class="text-warning"><i class="fas fa-edit"></i></a>';
            return '<div class="action-icon">' . $edit_icon . $delete_icon .'</div>' ;
        })
        ->rawColumns(['user_agent','action'])
        ->make(true);
    }
    public function create(){
        return view('backend.user.create');
    }
    public function store(StoreUser $request){
        DB::beginTransaction();
        try{
            $user=new User();
        $user->name =$request->name;
        $user->email =$request->email;
        $user->phone =$request->phone;
        $user->password =Hash::make($request->password);
        $user->save();

        Wallet::firstOrCreate(
            ['user_id' =>  $user->id
            ],
            ['account_number' => UUIDGenerate::accountNumber(),
             'amount' => 0
            ]
        );
        DB::commit();

        return redirect()->route('admin.user.index')->with('create','Successfully Created');

        } catch(\Exception $e){
            DB::rollBack();
            return back()->withErrors(['fail' => 'Something wrong. ' . $e->getMessage()])->withInput();

        }


        
    }
    public function edit($id){
        $user = User::findOrfail($id);
        return view('backend.user.edit', compact('user'));
    }
    public function update($id,UpdateUser $request){
        DB::beginTransaction();
        try{
        $user=User::findorfail($id);
        $user->name =$request->name;
        $user->email =$request->email;
        $user->phone =$request->phone;
        $user->password =$request->password ? Hash::make($request->password) : $user->password;
        $user->update();

        Wallet::firstOrCreate(
            ['user_id' =>  $user->id
            ],
            ['account_number' => UUIDGenerate::accountNumber(),
             'amount' => 0
            ]
        );
        DB::commit();
            return redirect()->route('admin.user.index')->with('update','Successfully Updated');
         } catch(\Exception $e){

             DB::rollBack();
            return back()->withErrors(['fail' => 'Something wrong. ' . $e->getMessage()])->withInput();

    }
    }
    public function destroy($id){
        $user=User::findorfail($id);
        $user->delete();

        return "success";
    }
    
}
