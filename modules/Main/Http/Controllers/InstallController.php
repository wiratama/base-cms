<?php
namespace Module\Main\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DB;
use Validator;
use Artisan;
use Module\Main\Console\DefaultSetting;
use Module\Main\Console\SetRole;
use Schema;

class InstallController extends Controller{

	public $request;

	public function __construct(Request $req){
		$this->request = $req;
	}

	public function index(){
		$has_install = $this->checkHasInstall();
		//redirect to base route if already installed
		$db = $this->checkDatabaseConnection();

		return view('main::install', compact(
			'db',
			'has_install'
		));
	}

	public function process(){
		$db = $this->checkDatabaseConnection();
		if($db){
			return route('cms.install')->with(['error' => 'Please fix the database connection problem first']);
		}

		$validate = Validator::make($this->request->all(), [
			'name' => 'required',
			'email' => 'required|email|strict_mail',
			'password' => 'required|min:6'
		], [
			'email.strict_mail' => 'Email is not accepted'
		]);

		if($validate->fails()){
			return back()->withInput()->with([
				'error' => $validate->errors()->first()
			]);
		}

		//kalau sudah oke : hajar

		#pertama2 migrate dulu~
		Artisan::call('migrate');
		
		#buat admin baru
        self::createUser($this->request->name, $this->request->email, $this->request->password);
        (new SetRole)->actionRunner();
        (new DefaultSetting)->actionRunner($this->request->title, $this->request->description);

		Artisan::call('vendor:publish', [
			'--tag' => 'tianrosandhy-cms'
		]);

        #buat penanda kalau install sudah berhasil dijalankan
        $this->createInstallHint();


        //sudah sukses.. redirect ke login p4n3lb04rd
        return redirect()->route('admin.splash')->with([
        	'success' => 'CMS Installation has been finished. Now you can use this CMS'
        ]);
	}

	protected function checkHasInstall(){
		try{
			DB::table('cms_installs')->get();
			return true;
		}catch(\Illuminate\Database\QueryException $e){
			return false;
		}
	}

	protected function createInstallHint(){
		if(!$this->checkHasInstall()){
			//create installation simple table
			Schema::create('cms_installs', function($table){
				$table->datetime('created_at');
			});
			DB::table('cms_installs')->insert([
				'created_at' => date('Y-m-d H:i:s')
			]);
		}
		return false;
	}

	protected function createUser($username, $email, $password){
		DB::table('users')->insert([
            'name' => $username,
            'email' => $email,
            'password' => bcrypt($password),
            'role_id' => 1, //default
            'image' => '',
            'activation_key' => null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
	}


	protected function checkDatabaseConnection(){
		try{
			//check connection exists
			#will return error if connection failed
			DB::table(DB::raw('DUAL'))->first([DB::raw(1)]);
		}catch(\Exception $e){
			return 'Wrong database connection';
		}

		try{
			//check database exists
			#will return error if database not exists
			DB::select('SHOW TABLES');
		}catch(\Exception $e){
			return 'Database not exists';
		}

		return false;
	}

}